<?php
declare(strict_types=1);
defined('AGORA') or exit;

/** Things the server must have before Agora can run. */
function install_requirements(): array
{
    return [
        [
            'label' => 'PHP 8.1 or newer',
            'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'detail' => 'This server has PHP ' . PHP_VERSION . '.',
        ],
        [
            'label' => 'SQLite database support (pdo_sqlite)',
            'ok' => extension_loaded('pdo_sqlite'),
            'detail' => 'Ask your host to enable the pdo_sqlite PHP extension.',
        ],
        [
            'label' => 'Multibyte text support (mbstring)',
            'ok' => extension_loaded('mbstring'),
            'detail' => 'Ask your host to enable the mbstring PHP extension.',
        ],
        [
            'label' => 'The data folder can be written to',
            'ok' => data_folder_is_writable(),
            'detail' => 'Make the "data" folder writable (permissions 755 or 775) in your host\'s file manager.',
        ],
    ];
}

/** Actually try writing a file — is_writable() gives wrong answers on some systems (e.g. Windows/OneDrive). */
function data_folder_is_writable(): bool
{
    if (!is_dir(AGORA_DATA)) {
        return false;
    }
    $probe = AGORA_DATA . '/.write-test-' . bin2hex(random_bytes(4)) . '.tmp';
    $ok = @file_put_contents($probe, 'ok') === 2;
    @unlink($probe);
    return $ok;
}

function page_install(array $errors = [], array $old = []): string
{
    $requirements = install_requirements();
    return view('install', [
        'title' => 'Set up your forum',
        'bare' => true,
        'requirements' => $requirements,
        'ready' => !in_array(false, array_column($requirements, 'ok'), true),
        'errors' => $errors,
        'old' => $old + ['site_name' => '', 'mail_from' => '', 'username' => '', 'email' => ''],
        'defaultMailFrom' => 'noreply@' . preg_replace('/^www\.|:\d+$/', '', (string) parse_url(detected_site_url(), PHP_URL_HOST)),
    ]);
}

function page_install_submit(): string
{
    $requirements = install_requirements();
    if (in_array(false, array_column($requirements, 'ok'), true)) {
        return page_install();
    }

    $old = [
        'site_name' => input('site_name'),
        'mail_from' => input('mail_from'),
        'username' => input('username'),
        'email' => input('email'),
    ];
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    $errors = validate_new_account($old['username'], $old['email'], $password, $confirm);
    if ($old['site_name'] === '' || text_length($old['site_name']) > 80) {
        $errors['site_name'] = 'Give your forum a name (up to 80 characters).';
    }
    if ($old['mail_from'] !== '' && validate_email($old['mail_from']) !== null) {
        $errors['mail_from'] = 'Enter a valid email address, or leave this blank.';
    }
    if ($errors) {
        http_response_code(422);
        return page_install($errors, $old);
    }

    // 1. Create the database with a hard-to-guess file name.
    $databaseFile = 'agora-' . bin2hex(random_bytes(8)) . '.sqlite';
    $databasePath = AGORA_DATA . '/' . $databaseFile;

    try {
        DB::connect($databasePath);
        DB::migrate();

        $adminId = DB::transaction(function () use ($old, $password): int {
            $time = now();
            // Migration 002 already added defaults for some settings; installer values win.
            DB::query("DELETE FROM settings WHERE key = 'mail_from'");
            DB::insert('settings', ['key' => 'mail_from', 'value' => $old['mail_from']]);
            foreach ([
                'site_name' => $old['site_name'],
                'site_url' => detected_site_url(),
                'site_description' => '',
                'registration' => 'open',
                'members_create_tags' => '0',
            ] as $key => $value) {
                DB::insert('settings', ['key' => $key, 'value' => $value]);
            }

            DB::insert('categories', [
                'name' => 'General',
                'slug' => 'general',
                'description' => 'Anything and everything.',
                'position' => 1,
                'created_at' => $time,
            ]);

            return DB::insert('users', [
                'username' => $old['username'],
                'email' => $old['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'admin',
                'email_verified_at' => $time,
                'created_at' => $time,
            ]);
        });

        // 2. Only once the database is ready, write the config file that marks the forum as installed.
        write_config_file([
            'database' => $databaseFile,
            'secret' => bin2hex(random_bytes(32)),
            'pretty_urls' => 'auto',
            'installed_at' => now(),
            'installed_version' => AGORA_VERSION,
        ]);
    } catch (Throwable $e) {
        error_log('[Agora] Install failed: ' . $e);
        if (is_file($databasePath)) {
            @unlink($databasePath);
        }
        return page_install(['general' => 'Setup could not finish: ' . $e->getMessage()], $old);
    }

    login_user($adminId);
    flash('success', 'Your forum is ready! You\'re signed in as the admin.');
    redirect('/');
}

function write_config_file(array $config): void
{
    $php = "<?php\n// Agora Forum configuration — created by the installer. Keep this file private.\n"
        . "defined('AGORA') or exit;\n\nreturn " . var_export($config, true) . ";\n";

    $temp = config_file() . '.tmp';
    if (file_put_contents($temp, $php, LOCK_EX) === false || !rename($temp, config_file())) {
        @unlink($temp);
        throw new RuntimeException('Could not write data/config.php. Check that the data folder is writable.');
    }
}

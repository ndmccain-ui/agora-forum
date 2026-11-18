<?php
declare(strict_types=1);
defined('AGORA') or exit;

/* ---------- Shared ---------- */

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        echo view('message', ['title' => 'Admins only', 'message' => 'This part of the forum is only for admins.'], 403);
        exit;
    }
    return $user;
}

/** Render an admin page inside the admin frame (side menu + content). */
function admin_view(string $section, string $title, array $data = []): string
{
    $template = $section;
    $data += ['errors' => [], 'section' => $section]; // a page can highlight a different menu item
    return view('admin/frame', $data + [
        'title' => $title . ' · Admin',
        'heading' => $title,
        'inner' => partial('admin/' . $template, $data),
    ]);
}

function admin_redirect(string $path, string $message): never
{
    flash('success', $message);
    redirect($path);
}

/* ---------- Dashboard ---------- */

function page_admin(): string
{
    require_admin();
    $since = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
    return admin_view('dashboard', 'Dashboard', [
        'stats' => [
            'Members' => (int) DB::value('SELECT COUNT(*) FROM users'),
            'Discussions' => (int) DB::value('SELECT COUNT(*) FROM discussions WHERE is_hidden = 0'),
            'Posts' => (int) DB::value('SELECT COUNT(*) FROM posts WHERE is_hidden = 0'),
            'New members this week' => (int) DB::value('SELECT COUNT(*) FROM users WHERE created_at >= ?', [$since]),
            'Posts this week' => (int) DB::value('SELECT COUNT(*) FROM posts WHERE created_at >= ?', [$since]),
        ],
        'mailDriver' => mail_driver(),
        'version' => AGORA_VERSION,
        'phpVersion' => PHP_VERSION,
        'sqliteVersion' => DB::value('SELECT sqlite_version()'),
        'searchEngine' => search_uses_fts() ? 'full-text' : 'basic',
    ]);
}

/* ---------- Settings ---------- */

function page_admin_settings(array $errors = []): string
{
    require_admin();
    return admin_view('settings', 'Settings', ['errors' => $errors]);
}

function page_admin_settings_submit(): string
{
    require_admin();
    $siteName = input('site_name');
    $siteUrl = rtrim(input('site_url'), '/');
    $errors = [];
    if ($siteName === '' || text_length($siteName) > 80) {
        $errors['site_name'] = 'Give your forum a name (up to 80 characters).';
    }
    if (!preg_match('#^https?://[^\s/]+(/[^\s]*)?$#i', $siteUrl)) {
        $errors['site_url'] = 'Enter the full address, starting with https://';
    }
    $age = filter_var(input('minimum_age'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 21]]);
    if ($age === false) {
        $errors['minimum_age'] = 'Enter an age from 0 to 21 (0 turns the age check off).';
    }
    if ($errors) {
        http_response_code(422);
        return page_admin_settings($errors);
    }
    save_settings([
        'site_name' => $siteName,
        'site_description' => mb_substr(input('site_description'), 0, 300),
        'site_url' => $siteUrl,
        'registration' => input('registration') === 'closed' ? 'closed' : 'open',
        'require_email_confirmation' => isset($_POST['require_email_confirmation']) ? '1' : '0',
        'minimum_age' => (string) $age,
        'members_create_tags' => isset($_POST['members_create_tags']) ? '1' : '0',
        'approve_first_posts' => isset($_POST['approve_first_posts']) ? '1' : '0',
    ]);
    admin_redirect('/admin/settings', 'Settings saved.');
}

/* ---------- Email ---------- */

function page_admin_email(array $errors = [], ?string $testResult = null, bool $testOk = false): string
{
    require_admin();
    return admin_view('email', 'Email', [
        'errors' => $errors,
        'driver' => mail_driver(),
        'testResult' => $testResult,
        'testOk' => $testOk,
        'defaultFrom' => 'noreply@' . preg_replace('/^www\./', '', (string) parse_url(site_url(), PHP_URL_HOST)),
    ]);
}

function page_admin_email_submit(): string
{
    require_admin();
    $errors = [];
    $from = input('mail_from');
    if ($from !== '' && validate_email($from) !== null) {
        $errors['mail_from'] = 'Enter a valid email address, or leave this blank.';
    }
    $driver = input('mail_driver');
    if (!in_array($driver, ['mail', 'smtp', 'log'], true)) {
        $driver = 'mail';
    }
    $port = filter_var(input('smtp_port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($driver === 'smtp') {
        if (!preg_match('/^[A-Za-z0-9.-]+$/', input('smtp_host'))) {
            $errors['smtp_host'] = 'Enter your mail server\'s address, like smtp.example.com';
        }
        if ($port === false) {
            $errors['smtp_port'] = 'Enter a port number (usually 587 or 465).';
        }
    }
    if ($errors) {
        http_response_code(422);
        return page_admin_email($errors);
    }
    $values = [
        'mail_from' => $from,
        'mail_driver' => $driver,
        'smtp_host' => input('smtp_host'),
        'smtp_port' => (string) ($port ?: 587),
        'smtp_encryption' => in_array(input('smtp_encryption'), ['tls', 'ssl', 'none'], true) ? input('smtp_encryption') : 'tls',
        'smtp_username' => input('smtp_username'),
    ];
    // Leaving the password box empty keeps the saved one.
    if (($_POST['smtp_password'] ?? '') !== '') {
        $values['smtp_password'] = (string) $_POST['smtp_password'];
    }
    save_settings($values);
    admin_redirect('/admin/email', 'Email settings saved.');
}

function page_admin_email_test(): string
{
    $user = require_admin();
    try {
        deliver_mail(
            $user['email'],
            'Test email from ' . setting('site_name', 'Agora Forum'),
            "Hi {$user['username']},\n\nIf you're reading this, your forum can send email. 🎉"
        );
        $where = mail_driver() === 'log' ? ' (saved to data/mail.log, because this is a local test server)' : '';
        return page_admin_email([], 'Sent a test email to ' . $user['email'] . $where . '.', true);
    } catch (Throwable $e) {
        return page_admin_email([], 'The test email failed. ' . $e->getMessage(), false);
    }
}

/* ---------- Appearance ---------- */

function page_admin_appearance(array $errors = []): string
{
    require_admin();
    $overrides = theme_color_overrides();
    return admin_view('appearance', 'Appearance', [
        'errors' => $errors,
        'themes' => available_themes(),
        'current' => current_theme()['slug'] ?? 'agora',
        'customColors' => !empty($overrides),
        'colors' => ['light' => effective_colors('light'), 'dark' => effective_colors('dark')],
        'logo' => logo_url(),
    ]);
}

function page_admin_appearance_submit(): string
{
    require_admin();
    $themes = available_themes();
    $theme = input('theme');
    $values = [
        'theme' => isset($themes[$theme]) ? $theme : 'agora',
        'theme_default_mode' => in_array(input('theme_default_mode'), ['auto', 'light', 'dark'], true) ? input('theme_default_mode') : 'auto',
        'custom_css' => mb_substr(str_replace('</', '<\/', (string) ($_POST['custom_css'] ?? '')), 0, 50000),
    ];

    $themeChanged = $values['theme'] !== setting('theme', 'agora');
    if (isset($_POST['custom_colors']) && !$themeChanged) {
        $colors = [];
        foreach (['light', 'dark'] as $mode) {
            foreach (array_keys(THEME_COLOR_KEYS) as $key) {
                $value = strtolower((string) ($_POST['colors'][$mode][$key] ?? ''));
                if (is_hex_color($value)) {
                    $colors[$mode][$key] = $value;
                }
            }
        }
        $values['theme_colors'] = json_encode($colors);
    } else {
        // Switching themes starts fresh with that theme's own colors.
        $values['theme_colors'] = '';
    }
    save_settings($values);
    bump_appearance_version();
    admin_redirect('/admin/appearance', $themeChanged ? 'Theme changed.' : 'Appearance saved.');
}

function page_admin_logo_upload(): string
{
    require_admin();
    try {
        $file = save_logo_upload($_FILES['logo'] ?? []);
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        return page_admin_appearance(['logo' => $e->getMessage()]);
    }
    $old = setting('logo', '');
    save_settings(['logo' => $file]);
    if ($old && preg_match('/^logo-[a-f0-9]+\.(png|webp)$/', $old)) {
        @unlink(AGORA_DATA . '/' . $old);
    }
    admin_redirect('/admin/appearance', 'Logo updated.');
}

function page_admin_logo_remove(): string
{
    require_admin();
    $old = setting('logo', '');
    save_settings(['logo' => '']);
    if ($old && preg_match('/^logo-[a-f0-9]+\.(png|webp)$/', $old)) {
        @unlink(AGORA_DATA . '/' . $old);
    }
    admin_redirect('/admin/appearance', 'Logo removed. Your forum name is shown instead.');
}

/** Resize a logo to fit 480×96 and save it as a clean image file. */
function save_logo_upload(array $upload): string
{
    if (!function_exists('imagecreatetruecolor')) {
        throw new InvalidArgumentException('Logos need the PHP GD extension, which this server doesn\'t have.');
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) {
        throw new InvalidArgumentException('Choose a picture to upload (up to 4 MB).');
    }
    $info = @getimagesize($upload['tmp_name']);
    $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_GIF => 'imagecreatefromgif', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    $loader = $info ? ($loaders[$info[2]] ?? null) : null;
    if (!$loader || !function_exists($loader) || $info[0] * $info[1] > 40_000_000) {
        throw new InvalidArgumentException('Please upload a JPG, PNG, GIF or WebP picture.');
    }
    $source = @$loader($upload['tmp_name']);
    if (!$source) {
        throw new InvalidArgumentException('That picture couldn\'t be read.');
    }
    $w = imagesx($source);
    $h = imagesy($source);
    $scale = min(1, 480 / $w, 96 / $h);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $logo = imagecreatetruecolor($nw, $nh);
    imagealphablending($logo, false);
    imagesavealpha($logo, true);
    imagefill($logo, 0, 0, imagecolorallocatealpha($logo, 0, 0, 0, 127));
    imagecopyresampled($logo, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $webp = function_exists('imagewebp');
    $name = 'logo-' . bin2hex(random_bytes(6)) . ($webp ? '.webp' : '.png');
    if (!($webp ? imagewebp($logo, AGORA_DATA . '/' . $name, 90) : imagepng($logo, AGORA_DATA . '/' . $name, 9))) {
        throw new RuntimeException('Could not save the logo.');
    }
    return $name;
}

function page_admin_theme_upload(): string
{
    require_admin();
    $upload = $_FILES['theme_file'] ?? [];
    $error = null;
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) {
        $error = 'Choose a theme file (.css) to upload.';
    } elseif (($upload['size'] ?? 0) > 200 * 1024) {
        $error = 'Theme files can be up to 200 KB.';
    } else {
        $css = (string) file_get_contents($upload['tmp_name']);
        $meta = parse_theme_header($css);
        $slug = slugify(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_FILENAME));
        $bundled = theme_dirs()['bundled'] . '/' . $slug . '.css';
        if (!mb_check_encoding($css, 'UTF-8') || !$meta['name']) {
            $error = 'That doesn\'t look like an Agora theme. It needs a header comment with a "Name:" line — see docs/THEMES.md.';
        } elseif (is_file($bundled)) {
            $error = 'A built-in theme already uses the name "' . $slug . '". Rename your file and try again.';
        }
    }
    if ($error) {
        http_response_code(422);
        return page_admin_appearance(['theme_file' => $error]);
    }
    $dir = theme_dirs()['uploaded'];
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($dir . '/' . $slug . '.css', str_replace('</', '<\/', $css));
    bump_appearance_version();
    admin_redirect('/admin/appearance', 'Theme "' . $meta['name'] . '" installed. Choose it below to use it.');
}

function page_admin_theme_delete(string $slug): string
{
    require_admin();
    $themes = available_themes();
    if (!isset($themes[$slug]) || $themes[$slug]['source'] !== 'uploaded') {
        not_found();
    }
    @unlink($themes[$slug]['file']);
    if (setting('theme') === $slug) {
        save_settings(['theme' => 'agora', 'theme_colors' => '']);
    }
    bump_appearance_version();
    admin_redirect('/admin/appearance', 'Theme removed.');
}

/** Download the current look (theme + color tweaks) as a theme file to share or edit. */
function page_admin_theme_export(): string
{
    require_admin();
    $theme = current_theme();
    $name = setting('site_name', 'My forum') . ' theme';
    $css = "/* Agora theme\n   Name: $name\n   Author: " . setting('site_name', '') . "\n   Description: Exported from " . site_url() . " on " . gmdate('Y-m-d') . "\n*/\n\n";
    $body = !empty($theme['file']) && is_file($theme['file']) ? (string) file_get_contents($theme['file']) : '';
    $body = preg_replace('#^\s*/\*.*?\*/\s*#s', '', $body); // drop the old header
    $css .= $body;
    foreach (['light' => ':root', 'dark' => ':root[data-theme="dark"]'] as $mode => $selector) {
        if (!empty(theme_color_overrides()[$mode])) {
            $css .= "\n/* Your color choices ($mode) */\n$selector {\n";
            foreach (palette_vars(effective_colors($mode), $mode === 'dark') as $var => $value) {
                $css .= "  --$var: $value;\n";
            }
            $css .= "}\n";
        }
    }
    $custom = trim((string) setting('custom_css', ''));
    if ($custom !== '') {
        $css .= "\n/* Custom CSS */\n$custom\n";
    }
    header('Content-Type: text/css; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . slugify($name) . '.css"');
    return $css;
}

function page_admin_guide(): string
{
    require_admin();
    return admin_view('guide', 'Making a theme', ['section' => 'appearance']);
}

/* ---------- Categories ---------- */

function page_admin_categories(array $errors = [], array $old = []): string
{
    require_admin();
    return admin_view('categories', 'Categories', [
        'errors' => $errors,
        'old' => $old + ['name' => '', 'description' => ''],
        'categories' => all_categories(),
    ]);
}

function category_input(array &$errors, ?int $exceptId = null): array
{
    $name = preg_replace('/\s+/u', ' ', input('name')) ?? '';
    $slug = slugify(input('slug') !== '' ? input('slug') : $name);
    if ($name === '' || text_length($name) > 60) {
        $errors['name'] = 'Give the category a name (up to 60 characters).';
    }
    if (DB::value('SELECT 1 FROM categories WHERE slug = ? AND id != ?', [$slug, $exceptId ?? 0])) {
        $errors['slug'] = 'Another category already uses the web address "' . $slug . '".';
    }
    return ['name' => $name, 'slug' => $slug, 'description' => mb_substr(input('description'), 0, 300)];
}

function page_admin_category_create(): string
{
    require_admin();
    $errors = [];
    $data = category_input($errors);
    if ($errors) {
        http_response_code(422);
        return page_admin_categories($errors, $data);
    }
    DB::insert('categories', $data + [
        'position' => (int) DB::value('SELECT COALESCE(MAX(position), 0) + 1 FROM categories'),
        'created_at' => now(),
    ]);
    admin_redirect('/admin/categories', 'Category "' . $data['name'] . '" added.');
}

function page_admin_category_update(string $id): string
{
    require_admin();
    $errors = [];
    $data = category_input($errors, (int) $id);
    if ($errors) {
        http_response_code(422);
        return page_admin_categories(['edit_' . $id => implode(' ', $errors)]);
    }
    DB::query('UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?', [$data['name'], $data['slug'], $data['description'], (int) $id]);
    admin_redirect('/admin/categories', 'Category saved.');
}

function page_admin_category_move(string $id): string
{
    require_admin();
    $ids = array_map('intval', array_column(DB::all('SELECT id FROM categories ORDER BY position, name'), 'id'));
    $at = array_search((int) $id, $ids, true);
    $to = input('direction') === 'up' ? $at - 1 : $at + 1;
    if ($at !== false && isset($ids[$to])) {
        [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $categoryId) {
                DB::query('UPDATE categories SET position = ? WHERE id = ?', [$position + 1, $categoryId]);
            }
        });
    }
    redirect('/admin/categories');
}

function page_admin_category_delete(string $id): string
{
    require_admin();
    $id = (int) $id;
    if ((int) DB::value('SELECT COUNT(*) FROM categories') <= 1) {
        return page_admin_categories(['general' => 'A forum needs at least one category, so the last one can\'t be deleted.']);
    }
    $moveTo = (int) input('move_to');
    $count = (int) DB::value('SELECT COUNT(*) FROM discussions WHERE category_id = ?', [$id]);
    if ($count > 0 && ($moveTo === $id || !DB::value('SELECT 1 FROM categories WHERE id = ?', [$moveTo]))) {
        return page_admin_categories(['general' => 'Choose where this category\'s discussions should go.']);
    }
    DB::transaction(function () use ($id, $moveTo): void {
        DB::query('UPDATE discussions SET category_id = ? WHERE category_id = ?', [$moveTo, $id]);
        DB::query('DELETE FROM categories WHERE id = ?', [$id]);
    });
    admin_redirect('/admin/categories', 'Category deleted' . ($count ? " and its $count discussion(s) moved." : '.'));
}

/* ---------- Tags ---------- */

function page_admin_tags(array $errors = []): string
{
    require_admin();
    $tags = DB::all(
        'SELECT t.*, COUNT(dt.discussion_id) AS discussion_count FROM tags t
         LEFT JOIN discussion_tags dt ON dt.tag_id = t.id GROUP BY t.id ORDER BY t.name COLLATE NOCASE'
    );
    return admin_view('tags', 'Tags', ['errors' => $errors, 'tags' => $tags]);
}

function valid_tag_name(string $name): bool
{
    return (bool) preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _-]{0,29}$/u', $name);
}

function page_admin_tag_create(): string
{
    require_admin();
    $name = preg_replace('/\s+/u', ' ', input('name')) ?? '';
    if (!valid_tag_name($name)) {
        return page_admin_tags(['name' => 'Tags are up to 30 letters, numbers, spaces or dashes.']);
    }
    if (DB::value('SELECT 1 FROM tags WHERE name = ? COLLATE NOCASE', [$name])) {
        return page_admin_tags(['name' => 'There\'s already a tag called "' . $name . '".']);
    }
    $id = create_tag($name);
    $color = input('color');
    if (isset($_POST['use_color']) && is_hex_color($color)) {
        DB::query('UPDATE tags SET color = ? WHERE id = ?', [strtolower($color), $id]);
        bump_appearance_version();
    }
    admin_redirect('/admin/tags', 'Tag "' . $name . '" added.');
}

function page_admin_tag_update(string $id): string
{
    require_admin();
    $tag = DB::one('SELECT * FROM tags WHERE id = ?', [(int) $id]) ?? not_found();
    $name = preg_replace('/\s+/u', ' ', input('name')) ?? '';
    if (!valid_tag_name($name)) {
        return page_admin_tags(['tag_' . $id => 'Tags are up to 30 letters, numbers, spaces or dashes.']);
    }
    if (DB::value('SELECT 1 FROM tags WHERE name = ? COLLATE NOCASE AND id != ?', [$name, $tag['id']])) {
        return page_admin_tags(['tag_' . $id => 'Another tag is already called "' . $name . '". Use "Merge" to combine them.']);
    }
    $color = isset($_POST['use_color']) && is_hex_color(input('color')) ? strtolower(input('color')) : null;
    $slug = $tag['slug'];
    if (mb_strtolower($name) !== mb_strtolower($tag['name'])) {
        $base = slugify($name);
        $slug = $base;
        for ($n = 2; DB::value('SELECT 1 FROM tags WHERE slug = ? AND id != ?', [$slug, $tag['id']]); $n++) {
            $slug = "$base-$n";
        }
    }
    DB::query('UPDATE tags SET name = ?, slug = ?, color = ? WHERE id = ?', [$name, $slug, $color, $tag['id']]);
    bump_appearance_version();
    admin_redirect('/admin/tags', 'Tag saved.');
}

function page_admin_tag_merge(string $id): string
{
    require_admin();
    $from = DB::one('SELECT * FROM tags WHERE id = ?', [(int) $id]) ?? not_found();
    $into = DB::one('SELECT * FROM tags WHERE id = ?', [(int) input('into')]);
    if ($into === null || (int) $into['id'] === (int) $from['id']) {
        return page_admin_tags(['tag_' . $id => 'Choose a different tag to merge into.']);
    }
    DB::transaction(function () use ($from, $into): void {
        DB::query('INSERT OR IGNORE INTO discussion_tags (discussion_id, tag_id) SELECT discussion_id, ? FROM discussion_tags WHERE tag_id = ?', [$into['id'], $from['id']]);
        DB::query('DELETE FROM tags WHERE id = ?', [$from['id']]);
    });
    bump_appearance_version();
    admin_redirect('/admin/tags', '"' . $from['name'] . '" was merged into "' . $into['name'] . '".');
}

function page_admin_tag_delete(string $id): string
{
    require_admin();
    $tag = DB::one('SELECT * FROM tags WHERE id = ?', [(int) $id]) ?? not_found();
    DB::query('DELETE FROM tags WHERE id = ?', [$tag['id']]);
    bump_appearance_version();
    admin_redirect('/admin/tags', 'Tag "' . $tag['name'] . '" deleted. Discussions that had it are untouched otherwise.');
}

/* ---------- Members ---------- */

const ADMIN_USERS_PER_PAGE = 30;

function page_admin_users(array $errors = []): string
{
    require_admin();
    $q = trim((string) ($_GET['q'] ?? ''));
    $role = (string) ($_GET['role'] ?? '');
    $where = '1 = 1';
    $params = [];
    if ($q !== '') {
        $where .= " AND (username LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\')";
        $like = '%' . addcslashes($q, '%_\\') . '%';
        array_push($params, $like, $like);
    }
    if (in_array($role, ['admin', 'moderator', 'member'], true)) {
        $where .= ' AND role = ?';
        $params[] = $role;
    }
    $page = current_page();
    $total = (int) DB::value("SELECT COUNT(*) FROM users WHERE $where", $params);
    $users = DB::all(
        "SELECT u.*, (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) AS post_count
         FROM users u WHERE $where ORDER BY u.created_at DESC
         LIMIT " . ADMIN_USERS_PER_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_USERS_PER_PAGE),
        $params
    );
    return admin_view('users', 'Members', [
        'errors' => $errors,
        'users' => $users,
        'q' => $q,
        'role' => $role,
        'page' => $page,
        'pages' => max(1, (int) ceil($total / ADMIN_USERS_PER_PAGE)),
        'total' => $total,
        'me' => current_user(),
    ]);
}

function page_admin_user_role(string $id): string
{
    $me = require_admin();
    $user = DB::one('SELECT * FROM users WHERE id = ?', [(int) $id]) ?? not_found();
    $role = input('role');
    if (!in_array($role, ['admin', 'moderator', 'member'], true)) {
        redirect('/admin/users');
    }
    if ($user['role'] === 'admin' && $role !== 'admin' && (int) DB::value("SELECT COUNT(*) FROM users WHERE role = 'admin'") <= 1) {
        return page_admin_users(['general' => 'The forum needs at least one admin. Make someone else an admin first.']);
    }
    DB::query('UPDATE users SET role = ? WHERE id = ?', [$role, $user['id']]);
    $labels = ['admin' => 'an admin', 'moderator' => 'a moderator', 'member' => 'a regular member'];
    if ((int) $user['id'] === (int) $me['id'] && $role !== 'admin') {
        flash('success', 'You are now ' . $labels[$role] . '.');
        redirect('/');
    }
    flash('success', $user['username'] . ' is now ' . $labels[$role] . '.');
    redirect('/admin/users', array_filter(['q' => $_GET['q'] ?? null]));
}

function page_admin_user_confirm(string $id): string
{
    require_admin();
    $user = DB::one('SELECT * FROM users WHERE id = ?', [(int) $id]) ?? not_found();
    DB::query('UPDATE users SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?', [now(), $user['id']]);
    admin_redirect('/admin/users', $user['username'] . '\'s email is marked as confirmed.');
}

/* ---------- Backup ---------- */

function page_admin_backup(): string
{
    require_admin();
    $dbFile = AGORA_DATA . '/' . config('database');
    $avatars = glob(avatar_dir() . '/*') ?: [];
    return admin_view('backup', 'Backup', [
        'dbSize' => is_file($dbFile) ? filesize($dbFile) : 0,
        'avatarCount' => count($avatars),
    ]);
}

/** Download a consistent copy of the database (safe even while people are posting). */
function page_admin_backup_download(): string
{
    require_admin();
    $temp = AGORA_DATA . '/backup-' . bin2hex(random_bytes(6)) . '.tmp';
    try {
        DB::query('VACUUM INTO ?', [$temp]);
    } catch (Throwable) {
        copy(AGORA_DATA . '/' . config('database'), $temp); // very old SQLite
    }
    $name = slugify((string) setting('site_name', 'agora')) . '-backup-' . gmdate('Y-m-d') . '.sqlite';
    header('Content-Type: application/vnd.sqlite3');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($temp));
    header('Cache-Control: no-store');
    readfile($temp);
    @unlink($temp);
    exit;
}

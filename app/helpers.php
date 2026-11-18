<?php
declare(strict_types=1);
defined('AGORA') or exit;

/* ---------- Config ---------- */

function config_file(): string
{
    return AGORA_DATA . '/config.php';
}

function is_installed(): bool
{
    return is_file(config_file());
}

/** Read a value from data/config.php (written by the installer). */
function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = is_installed() ? (require config_file()) : [];
        if (!is_array($config)) {
            $config = [];
        }
    }
    return $config[$key] ?? $default;
}

/** Read a forum setting stored in the database (site name, etc.). */
function setting(string $key, ?string $default = null): ?string
{
    if (!DB::connected()) {
        return $default;
    }
    if (!isset($GLOBALS['agora_settings'])) {
        $GLOBALS['agora_settings'] = [];
        foreach (DB::all('SELECT key, value FROM settings') as $row) {
            $GLOBALS['agora_settings'][$row['key']] = $row['value'];
        }
    }
    return $GLOBALS['agora_settings'][$key] ?? $default;
}

/** Save forum settings (admin panel). */
function save_settings(array $values): void
{
    foreach ($values as $key => $value) {
        DB::query('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)', [$key, (string) $value]);
    }
    unset($GLOBALS['agora_settings']);
}

/* ---------- Output ---------- */

/** Escape text for safe use in HTML. Use on everything a user typed. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Render a template inside the page layout.
 * Templates live in templates/ and receive $data as variables.
 */
function view(string $template, array $data = [], ?int $status = null): string
{
    if ($status !== null) {
        http_response_code($status);
    }
    header('Content-Type: text/html; charset=utf-8');
    $content = partial($template, $data);
    return partial('layout', $data + ['content' => $content]);
}

function partial(string $template, array $data = []): string
{
    $file = AGORA_ROOT . '/templates/' . $template . '.php';
    if (!preg_match('/^[a-z0-9_\/-]+$/', $template) || !is_file($file)) {
        throw new RuntimeException("Template not found: $template");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        require $file;
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    return (string) ob_get_clean();
}

function not_found(): never
{
    echo view('message', [
        'title' => 'Page not found',
        'message' => "We couldn't find that page. It may have been moved or deleted.",
    ], 404);
    exit;
}

/* ---------- URLs & requests ---------- */

/** The folder the forum lives in, e.g. "" or "/forum". */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        if (PHP_SAPI === 'cli-server') {
            $base = '';
        } else {
            $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $base = rtrim($dir, '/.');
        }
    }
    return $base;
}

/** Whether the server rewrites clean URLs (/d/5) to index.php. */
function pretty_urls(): bool
{
    $setting = config('pretty_urls', 'auto');
    if (is_bool($setting)) {
        return $setting;
    }
    return PHP_SAPI === 'cli-server'
        || getenv('AGORA_REWRITE') === '1'
        || ($_SERVER['AGORA_REWRITE'] ?? '') === '1'
        || ($_SERVER['REDIRECT_AGORA_REWRITE'] ?? '') === '1';
}

/** Build a link to a forum page. url('/c/general') */
function url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    if (pretty_urls() || $path === '/') {
        $url = base_path() . $path;
    } else {
        $url = base_path() . '/index.php' . $path;
    }
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    return $url;
}

/**
 * The forum's full address, e.g. "https://forum.example.com".
 * Saved at install time so email links can't be faked by a forged Host header.
 */
function site_url(): string
{
    $saved = setting('site_url');
    if ($saved) {
        return rtrim($saved, '/');
    }
    return detected_site_url();
}

function detected_site_url(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[A-Za-z0-9.-]+(:\d+)?$|^\[[0-9a-fA-F:]+\](:\d+)?$/', $host)) {
        $host = 'localhost';
    }
    return (is_https() ? 'https' : 'http') . '://' . $host . base_path();
}

/** A full link for use in emails. */
function absolute_url(string $path = '/', array $query = []): string
{
    $relative = url($path, $query);
    $base = base_path();
    if ($base !== '' && str_starts_with($relative, $base)) {
        $relative = substr($relative, strlen($base));
    }
    return site_url() . $relative;
}

function asset(string $file): string
{
    return base_path() . '/assets/' . $file . '?v=' . rawurlencode(AGORA_VERSION);
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/** The page being asked for, e.g. "/c/general", independent of install folder. */
function request_path(): string
{
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    if (str_starts_with($path, '/index.php')) {
        $path = substr($path, strlen('/index.php'));
    }
    $path = '/' . trim($path, '/');
    return $path;
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query), true, 303);
    exit;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/* ---------- Security ---------- */

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('agora_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (base_path() ?: '') . '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** Stop the request if a form wasn't sent from this forum. */
function csrf_verify(): void
{
    $sent = $_POST['_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        echo view('message', [
            'title' => 'Please try again',
            'message' => 'This form expired or was sent from somewhere else. Go back, refresh the page, and try again.',
        ], 419);
        exit;
    }
}

/**
 * Allow an action at most $max times per $minutes for a subject (an IP address or user id).
 * Returns false when the limit is reached; otherwise records the attempt and returns true.
 */
function rate_limit(string $action, string $subject, int $max, int $minutes): bool
{
    $since = gmdate('Y-m-d H:i:s', time() - $minutes * 60);
    DB::query('DELETE FROM rate_limits WHERE action = ? AND created_at < ?', [$action, $since]);
    $count = (int) DB::value(
        'SELECT COUNT(*) FROM rate_limits WHERE action = ? AND subject = ?',
        [$action, $subject]
    );
    if ($count >= $max) {
        return false;
    }
    DB::insert('rate_limits', ['action' => $action, 'subject' => $subject, 'created_at' => now()]);
    return true;
}

/* ---------- Flash messages & forms ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

/**
 * A labelled form field with hint and error message, wired up for screen readers.
 * Options: type, value, error, hint, autocomplete, required (default true), placeholder, maxlength.
 */
function form_field(string $name, string $label, array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $id = 'f-' . $name;
    $required = $o['required'] ?? true;
    $error = $o['error'] ?? '';
    $hint = $o['hint'] ?? '';
    $value = $type === 'password' ? '' : (string) ($o['value'] ?? '');
    $describedBy = trim(($hint ? "$id-hint " : '') . ($error ? "$id-error" : ''));

    $attrs = ' id="' . $id . '" name="' . e($name) . '"'
        . ($required ? ' required' : '')
        . (isset($o['autocomplete']) ? ' autocomplete="' . e($o['autocomplete']) . '"' : '')
        . (isset($o['placeholder']) ? ' placeholder="' . e($o['placeholder']) . '"' : '')
        . (isset($o['maxlength']) ? ' maxlength="' . (int) $o['maxlength'] . '"' : '')
        . ($describedBy ? ' aria-describedby="' . $describedBy . '"' : '')
        . ($error ? ' aria-invalid="true"' : '');

    $html = '<div class="field' . ($error ? ' field--error' : '') . '">'
        . '<label for="' . $id . '">' . e($label) . ($required ? '' : ' <span class="optional">(optional)</span>') . '</label>';
    if ($hint) {
        $html .= '<p class="field__hint" id="' . $id . '-hint">' . e($hint) . '</p>';
    }
    $html .= $type === 'textarea'
        ? '<textarea' . $attrs . ' rows="4">' . e($value) . '</textarea>'
        : '<input type="' . e($type) . '"' . $attrs . ' value="' . e($value) . '">';
    if ($error) {
        $html .= '<p class="field__error" id="' . $id . '-error">' . e($error) . '</p>';
    }
    return $html . '</div>';
}

/** A trimmed text field from a submitted form. */
function input(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/* ---------- Text & time ---------- */

function text_length(string $text): int
{
    return mb_strlen($text, 'UTF-8');
}

function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    if (function_exists('transliterator_transliterate')) {
        $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
    }
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? substr($text, 0, 80) : 'item';
}

/** Current time in UTC, as stored in the database. */
function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** "5 minutes ago", "3 days ago", or a date for older things. */
function time_ago(?string $utc): string
{
    if (!$utc) {
        return '';
    }
    $seconds = time() - (int) strtotime($utc . ' UTC');
    if ($seconds < 60) {
        return 'just now';
    }
    $units = [['minute', 60], ['hour', 3600], ['day', 86400]];
    $label = null;
    foreach ($units as [$name, $size]) {
        $n = intdiv($seconds, $size);
        if ($n >= 1) {
            $label = $n . ' ' . $name . ($n === 1 ? '' : 's') . ' ago';
        }
    }
    if ($seconds >= 86400 * 30) {
        return gmdate('M j, Y', (int) strtotime($utc . ' UTC'));
    }
    return $label ?? 'just now';
}

/** Machine-readable date for <time datetime="">. */
function iso_time(?string $utc): string
{
    return $utc ? gmdate('c', (int) strtotime($utc . ' UTC')) : '';
}

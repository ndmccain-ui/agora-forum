<?php
declare(strict_types=1);
defined('AGORA') or exit;

const REMEMBER_COOKIE = 'agora_remember';
const REMEMBER_DAYS = 30;

/** The signed-in user, or null for guests. */
function current_user(): ?array
{
    static $resolved = false;
    static $user = null;
    if ($resolved && !isset($GLOBALS['agora_user_changed'])) {
        return $user;
    }
    unset($GLOBALS['agora_user_changed']);
    $resolved = true;
    $user = null;

    if (!DB::connected()) {
        return null;
    }

    $id = $_SESSION['user_id'] ?? null;
    if (is_int($id)) {
        $user = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
        // Signed out everywhere (password changed) or account deleted.
        // Sessions from before auth_version existed count as version 1.
        if ($user === null || (int) $user['auth_version'] !== ($_SESSION['auth_version'] ?? 1)) {
            $user = null;
            unset($_SESSION['user_id'], $_SESSION['auth_version']);
        }
    }

    if ($user === null && isset($_COOKIE[REMEMBER_COOKIE])) {
        $user = login_from_remember_cookie((string) $_COOKIE[REMEMBER_COOKIE]);
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function is_moderator(?array $user): bool
{
    return in_array($user['role'] ?? null, ['admin', 'moderator'], true);
}

const PERMANENT_BAN = '9999-12-31 00:00:00';

function is_banned(?array $user): bool
{
    return $user !== null && !empty($user['banned_until']) && $user['banned_until'] > now();
}

function ban_description(array $user): string
{
    $until = $user['banned_until'] === PERMANENT_BAN
        ? 'permanently'
        : 'until ' . gmdate('F j, Y \a\t H:i', (int) strtotime($user['banned_until'] . ' UTC')) . ' UTC';
    return 'banned ' . $until . (!empty($user['ban_reason']) ? ' — ' . $user['ban_reason'] : '');
}

/** Is this user still waiting to confirm their email address? */
function needs_email_confirmation(?array $user): bool
{
    return $user !== null
        && $user['email_verified_at'] === null
        && setting('require_email_confirmation', '1') === '1';
}

function login_user(int $userId, bool $remember = false): void
{
    $user = DB::one('SELECT id, auth_version FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        return;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['auth_version'] = (int) $user['auth_version'];
    DB::query('UPDATE users SET last_seen_at = ? WHERE id = ?', [now(), $userId]);
    if ($remember) {
        issue_remember_cookie($userId);
    }
    $GLOBALS['agora_user_changed'] = true;
}

function logout_user(): void
{
    if (isset($_COOKIE[REMEMBER_COOKIE])) {
        [$selector] = explode(':', (string) $_COOKIE[REMEMBER_COOKIE], 2) + [''];
        DB::query('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
        set_remember_cookie('', time() - 3600);
    }
    $_SESSION = [];
    session_regenerate_id(true);
    $GLOBALS['agora_user_changed'] = true;
}

/** Sign a user out on every device (after a password change or reset). */
function bump_auth_version(int $userId): void
{
    DB::query('UPDATE users SET auth_version = auth_version + 1 WHERE id = ?', [$userId]);
    DB::query('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
}

/* ---------- Remember me ---------- */

function set_remember_cookie(string $value, int $expires): void
{
    setcookie(REMEMBER_COOKIE, $value, [
        'expires' => $expires,
        'path' => base_path() . '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function issue_remember_cookie(int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + REMEMBER_DAYS * 86400;
    DB::insert('remember_tokens', [
        'user_id' => $userId,
        'selector' => $selector,
        'validator_hash' => hash('sha256', $validator),
        'expires_at' => gmdate('Y-m-d H:i:s', $expires),
        'created_at' => now(),
    ]);
    set_remember_cookie($selector . ':' . $validator, $expires);
}

function login_from_remember_cookie(string $cookie): ?array
{
    [$selector, $validator] = explode(':', $cookie, 2) + ['', ''];
    $token = DB::one('SELECT * FROM remember_tokens WHERE selector = ?', [$selector]);

    if ($token === null || $token['expires_at'] < now()) {
        set_remember_cookie('', time() - 3600);
        return null;
    }
    if (!hash_equals($token['validator_hash'], hash('sha256', $validator))) {
        // Right selector, wrong secret: the cookie may have been stolen. Revoke all of them.
        DB::query('DELETE FROM remember_tokens WHERE user_id = ?', [$token['user_id']]);
        set_remember_cookie('', time() - 3600);
        return null;
    }

    // Use each remember token once, then swap it for a fresh one.
    DB::query('DELETE FROM remember_tokens WHERE id = ?', [$token['id']]);
    $userId = (int) $token['user_id'];
    session_regenerate_id(true);
    $user = DB::one('SELECT * FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        return null;
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['auth_version'] = (int) $user['auth_version'];
    issue_remember_cookie($userId);
    return $user;
}

/* ---------- Passwords ---------- */

const LOGIN_MAX_ATTEMPTS = 10;
const LOGIN_WINDOW_MINUTES = 15;

function login_is_throttled(): bool
{
    $since = gmdate('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
    DB::query('DELETE FROM login_attempts WHERE attempted_at < ?', [$since]);
    $count = (int) DB::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [client_ip()]);
    return $count >= LOGIN_MAX_ATTEMPTS;
}

/**
 * Check a username-or-email and password. Returns the user on success.
 * Failed attempts are counted per IP address to slow down password guessing.
 */
function attempt_login(string $login, string $password): ?array
{
    $user = DB::one(
        'SELECT * FROM users WHERE username = ? COLLATE NOCASE OR email = ? COLLATE NOCASE',
        [$login, $login]
    );

    if ($user === null || !password_verify($password, $user['password_hash'])) {
        DB::insert('login_attempts', ['ip' => client_ip(), 'attempted_at' => now()]);
        return null;
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        DB::query('UPDATE users SET password_hash = ? WHERE id = ?', [
            password_hash($password, PASSWORD_DEFAULT),
            $user['id'],
        ]);
    }
    DB::query('DELETE FROM login_attempts WHERE ip = ?', [client_ip()]);
    return $user;
}

function validate_username(string $username): ?string
{
    if (!preg_match('/^[A-Za-z0-9_-]{3,30}$/', $username)) {
        return 'Use 3–30 letters, numbers, dashes or underscores.';
    }
    return null;
}

function validate_email(string $email): ?string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        return 'Enter a valid email address.';
    }
    return null;
}

function validate_password(string $password, string $confirm, array &$errors, string $field = 'password'): void
{
    if (strlen($password) < 8) {
        $errors[$field] = 'Use at least 8 characters.';
    } elseif (strlen($password) > 200) {
        $errors[$field] = 'That password is too long.';
    }
    if ($password !== $confirm) {
        $errors[$field . '_confirm'] = "The passwords don't match.";
    }
}

/** Validation shared by the installer and sign-up. Returns error messages keyed by field. */
function validate_new_account(string $username, string $email, string $password, string $confirm): array
{
    $errors = array_filter([
        'username' => validate_username($username),
        'email' => validate_email($email),
    ]);
    validate_password($password, $confirm, $errors);
    return $errors;
}

/* ---------- One-time email links ---------- */

/** Create a secret link token. Only its hash is stored, so a leaked database can't be used to take over accounts. */
function create_user_token(int $userId, string $type, int $minutes): string
{
    DB::query('DELETE FROM user_tokens WHERE user_id = ? AND type = ?', [$userId, $type]);
    $token = bin2hex(random_bytes(32));
    DB::insert('user_tokens', [
        'user_id' => $userId,
        'type' => $type,
        'token_hash' => hash('sha256', $token),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + $minutes * 60),
        'created_at' => now(),
    ]);
    return $token;
}

/** Look up a valid (unexpired) token. Returns the token row, or null. */
function find_user_token(string $token, string $type): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return DB::one(
        'SELECT * FROM user_tokens WHERE token_hash = ? AND type = ? AND expires_at > ?',
        [hash('sha256', $token), $type, now()]
    );
}

function send_confirmation_email(array $user): bool
{
    $token = create_user_token((int) $user['id'], 'confirm_email', 60 * 48);
    $link = absolute_url('/confirm/' . $token);
    return send_mail(
        $user['email'],
        'Confirm your email for ' . setting('site_name', 'Agora Forum'),
        "Hi {$user['username']},\n\n"
        . "Please confirm your email address by opening this link:\n\n$link\n\n"
        . "The link works for 48 hours. If you didn't sign up, you can ignore this email."
    );
}

function send_password_reset_email(array $user): bool
{
    $token = create_user_token((int) $user['id'], 'reset_password', 60);
    $link = absolute_url('/reset/' . $token);
    return send_mail(
        $user['email'],
        'Reset your password for ' . setting('site_name', 'Agora Forum'),
        "Hi {$user['username']},\n\n"
        . "Someone (hopefully you) asked to reset your password. Open this link to choose a new one:\n\n$link\n\n"
        . "The link works for 1 hour and can only be used once. If you didn't ask for this, you can ignore this email — your password won't change."
    );
}

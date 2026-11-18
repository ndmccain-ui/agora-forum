<?php
declare(strict_types=1);
defined('AGORA') or exit;

/** How many days of recent activity a brand-new member sees as unread. */
const NEW_MEMBER_UNREAD_DAYS = 7;

/** Send guests to the sign-in page, then bring them back here afterwards. */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $_SESSION['after_login'] = request_path();
        flash('info', 'Please sign in first.');
        redirect('/login');
    }
    return $user;
}

function redirect_after_login(): never
{
    $next = $_SESSION['after_login'] ?? '/';
    unset($_SESSION['after_login']);
    // Only ever go back to a page on this forum.
    if (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
        $next = '/';
    }
    redirect($next);
}

function registration_open(): bool
{
    return setting('registration', 'open') === 'open';
}

function minimum_age(): int
{
    return max(0, (int) setting('minimum_age', '13'));
}

/* ---------- Sign in / out ---------- */

function page_login(string $error = '', string $login = ''): string
{
    if (current_user()) {
        redirect('/');
    }
    return view('login', [
        'title' => 'Sign in',
        'error' => $error,
        'login' => $login,
        'canRegister' => registration_open(),
    ]);
}

function page_login_submit(): string
{
    $login = input('login');
    $password = (string) ($_POST['password'] ?? '');

    if (login_is_throttled()) {
        http_response_code(429);
        return page_login('Too many sign-in attempts. Please wait 15 minutes and try again.', $login);
    }

    $user = attempt_login($login, $password);
    if ($user === null) {
        http_response_code(422);
        return page_login("That username/email and password don't match.", $login);
    }

    login_user((int) $user['id'], isset($_POST['remember']));
    flash('success', 'Welcome back, ' . $user['username'] . '!');
    redirect_after_login();
}

function page_logout(): string
{
    logout_user();
    flash('info', "You've signed out.");
    redirect('/');
}

/* ---------- Sign up ---------- */

function page_register(array $errors = [], array $old = []): string
{
    if (current_user()) {
        redirect('/');
    }
    if (!registration_open()) {
        return view('message', [
            'title' => 'Sign-ups are closed',
            'message' => "This forum isn't accepting new members right now.",
        ]);
    }
    return view('register', [
        'title' => 'Create an account',
        'errors' => $errors,
        'old' => $old + ['username' => '', 'email' => ''],
        'minimumAge' => minimum_age(),
    ]);
}

function page_register_submit(): string
{
    if (current_user() || !registration_open()) {
        return page_register();
    }

    $old = ['username' => input('username'), 'email' => input('email')];
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    // Hidden "website" field: people never see it, but spam bots fill it in.
    if (input('website') !== '') {
        http_response_code(422);
        return page_register(['general' => 'Something went wrong. Please try again.'], $old);
    }

    $errors = validate_new_account($old['username'], $old['email'], $password, $confirm);
    if (minimum_age() > 0 && !isset($_POST['age_ok'])) {
        $errors['age_ok'] = 'You need to be ' . minimum_age() . ' or older to join.';
    }
    if (!isset($errors['username']) && DB::value('SELECT 1 FROM users WHERE username = ? COLLATE NOCASE', [$old['username']])) {
        $errors['username'] = 'That username is taken.';
    }
    if (!isset($errors['email']) && DB::value('SELECT 1 FROM users WHERE email = ? COLLATE NOCASE', [$old['email']])) {
        $errors['email'] = 'There\'s already an account with that email. Try signing in or resetting your password.';
    }
    if ($errors) {
        http_response_code(422);
        return page_register($errors, $old);
    }

    if (!rate_limit('register', client_ip(), 5, 60)) {
        http_response_code(429);
        return page_register(['general' => 'Too many new accounts from your connection. Please try again later.'], $old);
    }

    $confirmationNeeded = setting('require_email_confirmation', '1') === '1';
    $userId = DB::insert('users', [
        'username' => $old['username'],
        'email' => $old['email'],
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'member',
        'email_verified_at' => $confirmationNeeded ? null : now(),
        'created_at' => now(),
        // New members see the last week's activity as unread; anything older counts as read.
        'read_all_post_id' => (int) DB::value(
            'SELECT COALESCE(MAX(id), 0) FROM posts WHERE created_at < ?',
            [gmdate('Y-m-d H:i:s', time() - NEW_MEMBER_UNREAD_DAYS * 86400)]
        ),
    ]);

    login_user($userId);

    if ($confirmationNeeded) {
        $user = DB::one('SELECT * FROM users WHERE id = ?', [$userId]);
        send_confirmation_email($user);
        flash('success', 'Welcome, ' . $old['username'] . '! We sent a link to ' . $old['email'] . ' — open it to confirm your email.');
    } else {
        flash('success', 'Welcome, ' . $old['username'] . '!');
    }
    redirect('/');
}

/* ---------- Email confirmation ---------- */

function page_confirm_email(string $token): string
{
    $row = find_user_token($token, 'confirm_email');
    if ($row === null) {
        return view('message', [
            'title' => 'That link has expired',
            'message' => 'Confirmation links work for 48 hours and only once. Sign in and use "Send a new link" to get another.',
        ], 410);
    }
    DB::query('UPDATE users SET email_verified_at = ? WHERE id = ?', [now(), $row['user_id']]);
    DB::query('DELETE FROM user_tokens WHERE id = ?', [$row['id']]);
    flash('success', 'Thanks — your email is confirmed!');
    redirect('/');
}

function page_confirm_resend(): string
{
    $user = require_login();
    if (!needs_email_confirmation($user)) {
        redirect('/');
    }
    if (!rate_limit('confirm_resend', (string) $user['id'], 3, 60)) {
        flash('error', 'We\'ve sent a few links already. Please check your spam folder, or try again in an hour.');
        redirect('/');
    }
    send_confirmation_email($user);
    flash('success', 'We sent a new link to ' . $user['email'] . '.');
    redirect('/');
}

/* ---------- Forgotten password ---------- */

function page_forgot(string $email = '', bool $sent = false): string
{
    return view('forgot', ['title' => 'Reset your password', 'email' => $email, 'sent' => $sent]);
}

function page_forgot_submit(): string
{
    $email = input('email');
    if (!rate_limit('forgot', client_ip(), 5, 60)) {
        http_response_code(429);
        return view('message', [
            'title' => 'Please wait a bit',
            'message' => 'Too many reset requests from your connection. Please try again in an hour.',
        ], 429);
    }
    $user = validate_email($email) === null
        ? DB::one('SELECT * FROM users WHERE email = ? COLLATE NOCASE', [$email])
        : null;
    if ($user !== null) {
        send_password_reset_email($user);
    }
    // Same answer either way, so this page can't be used to find out who has an account.
    return page_forgot($email, true);
}

function page_reset(string $token, array $errors = []): string
{
    if (find_user_token($token, 'reset_password') === null) {
        return view('message', [
            'title' => 'That link has expired',
            'message' => 'Password reset links work for 1 hour and only once. You can ask for a new one from the sign-in page.',
        ], 410);
    }
    return view('reset', ['title' => 'Choose a new password', 'token' => $token, 'errors' => $errors]);
}

function page_reset_submit(string $token): string
{
    $row = find_user_token($token, 'reset_password');
    if ($row === null) {
        return page_reset($token);
    }
    $password = (string) ($_POST['password'] ?? '');
    $errors = [];
    validate_password($password, (string) ($_POST['password_confirm'] ?? ''), $errors);
    if ($errors) {
        http_response_code(422);
        return page_reset($token, $errors);
    }

    $userId = (int) $row['user_id'];
    DB::transaction(function () use ($userId, $password, $row): void {
        // They proved they can read this inbox, so the email counts as confirmed too.
        DB::query(
            'UPDATE users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), now(), $userId]
        );
        DB::query('DELETE FROM user_tokens WHERE user_id = ? AND type = ?', [$userId, 'reset_password']);
        bump_auth_version($userId);
    });

    login_user($userId);
    flash('success', 'Your password has been changed, and you\'re signed in. Any other devices have been signed out.');
    redirect('/');
}

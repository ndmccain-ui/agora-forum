<?php
declare(strict_types=1);
defined('AGORA') or exit;

const BIO_MAX = 500;

function page_profile(string $username): string
{
    $profile = DB::one('SELECT * FROM users WHERE username = ? COLLATE NOCASE', [$username]);
    if ($profile === null) {
        not_found();
    }
    $id = (int) $profile['id'];

    $posts = DB::all(
        'SELECT p.id, p.body, p.created_at, d.id AS discussion_id, d.slug, d.title
         FROM posts p JOIN discussions d ON d.id = p.discussion_id
         WHERE p.user_id = ? AND p.is_hidden = 0 AND d.is_hidden = 0
         ORDER BY p.id DESC LIMIT 10',
        [$id]
    );

    return view('profile', [
        'title' => $profile['username'],
        'profile' => $profile,
        'isMe' => (current_user()['id'] ?? null) === $id,
        'postCount' => (int) DB::value('SELECT COUNT(*) FROM posts WHERE user_id = ? AND is_hidden = 0', [$id]),
        'discussionCount' => (int) DB::value('SELECT COUNT(*) FROM discussions WHERE user_id = ? AND is_hidden = 0', [$id]),
        'posts' => $posts,
    ]);
}

function page_settings(array $errors = [], ?string $section = null): string
{
    $user = require_login();
    return view('settings', [
        'title' => 'Your settings',
        'user' => $user,
        'errors' => $errors,
        'section' => $section,
        'bioMax' => BIO_MAX,
        'canUpload' => function_exists('imagecreatetruecolor'),
        'needsConfirmation' => needs_email_confirmation($user),
    ]);
}

function page_settings_profile(): string
{
    $user = require_login();
    $bio = str_replace("\r\n", "\n", input('bio'));
    if (text_length($bio) > BIO_MAX) {
        http_response_code(422);
        return page_settings(['bio' => 'Keep your bio under ' . BIO_MAX . ' characters.'], 'profile');
    }
    DB::query('UPDATE users SET bio = ? WHERE id = ?', [$bio, $user['id']]);
    flash('success', 'Profile saved.');
    redirect('/settings');
}

function page_settings_avatar(): string
{
    $user = require_login();
    try {
        $file = save_avatar_upload($_FILES['avatar'] ?? [], (int) $user['id']);
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        return page_settings(['avatar' => $e->getMessage()], 'avatar');
    }
    DB::query('UPDATE users SET avatar = ? WHERE id = ?', [$file, $user['id']]);
    delete_avatar_file($user['avatar']);
    flash('success', 'Picture updated.');
    redirect('/settings');
}

function page_settings_avatar_remove(): string
{
    $user = require_login();
    DB::query('UPDATE users SET avatar = NULL WHERE id = ?', [$user['id']]);
    delete_avatar_file($user['avatar']);
    flash('success', 'Picture removed.');
    redirect('/settings');
}

function page_settings_email(): string
{
    $user = require_login();
    $email = input('email');
    $errors = array_filter(['email' => validate_email($email)]);

    if (!password_verify((string) ($_POST['email_password'] ?? ''), $user['password_hash'])) {
        $errors['email_password'] = 'That password isn\'t right.';
    }
    if (!$errors && strcasecmp($email, $user['email']) === 0) {
        redirect('/settings');
    }
    if (!$errors && DB::value('SELECT 1 FROM users WHERE email = ? COLLATE NOCASE AND id != ?', [$email, $user['id']])) {
        $errors['email'] = 'Another account already uses that email.';
    }
    if ($errors) {
        http_response_code(422);
        return page_settings($errors, 'email');
    }

    $confirm = setting('require_email_confirmation', '1') === '1';
    DB::query('UPDATE users SET email = ?, email_verified_at = ? WHERE id = ?', [
        $email,
        $confirm ? null : now(),
        $user['id'],
    ]);
    if ($confirm) {
        send_confirmation_email(['email' => $email] + $user);
        flash('success', 'Email changed. We sent a link to ' . $email . ' — open it to confirm.');
    } else {
        flash('success', 'Email changed.');
    }
    redirect('/settings');
}

function page_settings_password(): string
{
    $user = require_login();
    $errors = [];
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $user['password_hash'])) {
        $errors['current_password'] = 'That password isn\'t right.';
    }
    validate_password((string) ($_POST['new_password'] ?? ''), (string) ($_POST['new_password_confirm'] ?? ''), $errors, 'new_password');
    if ($errors) {
        http_response_code(422);
        return page_settings($errors, 'password');
    }

    DB::query('UPDATE users SET password_hash = ? WHERE id = ?', [
        password_hash((string) $_POST['new_password'], PASSWORD_DEFAULT),
        $user['id'],
    ]);
    bump_auth_version((int) $user['id']);
    login_user((int) $user['id']); // keep this device signed in
    flash('success', 'Password changed. Any other devices have been signed out.');
    redirect('/settings');
}

/** Serve a profile picture from the private data folder. */
function page_avatar(string $file): string
{
    $path = avatar_dir() . '/' . $file;
    if (!is_file($path)) {
        not_found();
    }
    header('Content-Type: ' . (str_ends_with($file, '.webp') ? 'image/webp' : 'image/png'));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($path);
    exit;
}

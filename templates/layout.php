<?php
defined('AGORA') or exit;
/** @var string $content */
$siteName = setting('site_name', 'Agora Forum');
$pageTitle = isset($title) && $title !== null ? $title . ' · ' . $siteName : $siteName;
$user = current_user();
$bare = $bare ?? false;
$path = request_path();
?>
<!doctype html>
<html lang="en" data-default-theme="<?= e(setting('theme_default_mode', 'auto')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <?php if (setting('site_description')): ?>
        <meta name="description" content="<?= e(setting('site_description')) ?>">
    <?php endif; ?>
    <meta name="color-scheme" content="light dark">
    <script src="<?= e(asset('theme.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset('agora.css')) ?>">
    <?php if (DB::connected()): ?>
        <link rel="stylesheet" href="<?= e(url('/theme.css', ['v' => theme_css_version()])) ?>">
    <?php endif; ?>
    <script src="<?= e(asset('agora.js')) ?>" defer></script>
    <link rel="icon" href="<?= e(asset('icon.svg')) ?>" type="image/svg+xml">
</head>
<body data-preview-url="<?= e(url('/preview')) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="container site-header__inner">
        <a class="site-name" href="<?= e(url('/')) ?>">
            <?php if (DB::connected() && ($logo = logo_url())): ?>
                <img class="site-logo" src="<?= e($logo) ?>" alt="<?= e($siteName) ?>">
            <?php else: ?>
                <?= e($siteName) ?>
            <?php endif; ?>
        </a>

        <?php if (!$bare): ?>
            <nav class="site-nav" aria-label="Main">
                <a href="<?= e(url('/')) ?>" <?= $path === '/' || str_starts_with($path, '/c/') ? 'aria-current="page"' : '' ?>>Discussions</a>
                <a href="<?= e(url('/tags')) ?>" <?= $path === '/tags' || str_starts_with($path, '/t/') ? 'aria-current="page"' : '' ?>>Tags</a>
                <a href="<?= e(url('/search')) ?>" <?= $path === '/search' ? 'aria-current="page"' : '' ?>>Search</a>
                <?php if (is_moderator($user)): ?>
                    <?php $modCount = array_sum(moderation_counts()); ?>
                    <a href="<?= e(url('/mod')) ?>" <?= str_starts_with($path, '/mod') ? 'aria-current="page"' : '' ?>>
                        Moderation<?php if ($modCount): ?> <span class="nav-count" title="<?= $modCount ?> waiting"><?= $modCount ?></span><?php endif; ?>
                    </a>
                <?php endif; ?>
                <?php if (($user['role'] ?? null) === 'admin'): ?>
                    <a href="<?= e(url('/admin')) ?>" <?= str_starts_with($path, '/admin') ? 'aria-current="page"' : '' ?>>Admin</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <div class="site-header__actions">
            <button type="button" class="icon-button" data-theme-toggle aria-label="Color theme: automatic" title="Color theme">
                <svg class="theme-icon theme-icon--auto" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 0 0 0 16z" fill="currentColor"/></svg>
                <svg class="theme-icon theme-icon--light" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg class="theme-icon theme-icon--dark" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
            </button>

            <?php if (!$bare): ?>
                <?php if ($user): ?>
                    <a class="user-chip" href="<?= e(url('/u/' . $user['username'])) ?>" title="Your profile">
                        <?= avatar_html($user) ?>
                        <span class="user-chip__name"><?= e($user['username']) ?></span>
                    </a>
                    <a class="icon-button" href="<?= e(url('/settings')) ?>" aria-label="Settings" title="Settings">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
                    </a>
                    <form method="post" action="<?= e(url('/logout')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="button button--quiet">Sign out</button>
                    </form>
                <?php else: ?>
                    <a class="button button--quiet" href="<?= e(url('/login')) ?>">Sign in</a>
                    <?php if (setting('registration', 'open') === 'open'): ?>
                        <a class="button button--primary" href="<?= e(url('/register')) ?>">Sign up</a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</header>

<main id="main" class="container site-main">
    <?php if (!$bare && is_banned($user)): ?>
        <div class="notice notice--error" role="status">You are <?= e(ban_description($user)) ?>. You can still read, but not post.</div>
    <?php endif; ?>

    <?php if (!$bare && needs_email_confirmation($user) && !str_starts_with($path, '/settings')): ?>
        <div class="notice notice--warning">
            Please confirm your email — we sent a link to <strong><?= e($user['email']) ?></strong>.
            <form method="post" action="<?= e(url('/confirm/resend')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="link-button">Send a new link</button>
            </form>
        </div>
    <?php endif; ?>

    <?php foreach (take_flashes() as $flash): ?>
        <div class="notice notice--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container">
        Powered by Agora Forum
    </div>
</footer>
</body>
</html>

<?php
defined('AGORA') or exit;
/** @var string $section @var string $heading @var string $inner */
$menu = [
    'dashboard' => ['/admin', 'Dashboard'],
    'settings' => ['/admin/settings', 'Settings'],
    'categories' => ['/admin/categories', 'Categories'],
    'tags' => ['/admin/tags', 'Tags'],
    'users' => ['/admin/users', 'Members'],
    'appearance' => ['/admin/appearance', 'Appearance'],
    'email' => ['/admin/email', 'Email'],
    'backup' => ['/admin/backup', 'Backup'],
];
?>
<div class="with-sidebar admin">
    <aside class="sidebar" aria-label="Admin menu">
        <p class="sidebar__label">Admin</p>
        <nav class="category-nav">
            <?php foreach ($menu as $key => [$path, $label]): ?>
                <a href="<?= e(url($path)) ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <section class="content">
        <header class="page-header"><h1><?= e($heading) ?></h1></header>
        <?php if (!empty($errors['general'])): ?>
            <div class="notice notice--error" role="alert"><?= e($errors['general']) ?></div>
        <?php endif; ?>
        <?= $inner ?>
    </section>
</div>

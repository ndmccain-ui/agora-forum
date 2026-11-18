<?php
defined('AGORA') or exit;
/** @var array $stats @var string $mailDriver @var string $version @var string $phpVersion @var string $sqliteVersion @var string $searchEngine */
?>
<dl class="stat-grid">
    <?php foreach ($stats as $label => $value): ?>
        <div class="card stat"><dt><?= e($label) ?></dt><dd><?= number_format($value) ?></dd></div>
    <?php endforeach; ?>
</dl>

<?php if ($mailDriver === 'mail'): ?>
    <div class="notice">
        Emails are sent with your host's basic mail function. If members say confirmation emails never arrive,
        <a href="<?= e(url('/admin/email')) ?>">set up SMTP</a>.
    </div>
<?php endif; ?>

<section class="card">
    <h2 class="card__title">Quick links</h2>
    <ul class="link-list">
        <li><a href="<?= e(url('/admin/categories')) ?>">Add or reorder categories</a></li>
        <li><a href="<?= e(url('/admin/tags')) ?>">Create tags</a></li>
        <li><a href="<?= e(url('/admin/appearance')) ?>">Change the theme or logo</a></li>
        <li><a href="<?= e(url('/admin/backup')) ?>">Download a backup</a></li>
    </ul>
</section>

<p class="meta">Agora <?= e($version) ?> · PHP <?= e($phpVersion) ?> · SQLite <?= e($sqliteVersion) ?> · <?= e($searchEngine) ?> search</p>

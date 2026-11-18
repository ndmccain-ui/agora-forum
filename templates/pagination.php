<?php
defined('AGORA') or exit;
/** @var int $page @var int $pages @var string $path @var array $query */
if ($pages <= 1) {
    return;
}
$link = fn (int $n) => e(url($path, $query + ($n > 1 ? ['page' => $n] : [])));
?>
<nav class="pagination" aria-label="Pages">
    <?php if ($page > 1): ?>
        <a class="button" href="<?= $link($page - 1) ?>" rel="prev">← Previous</a>
    <?php endif; ?>
    <span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?>
        <a class="button" href="<?= $link($page + 1) ?>" rel="next">Next →</a>
    <?php endif; ?>
</nav>

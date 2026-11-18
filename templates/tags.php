<?php
defined('AGORA') or exit;
/** @var array $tags */
?>
<header class="page-header">
    <h1>Tags</h1>
</header>

<?php if (!$tags): ?>
    <div class="empty-state">
        <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M6 8v14l20 20 16-16L22 6H8a2 2 0 0 0-2 2z"/><circle cx="15" cy="15" r="3"/></svg>
        <h2>No tags yet</h2>
        <p>Tags help people find discussions about the same topic. Each forum creates its own.</p>
    </div>
<?php else: ?>
    <ul class="tag-cloud">
        <?php foreach ($tags as $tag): ?>
            <li>
                <a class="tag" data-tag-slug="<?= e($tag['slug']) ?>" href="<?= e(url('/t/' . $tag['slug'])) ?>">
                    <?= e($tag['name']) ?> <span class="count"><?= (int) $tag['discussion_count'] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

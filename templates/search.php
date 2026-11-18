<?php
defined('AGORA') or exit;
/** @var string $query @var array $terms @var array $results @var int $total @var int $page @var int $pages @var array $categories @var array $tags @var string $categorySlug @var string $tagSlug */
?>
<div class="narrow narrow--wide">
    <h1>Search</h1>
    <form method="get" action="<?= e(url('/search')) ?>" class="search-form" role="search">
        <label for="f-q" class="visually-hidden">Search for</label>
        <input id="f-q" type="search" name="q" value="<?= e($query) ?>" placeholder="Search discussions and posts" autofocus>
        <label for="f-category" class="visually-hidden">Category</label>
        <select id="f-category" name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= e($c['slug']) ?>"<?= $categorySlug === $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($tags): ?>
            <label for="f-tag" class="visually-hidden">Tag</label>
            <select id="f-tag" name="tag">
                <option value="">Any tag</option>
                <?php foreach ($tags as $t): ?>
                    <option value="<?= e($t['slug']) ?>"<?= $tagSlug === $t['slug'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button type="submit" class="button button--primary">Search</button>
    </form>

    <?php if ($query !== '' && !$terms): ?>
        <p class="lede">Type a word or two to search for.</p>
    <?php elseif ($terms): ?>
        <p class="meta" role="status"><?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?> for “<?= e($query) ?>”</p>
        <?php if (!$results): ?>
            <div class="empty-state">
                <h2>Nothing found</h2>
                <p>Try fewer or different words<?= $categorySlug || $tagSlug ? ', or search everything' : '' ?>.</p>
            </div>
        <?php else: ?>
            <ol class="discussion-list search-results">
                <?php foreach ($results as $r): ?>
                    <li class="discussion-item">
                        <?= avatar_html(['username' => $r['username'] ?? '?', 'avatar' => $r['avatar']]) ?>
                        <div class="discussion-item__main">
                            <a class="discussion-item__title" href="<?= e(url('/p/' . $r['id'])) ?>"><?= e($r['title']) ?></a>
                            <p class="excerpt"><?= search_snippet_html((string) $r['snippet'], $terms) ?></p>
                            <div class="meta">
                                <?= e($r['category_name']) ?> · <?= e($r['username'] ?? 'Former member') ?>
                                <time datetime="<?= e(iso_time($r['created_at'])) ?>"><?= e(time_ago($r['created_at'])) ?></time>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?= partial('pagination', ['page' => $page, 'pages' => $pages, 'path' => '/search', 'query' => array_filter(['q' => $query, 'category' => $categorySlug, 'tag' => $tagSlug])]) ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
defined('AGORA') or exit;
/** @var array $categories @var ?array $category @var ?array $tag @var array $discussions @var array $discussionTags @var int $page @var int $pages @var int $total */
$listPath = $category ? '/c/' . $category['slug'] : '/';
$listQuery = $tag ? ['tag' => $tag['slug']] : [];
$newQuery = $category ? ['category' => $category['slug']] : [];
?>
<div class="with-sidebar">
    <aside class="sidebar" aria-label="Categories">
        <a class="button button--primary button--wide" href="<?= e(url('/new', $newQuery)) ?>">Start a discussion</a>
        <nav class="category-nav">
            <a href="<?= e(url('/', $listQuery)) ?>" <?= $category === null ? 'aria-current="page"' : '' ?>>All discussions</a>
            <?php foreach ($categories as $c): ?>
                <a href="<?= e(url('/c/' . $c['slug'], $listQuery)) ?>" <?= $category && $category['id'] === $c['id'] ? 'aria-current="page"' : '' ?>>
                    <span><?= e($c['name']) ?></span>
                    <span class="count"><?= (int) $c['discussion_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <section class="content">
        <header class="page-header">
            <h1><?= e($category['name'] ?? 'All discussions') ?></h1>
            <?php if ($category && $category['description'] !== ''): ?>
                <p class="lede"><?= e($category['description']) ?></p>
            <?php endif; ?>
            <?php if ($tag): ?>
                <p class="filter">
                    Tagged <span class="tag tag--small" data-tag-slug="<?= e($tag['slug']) ?>"><?= e($tag['name']) ?></span>
                    <a class="filter__clear" href="<?= e(url($listPath)) ?>">Show all</a>
                </p>
            <?php endif; ?>
        </header>

        <?php if (!$discussions): ?>
            <div class="empty-state">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M8 10h26a4 4 0 0 1 4 4v14a4 4 0 0 1-4 4H20l-8 7v-7H8a4 4 0 0 1-4-4V14a4 4 0 0 1 4-4z"/><path d="M38 18h2a4 4 0 0 1 4 4v12a4 4 0 0 1-4 4h-2v6l-7-6h-7"/></svg>
                <h2>No discussions yet</h2>
                <p>Be the first to <a href="<?= e(url('/new', $newQuery)) ?>">start a conversation</a>.</p>
            </div>
        <?php else: ?>
            <ol class="discussion-list">
                <?php foreach ($discussions as $d): ?>
                    <li class="discussion-item<?= $d['is_pinned'] ? ' is-pinned' : '' ?><?= !empty($d['unread']) ? ' is-unread' : '' ?>">
                        <?= avatar_html(['username' => $d['author_name'] ?? '?', 'avatar' => $d['author_avatar']]) ?>
                        <div class="discussion-item__main">
                            <a class="discussion-item__title" href="<?= e(discussion_url($d)) ?>"><?= e($d['title']) ?></a>
                            <?php if (!empty($d['unread'])): ?>
                                <?php if ($d['new_posts'] > 0): ?>
                                    <a class="badge badge--new" href="<?= e(url('/d/' . $d['id'] . '/unread')) ?>"><?= (int) $d['new_posts'] ?> new</a>
                                <?php else: ?>
                                    <span class="badge badge--new">New</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="meta">
                                <?php if ($d['is_pinned']): ?><span class="badge">Pinned</span><?php endif; ?>
                                <?php if ($d['is_locked']): ?><span class="badge badge--muted">Locked</span><?php endif; ?>
                                <?php if ($category === null): ?>
                                    <a href="<?= e(url('/c/' . $d['category_slug'])) ?>"><?= e($d['category_name']) ?></a> ·
                                <?php endif; ?>
                                <?= $d['reply_count'] > 0 ? 'Last reply by' : 'Started by' ?>
                                <?= e($d['last_user_name'] ?? 'a former member') ?>
                                <time datetime="<?= e(iso_time($d['last_posted_at'])) ?>"><?= e(time_ago($d['last_posted_at'])) ?></time>
                            </div>
                            <?php if (!empty($discussionTags[$d['id']])): ?>
                                <ul class="tag-list" aria-label="Tags">
                                    <?php foreach ($discussionTags[$d['id']] as $t): ?>
                                        <li><a class="tag tag--small" data-tag-slug="<?= e($t['slug']) ?>" href="<?= e(url($listPath, ['tag' => $t['slug']])) ?>"><?= e($t['name']) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <span class="reply-count" title="<?= (int) $d['reply_count'] ?> <?= $d['reply_count'] == 1 ? 'reply' : 'replies' ?>">
                            <svg class="icon icon--small" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.8 7L4 20l1.1-4.6A8 8 0 1 1 21 12z"/></svg>
                            <span class="visually-hidden">Replies:</span> <?= (int) $d['reply_count'] ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?= partial('pagination', ['page' => min($page, $pages), 'pages' => $pages, 'path' => $listPath, 'query' => $listQuery]) ?>

            <?php if (current_user()): ?>
                <form method="post" action="<?= e(url('/mark-read')) ?>" class="mark-read">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button--quiet button--small">Mark everything as read</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

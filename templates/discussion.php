<?php
defined('AGORA') or exit;
/** @var array $discussion @var array $tags @var array $posts @var int $page @var int $pages @var int $firstPostId @var ?int $firstUnreadId @var callable $canEdit @var callable $canDelete @var callable $canReport @var bool $isMod @var array $categories @var ?string $blocked @var ?array $user @var array $replyErrors @var string $replyBody */
$roleLabels = ['admin' => 'Admin', 'moderator' => 'Moderator'];
$path = '/d/' . $discussion['id'] . '-' . $discussion['slug'];
?>
<article class="discussion">
    <header class="discussion__header">
        <p class="breadcrumb"><a href="<?= e(url('/c/' . $discussion['category_slug'])) ?>"><?= e($discussion['category_name']) ?></a></p>
        <h1><?= e($discussion['title']) ?></h1>
        <div class="meta">
            <?php if ($discussion['is_pinned']): ?><span class="badge">Pinned</span><?php endif; ?>
            <?php if ($discussion['is_locked']): ?><span class="badge badge--muted">Locked</span><?php endif; ?>
            <?= (int) $discussion['reply_count'] ?> <?= $discussion['reply_count'] == 1 ? 'reply' : 'replies' ?>
        </div>
        <?php if ($isMod): ?>
            <form method="post" action="<?= e(url('/d/' . $discussion['id'] . '/moderate')) ?>" class="mod-toolbar" aria-label="Moderator tools">
                <?= csrf_field() ?>
                <span class="mod-toolbar__label">Moderate:</span>
                <button type="submit" name="action" value="<?= $discussion['is_pinned'] ? 'unpin' : 'pin' ?>" class="button button--small"><?= $discussion['is_pinned'] ? 'Unpin' : 'Pin' ?></button>
                <button type="submit" name="action" value="<?= $discussion['is_locked'] ? 'unlock' : 'lock' ?>" class="button button--small"><?= $discussion['is_locked'] ? 'Unlock' : 'Lock' ?></button>
                <span class="mod-toolbar__move">
                    <label for="f-move" class="visually-hidden">Move to category</label>
                    <select id="f-move" name="category_id">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === (int) $discussion['category_id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="action" value="move" class="button button--small">Move</button>
                </span>
                <button type="submit" name="action" value="delete" class="button button--small button--danger" data-confirm-click="Delete this whole discussion? You can restore it from Moderation → Deleted.">Delete</button>
            </form>
        <?php endif; ?>
        <?php if ($tags): ?>
            <ul class="tag-list" aria-label="Tags">
                <?php foreach ($tags as $t): ?>
                    <li><a class="tag tag--small" data-tag-slug="<?= e($t['slug']) ?>" href="<?= e(url('/t/' . $t['slug'])) ?>"><?= e($t['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </header>

    <?= partial('pagination', ['page' => $page, 'pages' => $pages, 'path' => $path, 'query' => []]) ?>

    <ol class="posts">
        <?php foreach ($posts as $post): ?>
            <?php if ((int) $post['id'] === $firstUnreadId): ?>
                <li class="unread-divider" id="unread"><span>New since your last visit</span></li>
            <?php endif; ?>
            <li class="post<?= (int) $post['id'] === $firstPostId ? ' post--first' : '' ?>" id="p<?= (int) $post['id'] ?>">
                <div class="post__side">
                    <?php if ($post['username']): ?>
                        <a href="<?= e(url('/u/' . $post['username'])) ?>" tabindex="-1" aria-hidden="true"><?= avatar_html($post) ?></a>
                    <?php else: ?>
                        <?= avatar_html(['username' => '?', 'avatar' => null]) ?>
                    <?php endif; ?>
                </div>
                <div class="post__main">
                    <header class="post__header">
                        <?php if ($post['username']): ?>
                            <a class="post__author" href="<?= e(url('/u/' . $post['username'])) ?>"><?= e($post['username']) ?></a>
                            <?php if (isset($roleLabels[$post['role']])): ?>
                                <span class="badge"><?= e($roleLabels[$post['role']]) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="post__author">Former member</span>
                        <?php endif; ?>
                        <a class="post__time" href="<?= e(url('/p/' . $post['id'])) ?>" title="Link to this post">
                            <time datetime="<?= e(iso_time($post['created_at'])) ?>"><?= e(time_ago($post['created_at'])) ?></time>
                        </a>
                        <?php if ($post['edited_at']): ?>
                            <span class="post__edited" title="Edited <?= e(time_ago($post['edited_at'])) ?>">· edited</span>
                        <?php endif; ?>
                    </header>
                    <div class="post-body">
                        <?= markdown($post['body']) ?>
                    </div>
                    <?php if ($blocked === null || $canEdit($post) || $canDelete($post) || $canReport($post)): ?>
                        <footer class="post__actions">
                            <?php if ($blocked === null): ?>
                                <a class="post__action" href="<?= e(url($path, ['quote' => $post['id'], 'page' => $pages])) ?>#reply-heading"
                                   data-quote="<?= e(url('/p/' . $post['id'] . '/quote')) ?>">Quote</a>
                            <?php endif; ?>
                            <?php if ($canEdit($post)): ?>
                                <a class="post__action" href="<?= e(url('/p/' . $post['id'] . '/edit')) ?>">Edit</a>
                            <?php endif; ?>
                            <?php if ($canDelete($post)): ?>
                                <?php $isFirstPost = (int) $post['id'] === $firstPostId; ?>
                                <form method="post" action="<?= e(url('/p/' . $post['id'] . '/delete')) ?>" class="inline-form"
                                      data-confirm="<?= $isFirstPost ? 'Delete this whole discussion?' : 'Delete this reply?' ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="post__action link-button">Delete</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canReport($post)): ?>
                                <a class="post__action post__action--end" href="<?= e(url('/p/' . $post['id'] . '/report')) ?>">Report</a>
                            <?php endif; ?>
                        </footer>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>

    <?= partial('pagination', ['page' => $page, 'pages' => $pages, 'path' => $path, 'query' => []]) ?>

    <section class="reply" aria-labelledby="reply-heading">
        <h2 id="reply-heading" class="visually-hidden">Reply</h2>
        <?php if ($page < $pages): ?>
            <p class="lede"><a href="<?= e(url($path, ['page' => $pages])) ?>#reply-heading">Go to the last page to reply →</a></p>
        <?php elseif ($blocked !== null && !$replyErrors): ?>
            <div class="notice">
                <?= e($blocked) ?>
                <?php if ($user === null): ?>
                    <a href="<?= e(url('/login')) ?>">Sign in</a><?php if (setting('registration', 'open') === 'open'): ?> or <a href="<?= e(url('/register')) ?>">create an account</a><?php endif; ?>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="post" action="<?= e(url('/d/' . $discussion['id'] . '/reply')) ?>" class="card reply__form" novalidate>
                <?= csrf_field() ?>
                <div class="reply__author"><?= avatar_html($user) ?></div>
                <div class="reply__fields">
                    <?= partial('editor', ['name' => 'body', 'label' => 'Your reply', 'value' => $replyBody, 'error' => $replyErrors['body'] ?? '', 'placeholder' => 'Write a reply…']) ?>
                    <button type="submit" class="button button--primary">Post reply</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</article>

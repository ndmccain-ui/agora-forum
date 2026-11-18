<?php
defined('AGORA') or exit;
/** @var string $tab @var array $counts @var array $items @var array $reasons */
$tabs = [
    'reports' => 'Reports' . ($counts['reports'] ? ' (' . $counts['reports'] . ')' : ''),
    'approval' => 'Waiting for approval' . ($counts['approval'] ? ' (' . $counts['approval'] . ')' : ''),
    'deleted' => 'Deleted',
];
$isAdmin = (current_user()['role'] ?? '') === 'admin';
$empty = [
    'reports' => ['All clear', 'No one has reported anything. 🎉'],
    'approval' => ['Nothing waiting', setting('approve_first_posts', '0') === '1'
        ? 'New members\' first posts will show up here for approval.'
        : 'First-post approval is off. You can turn it on in Admin → Settings to catch spam from new accounts.'],
    'deleted' => ['Nothing deleted', 'Deleted discussions and replies appear here, so they can be restored.'],
];
?>
<header class="page-header">
    <h1>Moderation</h1>
</header>

<nav class="tabs" aria-label="Moderation sections">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= e(url('/mod', ['tab' => $key])) ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$items): ?>
    <div class="empty-state"><h2><?= e($empty[$tab][0]) ?></h2><p><?= e($empty[$tab][1]) ?></p></div>
<?php else: ?>
    <ol class="mod-list">
        <?php foreach ($items as $item): ?>
            <li class="card mod-item">
                <header class="mod-item__header">
                    <?= avatar_html(['username' => $item['username'] ?? '?', 'avatar' => $item['avatar']]) ?>
                    <div>
                        <?php if ($item['username']): ?>
                            <a class="post__author" href="<?= e(url('/u/' . $item['username'])) ?>"><?= e($item['username']) ?></a>
                            <?php if (!empty($item['banned_until']) && $item['banned_until'] > now()): ?><span class="badge badge--danger">Banned</span><?php endif; ?>
                        <?php else: ?>
                            <span class="post__author">Former member</span>
                        <?php endif; ?>
                        <span class="meta">
                            <?= !empty($item['is_first']) ? 'started' : 'replied in' ?>
                            <?php if ((int) $item['is_hidden'] === 0 && (int) $item['discussion_hidden'] === 0): ?>
                                <a href="<?= e(url('/p/' . $item['id'])) ?>"><?= e($item['title']) ?></a>
                            <?php else: ?>
                                “<?= e($item['title']) ?>”
                            <?php endif; ?>
                            · <?= e(time_ago($item['created_at'])) ?>
                        </span>
                    </div>
                </header>

                <div class="post-body mod-item__body"><?= markdown($item['body']) ?></div>

                <?php if ($tab === 'reports'): ?>
                    <ul class="report-notes">
                        <?php foreach ($item['notes'] as $note): ?>
                            <li>
                                <strong><?= e($reasons[$note['reason']] ?? $note['reason']) ?></strong>
                                — reported by <?= e($note['username'] ?? 'a former member') ?>, <?= e(time_ago($note['created_at'])) ?>
                                <?php if ($note['note'] !== ''): ?><br><span class="report-note">“<?= e($note['note']) ?>”</span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="post" action="<?= e(url('/mod/reports/' . $item['id'])) ?>" class="mod-item__actions">
                        <?= csrf_field() ?>
                        <button type="submit" name="action" value="delete" class="button button--danger"><?= !empty($item['is_first']) ? 'Delete discussion' : 'Delete post' ?></button>
                        <button type="submit" name="action" value="dismiss" class="button">It's fine — dismiss</button>
                        <?php if ($item['username']): ?>
                            <a class="button button--quiet" href="<?= e(url('/u/' . $item['username'])) ?>#ban">Ban <?= e($item['username']) ?>…</a>
                        <?php endif; ?>
                    </form>
                <?php elseif ($tab === 'approval'): ?>
                    <form method="post" action="<?= e(url('/mod/approval/' . $item['id'])) ?>" class="mod-item__actions">
                        <?= csrf_field() ?>
                        <button type="submit" name="action" value="approve" class="button button--primary">Approve</button>
                        <button type="submit" name="action" value="reject" class="button button--danger">Reject</button>
                        <?php if ($item['username']): ?>
                            <a class="button button--quiet" href="<?= e(url('/u/' . $item['username'])) ?>#ban">Ban <?= e($item['username']) ?>…</a>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <p class="meta">Deleted <?= e(time_ago($item['deleted_at'])) ?><?= $item['hidden_by_name'] ? ' by ' . e($item['hidden_by_name']) : '' ?><?= !empty($item['is_first']) ? ' · whole discussion' : '' ?></p>
                    <form method="post" action="<?= e(url('/mod/deleted/' . $item['id'])) ?>" class="mod-item__actions">
                        <?= csrf_field() ?>
                        <button type="submit" name="action" value="restore" class="button">Restore</button>
                        <?php if ($isAdmin): ?>
                            <button type="submit" name="action" value="purge" class="button button--danger"
                                data-confirm-click="Delete this forever? This can't be undone.">Delete forever</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

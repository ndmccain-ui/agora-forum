<?php
defined('AGORA') or exit;
/** @var array $users @var string $q @var string $role @var int $page @var int $pages @var int $total @var array $me */
$roles = ['member' => 'Member', 'moderator' => 'Moderator', 'admin' => 'Admin'];
?>
<form method="get" action="<?= e(url('/admin/users')) ?>" class="search-bar">
    <label for="f-user-q" class="visually-hidden">Search members</label>
    <input id="f-user-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by username or email">
    <label for="f-user-role" class="visually-hidden">Role</label>
    <select id="f-user-role" name="role">
        <option value="">Everyone</option>
        <?php foreach ($roles as $value => $label): ?>
            <option value="<?= $value ?>"<?= $role === $value ? ' selected' : '' ?>><?= e($label) ?>s</option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="button">Search</button>
</form>

<p class="meta"><?= number_format($total) ?> member<?= $total === 1 ? '' : 's' ?></p>

<div class="table-wrap">
    <table class="admin-table">
        <thead>
            <tr><th scope="col">Member</th><th scope="col">Email</th><th scope="col">Joined</th><th scope="col">Posts</th><th scope="col">Role</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <a class="member-cell" href="<?= e(url('/u/' . $u['username'])) ?>"><?= avatar_html($u) ?> <?= e($u['username']) ?></a>
                        <?php if (is_banned($u)): ?><span class="badge badge--danger" title="<?= e(ban_description($u)) ?>">Banned</span><?php endif; ?>
                    </td>
                    <td>
                        <?= e($u['email']) ?>
                        <?php if ($u['email_verified_at'] === null): ?>
                            <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/confirm')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <span class="badge badge--muted">Not confirmed</span>
                                <button type="submit" class="link-button">Confirm</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><time datetime="<?= e(iso_time($u['created_at'])) ?>"><?= e(gmdate('M j, Y', (int) strtotime($u['created_at'] . ' UTC'))) ?></time></td>
                    <td><?= (int) $u['post_count'] ?></td>
                    <td>
                        <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/role')) ?>" class="role-form"
                            <?= (int) $u['id'] === (int) $me['id'] ? 'data-confirm="Change your own role? You may lose access to this admin panel."' : '' ?>>
                            <?= csrf_field() ?>
                            <label for="role-<?= $u['id'] ?>" class="visually-hidden">Role for <?= e($u['username']) ?></label>
                            <select id="role-<?= $u['id'] ?>" name="role">
                                <?php foreach ($roles as $value => $label): ?>
                                    <option value="<?= $value ?>"<?= $u['role'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="button button--small">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= partial('pagination', ['page' => $page, 'pages' => $pages, 'path' => '/admin/users', 'query' => array_filter(['q' => $q, 'role' => $role])]) ?>

<p class="field__hint">Moderators can pin, lock, move and delete discussions, edit or delete any post, handle reports, and ban members. To ban someone, open their profile.</p>

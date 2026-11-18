<?php
defined('AGORA') or exit;
/** @var array $profile @var bool $isMe @var int $postCount @var int $discussionCount @var array $posts */
$roleLabels = ['admin' => 'Admin', 'moderator' => 'Moderator'];
?>
<div class="profile">
    <section class="card profile__header">
        <?= avatar_html($profile, 'avatar--large') ?>
        <div class="profile__info">
            <h1>
                <?= e($profile['username']) ?>
                <?php if (isset($roleLabels[$profile['role']])): ?>
                    <span class="badge"><?= e($roleLabels[$profile['role']]) ?></span>
                <?php endif; ?>
            </h1>
            <p class="meta">
                Joined <time datetime="<?= e(iso_time($profile['created_at'])) ?>"><?= e(gmdate('F Y', (int) strtotime($profile['created_at'] . ' UTC'))) ?></time>
                <?php if ($profile['last_seen_at']): ?>
                    · Last active <?= e(time_ago($profile['last_seen_at'])) ?>
                <?php endif; ?>
            </p>
            <?php if (is_banned($profile)): ?>
                <p class="notice notice--error profile__ban">This member is <?= e(ban_description($profile)) ?>.</p>
            <?php endif; ?>
            <?php if ($profile['bio'] !== ''): ?>
                <p class="profile__bio"><?= nl2br(e($profile['bio'])) ?></p>
            <?php elseif ($isMe): ?>
                <p class="profile__bio lede">You haven't written a bio yet.</p>
            <?php endif; ?>
            <dl class="stats">
                <div><dt>Discussions</dt><dd><?= $discussionCount ?></dd></div>
                <div><dt>Posts</dt><dd><?= $postCount ?></dd></div>
            </dl>
        </div>
        <?php if ($isMe): ?>
            <a class="button profile__edit" href="<?= e(url('/settings')) ?>">Edit profile</a>
        <?php endif; ?>
    </section>

    <?php $viewer = current_user(); ?>
    <?php if (can_ban($viewer, $profile)): ?>
        <section class="card ban-box" id="ban" aria-labelledby="ban-heading">
            <h2 class="card__title" id="ban-heading">Moderator: ban <?= e($profile['username']) ?></h2>
            <?php if (is_banned($profile)): ?>
                <form method="post" action="<?= e(url('/mod/users/' . $profile['id'] . '/unban')) ?>">
                    <?= csrf_field() ?>
                    <p>Currently <?= e(ban_description($profile)) ?>.</p>
                    <button type="submit" class="button">Lift the ban</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= e(url('/mod/users/' . $profile['id'] . '/ban')) ?>"
                      data-confirm="Ban <?= e($profile['username']) ?>? They'll still be able to read, but not post.">
                    <?= csrf_field() ?>
                    <p class="field__hint">Banned members can still sign in and read, but can't post, edit, or report.</p>
                    <div class="field-row field-row--even">
                        <div class="field">
                            <label for="f-ban-length">How long</label>
                            <select id="f-ban-length" name="length">
                                <?php foreach (BAN_LENGTHS as $value => $label): ?>
                                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?= form_field('reason', 'Reason (they\'ll see this)', ['required' => false, 'maxlength' => 300, 'placeholder' => 'e.g. Spam']) ?>
                    </div>
                    <button type="submit" class="button button--danger">Ban</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <h2>Recent posts</h2>
    <?php if (!$posts): ?>
        <p class="lede"><?= $isMe ? "You haven't posted yet." : e($profile['username']) . " hasn't posted yet." ?></p>
    <?php else: ?>
        <ol class="discussion-list">
            <?php foreach ($posts as $post): ?>
                <li class="discussion-item">
                    <div class="discussion-item__main">
                        <a class="discussion-item__title" href="<?= e(url('/p/' . $post['id'])) ?>"><?= e($post['title']) ?></a>
                        <p class="excerpt"><?= e(markdown_excerpt($post['body'])) ?></p>
                        <div class="meta"><time datetime="<?= e(iso_time($post['created_at'])) ?>"><?= e(time_ago($post['created_at'])) ?></time></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>

<?php
defined('AGORA') or exit;
/** @var array $post @var array $errors @var array $reasons */
?>
<div class="narrow">
    <p class="breadcrumb"><a href="<?= e(url('/p/' . $post['id'])) ?>">← <?= e($post['title']) ?></a></p>
    <h1>Report a post</h1>
    <p class="lede">Reports go to the forum's moderators. The person you report won't see who reported them.</p>

    <blockquote class="card report-quote">
        <p class="meta"><?= e($post['username'] ?? 'Former member') ?> wrote:</p>
        <p><?= e(markdown_excerpt($post['body'], 300)) ?></p>
    </blockquote>

    <form method="post" action="<?= e(url('/p/' . $post['id'] . '/report')) ?>" class="card">
        <?= csrf_field() ?>
        <fieldset class="field radio-group<?= isset($errors['reason']) ? ' field--error' : '' ?>">
            <legend>What's wrong with it?</legend>
            <?php foreach ($reasons as $value => $label): ?>
                <label><input type="radio" name="reason" value="<?= e($value) ?>" required> <?= e($label) ?></label>
            <?php endforeach; ?>
            <?php if (isset($errors['reason'])): ?><p class="field__error"><?= e($errors['reason']) ?></p><?php endif; ?>
        </fieldset>
        <?= form_field('note', 'Anything moderators should know?', ['type' => 'textarea', 'required' => false, 'maxlength' => 500]) ?>
        <div class="form-actions">
            <button type="submit" class="button button--primary">Send report</button>
            <a class="button button--quiet" href="<?= e(url('/p/' . $post['id'])) ?>">Cancel</a>
        </div>
    </form>
</div>

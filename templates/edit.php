<?php
defined('AGORA') or exit;
/** @var array $post @var array $discussion @var bool $isFirst @var array $categories @var array $allTags @var bool $canCreateTags @var array $errors @var array $old @var int $tagsMax @var int $titleMax */
?>
<div class="narrow narrow--wide">
    <p class="breadcrumb"><a href="<?= e(url('/p/' . $post['id'])) ?>">← <?= e($discussion['title']) ?></a></p>
    <h1><?= $isFirst ? 'Edit discussion' : 'Edit reply' ?></h1>

    <form method="post" action="<?= e(url('/p/' . $post['id'] . '/edit')) ?>" class="card" novalidate>
        <?= csrf_field() ?>
        <?php if ($errors): ?>
            <div class="notice notice--error" role="alert">Please fix the highlighted fields.</div>
        <?php endif; ?>

        <?php if ($isFirst): ?>
            <?= form_field('title', 'Title', ['value' => $old['title'], 'error' => $errors['title'] ?? '', 'maxlength' => $titleMax]) ?>
            <div class="field-row">
                <div class="field<?= isset($errors['category_id']) ? ' field--error' : '' ?>">
                    <label for="f-category_id">Category</label>
                    <select id="f-category_id" name="category_id">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"<?= (int) $old['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?><p class="field__error"><?= e($errors['category_id']) ?></p><?php endif; ?>
                </div>
                <?php if ($allTags || $canCreateTags): ?>
                    <div class="field<?= isset($errors['tags']) ? ' field--error' : '' ?>">
                        <label for="f-tags">Tags <span class="optional">(optional)</span></label>
                        <input id="f-tags" name="tags" type="text" value="<?= e($old['tags']) ?>" list="tag-options" autocomplete="off" aria-describedby="f-tags-hint">
                        <p class="field__hint" id="f-tags-hint">Up to <?= $tagsMax ?>, separated by commas.</p>
                        <?php if (isset($errors['tags'])): ?><p class="field__error"><?= e($errors['tags']) ?></p><?php endif; ?>
                        <datalist id="tag-options">
                            <?php foreach ($allTags as $t): ?><option value="<?= e($t['name']) ?>"></option><?php endforeach; ?>
                        </datalist>
                        <?php if ($allTags): ?>
                            <div class="tag-picker" data-tag-picker hidden>
                                <?php foreach ($allTags as $t): ?>
                                    <button type="button" class="tag tag--small" data-tag="<?= e($t['name']) ?>"><?= e($t['name']) ?></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?= partial('editor', ['name' => 'body', 'label' => $isFirst ? 'Message' : 'Reply', 'value' => $old['body'], 'error' => $errors['body'] ?? '', 'placeholder' => '']) ?>

        <div class="form-actions">
            <button type="submit" class="button button--primary">Save changes</button>
            <a class="button button--quiet" href="<?= e(url('/p/' . $post['id'])) ?>">Cancel</a>
        </div>
    </form>
</div>

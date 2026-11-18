<?php
defined('AGORA') or exit;
/** @var ?string $blocked @var array $categories @var array $allTags @var bool $canCreateTags @var array $errors @var array $old @var int $tagsMax @var int $titleMax */
?>
<div class="narrow narrow--wide">
    <h1>Start a discussion</h1>

    <?php if ($blocked !== null): ?>
        <div class="notice notice--warning"><?= e($blocked) ?></div>
    <?php else: ?>
        <form method="post" action="<?= e(url('/new')) ?>" class="card" novalidate>
            <?= csrf_field() ?>
            <?php if (!empty($errors['general'])): ?>
                <div class="notice notice--error" role="alert"><?= e($errors['general']) ?></div>
            <?php elseif ($errors): ?>
                <div class="notice notice--error" role="alert">Please fix the highlighted fields.</div>
            <?php endif; ?>

            <?= form_field('title', 'Title', ['value' => $old['title'], 'error' => $errors['title'] ?? '', 'maxlength' => $titleMax, 'placeholder' => 'What do you want to talk about?']) ?>

            <div class="field-row">
                <div class="field<?= isset($errors['category_id']) ? ' field--error' : '' ?>">
                    <label for="f-category_id">Category</label>
                    <select id="f-category_id" name="category_id" required<?= isset($errors['category_id']) ? ' aria-invalid="true" aria-describedby="f-category_id-error"' : '' ?>>
                        <?php if (count($categories) > 1): ?>
                            <option value="">Choose…</option>
                        <?php endif; ?>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"<?= (int) $old['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?>
                        <p class="field__error" id="f-category_id-error"><?= e($errors['category_id']) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($allTags || $canCreateTags): ?>
                    <div class="field<?= isset($errors['tags']) ? ' field--error' : '' ?>">
                        <label for="f-tags">Tags <span class="optional">(optional)</span></label>
                        <input id="f-tags" name="tags" type="text" value="<?= e($old['tags']) ?>" list="tag-options" autocomplete="off"
                            aria-describedby="f-tags-hint<?= isset($errors['tags']) ? ' f-tags-error' : '' ?>"<?= isset($errors['tags']) ? ' aria-invalid="true"' : '' ?>>
                        <p class="field__hint" id="f-tags-hint">
                            Up to <?= $tagsMax ?>, separated by commas.
                            <?= $canCreateTags ? 'New tags are created automatically.' : '' ?>
                        </p>
                        <?php if (isset($errors['tags'])): ?>
                            <p class="field__error" id="f-tags-error"><?= e($errors['tags']) ?></p>
                        <?php endif; ?>
                        <datalist id="tag-options">
                            <?php foreach ($allTags as $t): ?>
                                <option value="<?= e($t['name']) ?>"></option>
                            <?php endforeach; ?>
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

            <?= partial('editor', ['name' => 'body', 'label' => 'Message', 'value' => $old['body'], 'error' => $errors['body'] ?? '', 'placeholder' => '']) ?>

            <div class="form-actions">
                <button type="submit" class="button button--primary">Post discussion</button>
                <a class="button button--quiet" href="<?= e(url('/')) ?>">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

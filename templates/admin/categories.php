<?php
defined('AGORA') or exit;
/** @var array $errors @var array $old @var array $categories */
$last = count($categories) - 1;
?>
<p class="lede">Categories are the sections of your forum. Every discussion lives in one.</p>

<ol class="admin-list">
    <?php foreach ($categories as $i => $c): ?>
        <li class="card admin-item">
            <details<?= isset($errors['edit_' . $c['id']]) ? ' open' : '' ?>>
                <summary class="admin-item__summary">
                    <span class="admin-item__title"><?= e($c['name']) ?></span>
                    <span class="meta"><?= (int) $c['discussion_count'] ?> discussion<?= $c['discussion_count'] == 1 ? '' : 's' ?> · /c/<?= e($c['slug']) ?></span>
                    <span class="admin-item__edit">Edit</span>
                </summary>
                <?php if (isset($errors['edit_' . $c['id']])): ?>
                    <div class="notice notice--error"><?= e($errors['edit_' . $c['id']]) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/admin/categories/' . $c['id'])) ?>" class="admin-item__form">
                    <?= csrf_field() ?>
                    <div class="field-row field-row--even">
                        <div class="field">
                            <label for="cat-name-<?= $c['id'] ?>">Name</label>
                            <input id="cat-name-<?= $c['id'] ?>" name="name" value="<?= e($c['name']) ?>" required maxlength="60">
                        </div>
                        <div class="field">
                            <label for="cat-slug-<?= $c['id'] ?>">Web address</label>
                            <input id="cat-slug-<?= $c['id'] ?>" name="slug" value="<?= e($c['slug']) ?>" pattern="[a-z0-9-]+">
                        </div>
                    </div>
                    <div class="field">
                        <label for="cat-desc-<?= $c['id'] ?>">Description</label>
                        <input id="cat-desc-<?= $c['id'] ?>" name="description" value="<?= e($c['description']) ?>" maxlength="300">
                    </div>
                    <button type="submit" class="button button--primary">Save</button>
                </form>
                <form method="post" action="<?= e(url('/admin/categories/' . $c['id'] . '/delete')) ?>" class="admin-item__danger" data-confirm="Delete the &quot;<?= e($c['name']) ?>&quot; category?">
                    <?= csrf_field() ?>
                    <?php if ($c['discussion_count'] > 0 && count($categories) > 1): ?>
                        <label for="cat-move-<?= $c['id'] ?>">Move its discussions to</label>
                        <select id="cat-move-<?= $c['id'] ?>" name="move_to">
                            <?php foreach ($categories as $other): if ($other['id'] === $c['id']) continue; ?>
                                <option value="<?= (int) $other['id'] ?>"><?= e($other['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <button type="submit" class="button button--danger"<?= count($categories) <= 1 ? ' disabled title="A forum needs at least one category"' : '' ?>>Delete category</button>
                </form>
            </details>
            <div class="admin-item__order">
                <form method="post" action="<?= e(url('/admin/categories/' . $c['id'] . '/move')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" name="direction" value="up" class="icon-button" aria-label="Move <?= e($c['name']) ?> up"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
                    <button type="submit" name="direction" value="down" class="icon-button" aria-label="Move <?= e($c['name']) ?> down"<?= $i === $last ? ' disabled' : '' ?>>↓</button>
                </form>
            </div>
        </li>
    <?php endforeach; ?>
</ol>

<form method="post" action="<?= e(url('/admin/categories')) ?>" class="card" novalidate>
    <?= csrf_field() ?>
    <h2 class="card__title">Add a category</h2>
    <?= form_field('name', 'Name', ['value' => $old['name'], 'error' => $errors['name'] ?? '', 'maxlength' => 60, 'placeholder' => 'e.g. Show & Tell']) ?>
    <?php if (isset($errors['slug'])): ?><div class="notice notice--error"><?= e($errors['slug']) ?></div><?php endif; ?>
    <?= form_field('description', 'Description', ['required' => false, 'value' => $old['description'], 'maxlength' => 300, 'placeholder' => 'What belongs here?']) ?>
    <button type="submit" class="button button--primary">Add category</button>
</form>

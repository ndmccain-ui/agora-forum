<?php
defined('AGORA') or exit;
/** @var array $errors @var array $tags */
?>
<p class="lede">
    Tags describe what a discussion is about, across categories.
    <?= setting('members_create_tags', '0') === '1' ? 'Members can create new tags too.' : 'Members pick from the tags you create here.' ?>
    <a href="<?= e(url('/admin/settings')) ?>">Change this</a>
</p>

<form method="post" action="<?= e(url('/admin/tags')) ?>" class="card" novalidate>
    <?= csrf_field() ?>
    <h2 class="card__title">Add a tag</h2>
    <div class="tag-form">
        <?= form_field('name', 'Name', ['error' => $errors['name'] ?? '', 'maxlength' => 30, 'placeholder' => 'e.g. iPad']) ?>
        <div class="field">
            <span class="field-label">Color</span>
            <label class="color-input"><input type="checkbox" name="use_color" value="1"> <input type="color" name="color" value="#b5532c" aria-label="Tag color"> Show a color dot</label>
        </div>
    </div>
    <button type="submit" class="button button--primary">Add tag</button>
</form>

<?php if (!$tags): ?>
    <div class="empty-state"><h2>No tags yet</h2><p>Add your first tag above.</p></div>
<?php else: ?>
    <ol class="admin-list">
        <?php foreach ($tags as $t): ?>
            <li class="card admin-item">
                <details<?= isset($errors['tag_' . $t['id']]) ? ' open' : '' ?>>
                    <summary class="admin-item__summary">
                        <span class="tag" data-tag-slug="<?= e($t['slug']) ?>"><?= e($t['name']) ?></span>
                        <span class="meta"><?= (int) $t['discussion_count'] ?> discussion<?= $t['discussion_count'] == 1 ? '' : 's' ?></span>
                        <span class="admin-item__edit">Edit</span>
                    </summary>
                    <?php if (isset($errors['tag_' . $t['id']])): ?>
                        <div class="notice notice--error"><?= e($errors['tag_' . $t['id']]) ?></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/admin/tags/' . $t['id'])) ?>" class="admin-item__form">
                        <?= csrf_field() ?>
                        <div class="tag-form">
                            <div class="field">
                                <label for="tag-name-<?= $t['id'] ?>">Name</label>
                                <input id="tag-name-<?= $t['id'] ?>" name="name" value="<?= e($t['name']) ?>" required maxlength="30">
                            </div>
                            <div class="field">
                                <span class="field-label">Color</span>
                                <label class="color-input">
                                    <input type="checkbox" name="use_color" value="1"<?= $t['color'] ? ' checked' : '' ?>>
                                    <input type="color" name="color" value="<?= e($t['color'] ?: '#b5532c') ?>" aria-label="Color for <?= e($t['name']) ?>">
                                    Show a color dot
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="button button--primary">Save</button>
                    </form>
                    <?php if (count($tags) > 1): ?>
                        <form method="post" action="<?= e(url('/admin/tags/' . $t['id'] . '/merge')) ?>" class="admin-item__inline">
                            <?= csrf_field() ?>
                            <label for="tag-into-<?= $t['id'] ?>">Merge into</label>
                            <select id="tag-into-<?= $t['id'] ?>" name="into">
                                <?php foreach ($tags as $other): if ($other['id'] === $t['id']) continue; ?>
                                    <option value="<?= (int) $other['id'] ?>"><?= e($other['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="button">Merge</button>
                            <span class="field__hint">Moves its discussions to the other tag, then removes this one.</span>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/admin/tags/' . $t['id'] . '/delete')) ?>" class="admin-item__danger" data-confirm="Delete the &quot;<?= e($t['name']) ?>&quot; tag? Discussions keep everything else.">
                        <?= csrf_field() ?>
                        <button type="submit" class="button button--danger">Delete tag</button>
                    </form>
                </details>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

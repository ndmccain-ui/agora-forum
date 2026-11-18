<?php
defined('AGORA') or exit;
/** @var array $errors @var array $themes @var string $current @var bool $customColors @var array $colors @var ?string $logo */
$swatch = function (array $theme, string $mode): array {
    $defaults = default_theme_colors()[$mode];
    $c = $theme['colors'][$mode] ?? [];
    return [$c['bg'] ?? $defaults['bg'], $c['surface'] ?? $defaults['surface'], $c['accent'] ?? $defaults['accent'], $c['text'] ?? $defaults['text']];
};
?>
<form method="post" action="<?= e(url('/admin/appearance')) ?>" id="appearance-form">
    <?= csrf_field() ?>

    <section class="card">
        <h2 class="card__title">Theme</h2>
        <div class="theme-grid" role="radiogroup" aria-label="Theme">
            <?php foreach ($themes as $theme): ?>
                <label class="theme-card">
                    <input type="radio" name="theme" value="<?= e($theme['slug']) ?>"<?= $theme['slug'] === $current ? ' checked' : '' ?>>
                    <span class="theme-card__preview" aria-hidden="true">
                        <?php foreach (['light', 'dark'] as $mode): [$bg, $surface, $accent, $text] = $swatch($theme, $mode); ?>
                            <svg viewBox="0 0 60 40" class="theme-card__mini">
                                <rect width="60" height="40" fill="<?= e($bg) ?>"/>
                                <rect x="0" y="0" width="60" height="8" fill="<?= e($surface) ?>"/>
                                <rect x="6" y="13" width="48" height="10" rx="2" fill="<?= e($surface) ?>"/>
                                <rect x="10" y="16.5" width="22" height="3" rx="1.5" fill="<?= e($text) ?>"/>
                                <rect x="6" y="27" width="16" height="7" rx="2" fill="<?= e($accent) ?>"/>
                            </svg>
                        <?php endforeach; ?>
                    </span>
                    <span class="theme-card__name"><?= e($theme['name'] ?? $theme['slug']) ?></span>
                    <?php if ($theme['description']): ?>
                        <span class="theme-card__desc"><?= e($theme['description']) ?></span>
                    <?php endif; ?>
                    <span class="theme-card__meta">
                        <?= $theme['author'] ? 'by ' . e($theme['author']) : '' ?>
                        <?= $theme['source'] === 'uploaded' ? ' · uploaded' : '' ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php foreach ($themes as $theme): ?>
            <?php if ($theme['source'] === 'uploaded'): ?>
                <button type="submit" form="delete-theme-<?= e($theme['slug']) ?>" class="link-button link-button--danger">Remove "<?= e($theme['name']) ?>"</button>
            <?php endif; ?>
        <?php endforeach; ?>

        <fieldset class="field radio-group radio-group--inline">
            <legend>For visitors who haven't picked light or dark</legend>
            <?php foreach (['auto' => 'Match their device', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label): ?>
                <label><input type="radio" name="theme_default_mode" value="<?= $value ?>"<?= setting('theme_default_mode', 'auto') === $value ? ' checked' : '' ?>> <?= e($label) ?></label>
            <?php endforeach; ?>
        </fieldset>
    </section>

    <section class="card">
        <h2 class="card__title">Colors</h2>
        <div class="field checkbox-field">
            <label><input type="checkbox" name="custom_colors" value="1" data-toggle-target="#color-pickers"<?= $customColors ? ' checked' : '' ?>> Use my own colors instead of the theme's</label>
            <p class="field__hint">Pick the four main colors; Agora works out the rest (borders, hover colors, readable button text). Choosing a different theme resets these.</p>
        </div>
        <div class="color-pickers" id="color-pickers">
            <?php foreach (['light' => 'Light mode', 'dark' => 'Dark mode'] as $mode => $modeLabel): ?>
                <fieldset class="color-set">
                    <legend><?= e($modeLabel) ?></legend>
                    <?php foreach (THEME_COLOR_KEYS as $key => $label): ?>
                        <label class="color-input">
                            <input type="color" name="colors[<?= $mode ?>][<?= $key ?>]" value="<?= e($colors[$mode][$key]) ?>">
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?php
                    $ratio = contrast_ratio($colors[$mode]['text'], $colors[$mode]['surface']);
                    if ($ratio < 4.5): ?>
                        <p class="field__error">Text on cards may be hard to read (contrast <?= number_format($ratio, 1) ?>:1 — aim for 4.5 or more).</p>
                    <?php endif; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2 class="card__title">Custom CSS</h2>
        <?= form_field('custom_css', 'Extra CSS', [
            'type' => 'textarea', 'required' => false, 'value' => setting('custom_css', ''),
            'hint' => 'For small tweaks, e.g. .site-name { letter-spacing: .05em; } — applied on top of the theme.',
        ]) ?>
    </section>

    <div class="form-actions">
        <button type="submit" class="button button--primary">Save appearance</button>
        <a class="button" href="<?= e(url('/admin/appearance/export')) ?>">Download as a theme file</a>
    </div>
</form>

<?php foreach ($themes as $theme): ?>
    <?php if ($theme['source'] === 'uploaded'): ?>
        <form method="post" action="<?= e(url('/admin/themes/' . $theme['slug'] . '/delete')) ?>" id="delete-theme-<?= e($theme['slug']) ?>" hidden>
            <?= csrf_field() ?>
        </form>
    <?php endif; ?>
<?php endforeach; ?>

<section class="card">
    <h2 class="card__title">Logo</h2>
    <div class="logo-editor">
        <div class="logo-editor__preview">
            <?php if ($logo): ?>
                <img src="<?= e($logo) ?>" alt="Current logo">
            <?php else: ?>
                <span class="lede">No logo — your forum's name is shown instead.</span>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= e(url('/admin/appearance/logo')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field<?= isset($errors['logo']) ? ' field--error' : '' ?>">
                <label for="f-logo">Upload a logo</label>
                <p class="field__hint">PNG with a transparent background works best. It's resized to fit 480 × 96.</p>
                <input id="f-logo" type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/webp" required>
                <?php if (isset($errors['logo'])): ?><p class="field__error"><?= e($errors['logo']) ?></p><?php endif; ?>
            </div>
            <button type="submit" class="button">Upload logo</button>
        </form>
        <?php if ($logo): ?>
            <form method="post" action="<?= e(url('/admin/appearance/logo/remove')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="button button--quiet">Remove logo</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <h2 class="card__title">Install a theme</h2>
    <p>Got a theme file (.css) from someone, or made your own? Upload it here. <a href="<?= e(url('/admin/appearance/guide')) ?>">How to make a theme</a></p>
    <form method="post" action="<?= e(url('/admin/themes/upload')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field<?= isset($errors['theme_file']) ? ' field--error' : '' ?>">
            <label for="f-theme_file">Theme file</label>
            <input id="f-theme_file" type="file" name="theme_file" accept=".css,text/css" required>
            <?php if (isset($errors['theme_file'])): ?><p class="field__error"><?= e($errors['theme_file']) ?></p><?php endif; ?>
        </div>
        <button type="submit" class="button">Upload theme</button>
    </form>
</section>

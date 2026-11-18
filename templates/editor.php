<?php
defined('AGORA') or exit;
/** Shared post editor with Write / Preview tabs. @var string $name @var string $label @var string $value @var string $error @var string $placeholder */
$id = 'f-' . $name;
?>
<div class="field editor<?= $error ? ' field--error' : '' ?>" data-editor>
    <div class="editor__bar">
        <label for="<?= e($id) ?>"><?= e($label) ?></label>
        <div class="editor__tabs" role="tablist" hidden>
            <button type="button" role="tab" aria-selected="true" data-editor-tab="write">Write</button>
            <button type="button" role="tab" aria-selected="false" data-editor-tab="preview">Preview</button>
        </div>
    </div>
    <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="8" required maxlength="20000"
        placeholder="<?= e($placeholder ?? '') ?>"
        aria-describedby="<?= e($id) ?>-help<?= $error ? ' ' . e($id) . '-error' : '' ?>"<?= $error ? ' aria-invalid="true"' : '' ?>><?= e($value) ?></textarea>
    <div class="editor__preview post-body" data-editor-preview hidden aria-live="polite"></div>
    <?php if ($error): ?>
        <p class="field__error" id="<?= e($id) ?>-error"><?= e($error) ?></p>
    <?php endif; ?>
    <details class="editor__help" id="<?= e($id) ?>-help">
        <summary>Formatting help</summary>
        <table>
            <tr><td><code>**bold**</code></td><td><strong>bold</strong></td></tr>
            <tr><td><code>*italic*</code></td><td><em>italic</em></td></tr>
            <tr><td><code>[link](https://…)</code></td><td><a href="#" tabindex="-1">link</a></td></tr>
            <tr><td><code>![description](https://…/picture.jpg)</code></td><td>a picture from the web</td></tr>
            <tr><td><code>&gt; quoted text</code></td><td>a quote</td></tr>
            <tr><td><code>- item</code></td><td>a bulleted list</td></tr>
            <tr><td><code>`code`</code></td><td><code>code</code></td></tr>
        </table>
    </details>
</div>

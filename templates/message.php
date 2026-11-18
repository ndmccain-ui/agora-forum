<?php
defined('AGORA') or exit;
/** @var string $title @var string $message */
?>
<div class="narrow message-page">
    <h1><?= e($title) ?></h1>
    <p class="lede"><?= e($message) ?></p>
    <?php if (!empty($detail)): ?>
        <pre class="debug"><?= e($detail) ?></pre>
    <?php endif; ?>
    <p><a class="button" href="<?= e(url('/')) ?>">Back to the forum</a></p>
</div>

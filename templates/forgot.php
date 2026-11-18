<?php
defined('AGORA') or exit;
/** @var string $email @var bool $sent */
?>
<div class="narrow narrow--small">
    <h1>Reset your password</h1>
    <?php if ($sent): ?>
        <div class="card">
            <p>If there's an account for <strong><?= e($email) ?></strong>, we've sent it a link to choose a new password.</p>
            <p class="lede">It can take a few minutes to arrive. Check your spam folder too. The link works for 1 hour.</p>
            <a class="button" href="<?= e(url('/login')) ?>">Back to sign in</a>
        </div>
    <?php else: ?>
        <p class="lede">Enter the email you signed up with and we'll send you a link.</p>
        <form method="post" action="<?= e(url('/forgot')) ?>" class="card">
            <?= csrf_field() ?>
            <?= form_field('email', 'Email', ['type' => 'email', 'value' => $email, 'autocomplete' => 'email']) ?>
            <button type="submit" class="button button--primary button--wide">Send reset link</button>
        </form>
    <?php endif; ?>
</div>

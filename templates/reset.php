<?php
defined('AGORA') or exit;
/** @var string $token @var array $errors */
?>
<div class="narrow narrow--small">
    <h1>Choose a new password</h1>
    <form method="post" action="<?= e(url('/reset/' . $token)) ?>" class="card" novalidate>
        <?= csrf_field() ?>
        <?= form_field('password', 'New password', ['type' => 'password', 'error' => $errors['password'] ?? '', 'hint' => 'At least 8 characters.', 'autocomplete' => 'new-password']) ?>
        <?= form_field('password_confirm', 'Confirm new password', ['type' => 'password', 'error' => $errors['password_confirm'] ?? '', 'autocomplete' => 'new-password']) ?>
        <button type="submit" class="button button--primary button--wide">Save password</button>
    </form>
</div>

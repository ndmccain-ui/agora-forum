<?php
defined('AGORA') or exit;
/** @var array $errors @var array $old @var int $minimumAge */
?>
<div class="narrow narrow--small">
    <h1>Create an account</h1>
    <p class="lede">Already have one? <a href="<?= e(url('/login')) ?>">Sign in</a></p>

    <form method="post" action="<?= e(url('/register')) ?>" class="card" novalidate>
        <?= csrf_field() ?>
        <?php if (!empty($errors['general'])): ?>
            <div class="notice notice--error" role="alert"><?= e($errors['general']) ?></div>
        <?php elseif ($errors): ?>
            <div class="notice notice--error" role="alert">Please fix the highlighted fields.</div>
        <?php endif; ?>

        <?= form_field('username', 'Username', ['value' => $old['username'], 'error' => $errors['username'] ?? '', 'hint' => 'This is shown on your posts. Letters, numbers, dashes and underscores.', 'autocomplete' => 'username', 'maxlength' => 30]) ?>
        <?= form_field('email', 'Email', ['type' => 'email', 'value' => $old['email'], 'error' => $errors['email'] ?? '', 'hint' => 'Private — never shown to other members.', 'autocomplete' => 'email']) ?>
        <?= form_field('password', 'Password', ['type' => 'password', 'error' => $errors['password'] ?? '', 'hint' => 'At least 8 characters.', 'autocomplete' => 'new-password']) ?>
        <?= form_field('password_confirm', 'Confirm password', ['type' => 'password', 'error' => $errors['password_confirm'] ?? '', 'autocomplete' => 'new-password']) ?>

        <!-- Spam trap: hidden from people, filled in by bots. -->
        <div class="trap" aria-hidden="true">
            <label for="f-website">Leave this empty</label>
            <input id="f-website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>

        <?php if ($minimumAge > 0): ?>
            <div class="field checkbox-field<?= isset($errors['age_ok']) ? ' field--error' : '' ?>">
                <label>
                    <input type="checkbox" name="age_ok" value="1" required<?= isset($errors['age_ok']) ? ' aria-invalid="true" aria-describedby="f-age-error"' : '' ?>>
                    I am <?= $minimumAge ?> or older
                </label>
                <?php if (isset($errors['age_ok'])): ?>
                    <p class="field__error" id="f-age-error"><?= e($errors['age_ok']) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <button type="submit" class="button button--primary button--wide">Create account</button>
    </form>
</div>

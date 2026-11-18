<?php
defined('AGORA') or exit;
/** @var array $user @var array $errors @var int $bioMax @var bool $canUpload @var bool $needsConfirmation */
?>
<div class="narrow">
    <h1>Your settings</h1>
    <p class="lede"><a href="<?= e(url('/u/' . $user['username'])) ?>">View your profile</a></p>

    <section class="card" id="picture">
        <h2 class="card__title">Picture</h2>
        <div class="avatar-editor">
            <?= avatar_html($user, 'avatar--large') ?>
            <div class="avatar-editor__forms">
                <?php if ($canUpload): ?>
                    <form method="post" action="<?= e(url('/settings/avatar')) ?>" enctype="multipart/form-data" class="avatar-upload">
                        <?= csrf_field() ?>
                        <div class="field<?= isset($errors['avatar']) ? ' field--error' : '' ?>">
                            <label for="f-avatar">Upload a new picture</label>
                            <p class="field__hint" id="f-avatar-hint">JPG, PNG, GIF or WebP, up to 4 MB. It'll be cropped to a square.</p>
                            <input id="f-avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/gif,image/webp" required aria-describedby="f-avatar-hint<?= isset($errors['avatar']) ? ' f-avatar-error' : '' ?>">
                            <?php if (isset($errors['avatar'])): ?>
                                <p class="field__error" id="f-avatar-error"><?= e($errors['avatar']) ?></p>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="button">Upload</button>
                    </form>
                <?php else: ?>
                    <p class="lede">This server can't process pictures, so your initial is shown instead.</p>
                <?php endif; ?>
                <?php if ($user['avatar']): ?>
                    <form method="post" action="<?= e(url('/settings/avatar/remove')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="button button--quiet">Remove picture</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <form method="post" action="<?= e(url('/settings/profile')) ?>" class="card" id="profile">
        <?= csrf_field() ?>
        <h2 class="card__title">About you</h2>
        <?= form_field('bio', 'Bio', [
            'type' => 'textarea', 'required' => false, 'value' => $user['bio'], 'error' => $errors['bio'] ?? '',
            'hint' => 'A few words for your profile page. Up to ' . $bioMax . ' characters.', 'maxlength' => $bioMax,
        ]) ?>
        <button type="submit" class="button button--primary">Save</button>
    </form>

    <section class="card" id="email">
        <h2 class="card__title">Email</h2>
        <?php if ($needsConfirmation): ?>
            <div class="notice notice--warning">
                <strong><?= e($user['email']) ?></strong> isn't confirmed yet.
                <form method="post" action="<?= e(url('/confirm/resend')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Send a new link</button>
                </form>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/settings/email')) ?>" novalidate>
            <?= csrf_field() ?>
            <?= form_field('email', 'Email address', ['type' => 'email', 'value' => $user['email'], 'error' => $errors['email'] ?? '', 'autocomplete' => 'email', 'hint' => 'Private — never shown to other members.']) ?>
            <?= form_field('email_password', 'Your current password',['type' => 'password', 'error' => $errors['email_password'] ?? '', 'autocomplete' => 'current-password', 'hint' => 'Needed to change your email.']) ?>
            <button type="submit" class="button">Change email</button>
        </form>
    </section>

    <form method="post" action="<?= e(url('/settings/password')) ?>" class="card" id="password" novalidate>
        <?= csrf_field() ?>
        <h2 class="card__title">Password</h2>
        <?= form_field('current_password', 'Current password', ['type' => 'password', 'error' => $errors['current_password'] ?? '', 'autocomplete' => 'current-password']) ?>
        <?= form_field('new_password', 'New password', ['type' => 'password', 'error' => $errors['new_password'] ?? '', 'autocomplete' => 'new-password', 'hint' => 'At least 8 characters. Other devices will be signed out.']) ?>
        <?= form_field('new_password_confirm', 'Confirm new password', ['type' => 'password', 'error' => $errors['new_password_confirm'] ?? '', 'autocomplete' => 'new-password']) ?>
        <button type="submit" class="button">Change password</button>
    </form>
</div>

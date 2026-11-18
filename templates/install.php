<?php
defined('AGORA') or exit;
/** @var array $requirements @var bool $ready @var array $errors @var array $old @var string $defaultMailFrom */
?>
<div class="narrow">
    <h1>Set up your forum</h1>
    <p class="lede">Welcome to Agora! This takes about a minute.</p>

    <section class="card">
        <h2 class="card__title">Server check</h2>
        <ul class="checklist">
            <?php foreach ($requirements as $req): ?>
                <li class="checklist__item checklist__item--<?= $req['ok'] ? 'ok' : 'fail' ?>">
                    <span class="checklist__mark" aria-hidden="true"><?= $req['ok'] ? '✓' : '✕' ?></span>
                    <span>
                        <?= e($req['label']) ?>
                        <?php if (!$req['ok']): ?>
                            <span class="checklist__detail"><?= e($req['detail']) ?></span>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($ready): ?>
        <form method="post" action="<?= e(url('/install')) ?>" class="card" novalidate>
            <?= csrf_field() ?>
            <?php if (!empty($errors['general'])): ?>
                <div class="notice notice--error" role="alert"><?= e($errors['general']) ?></div>
            <?php elseif ($errors): ?>
                <div class="notice notice--error" role="alert">Please fix the highlighted fields.</div>
            <?php endif; ?>

            <h2 class="card__title">Your forum</h2>
            <?= form_field('site_name', 'Forum name', ['value' => $old['site_name'], 'error' => $errors['site_name'] ?? '', 'hint' => 'You can change this later.', 'maxlength' => 80]) ?>
            <?= form_field('mail_from', 'Send emails from', [
                'type' => 'email', 'required' => false, 'value' => $old['mail_from'], 'error' => $errors['mail_from'] ?? '',
                'placeholder' => $defaultMailFrom, 'autocomplete' => 'off',
                'hint' => 'Emails to members (like password resets) come from this address. It doesn\'t need to be yours — leave blank to use ' . $defaultMailFrom . '.',
            ]) ?>

            <h2 class="card__title">Your admin account</h2>
            <?= form_field('username', 'Username', ['value' => $old['username'], 'error' => $errors['username'] ?? '', 'hint' => 'Letters, numbers, dashes and underscores.', 'autocomplete' => 'username']) ?>
            <?= form_field('email', 'Email', ['type' => 'email', 'value' => $old['email'], 'error' => $errors['email'] ?? '', 'autocomplete' => 'email', 'hint' => 'Private — only used to sign in and reset your password.']) ?>
            <?= form_field('password', 'Password', ['type' => 'password', 'error' => $errors['password'] ?? '', 'hint' => 'At least 8 characters.', 'autocomplete' => 'new-password']) ?>
            <?= form_field('password_confirm', 'Confirm password', ['type' => 'password', 'error' => $errors['password_confirm'] ?? '', 'autocomplete' => 'new-password']) ?>

            <button type="submit" class="button button--primary button--wide">Create my forum</button>
        </form>
    <?php else: ?>
        <div class="notice notice--error">Fix the items marked ✕ above, then refresh this page.</div>
    <?php endif; ?>
</div>

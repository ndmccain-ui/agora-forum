<?php
defined('AGORA') or exit;
/** @var string $error @var string $login @var bool $canRegister */
?>
<div class="narrow narrow--small">
    <h1>Sign in</h1>
    <?php if ($canRegister): ?>
        <p class="lede">New here? <a href="<?= e(url('/register')) ?>">Create an account</a></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/login')) ?>" class="card">
        <?= csrf_field() ?>
        <?php if ($error): ?>
            <div class="notice notice--error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="field">
            <label for="f-login">Username or email</label>
            <input id="f-login" name="login" type="text" value="<?= e($login) ?>" required autocomplete="username" autofocus>
        </div>
        <div class="field">
            <div class="label-row">
                <label for="f-password">Password</label>
                <a href="<?= e(url('/forgot')) ?>">Forgot it?</a>
            </div>
            <input id="f-password" name="password" type="password" required autocomplete="current-password">
        </div>
        <div class="field checkbox-field">
            <label><input type="checkbox" name="remember" value="1"> Keep me signed in for 30 days</label>
        </div>
        <button type="submit" class="button button--primary button--wide">Sign in</button>
    </form>
</div>

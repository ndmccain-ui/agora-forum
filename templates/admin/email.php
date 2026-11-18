<?php
defined('AGORA') or exit;
/** @var array $errors @var string $driver @var ?string $testResult @var bool $testOk @var string $defaultFrom */
$v = fn (string $key, string $default = '') => $_POST[$key] ?? setting($key, $default);
?>
<?php if ($testResult !== null): ?>
    <div class="notice notice--<?= $testOk ? 'success' : 'error' ?>" role="status"><?= e($testResult) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/email')) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="card">
        <h2 class="card__title">Sender</h2>
        <?= form_field('mail_from', 'Send emails from', [
            'type' => 'email', 'required' => false, 'value' => $v('mail_from'), 'placeholder' => $defaultFrom,
            'error' => $errors['mail_from'] ?? '',
            'hint' => 'Members see this as the sender. It doesn\'t need to be anyone\'s personal email. With SMTP, use an address your email provider lets you send from.',
        ]) ?>
    </section>

    <section class="card">
        <h2 class="card__title">How emails are sent</h2>
        <fieldset class="field radio-group">
            <legend class="visually-hidden">Sending method</legend>
            <label><input type="radio" name="mail_driver" value="mail"<?= $driver === 'mail' ? ' checked' : '' ?>> <span><strong>Your web host</strong> — simplest, works on most hosts, but emails sometimes land in spam</span></label>
            <label><input type="radio" name="mail_driver" value="smtp"<?= $driver === 'smtp' ? ' checked' : '' ?>> <span><strong>SMTP</strong> — send through your email provider (most reliable)</span></label>
            <label><input type="radio" name="mail_driver" value="log"<?= $driver === 'log' ? ' checked' : '' ?>> <span><strong>Don't send</strong> — save emails to <code>data/mail.log</code> (for testing)</span></label>
        </fieldset>

        <div class="smtp-settings">
            <h3 class="card__title">SMTP settings</h3>
            <p class="field__hint">Your email provider lists these, often under "SMTP" or "sending from other apps".</p>
            <div class="field-row field-row--even">
                <?= form_field('smtp_host', 'Server', ['required' => false, 'value' => $v('smtp_host'), 'placeholder' => 'smtp.example.com', 'error' => $errors['smtp_host'] ?? '']) ?>
                <?= form_field('smtp_port', 'Port', ['type' => 'number', 'required' => false, 'value' => $v('smtp_port', '587'), 'error' => $errors['smtp_port'] ?? '']) ?>
            </div>
            <div class="field">
                <label for="f-smtp_encryption">Security</label>
                <select id="f-smtp_encryption" name="smtp_encryption">
                    <?php foreach (['tls' => 'STARTTLS (usually port 587)', 'ssl' => 'SSL/TLS (usually port 465)', 'none' => 'None (not recommended)'] as $value => $label): ?>
                        <option value="<?= $value ?>"<?= $v('smtp_encryption', 'tls') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-row field-row--even">
                <?= form_field('smtp_username', 'Username', ['required' => false, 'value' => $v('smtp_username'), 'autocomplete' => 'off']) ?>
                <?= form_field('smtp_password', 'Password', ['type' => 'password', 'required' => false, 'autocomplete' => 'new-password',
                    'hint' => setting('smtp_password', '') !== '' ? 'Saved. Leave empty to keep it.' : 'Often an "app password" from your provider.']) ?>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button button--primary">Save email settings</button>
    </div>
</form>

<form method="post" action="<?= e(url('/admin/email/test')) ?>" class="card test-email">
    <?= csrf_field() ?>
    <h2 class="card__title">Check it works</h2>
    <p>Save your settings first, then send yourself a test email.</p>
    <button type="submit" class="button">Send a test email to me</button>
</form>

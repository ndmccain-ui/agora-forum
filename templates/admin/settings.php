<?php
defined('AGORA') or exit;
/** @var array $errors */
$checked = fn (string $key, string $default) => setting($key, $default) === '1' ? ' checked' : '';
?>
<form method="post" action="<?= e(url('/admin/settings')) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="card">
        <h2 class="card__title">Your forum</h2>
        <?= form_field('site_name', 'Forum name', ['value' => $_POST['site_name'] ?? setting('site_name', ''), 'error' => $errors['site_name'] ?? '', 'maxlength' => 80]) ?>
        <?= form_field('site_description', 'Short description', ['type' => 'textarea', 'required' => false, 'value' => $_POST['site_description'] ?? setting('site_description', ''), 'maxlength' => 300, 'hint' => 'Shown to search engines and on the home page.']) ?>
        <?= form_field('site_url', 'Forum address', ['type' => 'url', 'value' => $_POST['site_url'] ?? site_url(), 'error' => $errors['site_url'] ?? '', 'hint' => 'The full address people visit, used for links in emails. Change it if you move the forum.']) ?>
    </section>

    <section class="card">
        <h2 class="card__title">Joining</h2>
        <fieldset class="field radio-group">
            <legend>New sign-ups</legend>
            <label><input type="radio" name="registration" value="open"<?= setting('registration', 'open') === 'open' ? ' checked' : '' ?>> Open — anyone can create an account</label>
            <label><input type="radio" name="registration" value="closed"<?= setting('registration', 'open') === 'closed' ? ' checked' : '' ?>> Closed — no new accounts</label>
        </fieldset>
        <div class="field checkbox-field">
            <label><input type="checkbox" name="require_email_confirmation" value="1"<?= $checked('require_email_confirmation', '1') ?>> New members must confirm their email before posting</label>
        </div>
        <?= form_field('minimum_age', 'Minimum age', ['type' => 'number', 'value' => $_POST['minimum_age'] ?? setting('minimum_age', '13'), 'error' => $errors['minimum_age'] ?? '', 'hint' => 'New members tick "I am this age or older". 0 turns this off.']) ?>
    </section>

    <section class="card">
        <h2 class="card__title">Spam protection</h2>
        <div class="field checkbox-field">
            <label><input type="checkbox" name="approve_first_posts" value="1"<?= $checked('approve_first_posts', '0') ?>> A moderator must approve each new member's first post</label>
            <p class="field__hint">Their first post waits in Moderation → Waiting for approval. Once one post is approved, they post freely.</p>
        </div>
        <p class="field__hint">Always on: new accounts can include at most 2 links per post for their first day, and everyone is slowed down if they post very quickly.</p>
    </section>

    <section class="card">
        <h2 class="card__title">Tags</h2>
        <div class="field checkbox-field">
            <label><input type="checkbox" name="members_create_tags" value="1"<?= $checked('members_create_tags', '0') ?>> Members can create new tags</label>
            <p class="field__hint">When off, members pick from the tags you create. Admins and moderators can always create tags.</p>
        </div>
    </section>

    <button type="submit" class="button button--primary">Save settings</button>
</form>

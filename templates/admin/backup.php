<?php
defined('AGORA') or exit;
/** @var int $dbSize @var int $avatarCount */
?>
<section class="card">
    <h2 class="card__title">Download your forum</h2>
    <p>Everything — members, discussions, posts, tags and settings — is in one database file (<?= number_format($dbSize / 1024, 0) ?> KB right now). Download a copy now and then and keep it somewhere safe.</p>
    <form method="post" action="<?= e(url('/admin/backup/download')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="button button--primary">Download backup</button>
    </form>
</section>

<section class="card">
    <h2 class="card__title">Pictures and files</h2>
    <p>Profile pictures (<?= (int) $avatarCount ?>), your logo and uploaded themes live in the <code>data</code> folder on your server. To back up absolutely everything, download the whole <code>data</code> folder with your host's file manager or FTP.</p>
</section>

<section class="card">
    <h2 class="card__title">Restoring</h2>
    <p>To restore, upload a backup file into the <code>data</code> folder and rename it to match the name in <code>data/config.php</code> (the line that says <code>'database'</code>).</p>
</section>

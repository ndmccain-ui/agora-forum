# Installing Agora Forum

This guide assumes ordinary web hosting with a file manager or FTP (cPanel, Plesk, DirectAdmin and similar). No command line is needed.

## 1. Check your host

Agora needs **PHP 8.1 or newer** with **pdo_sqlite** and **mbstring**. Nearly every host has these. If you're not sure, upload Agora anyway: the setup page checks everything and tells you exactly what's missing.

Profile pictures and logos also need the **gd** extension (also very common). Without it, Agora still works and shows initials instead.

## 2. Choose an address

- **A subdomain** like `forum.example.com`. Create it in your host's control panel, then upload Agora into the subdomain's folder.
- **A folder** like `example.com/forum`. Upload Agora into `public_html/forum` (or your host's equivalent).

Both work the same way.

## 3. Upload

1. Unzip the Agora download on your computer.
2. Upload **everything inside** the Agora folder, including the hidden `.htaccess` files. (In FileZilla: Server → Force showing hidden files.)

## 4. Make `data` writable

The `data` folder is where Agora keeps your database and settings. In your file manager, set its permissions to **755**. If setup says it still can't write there, try **775**. Never use 777.

## 5. Run setup

Visit your forum's address. You'll see a server check and a short form:

- **Forum name**: you can change it later.
- **Send emails from**: optional. The address members see on emails like password resets, e.g. `hello@example.com`. Leave it blank to use `noreply@yourdomain`.
- **Your admin account**: the username, email and password you'll sign in with.

Click **Create my forum** and you're done.

## 6. Recommended next steps

1. **Admin → Email → Send a test email.** If it doesn't arrive (check spam), switch to **SMTP** and enter your email provider's settings. SMTP is much more reliable than the host's built-in mail.
2. **Admin → Categories**: add the sections your community needs.
3. **Admin → Appearance**: pick a theme and upload a logo.
4. **Admin → Backup**: download a backup now and then.

## Nginx

Nginx doesn't read `.htaccess` files. Add the rules from [`nginx.conf.example`](nginx.conf.example) to your server block. They protect the private folders and turn on clean addresses.

## Your own server (VPS) with Apache

On Ubuntu or Debian with Apache installed the usual way:

1. Turn on clean addresses: `sudo a2enmod rewrite`
2. Install PHP's extras: `sudo apt install libapache2-mod-php php-sqlite3 php-mbstring php-gd`
3. In your site's Apache config, allow `.htaccess` for the forum folder:

   ```apache
   <Directory /var/www/forum>
       AllowOverride All
   </Directory>
   ```

4. Let Apache write to `data`: `sudo chown -R www-data:www-data /var/www/forum/data`
5. Restart Apache: `sudo systemctl restart apache2`

If `a2enmod` says "command not found", Apache was probably installed by a control panel (see aaPanel below), so manage it there instead.

## aaPanel

aaPanel runs its own Apache, so you do everything in its dashboard.

1. **Website → Add site**: enter your domain and pick **PHP 8.1 or newer**. No database is needed.
2. **Files**: open the site folder (usually `/www/wwwroot/your-domain`), upload the Agora ZIP and **Unzip** it. Move everything from the `agora-forum-main` folder up into the site folder, so `index.php` is directly inside it. Delete aaPanel's placeholder `index.html`.
3. Right-click the `data` folder → **Permission**: **755**, owner **www**.
4. **Website → your site → SSL**: get a Let's Encrypt certificate and turn on **Force HTTPS**.
5. Visit your forum and run setup.

Leave the site's **URL rewrite** setting empty: Agora's `.htaccess` takes care of it. If setup ever reports a "disabled function", remove that function from **App Store → PHP → Setting → Disabled functions**.

## Troubleshooting

**"Something went wrong" or a blank page.** Check your host's PHP error log. The most common cause is a PHP version older than 8.1: choose a newer one in your control panel ("PHP Selector" or "MultiPHP Manager").

**Setup says the data folder isn't writable.** Set `data` to 755 or 775 (step 4). On some hosts the folder also has to belong to your account. Ask support if it keeps failing.

**Addresses contain `index.php`** (like `/index.php/c/general`). Clean addresses need Apache's `mod_rewrite`. Make sure the `.htaccess` file was uploaded. Everything still works either way.

**Emails don't arrive.** Use Admin → Email → Send a test email to see the exact error. With SMTP, the most common problems are the wrong port (587 with STARTTLS, or 465 with SSL/TLS) and needing an "app password" instead of your normal password.

**I'm locked out of the admin account.** Use "Forgot it?" on the sign-in page. If email isn't working yet, ask for help in the project's issues.

## Moving or restoring

- **Moving to a new address:** move the whole folder, then update **Admin → Settings → Forum address** so links in emails are right.
- **Restoring a backup:** upload the backup file into `data` and rename it to match the `'database'` name in `data/config.php`.

## Upgrading

Download a backup first (Admin → Backup). Then upload the new version over the old one, **without replacing your `data` folder**. Agora upgrades its database automatically the next time someone visits.

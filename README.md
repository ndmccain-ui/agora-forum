# Agora Forum

A simple, open-source discussion forum you can install by uploading a folder.

- **Upload, visit, done.** No command line, no Composer, no Node, no database server.
- **One file to back up.** Everything lives in a single SQLite database.
- **Small and calm.** Fast pages that work without JavaScript, on phones and computers, in light and dark mode.

> **Status: 0.9 (release candidate).** Feature-complete for version 1. Please try it and [report problems](../../issues).

## Features

**Discussions**
- Categories and tags, pinned and locked discussions
- Markdown formatting with a live preview, quoting, and editing
- Unread tracking ("3 new", jump to the first unread post, mark all as read)
- Full-text search with category and tag filters

**Members**
- Sign-up with email confirmation, password reset, "keep me signed in"
- Profiles with a picture and bio
- Optional minimum age (13+ by default)

**Moderation**
- Moderator and admin roles
- Report button and a moderation queue
- Restorable deletes, timed or permanent bans
- Spam protection: optional first-post approval, link limits for new accounts, rate limits, a hidden bot trap

**Admin panel**
- Settings, categories, tags (with colors and merging), members and roles
- Email via your host or SMTP, with a "send test email" button
- One-click database backup
- **Themes**: choose a theme, pick your own colors without code, upload a logo, add custom CSS, and download your look as a theme file to share. See [docs/THEMES.md](docs/THEMES.md).

**Security**
- Passwords hashed with PHP's `password_hash`; prepared statements everywhere
- CSRF protection on every form, strict Content Security Policy, no third-party scripts or fonts
- Member-written Markdown is escaped first, so posts can never inject HTML or scripts
- Login and sign-up rate limiting; changing a password signs out other devices

## Requirements

- PHP 8.1 or newer with the `pdo_sqlite` and `mbstring` extensions (standard on most hosts)
- Optional: the `gd` extension for profile pictures and logos
- Apache, LiteSpeed or Nginx

## Installing

1. Download Agora (the latest [release](../../releases), or **Code → Download ZIP** on this page) and unzip it.
2. Upload the folder to your web host — for example to `public_html/forum`, or to the root of a subdomain like `forum.example.com`.
3. Make sure the `data` folder is writable.
4. Visit your forum's address and fill in the setup form.

Step-by-step instructions, including Nginx and troubleshooting: **[docs/INSTALL.md](docs/INSTALL.md)**.

## Upgrading

Upload the new version over the old one, **keeping your `data` folder**. Agora updates its database automatically on the next page load. (Download a backup from Admin → Backup first, just in case.)

## Backing up

Admin → Backup downloads the database. To save everything, including profile pictures and your logo, download the whole `data` folder.

## Developing

```bash
php -S localhost:8091 index.php
```

Then open http://localhost:8091. On the local server, emails are written to `data/mail.log` instead of being sent.

The code is plain PHP with no framework and no build step:

| Folder | What's in it |
| --- | --- |
| `index.php` | Every request starts here |
| `app/` | Pages, database, sign-in, email, Markdown, themes |
| `app/migrations/` | Numbered database upgrades, run automatically |
| `templates/` | HTML templates |
| `assets/` | CSS, JavaScript, icons |
| `themes/` | Built-in themes (one CSS file each) |
| `data/` | Your forum's database, settings and uploads (never served directly) |

## License

[MIT](LICENSE)

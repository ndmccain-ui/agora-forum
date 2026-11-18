<?php
declare(strict_types=1);
defined('AGORA') or exit;

/** @var Router $router */

require AGORA_ROOT . '/app/pages/install.php';
require AGORA_ROOT . '/app/pages/forum.php';
require AGORA_ROOT . '/app/pages/discussion.php';
require AGORA_ROOT . '/app/pages/account.php';
require AGORA_ROOT . '/app/pages/profile.php';
require AGORA_ROOT . '/app/pages/admin.php';
require AGORA_ROOT . '/app/pages/moderation.php';

// Note: route patterns can't contain {curly braces} (like [0-9]{64}) — use + instead.

// Setup (only reachable before the forum is installed)
$router->get('/install', 'page_install');
$router->post('/install', 'page_install_submit');

// Reading
$router->get('/', 'page_home');
$router->get('/c/{slug:[a-z0-9-]+}', 'page_category');
$router->get('/tags', 'page_tags');
$router->get('/t/{slug:[a-z0-9-]+}', 'page_tag');
$router->get('/d/{id:\d+}', 'page_discussion');
$router->get('/d/{id:\d+}-{slug:[a-z0-9-]*}', 'page_discussion');
$router->get('/p/{id:\d+}', 'page_post_link');
$router->get('/d/{id:\d+}/unread', 'page_unread');
$router->post('/mark-read', 'page_mark_all_read');
$router->get('/search', 'page_search');
$router->get('/theme.css', 'page_theme_css');
$router->get('/logo/{file:logo-[a-f0-9]+\.(?:png|webp)}', 'page_logo');

// Writing
$router->get('/new', 'page_compose');
$router->post('/new', 'page_compose_submit');
$router->post('/d/{id:\d+}/reply', 'page_reply_submit');
$router->post('/preview', 'page_preview');
$router->get('/p/{id:\d+}/quote', 'page_quote');
$router->get('/p/{id:\d+}/edit', 'page_edit_post');
$router->post('/p/{id:\d+}/edit', 'page_edit_post_submit');

// Moderation
$router->post('/d/{id:\d+}/moderate', 'page_mod_discussion');
$router->post('/p/{id:\d+}/delete', 'page_post_delete');
$router->get('/p/{id:\d+}/report', 'page_report');
$router->post('/p/{id:\d+}/report', 'page_report_submit');
$router->get('/mod', 'page_mod_queue');
$router->post('/mod/reports/{postId:\d+}', 'page_mod_report_resolve');
$router->post('/mod/approval/{postId:\d+}', 'page_mod_approval');
$router->post('/mod/deleted/{postId:\d+}', 'page_mod_restore');
$router->post('/mod/users/{userId:\d+}/ban', 'page_mod_ban');
$router->post('/mod/users/{userId:\d+}/unban', 'page_mod_unban');

// Admin panel
$router->get('/admin', 'page_admin');
$router->get('/admin/settings', 'page_admin_settings');
$router->post('/admin/settings', 'page_admin_settings_submit');
$router->get('/admin/email', 'page_admin_email');
$router->post('/admin/email', 'page_admin_email_submit');
$router->post('/admin/email/test', 'page_admin_email_test');
$router->get('/admin/appearance', 'page_admin_appearance');
$router->post('/admin/appearance', 'page_admin_appearance_submit');
$router->get('/admin/appearance/guide', 'page_admin_guide');
$router->get('/admin/appearance/export', 'page_admin_theme_export');
$router->post('/admin/appearance/logo', 'page_admin_logo_upload');
$router->post('/admin/appearance/logo/remove', 'page_admin_logo_remove');
$router->post('/admin/themes/upload', 'page_admin_theme_upload');
$router->post('/admin/themes/{slug:[a-z0-9-]+}/delete', 'page_admin_theme_delete');
$router->get('/admin/categories', 'page_admin_categories');
$router->post('/admin/categories', 'page_admin_category_create');
$router->post('/admin/categories/{id:\d+}', 'page_admin_category_update');
$router->post('/admin/categories/{id:\d+}/move', 'page_admin_category_move');
$router->post('/admin/categories/{id:\d+}/delete', 'page_admin_category_delete');
$router->get('/admin/tags', 'page_admin_tags');
$router->post('/admin/tags', 'page_admin_tag_create');
$router->post('/admin/tags/{id:\d+}', 'page_admin_tag_update');
$router->post('/admin/tags/{id:\d+}/merge', 'page_admin_tag_merge');
$router->post('/admin/tags/{id:\d+}/delete', 'page_admin_tag_delete');
$router->get('/admin/users', 'page_admin_users');
$router->post('/admin/users/{id:\d+}/role', 'page_admin_user_role');
$router->post('/admin/users/{id:\d+}/confirm', 'page_admin_user_confirm');
$router->get('/admin/backup', 'page_admin_backup');
$router->post('/admin/backup/download', 'page_admin_backup_download');

// Signing in and out
$router->get('/login', 'page_login');
$router->post('/login', 'page_login_submit');
$router->post('/logout', 'page_logout');

// Joining
$router->get('/register', 'page_register');
$router->post('/register', 'page_register_submit');
$router->get('/confirm/{token:[a-f0-9]+}', 'page_confirm_email');
$router->post('/confirm/resend', 'page_confirm_resend');

// Forgotten passwords
$router->get('/forgot', 'page_forgot');
$router->post('/forgot', 'page_forgot_submit');
$router->get('/reset/{token:[a-f0-9]+}', 'page_reset');
$router->post('/reset/{token:[a-f0-9]+}', 'page_reset_submit');

// Profiles and settings
$router->get('/u/{username:[A-Za-z0-9_-]+}', 'page_profile');
$router->get('/avatar/{file:[0-9]+-[a-f0-9]+\.(?:webp|png)}', 'page_avatar');
$router->get('/settings', 'page_settings');
$router->post('/settings/profile', 'page_settings_profile');
$router->post('/settings/avatar', 'page_settings_avatar');
$router->post('/settings/avatar/remove', 'page_settings_avatar_remove');
$router->post('/settings/email', 'page_settings_email');
$router->post('/settings/password', 'page_settings_password');

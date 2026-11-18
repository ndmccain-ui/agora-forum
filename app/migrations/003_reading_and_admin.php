<?php
declare(strict_types=1);
defined('AGORA') or exit;

/**
 * Unread tracking, search, and settings for the admin panel and themes.
 */
return function (PDO $db): void {
    $db->exec(<<<'SQL'
        -- Newest post in each discussion (for "unread" checks).
        ALTER TABLE discussions ADD COLUMN last_post_id INTEGER;
        UPDATE discussions SET last_post_id = (SELECT MAX(id) FROM posts WHERE posts.discussion_id = discussions.id);

        -- "Mark all as read": every post up to this id counts as read for the user.
        ALTER TABLE users ADD COLUMN read_all_post_id INTEGER NOT NULL DEFAULT 0;

        -- How far each member has read in each discussion.
        CREATE TABLE discussion_reads (
            user_id           INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            discussion_id     INTEGER NOT NULL REFERENCES discussions(id) ON DELETE CASCADE,
            last_read_post_id INTEGER NOT NULL,
            PRIMARY KEY (user_id, discussion_id)
        );

        INSERT OR IGNORE INTO settings (key, value) VALUES
            ('theme', 'agora'),
            ('theme_default_mode', 'auto'),
            ('theme_colors', ''),
            ('custom_css', ''),
            ('logo', ''),
            ('appearance_version', '1'),
            ('mail_driver', ''),
            ('smtp_host', ''),
            ('smtp_port', '587'),
            ('smtp_encryption', 'tls'),
            ('smtp_username', ''),
            ('smtp_password', '');
    SQL);

    // Full-text search, if this server's SQLite has it (almost all do). Otherwise Agora uses simpler matching.
    try {
        $db->exec("CREATE VIRTUAL TABLE search_index USING fts5(title, body, discussion_id UNINDEXED, tokenize = 'unicode61 remove_diacritics 2')");
        $db->exec(
            'INSERT INTO search_index (rowid, title, body, discussion_id)
             SELECT p.id, d.title, p.body, d.id FROM posts p JOIN discussions d ON d.id = p.discussion_id
             WHERE p.is_hidden = 0 AND d.is_hidden = 0'
        );
        $engine = 'fts5';
    } catch (PDOException) {
        $engine = 'like';
    }
    $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('search_engine', ?)")->execute([$engine]);
};

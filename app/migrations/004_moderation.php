<?php
declare(strict_types=1);
defined('AGORA') or exit;

/**
 * Moderation: bans, reports, and who hid what.
 *
 * posts.is_hidden / discussions.is_hidden now mean:
 *   0 = visible, 1 = deleted (restorable), 2 = waiting for a moderator's approval
 */
return function (PDO $db): void {
    $db->exec(<<<'SQL'
        ALTER TABLE users ADD COLUMN banned_until TEXT;      -- NULL = not banned; '9999-12-31 00:00:00' = permanent
        ALTER TABLE users ADD COLUMN ban_reason TEXT;
        ALTER TABLE users ADD COLUMN banned_by INTEGER REFERENCES users(id) ON DELETE SET NULL;

        ALTER TABLE posts ADD COLUMN hidden_by INTEGER REFERENCES users(id) ON DELETE SET NULL;
        ALTER TABLE posts ADD COLUMN hidden_at TEXT;
        ALTER TABLE discussions ADD COLUMN hidden_by INTEGER REFERENCES users(id) ON DELETE SET NULL;
        ALTER TABLE discussions ADD COLUMN hidden_at TEXT;

        CREATE TABLE reports (
            id          INTEGER PRIMARY KEY,
            post_id     INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
            reporter_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            reason      TEXT NOT NULL CHECK (reason IN ('spam', 'abuse', 'off_topic', 'other')),
            note        TEXT NOT NULL DEFAULT '',
            created_at  TEXT NOT NULL,
            resolved_at TEXT,
            resolved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
            resolution  TEXT
        );
        CREATE INDEX reports_open ON reports (resolved_at, post_id);
        CREATE INDEX posts_hidden ON posts (is_hidden);

        INSERT OR IGNORE INTO settings (key, value) VALUES ('approve_first_posts', '0');
    SQL);
};

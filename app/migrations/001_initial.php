<?php
declare(strict_types=1);
defined('AGORA') or exit;

/**
 * The starting database. Later features (unread tracking, reports, bans,
 * search) arrive as their own numbered migration files.
 */
return function (PDO $db): void {
    $db->exec(<<<'SQL'
        CREATE TABLE settings (
            key   TEXT PRIMARY KEY,
            value TEXT
        );

        CREATE TABLE users (
            id                INTEGER PRIMARY KEY,
            username          TEXT NOT NULL UNIQUE COLLATE NOCASE,
            email             TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash     TEXT NOT NULL,
            role              TEXT NOT NULL DEFAULT 'member' CHECK (role IN ('admin', 'moderator', 'member')),
            bio               TEXT NOT NULL DEFAULT '',
            avatar            TEXT,
            email_verified_at TEXT,
            created_at        TEXT NOT NULL,
            last_seen_at      TEXT
        );

        CREATE TABLE categories (
            id          INTEGER PRIMARY KEY,
            name        TEXT NOT NULL,
            slug        TEXT NOT NULL UNIQUE,
            description TEXT NOT NULL DEFAULT '',
            position    INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL
        );

        CREATE TABLE discussions (
            id             INTEGER PRIMARY KEY,
            category_id    INTEGER NOT NULL REFERENCES categories(id),
            user_id        INTEGER REFERENCES users(id) ON DELETE SET NULL,
            title          TEXT NOT NULL,
            slug           TEXT NOT NULL,
            is_pinned      INTEGER NOT NULL DEFAULT 0,
            is_locked      INTEGER NOT NULL DEFAULT 0,
            is_hidden      INTEGER NOT NULL DEFAULT 0,
            reply_count    INTEGER NOT NULL DEFAULT 0,
            last_user_id   INTEGER REFERENCES users(id) ON DELETE SET NULL,
            last_posted_at TEXT NOT NULL,
            created_at     TEXT NOT NULL
        );
        CREATE INDEX discussions_activity ON discussions (is_pinned DESC, last_posted_at DESC);
        CREATE INDEX discussions_category ON discussions (category_id, is_pinned DESC, last_posted_at DESC);

        CREATE TABLE posts (
            id            INTEGER PRIMARY KEY,
            discussion_id INTEGER NOT NULL REFERENCES discussions(id) ON DELETE CASCADE,
            user_id       INTEGER REFERENCES users(id) ON DELETE SET NULL,
            body          TEXT NOT NULL,
            is_hidden     INTEGER NOT NULL DEFAULT 0,
            created_at    TEXT NOT NULL,
            edited_at     TEXT
        );
        CREATE INDEX posts_discussion ON posts (discussion_id, id);
        CREATE INDEX posts_user ON posts (user_id, id);

        CREATE TABLE tags (
            id         INTEGER PRIMARY KEY,
            name       TEXT NOT NULL UNIQUE COLLATE NOCASE,
            slug       TEXT NOT NULL UNIQUE,
            color      TEXT,
            created_at TEXT NOT NULL
        );

        CREATE TABLE discussion_tags (
            discussion_id INTEGER NOT NULL REFERENCES discussions(id) ON DELETE CASCADE,
            tag_id        INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
            PRIMARY KEY (discussion_id, tag_id)
        );
        CREATE INDEX discussion_tags_tag ON discussion_tags (tag_id);

        CREATE TABLE login_attempts (
            ip           TEXT NOT NULL,
            attempted_at TEXT NOT NULL
        );
        CREATE INDEX login_attempts_ip ON login_attempts (ip, attempted_at);
    SQL);
};

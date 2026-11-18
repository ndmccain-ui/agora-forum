<?php
declare(strict_types=1);
defined('AGORA') or exit;

/**
 * Accounts: email confirmation and password reset links, "remember me",
 * general rate limits, and a way to sign a user out everywhere.
 */
return function (PDO $db): void {
    $db->exec(<<<'SQL'
        -- Bumped when a password changes: signs the user out on every other device.
        ALTER TABLE users ADD COLUMN auth_version INTEGER NOT NULL DEFAULT 1;

        -- One-time links sent by email. Only a hash of the secret is stored.
        CREATE TABLE user_tokens (
            id         INTEGER PRIMARY KEY,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            type       TEXT NOT NULL CHECK (type IN ('confirm_email', 'reset_password')),
            token_hash TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL
        );
        CREATE INDEX user_tokens_user ON user_tokens (user_id, type);

        -- "Remember me" logins. The cookie holds selector:validator; we store a hash of the validator.
        CREATE TABLE remember_tokens (
            id             INTEGER PRIMARY KEY,
            user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            selector       TEXT NOT NULL UNIQUE,
            validator_hash TEXT NOT NULL,
            expires_at     TEXT NOT NULL,
            created_at     TEXT NOT NULL
        );
        CREATE INDEX remember_tokens_user ON remember_tokens (user_id);

        -- Slows down sign-up and email floods (per IP address or per user).
        CREATE TABLE rate_limits (
            action     TEXT NOT NULL,
            subject    TEXT NOT NULL,
            created_at TEXT NOT NULL
        );
        CREATE INDEX rate_limits_lookup ON rate_limits (action, subject, created_at);

        INSERT OR IGNORE INTO settings (key, value) VALUES
            ('require_email_confirmation', '1'),
            ('minimum_age', '13'),
            ('mail_from', '');
    SQL);
};

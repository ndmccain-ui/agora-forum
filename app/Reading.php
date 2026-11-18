<?php
declare(strict_types=1);
defined('AGORA') or exit;

/* ---------- Unread tracking ---------- */

/** Remember that a member has read a discussion up to (at least) this post. */
function mark_read(int $userId, int $discussionId, int $postId): void
{
    DB::query(
        'INSERT INTO discussion_reads (user_id, discussion_id, last_read_post_id) VALUES (?, ?, ?)
         ON CONFLICT (user_id, discussion_id) DO UPDATE SET last_read_post_id = MAX(last_read_post_id, excluded.last_read_post_id)',
        [$userId, $discussionId, $postId]
    );
}

/** The id of the last post this member has read in a discussion (0 = never opened). */
function last_read_post_id(array $user, int $discussionId): int
{
    $read = (int) DB::value(
        'SELECT last_read_post_id FROM discussion_reads WHERE user_id = ? AND discussion_id = ?',
        [$user['id'], $discussionId]
    );
    return max($read, (int) $user['read_all_post_id']);
}

/**
 * Add unread info to a list of discussions:
 *   'unread' => true if there's anything new, 'new_posts' => how many new posts (0 = never opened, all new).
 */
function add_unread_info(array $discussions, ?array $user): array
{
    if ($user === null || !$discussions) {
        return $discussions;
    }
    $ids = array_column($discussions, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $reads = [];
    foreach (DB::all(
        "SELECT discussion_id, last_read_post_id FROM discussion_reads WHERE user_id = ? AND discussion_id IN ($placeholders)",
        array_merge([$user['id']], $ids)
    ) as $row) {
        $reads[$row['discussion_id']] = (int) $row['last_read_post_id'];
    }

    foreach ($discussions as &$d) {
        $readUpTo = max($reads[$d['id']] ?? 0, (int) $user['read_all_post_id']);
        $d['unread'] = (int) $d['last_post_id'] > $readUpTo;
        $d['new_posts'] = 0;
        // Count new posts if they'd read part of it; a discussion that's entirely new just shows "New".
        $firstPostId = (int) DB::value('SELECT MIN(id) FROM posts WHERE discussion_id = ?', [$d['id']]);
        if ($d['unread'] && $readUpTo >= $firstPostId) {
            $d['new_posts'] = (int) DB::value(
                'SELECT COUNT(*) FROM posts WHERE discussion_id = ? AND is_hidden = 0 AND id > ?',
                [$d['id'], $readUpTo]
            );
        }
    }
    return $discussions;
}

/* ---------- Search index ---------- */

function search_uses_fts(): bool
{
    return setting('search_engine') === 'fts5';
}

/** Add or refresh a post in the search index. */
function index_post(int $postId): void
{
    if (!search_uses_fts()) {
        return;
    }
    DB::query('DELETE FROM search_index WHERE rowid = ?', [$postId]);
    DB::query(
        'INSERT INTO search_index (rowid, title, body, discussion_id)
         SELECT p.id, d.title, p.body, d.id FROM posts p JOIN discussions d ON d.id = p.discussion_id
         WHERE p.id = ? AND p.is_hidden = 0 AND d.is_hidden = 0',
        [$postId]
    );
}

/** Refresh every post in a discussion (after its title changes). */
function index_discussion(int $discussionId): void
{
    if (!search_uses_fts()) {
        return;
    }
    unindex_discussion($discussionId);
    DB::query(
        'INSERT INTO search_index (rowid, title, body, discussion_id)
         SELECT p.id, d.title, p.body, d.id FROM posts p JOIN discussions d ON d.id = p.discussion_id
         WHERE d.id = ? AND p.is_hidden = 0 AND d.is_hidden = 0',
        [$discussionId]
    );
}

/**
 * Remove a discussion's posts from search. Matches by post id (rowid): the search table
 * stores discussion_id without a type, so comparing it directly isn't reliable.
 */
function unindex_discussion(int $discussionId): void
{
    if (search_uses_fts()) {
        DB::query('DELETE FROM search_index WHERE rowid IN (SELECT id FROM posts WHERE discussion_id = ?)', [$discussionId]);
    }
}

/** Split what someone typed into search words (max 8, so huge queries stay fast). */
function search_terms(string $query): array
{
    preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'_-]*/u', $query, $m);
    $terms = array_values(array_unique(array_map(fn ($t) => mb_strtolower($t), $m[0])));
    return array_slice($terms, 0, 8);
}

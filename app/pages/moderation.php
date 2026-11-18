<?php
declare(strict_types=1);
defined('AGORA') or exit;

const HIDDEN_DELETED = 1;
const HIDDEN_PENDING = 2;
const REPORT_REASONS = [
    'spam' => 'Spam or advertising',
    'abuse' => 'Rude, hateful or harassing',
    'off_topic' => 'Off-topic or in the wrong place',
    'other' => 'Something else',
];
const BAN_LENGTHS = ['1' => '1 day', '7' => '1 week', '30' => '30 days', 'forever' => 'Permanently'];
const NEW_ACCOUNT_HOURS = 24;
const NEW_ACCOUNT_MAX_LINKS = 2;

/* ---------- Who's who ---------- */

function require_moderator(): array
{
    $user = require_login();
    if (!is_moderator($user)) {
        echo view('message', ['title' => 'Moderators only', 'message' => 'This part of the forum is only for moderators and admins.'], 403);
        exit;
    }
    return $user;
}

/** Can $mod ban $target? Moderators can ban members; admins can ban anyone except other admins. */
function can_ban(?array $mod, array $target): bool
{
    if (!is_moderator($mod) || (int) $mod['id'] === (int) $target['id'] || $target['role'] === 'admin') {
        return false;
    }
    return $mod['role'] === 'admin' || $target['role'] === 'member';
}

/** Should this member's next post wait for a moderator? (Only when the setting is on and they've never had a post approved.) */
function post_needs_approval(array $user): bool
{
    return setting('approve_first_posts', '0') === '1'
        && !is_moderator($user)
        && !DB::value('SELECT 1 FROM posts WHERE user_id = ? AND is_hidden = 0 LIMIT 1', [$user['id']]);
}

/** Brand-new accounts can only include a couple of links per post (a common spam pattern). */
function new_account_link_error(array $user, string $body): ?string
{
    if (is_moderator($user) || strtotime($user['created_at'] . ' UTC') < time() - NEW_ACCOUNT_HOURS * 3600) {
        return null;
    }
    $links = preg_match_all('#https?://#i', $body);
    return $links > NEW_ACCOUNT_MAX_LINKS
        ? 'New members can include up to ' . NEW_ACCOUNT_MAX_LINKS . ' links per post for their first day. Please remove some links.'
        : null;
}

/* ---------- Keeping counts right ---------- */

/** Recount a discussion's replies and latest post after something was hidden, restored or approved. */
function refresh_discussion_stats(int $discussionId): void
{
    $latest = DB::one(
        'SELECT id, user_id, created_at FROM posts WHERE discussion_id = ? AND is_hidden = 0 ORDER BY id DESC LIMIT 1',
        [$discussionId]
    );
    if ($latest === null) {
        return;
    }
    $count = (int) DB::value('SELECT COUNT(*) FROM posts WHERE discussion_id = ? AND is_hidden = 0', [$discussionId]);
    DB::query(
        'UPDATE discussions SET reply_count = ?, last_post_id = ?, last_user_id = ?, last_posted_at = ? WHERE id = ?',
        [max(0, $count - 1), $latest['id'], $latest['user_id'], $latest['created_at'], $discussionId]
    );
}

function first_post_id(int $discussionId): int
{
    return (int) DB::value('SELECT MIN(id) FROM posts WHERE discussion_id = ?', [$discussionId]);
}

/* ---------- Discussion tools (pin, lock, move, delete) ---------- */

function page_mod_discussion(string $id): string
{
    $mod = require_moderator();
    $discussion = find_discussion((int) $id) ?? not_found();
    $action = input('action');
    $messages = [
        'pin' => 'Pinned to the top.', 'unpin' => 'Unpinned.',
        'lock' => 'Locked — no new replies.', 'unlock' => 'Unlocked.',
    ];

    switch ($action) {
        case 'pin':
        case 'unpin':
            DB::query('UPDATE discussions SET is_pinned = ? WHERE id = ?', [$action === 'pin' ? 1 : 0, $discussion['id']]);
            break;
        case 'lock':
        case 'unlock':
            DB::query('UPDATE discussions SET is_locked = ? WHERE id = ?', [$action === 'lock' ? 1 : 0, $discussion['id']]);
            break;
        case 'move':
            $category = DB::one('SELECT * FROM categories WHERE id = ?', [(int) input('category_id')]);
            if ($category === null) {
                redirect('/d/' . $discussion['id']);
            }
            DB::query('UPDATE discussions SET category_id = ? WHERE id = ?', [$category['id'], $discussion['id']]);
            $messages['move'] = 'Moved to ' . $category['name'] . '.';
            break;
        case 'delete':
            hide_discussion($discussion, $mod);
            flash('success', '"' . $discussion['title'] . '" was deleted. Moderators can restore it from the Deleted list.');
            redirect('/c/' . $discussion['category_slug']);
        default:
            redirect('/d/' . $discussion['id']);
    }
    flash('success', $messages[$action]);
    header('Location: ' . discussion_url($discussion), true, 303);
    exit;
}

function hide_discussion(array $discussion, array $by): void
{
    DB::transaction(function () use ($discussion, $by): void {
        DB::query('UPDATE discussions SET is_hidden = ?, hidden_by = ?, hidden_at = ? WHERE id = ?', [HIDDEN_DELETED, $by['id'], now(), $discussion['id']]);
        unindex_discussion((int) $discussion['id']);
        DB::query("UPDATE reports SET resolved_at = ?, resolved_by = ?, resolution = 'deleted'
                   WHERE resolved_at IS NULL AND post_id IN (SELECT id FROM posts WHERE discussion_id = ?)", [now(), $by['id'], $discussion['id']]);
    });
}

/* ---------- Deleting a single post ---------- */

function page_post_delete(string $id): string
{
    $user = require_login();
    $post = DB::one('SELECT * FROM posts WHERE id = ? AND is_hidden = 0', [(int) $id]) ?? not_found();
    $discussion = find_discussion((int) $post['discussion_id']) ?? not_found();
    $isAuthor = (int) $post['user_id'] === (int) $user['id'];
    $isFirst = (int) $post['id'] === first_post_id((int) $discussion['id']);

    if (!is_moderator($user) && !($isAuthor && !is_banned($user) && !$discussion['is_locked'] && (!$isFirst || (int) $discussion['reply_count'] === 0))) {
        echo view('message', ['title' => 'You can\'t delete this', 'message' => $isFirst
            ? 'A discussion that has replies can only be deleted by a moderator.'
            : 'Only the author or a moderator can delete a post.'], 403);
        exit;
    }

    if ($isFirst) {
        hide_discussion($discussion, $user);
        flash('success', 'The discussion was deleted.');
        redirect('/c/' . $discussion['category_slug']);
    }

    DB::transaction(function () use ($post, $user, $discussion): void {
        DB::query('UPDATE posts SET is_hidden = ?, hidden_by = ?, hidden_at = ? WHERE id = ?', [HIDDEN_DELETED, $user['id'], now(), $post['id']]);
        DB::query("UPDATE reports SET resolved_at = ?, resolved_by = ?, resolution = 'deleted' WHERE post_id = ? AND resolved_at IS NULL", [now(), $user['id'], $post['id']]);
        if (search_uses_fts()) {
            DB::query('DELETE FROM search_index WHERE rowid = ?', [$post['id']]);
        }
        refresh_discussion_stats((int) $discussion['id']);
    });
    flash('success', 'The reply was deleted.');
    header('Location: ' . discussion_url($discussion), true, 303);
    exit;
}

/* ---------- Reports ---------- */

function page_report(string $id, array $errors = []): string
{
    $user = require_login();
    $post = DB::one(
        'SELECT p.*, u.username, d.title, d.id AS discussion_id FROM posts p
         JOIN discussions d ON d.id = p.discussion_id LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ? AND p.is_hidden = 0 AND d.is_hidden = 0',
        [(int) $id]
    ) ?? not_found();
    if ((int) $post['user_id'] === (int) $user['id']) {
        return view('message', ['title' => 'That\'s your own post', 'message' => 'You can edit or delete your own posts instead of reporting them.']);
    }
    if (DB::value('SELECT 1 FROM reports WHERE post_id = ? AND reporter_id = ? AND resolved_at IS NULL', [$post['id'], $user['id']])) {
        return view('message', ['title' => 'Already reported', 'message' => 'Thanks — you\'ve already reported this post, and a moderator will look at it.']);
    }
    return view('report', ['title' => 'Report a post', 'post' => $post, 'errors' => $errors, 'reasons' => REPORT_REASONS]);
}

function page_report_submit(string $id): string
{
    $user = require_login();
    $post = DB::one('SELECT p.* FROM posts p JOIN discussions d ON d.id = p.discussion_id WHERE p.id = ? AND p.is_hidden = 0 AND d.is_hidden = 0', [(int) $id]) ?? not_found();
    if ((int) $post['user_id'] === (int) $user['id'] || DB::value('SELECT 1 FROM reports WHERE post_id = ? AND reporter_id = ? AND resolved_at IS NULL', [$post['id'], $user['id']])) {
        return page_report($id);
    }
    $reason = input('reason');
    if (!isset(REPORT_REASONS[$reason])) {
        http_response_code(422);
        return page_report($id, ['reason' => 'Choose a reason.']);
    }
    if (!rate_limit('report', (string) $user['id'], 10, 60)) {
        return view('message', ['title' => 'Please wait a bit', 'message' => 'You\'ve sent a lot of reports recently. Please try again later.'], 429);
    }
    DB::insert('reports', [
        'post_id' => $post['id'],
        'reporter_id' => $user['id'],
        'reason' => $reason,
        'note' => mb_substr(input('note'), 0, 500),
        'created_at' => now(),
    ]);
    flash('success', 'Thanks for letting us know. A moderator will take a look.');
    return page_post_link((string) $post['id']);
}

/* ---------- The moderation queue ---------- */

function moderation_counts(): array
{
    return [
        'reports' => (int) DB::value('SELECT COUNT(DISTINCT post_id) FROM reports WHERE resolved_at IS NULL'),
        'approval' => (int) DB::value('SELECT COUNT(*) FROM posts WHERE is_hidden = ?', [HIDDEN_PENDING]),
    ];
}

function page_mod_queue(): string
{
    require_moderator();
    $tab = in_array($_GET['tab'] ?? '', ['reports', 'approval', 'deleted'], true) ? $_GET['tab'] : 'reports';
    $data = ['title' => 'Moderation', 'tab' => $tab, 'counts' => moderation_counts(), 'reasons' => REPORT_REASONS];

    $postFields = 'p.id, p.body, p.created_at, p.is_hidden, p.hidden_at, p.discussion_id, d.title, d.slug, d.is_hidden AS discussion_hidden,
                   u.id AS author_id, u.username, u.avatar, u.role, u.banned_until';
    $postJoins = 'JOIN discussions d ON d.id = p.discussion_id LEFT JOIN users u ON u.id = p.user_id';

    if ($tab === 'reports') {
        $data['items'] = DB::all(
            "SELECT $postFields, COUNT(r.id) AS report_count, MIN(r.created_at) AS first_reported,
                    GROUP_CONCAT(r.reason) AS reasons_list
             FROM reports r JOIN posts p ON p.id = r.post_id $postJoins
             WHERE r.resolved_at IS NULL
             GROUP BY p.id ORDER BY first_reported LIMIT 50"
        );
        foreach ($data['items'] as &$item) {
            $item['notes'] = DB::all(
                'SELECT r.reason, r.note, r.created_at, u.username FROM reports r LEFT JOIN users u ON u.id = r.reporter_id
                 WHERE r.post_id = ? AND r.resolved_at IS NULL ORDER BY r.id',
                [$item['id']]
            );
        }
        unset($item);
    } elseif ($tab === 'approval') {
        $data['items'] = DB::all(
            "SELECT $postFields, (p.id = (SELECT MIN(id) FROM posts WHERE discussion_id = p.discussion_id)) AS is_first
             FROM posts p $postJoins WHERE p.is_hidden = ? ORDER BY p.id LIMIT 50",
            [HIDDEN_PENDING]
        );
    } else {
        $data['items'] = DB::all(
            "SELECT $postFields, (p.id = (SELECT MIN(id) FROM posts WHERE discussion_id = p.discussion_id)) AS is_first,
                    COALESCE(hp.username, hd.username) AS hidden_by_name, COALESCE(p.hidden_at, d.hidden_at) AS deleted_at
             FROM posts p $postJoins
             LEFT JOIN users hp ON hp.id = p.hidden_by LEFT JOIN users hd ON hd.id = d.hidden_by
             WHERE p.is_hidden = ? OR (d.is_hidden = ? AND p.id = (SELECT MIN(id) FROM posts WHERE discussion_id = d.id))
             ORDER BY deleted_at DESC LIMIT 50",
            [HIDDEN_DELETED, HIDDEN_DELETED]
        );
    }
    return view('moderation', $data);
}

/** Resolve reports on a post: "dismiss" (it's fine) or "delete" the post. */
function page_mod_report_resolve(string $postId): string
{
    $mod = require_moderator();
    $post = DB::one('SELECT * FROM posts WHERE id = ?', [(int) $postId]) ?? not_found();
    if (input('action') === 'delete' && (int) $post['is_hidden'] === 0) {
        $discussion = DB::one('SELECT d.*, c.slug AS category_slug FROM discussions d JOIN categories c ON c.id = d.category_id WHERE d.id = ?', [$post['discussion_id']]);
        if ((int) $post['id'] === first_post_id((int) $post['discussion_id'])) {
            hide_discussion($discussion, $mod);
        } else {
            DB::transaction(function () use ($post, $mod): void {
                DB::query('UPDATE posts SET is_hidden = ?, hidden_by = ?, hidden_at = ? WHERE id = ?', [HIDDEN_DELETED, $mod['id'], now(), $post['id']]);
                if (search_uses_fts()) {
                    DB::query('DELETE FROM search_index WHERE rowid = ?', [$post['id']]);
                }
                refresh_discussion_stats((int) $post['discussion_id']);
            });
        }
        $message = 'Post deleted and reports closed.';
        $resolution = 'deleted';
    } else {
        $message = 'Reports dismissed — the post stays up.';
        $resolution = 'dismissed';
    }
    DB::query('UPDATE reports SET resolved_at = ?, resolved_by = ?, resolution = ? WHERE post_id = ? AND resolved_at IS NULL', [now(), $mod['id'], $resolution, $post['id']]);
    flash('success', $message);
    redirect('/mod', ['tab' => 'reports']);
}

/** Approve or reject a post that was waiting for approval. */
function page_mod_approval(string $postId): string
{
    $mod = require_moderator();
    $post = DB::one('SELECT * FROM posts WHERE id = ? AND is_hidden = ?', [(int) $postId, HIDDEN_PENDING]) ?? not_found();
    $discussionId = (int) $post['discussion_id'];
    $isFirst = (int) $post['id'] === first_post_id($discussionId);
    $approve = input('action') === 'approve';

    DB::transaction(function () use ($post, $mod, $discussionId, $isFirst, $approve): void {
        if ($approve) {
            DB::query('UPDATE posts SET is_hidden = 0 WHERE id = ?', [$post['id']]);
            if ($isFirst) {
                DB::query('UPDATE discussions SET is_hidden = 0 WHERE id = ?', [$discussionId]);
            }
        } else {
            DB::query('UPDATE posts SET is_hidden = ?, hidden_by = ?, hidden_at = ? WHERE id = ?', [HIDDEN_DELETED, $mod['id'], now(), $post['id']]);
            if ($isFirst) {
                DB::query('UPDATE discussions SET is_hidden = ?, hidden_by = ?, hidden_at = ? WHERE id = ?', [HIDDEN_DELETED, $mod['id'], now(), $discussionId]);
            }
        }
        refresh_discussion_stats($discussionId);
        if ($approve) {
            $isFirst ? index_discussion($discussionId) : index_post((int) $post['id']);
        }
    });
    flash('success', $approve ? 'Approved — it\'s now visible to everyone.' : 'Rejected. It\'s in the Deleted list if you change your mind.');
    redirect('/mod', ['tab' => 'approval']);
}

/** Bring back something that was deleted. Admins can also delete it forever. */
function page_mod_restore(string $postId): string
{
    $mod = require_moderator();
    $post = DB::one('SELECT * FROM posts WHERE id = ?', [(int) $postId]) ?? not_found();
    $discussionId = (int) $post['discussion_id'];
    $isFirst = (int) $post['id'] === first_post_id($discussionId);

    if (input('action') === 'purge') {
        if ($mod['role'] !== 'admin') {
            redirect('/mod', ['tab' => 'deleted']);
        }
        DB::transaction(function () use ($post, $discussionId, $isFirst): void {
            if ($isFirst) {
                unindex_discussion($discussionId);
                DB::query('DELETE FROM discussions WHERE id = ?', [$discussionId]); // posts, tags and reads go with it
            } else {
                DB::query('DELETE FROM posts WHERE id = ?', [$post['id']]);
                refresh_discussion_stats($discussionId);
            }
        });
        flash('success', 'Deleted forever.');
        redirect('/mod', ['tab' => 'deleted']);
    }

    DB::transaction(function () use ($post, $discussionId, $isFirst): void {
        if ($isFirst) {
            DB::query('UPDATE discussions SET is_hidden = 0, hidden_by = NULL, hidden_at = NULL WHERE id = ?', [$discussionId]);
        }
        DB::query('UPDATE posts SET is_hidden = 0, hidden_by = NULL, hidden_at = NULL WHERE id = ? AND is_hidden = ?', [$post['id'], HIDDEN_DELETED]);
        refresh_discussion_stats($discussionId);
        $isFirst ? index_discussion($discussionId) : index_post((int) $post['id']);
    });
    flash('success', 'Restored.');
    redirect('/mod', ['tab' => 'deleted']);
}

/* ---------- Bans ---------- */

function page_mod_ban(string $userId): string
{
    $mod = require_moderator();
    $target = DB::one('SELECT * FROM users WHERE id = ?', [(int) $userId]) ?? not_found();
    if (!can_ban($mod, $target)) {
        echo view('message', ['title' => 'Not allowed', 'message' => 'Moderators can ban members; admins can also ban moderators. Admins can\'t be banned.'], 403);
        exit;
    }
    $length = input('length');
    if (!isset(BAN_LENGTHS[$length])) {
        flash('error', 'Choose how long the ban lasts.');
        redirect('/u/' . $target['username']);
    }
    $until = $length === 'forever' ? PERMANENT_BAN : gmdate('Y-m-d H:i:s', time() + (int) $length * 86400);
    DB::transaction(function () use ($target, $until, $mod): void {
        DB::query('UPDATE users SET banned_until = ?, ban_reason = ?, banned_by = ? WHERE id = ?', [$until, mb_substr(input('reason'), 0, 300), $mod['id'], $target['id']]);
        DB::query('DELETE FROM remember_tokens WHERE user_id = ?', [$target['id']]);
    });
    flash('success', $target['username'] . ' is banned ' . ($length === 'forever' ? 'permanently' : 'for ' . BAN_LENGTHS[$length]) . '.');
    redirect('/u/' . $target['username']);
}

function page_mod_unban(string $userId): string
{
    $mod = require_moderator();
    $target = DB::one('SELECT * FROM users WHERE id = ?', [(int) $userId]) ?? not_found();
    if (!can_ban($mod, $target) && !($mod['role'] === 'admin' && $target['role'] !== 'admin')) {
        redirect('/u/' . $target['username']);
    }
    DB::query('UPDATE users SET banned_until = NULL, ban_reason = NULL, banned_by = NULL WHERE id = ?', [$target['id']]);
    flash('success', $target['username'] . ' is no longer banned.');
    redirect('/u/' . $target['username']);
}

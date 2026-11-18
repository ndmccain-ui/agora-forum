<?php
declare(strict_types=1);
defined('AGORA') or exit;

const POSTS_PER_PAGE = 20;
const TITLE_MIN = 3;
const TITLE_MAX = 120;
const BODY_MAX = 20000;
const TAGS_MAX = 5;

/* ---------- Who may do what ---------- */

/** Why this user can't post right now, or null if they can. */
function posting_blocked_reason(?array $user): ?string
{
    if ($user === null) {
        return 'Sign in to join the conversation.';
    }
    if (needs_email_confirmation($user)) {
        return 'Please confirm your email address before posting — check your inbox for the link.';
    }
    if (is_banned($user)) {
        return 'You can\'t post right now: you are ' . ban_description($user) . '.';
    }
    return null;
}

function can_create_tags(?array $user): bool
{
    return is_moderator($user) || setting('members_create_tags', '0') === '1';
}

/* ---------- Tags ---------- */

/**
 * Turn "ipad, Inking" into tag ids. Returns [existingIds, newNames];
 * new tags are only created later, once the whole form is valid.
 */
function parse_tags(string $input, ?array $user, array &$errors): array
{
    $names = [];
    foreach (explode(',', $input) as $raw) {
        $name = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        if ($name !== '' && !isset($names[mb_strtolower($name)])) {
            $names[mb_strtolower($name)] = $name;
        }
    }
    if (count($names) > TAGS_MAX) {
        $errors['tags'] = 'Use up to ' . TAGS_MAX . ' tags.';
        return [[], []];
    }

    $ids = [];
    $new = [];
    $unknown = [];
    foreach ($names as $name) {
        if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _-]{0,29}$/u', $name)) {
            $errors['tags'] = '"' . $name . '" isn\'t a valid tag. Tags are up to 30 letters, numbers, spaces or dashes.';
            return [[], []];
        }
        $id = DB::value('SELECT id FROM tags WHERE name = ? COLLATE NOCASE', [$name]);
        if ($id !== null) {
            $ids[] = (int) $id;
        } elseif (can_create_tags($user)) {
            $new[] = $name;
        } else {
            $unknown[] = $name;
        }
    }
    if ($unknown) {
        $errors['tags'] = 'Not a tag on this forum: ' . implode(', ', $unknown) . '. Pick from the existing tags.';
    }
    return [$ids, $new];
}

/** Replace a discussion's tags (creating any new ones). Call inside a transaction. */
function set_discussion_tags(int $discussionId, array $tagIds, array $newTags): void
{
    foreach ($newTags as $name) {
        $tagIds[] = create_tag($name);
    }
    DB::query('DELETE FROM discussion_tags WHERE discussion_id = ?', [$discussionId]);
    foreach (array_unique($tagIds) as $tagId) {
        DB::insert('discussion_tags', ['discussion_id' => $discussionId, 'tag_id' => $tagId]);
    }
}

function create_tag(string $name): int
{
    $base = slugify($name);
    $slug = $base;
    for ($n = 2; DB::value('SELECT 1 FROM tags WHERE slug = ?', [$slug]); $n++) {
        $slug = $base . '-' . $n;
    }
    return DB::insert('tags', ['name' => $name, 'slug' => $slug, 'created_at' => now()]);
}

function tags_for_discussions(array $discussionIds): array
{
    if (!$discussionIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($discussionIds), '?'));
    $map = [];
    foreach (DB::all(
        "SELECT dt.discussion_id, t.name, t.slug, t.color FROM discussion_tags dt
         JOIN tags t ON t.id = dt.tag_id WHERE dt.discussion_id IN ($placeholders) ORDER BY t.name COLLATE NOCASE",
        array_values($discussionIds)
    ) as $row) {
        $map[$row['discussion_id']][] = $row;
    }
    return $map;
}

/* ---------- Reading ---------- */

function discussion_url(array $d, array $query = []): string
{
    return url('/d/' . $d['id'] . '-' . $d['slug'], $query);
}

function find_discussion(int $id): ?array
{
    return DB::one(
        'SELECT d.*, c.name AS category_name, c.slug AS category_slug
         FROM discussions d JOIN categories c ON c.id = d.category_id
         WHERE d.id = ? AND d.is_hidden = 0',
        [$id]
    );
}

function page_discussion(string $id, string $slug = '', array $replyErrors = [], string $replyBody = ''): string
{
    $discussion = find_discussion((int) $id);
    if ($discussion === null) {
        not_found();
    }

    $total = (int) DB::value('SELECT COUNT(*) FROM posts WHERE discussion_id = ? AND is_hidden = 0', [$discussion['id']]);
    $pages = max(1, (int) ceil($total / POSTS_PER_PAGE));
    $page = min(current_page(), $pages);

    $posts = DB::all(
        'SELECT p.*, u.username, u.avatar, u.role
         FROM posts p LEFT JOIN users u ON u.id = p.user_id
         WHERE p.discussion_id = ? AND p.is_hidden = 0
         ORDER BY p.id LIMIT ' . POSTS_PER_PAGE . ' OFFSET ' . (($page - 1) * POSTS_PER_PAGE),
        [$discussion['id']]
    );

    $user = current_user();
    $blocked = posting_blocked_reason($user);
    if ($blocked === null && $discussion['is_locked'] && !is_moderator($user)) {
        $blocked = 'This discussion is locked, so new replies are turned off.';
    }

    // Unread: remember where this member got to, and mark where new posts begin.
    $firstUnreadId = null;
    if ($user !== null && $posts) {
        $readUpTo = last_read_post_id($user, (int) $discussion['id']);
        $startedBefore = $readUpTo >= (int) DB::value('SELECT MIN(id) FROM posts WHERE discussion_id = ?', [$discussion['id']]);
        foreach ($posts as $p) {
            if ($startedBefore && (int) $p['id'] > $readUpTo) {
                $firstUnreadId = (int) $p['id'];
                break;
            }
        }
        mark_read((int) $user['id'], (int) $discussion['id'], (int) end($posts)['id']);
    }

    // "Quote" without JavaScript: open the last page with the quote already in the reply box.
    if ($replyBody === '' && isset($_GET['quote']) && $blocked === null) {
        $replyBody = quote_text((int) $_GET['quote']) ?? '';
    }

    $isMod = is_moderator($user);
    $firstId = first_post_id((int) $discussion['id']);
    return view('discussion', [
        'firstUnreadId' => $firstUnreadId,
        'canEdit' => fn (array $post) => can_edit_post($user, $post, $discussion),
        'canDelete' => fn (array $post) => $isMod || (
            $user !== null && (int) $post['user_id'] === (int) $user['id'] && !is_banned($user) && !$discussion['is_locked']
            && ((int) $post['id'] !== $firstId || (int) $discussion['reply_count'] === 0)
        ),
        'canReport' => fn (array $post) => $user !== null && !is_banned($user) && !needs_email_confirmation($user) && (int) $post['user_id'] !== (int) $user['id'],
        'isMod' => $isMod,
        'categories' => $isMod ? DB::all('SELECT id, name FROM categories ORDER BY position, name') : [],
        'title' => $discussion['title'],
        'discussion' => $discussion,
        'tags' => tags_for_discussions([$discussion['id']])[$discussion['id']] ?? [],
        'posts' => $posts,
        'page' => $page,
        'pages' => $pages,
        'firstPostId' => (int) DB::value('SELECT MIN(id) FROM posts WHERE discussion_id = ?', [$discussion['id']]),
        'blocked' => $blocked,
        'user' => $user,
        'replyErrors' => $replyErrors,
        'replyBody' => $replyBody,
    ]);
}

/** /p/123 → the right page of the right discussion, scrolled to that post. */
function page_post_link(string $id): string
{
    $post = DB::one('SELECT id, discussion_id FROM posts WHERE id = ? AND is_hidden = 0', [(int) $id]);
    $discussion = $post ? find_discussion((int) $post['discussion_id']) : null;
    if ($discussion === null) {
        not_found();
    }
    $position = (int) DB::value(
        'SELECT COUNT(*) FROM posts WHERE discussion_id = ? AND is_hidden = 0 AND id <= ?',
        [$discussion['id'], $post['id']]
    );
    $page = (int) ceil($position / POSTS_PER_PAGE);
    header('Location: ' . discussion_url($discussion, $page > 1 ? ['page' => $page] : []) . '#p' . $post['id'], true, 303);
    exit;
}

/* ---------- Writing ---------- */

function validate_body(string $body, array &$errors, string $field = 'body'): void
{
    if (trim($body) === '') {
        $errors[$field] = 'Write something first.';
    } elseif (text_length($body) > BODY_MAX) {
        $errors[$field] = 'That\'s too long — please keep it under ' . number_format(BODY_MAX) . ' characters.';
    }
}

function posting_rate_ok(array $user): bool
{
    return is_moderator($user) || rate_limit('post', (string) $user['id'], 10, 5);
}

function page_compose(array $errors = [], array $old = []): string
{
    $user = require_login();
    $categories = DB::all('SELECT id, name, slug FROM categories ORDER BY position, name');
    $preselect = $old['category_id'] ?? null;
    if ($preselect === null && isset($_GET['category'])) {
        foreach ($categories as $c) {
            if ($c['slug'] === $_GET['category']) {
                $preselect = $c['id'];
            }
        }
    }
    return view('compose', [
        'title' => 'Start a discussion',
        'blocked' => posting_blocked_reason($user),
        'categories' => $categories,
        'allTags' => DB::all('SELECT name FROM tags ORDER BY name COLLATE NOCASE'),
        'canCreateTags' => can_create_tags($user),
        'errors' => $errors,
        'old' => $old + ['title' => '', 'tags' => '', 'body' => '', 'category_id' => $preselect],
        'tagsMax' => TAGS_MAX,
        'titleMax' => TITLE_MAX,
    ]);
}

function page_compose_submit(): string
{
    $user = require_login();
    if (posting_blocked_reason($user) !== null) {
        return page_compose();
    }

    $old = [
        'title' => preg_replace('/\s+/u', ' ', input('title')) ?? '',
        'category_id' => (int) input('category_id'),
        'tags' => input('tags'),
        'body' => (string) ($_POST['body'] ?? ''),
    ];
    $errors = [];
    $length = text_length($old['title']);
    if ($length < TITLE_MIN || $length > TITLE_MAX) {
        $errors['title'] = 'Use a title between ' . TITLE_MIN . ' and ' . TITLE_MAX . ' characters.';
    }
    if (!DB::value('SELECT 1 FROM categories WHERE id = ?', [$old['category_id']])) {
        $errors['category_id'] = 'Choose a category.';
    }
    [$tagIds, $newTags] = parse_tags($old['tags'], $user, $errors);
    validate_body($old['body'], $errors);
    if (!isset($errors['body']) && ($linkError = new_account_link_error($user, $old['body']))) {
        $errors['body'] = $linkError;
    }

    if (!$errors && !posting_rate_ok($user)) {
        $errors['general'] = 'You\'re posting very quickly. Please wait a few minutes and try again.';
    }
    if ($errors) {
        http_response_code(422);
        return page_compose($errors, $old);
    }

    $pending = post_needs_approval($user);
    $discussionId = DB::transaction(function () use ($user, $old, $tagIds, $newTags, $pending): int {
        $time = now();
        $hidden = $pending ? HIDDEN_PENDING : 0;
        $discussionId = DB::insert('discussions', [
            'category_id' => $old['category_id'],
            'user_id' => $user['id'],
            'title' => $old['title'],
            'slug' => slugify($old['title']),
            'is_hidden' => $hidden,
            'last_user_id' => $user['id'],
            'last_posted_at' => $time,
            'created_at' => $time,
        ]);
        $postId = DB::insert('posts', [
            'discussion_id' => $discussionId,
            'user_id' => $user['id'],
            'body' => $old['body'],
            'is_hidden' => $hidden,
            'created_at' => $time,
        ]);
        DB::query('UPDATE discussions SET last_post_id = ? WHERE id = ?', [$postId, $discussionId]);
        set_discussion_tags($discussionId, $tagIds, $newTags);
        mark_read((int) $user['id'], $discussionId, $postId);
        if (!$pending) {
            index_post($postId);
        }
        return $discussionId;
    });

    if ($pending) {
        flash('success', 'Thanks! Your first post is waiting for a moderator to approve it — it will appear soon.');
        redirect('/');
    }
    header('Location: ' . discussion_url(find_discussion($discussionId)), true, 303);
    exit;
}

function page_reply_submit(string $id): string
{
    $user = require_login();
    $discussion = find_discussion((int) $id);
    if ($discussion === null) {
        not_found();
    }
    $body = (string) ($_POST['body'] ?? '');
    $errors = [];

    $blocked = posting_blocked_reason($user);
    if ($blocked === null && $discussion['is_locked'] && !is_moderator($user)) {
        $blocked = 'This discussion is locked.';
    }
    if ($blocked !== null) {
        $errors['body'] = $blocked;
    } else {
        validate_body($body, $errors);
        if (!$errors && ($linkError = new_account_link_error($user, $body))) {
            $errors['body'] = $linkError;
        }
        if (!$errors && !posting_rate_ok($user)) {
            $errors['body'] = 'You\'re posting very quickly. Please wait a few minutes and try again.';
        }
    }
    if ($errors) {
        http_response_code(422);
        $_GET['page'] = PHP_INT_MAX; // show the last page, where the reply box is
        return page_discussion($id, '', $errors, $body);
    }

    if (post_needs_approval($user)) {
        DB::insert('posts', [
            'discussion_id' => $discussion['id'],
            'user_id' => $user['id'],
            'body' => $body,
            'is_hidden' => HIDDEN_PENDING,
            'created_at' => now(),
        ]);
        flash('success', 'Thanks! Your first post is waiting for a moderator to approve it — it will appear soon.');
        header('Location: ' . discussion_url($discussion), true, 303);
        exit;
    }

    $postId = DB::transaction(function () use ($user, $discussion, $body): int {
        $time = now();
        $postId = DB::insert('posts', [
            'discussion_id' => $discussion['id'],
            'user_id' => $user['id'],
            'body' => $body,
            'created_at' => $time,
        ]);
        DB::query(
            'UPDATE discussions SET reply_count = reply_count + 1, last_user_id = ?, last_posted_at = ?, last_post_id = ? WHERE id = ?',
            [$user['id'], $time, $postId, $discussion['id']]
        );
        mark_read((int) $user['id'], (int) $discussion['id'], $postId);
        index_post($postId);
        return $postId;
    });

    return page_post_link((string) $postId);
}

/* ---------- Reading comfort ---------- */

/** /d/5/unread → the first post this member hasn't read yet. */
function page_unread(string $id): string
{
    $discussion = find_discussion((int) $id);
    if ($discussion === null) {
        not_found();
    }
    $user = current_user();
    $target = null;
    if ($user !== null) {
        $target = DB::value(
            'SELECT MIN(id) FROM posts WHERE discussion_id = ? AND is_hidden = 0 AND id > ?',
            [$discussion['id'], last_read_post_id($user, (int) $discussion['id'])]
        );
    }
    $target ??= DB::value('SELECT MAX(id) FROM posts WHERE discussion_id = ? AND is_hidden = 0', [$discussion['id']]);
    return page_post_link((string) $target);
}

function page_mark_all_read(): string
{
    $user = require_login();
    DB::query('UPDATE users SET read_all_post_id = (SELECT COALESCE(MAX(id), 0) FROM posts) WHERE id = ?', [$user['id']]);
    DB::query('DELETE FROM discussion_reads WHERE user_id = ?', [$user['id']]);
    flash('success', 'Everything is marked as read.');
    redirect('/');
}

/** A post turned into Markdown quote lines, ready for the reply box. */
function quote_text(int $postId): ?string
{
    $post = DB::one(
        'SELECT p.id, p.body, u.username FROM posts p
         JOIN discussions d ON d.id = p.discussion_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ? AND p.is_hidden = 0 AND d.is_hidden = 0',
        [$postId]
    );
    if ($post === null) {
        return null;
    }
    // Drop quotes-inside-quotes so replies don't turn into towers.
    $lines = array_filter(explode("\n", str_replace("\r", '', $post['body'])), fn ($l) => !preg_match('/^\s*>/', $l));
    $body = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    $who = $post['username'] ? '**' . $post['username'] . '**' : 'Someone';
    $quoted = implode("\n", array_map(fn ($l) => '> ' . $l, explode("\n", $body)));
    return '> ' . $who . ' [wrote](' . url('/p/' . $post['id']) . "):\n" . $quoted . "\n\n";
}

/** Used by the Quote button's JavaScript. */
function page_quote(string $id): string
{
    $text = quote_text((int) $id);
    if ($text === null) {
        not_found();
    }
    header('Content-Type: text/plain; charset=utf-8');
    return $text;
}

/* ---------- Editing ---------- */

function can_edit_post(?array $user, array $post, array $discussion): bool
{
    if ($user === null || needs_email_confirmation($user) || is_banned($user)) {
        return false;
    }
    if (is_moderator($user)) {
        return true;
    }
    return (int) $post['user_id'] === (int) $user['id'] && !$discussion['is_locked'];
}

function find_post_for_edit(string $id): array
{
    $user = require_login();
    $post = DB::one('SELECT * FROM posts WHERE id = ? AND is_hidden = 0', [(int) $id]);
    $discussion = $post ? find_discussion((int) $post['discussion_id']) : null;
    if ($discussion === null) {
        not_found();
    }
    if (!can_edit_post($user, $post, $discussion)) {
        echo view('message', ['title' => 'You can\'t edit this', 'message' => 'Only the author (or a moderator) can edit a post, and not once its discussion is locked.'], 403);
        exit;
    }
    $isFirst = (int) $post['id'] === (int) DB::value('SELECT MIN(id) FROM posts WHERE discussion_id = ?', [$discussion['id']]);
    return [$user, $post, $discussion, $isFirst];
}

function page_edit_post(string $id, array $errors = [], ?array $old = null): string
{
    [$user, $post, $discussion, $isFirst] = find_post_for_edit($id);
    $tags = array_column(tags_for_discussions([$discussion['id']])[$discussion['id']] ?? [], 'name');
    return view('edit', [
        'title' => 'Edit post',
        'post' => $post,
        'discussion' => $discussion,
        'isFirst' => $isFirst,
        'categories' => DB::all('SELECT id, name FROM categories ORDER BY position, name'),
        'allTags' => DB::all('SELECT name FROM tags ORDER BY name COLLATE NOCASE'),
        'canCreateTags' => can_create_tags($user),
        'errors' => $errors,
        'old' => $old ?? [
            'title' => $discussion['title'],
            'category_id' => $discussion['category_id'],
            'tags' => implode(', ', $tags),
            'body' => $post['body'],
        ],
        'tagsMax' => TAGS_MAX,
        'titleMax' => TITLE_MAX,
    ]);
}

function page_edit_post_submit(string $id): string
{
    [$user, $post, $discussion, $isFirst] = find_post_for_edit($id);
    $old = [
        'title' => preg_replace('/\s+/u', ' ', input('title')) ?? '',
        'category_id' => (int) input('category_id'),
        'tags' => input('tags'),
        'body' => (string) ($_POST['body'] ?? ''),
    ];
    $errors = [];
    validate_body($old['body'], $errors);
    $tagIds = $newTags = [];
    if ($isFirst) {
        $length = text_length($old['title']);
        if ($length < TITLE_MIN || $length > TITLE_MAX) {
            $errors['title'] = 'Use a title between ' . TITLE_MIN . ' and ' . TITLE_MAX . ' characters.';
        }
        if (!DB::value('SELECT 1 FROM categories WHERE id = ?', [$old['category_id']])) {
            $errors['category_id'] = 'Choose a category.';
        }
        [$tagIds, $newTags] = parse_tags($old['tags'], $user, $errors);
    }
    if ($errors) {
        http_response_code(422);
        return page_edit_post($id, $errors, $old);
    }

    DB::transaction(function () use ($post, $discussion, $isFirst, $old, $tagIds, $newTags): void {
        if ($old['body'] !== $post['body']) {
            DB::query('UPDATE posts SET body = ?, edited_at = ? WHERE id = ?', [$old['body'], now(), $post['id']]);
        }
        if ($isFirst) {
            DB::query('UPDATE discussions SET title = ?, slug = ?, category_id = ? WHERE id = ?', [
                $old['title'], slugify($old['title']), $old['category_id'], $discussion['id'],
            ]);
            set_discussion_tags((int) $discussion['id'], $tagIds, $newTags);
            index_discussion((int) $discussion['id']);
        } else {
            index_post((int) $post['id']);
        }
    });

    flash('success', 'Your changes are saved.');
    return page_post_link($id);
}

/* ---------- Search ---------- */

const SEARCH_PER_PAGE = 20;

function page_search(): string
{
    $query = trim((string) ($_GET['q'] ?? ''));
    $categorySlug = (string) ($_GET['category'] ?? '');
    $tagSlug = (string) ($_GET['tag'] ?? '');
    $page = current_page();
    $terms = search_terms($query);

    $results = [];
    $total = 0;
    if ($terms) {
        $where = 'p.is_hidden = 0 AND d.is_hidden = 0';
        $params = [];
        if ($categorySlug !== '') {
            $where .= ' AND c.slug = ?';
            $params[] = $categorySlug;
        }
        if ($tagSlug !== '') {
            $where .= ' AND d.id IN (SELECT dt.discussion_id FROM discussion_tags dt JOIN tags t ON t.id = dt.tag_id WHERE t.slug = ?)';
            $params[] = $tagSlug;
        }
        $offset = ($page - 1) * SEARCH_PER_PAGE;
        $select = 'p.id, p.created_at, d.id AS discussion_id, d.title, d.slug, c.name AS category_name, c.slug AS category_slug, u.username, u.avatar';
        $joins = 'JOIN discussions d ON d.id = p.discussion_id JOIN categories c ON c.id = d.category_id LEFT JOIN users u ON u.id = p.user_id';

        if (search_uses_fts()) {
            // Each word is quoted, so search-syntax characters can't cause errors. The last word also matches as a prefix.
            $match = implode(' ', array_map(fn ($t) => '"' . str_replace('"', '""', $t) . '"', $terms)) . '*';
            $base = "FROM search_index s JOIN posts p ON p.id = s.rowid $joins WHERE search_index MATCH ? AND $where";
            $args = array_merge([$match], $params);
            $total = (int) DB::value("SELECT COUNT(*) $base", $args);
            $results = DB::all(
                "SELECT $select, snippet(search_index, 1, char(2), char(3), '…', 24) AS snippet $base
                 ORDER BY bm25(search_index, 4.0, 1.0) LIMIT " . SEARCH_PER_PAGE . " OFFSET $offset",
                $args
            );
        } else {
            $like = [];
            foreach ($terms as $t) {
                $like[] = "(p.body LIKE ? ESCAPE '\\' OR d.title LIKE ? ESCAPE '\\')";
                $pattern = '%' . addcslashes($t, '%_\\') . '%';
                array_push($params, $pattern, $pattern);
            }
            $base = "FROM posts p $joins WHERE $where AND " . implode(' AND ', $like);
            $total = (int) DB::value("SELECT COUNT(*) $base", $params);
            $results = DB::all("SELECT $select, p.body AS snippet $base ORDER BY p.id DESC LIMIT " . SEARCH_PER_PAGE . " OFFSET $offset", $params);
            foreach ($results as &$r) {
                $r['snippet'] = markdown_excerpt($r['snippet'], 220);
            }
            unset($r);
        }
    }

    return view('search', [
        'title' => $query !== '' ? 'Search: ' . $query : 'Search',
        'query' => $query,
        'terms' => $terms,
        'results' => $results,
        'total' => $total,
        'page' => $page,
        'pages' => max(1, (int) ceil($total / SEARCH_PER_PAGE)),
        'categories' => DB::all('SELECT name, slug FROM categories ORDER BY position, name'),
        'tags' => DB::all('SELECT name, slug FROM tags ORDER BY name COLLATE NOCASE'),
        'categorySlug' => $categorySlug,
        'tagSlug' => $tagSlug,
    ]);
}

/** Search snippet → safe HTML with matches highlighted. */
function search_snippet_html(string $snippet, array $terms): string
{
    if (str_contains($snippet, "\x02")) {
        $snippet = str_replace(["\r", "\n", '**', '__', '~~', '`'], [' ', ' ', '', '', '', ''], $snippet);
        $snippet = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $snippet);
        $snippet = preg_replace('/\s+/', ' ', $snippet);
        return str_replace(["\x02", "\x03"], ['<mark>', '</mark>'], e($snippet));
    }
    $html = e($snippet);
    foreach ($terms as $t) {
        $html = preg_replace('/(' . preg_quote(e($t), '/') . ')/iu', '<mark>$1</mark>', $html);
    }
    return $html;
}

/** Renders Markdown for the "Preview" button. */
function page_preview(): string
{
    require_login();
    header('Content-Type: text/html; charset=utf-8');
    $body = (string) ($_POST['body'] ?? '');
    if (trim($body) === '') {
        return '<p class="lede">Nothing to preview yet.</p>';
    }
    return markdown(mb_substr($body, 0, BODY_MAX));
}

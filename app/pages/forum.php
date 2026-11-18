<?php
declare(strict_types=1);
defined('AGORA') or exit;

const DISCUSSIONS_PER_PAGE = 25;

function all_categories(): array
{
    return DB::all(
        'SELECT c.*, (SELECT COUNT(*) FROM discussions d WHERE d.category_id = c.id AND d.is_hidden = 0) AS discussion_count
         FROM categories c ORDER BY c.position, c.name'
    );
}

/** Discussions for a list, newest activity first, pinned on top. Optionally within a category and/or tag. */
function discussion_list(?int $categoryId, ?int $tagId, int $page): array
{
    $where = 'd.is_hidden = 0';
    $params = [];
    if ($categoryId !== null) {
        $where .= ' AND d.category_id = ?';
        $params[] = $categoryId;
    }
    if ($tagId !== null) {
        $where .= ' AND d.id IN (SELECT discussion_id FROM discussion_tags WHERE tag_id = ?)';
        $params[] = $tagId;
    }
    $total = (int) DB::value("SELECT COUNT(*) FROM discussions d WHERE $where", $params);
    $pages = max(1, (int) ceil($total / DISCUSSIONS_PER_PAGE));
    $offset = (min($page, $pages) - 1) * DISCUSSIONS_PER_PAGE;

    $rows = DB::all(
        "SELECT d.*, c.name AS category_name, c.slug AS category_slug,
                author.username AS author_name, author.avatar AS author_avatar, last.username AS last_user_name
         FROM discussions d
         JOIN categories c ON c.id = d.category_id
         LEFT JOIN users author ON author.id = d.user_id
         LEFT JOIN users last ON last.id = d.last_user_id
         WHERE $where
         ORDER BY d.is_pinned DESC, d.last_posted_at DESC
         LIMIT " . DISCUSSIONS_PER_PAGE . " OFFSET $offset",
        $params
    );

    return [
        'discussions' => add_unread_info($rows, current_user()),
        'discussionTags' => tags_for_discussions(array_column($rows, 'id')),
        'total' => $total,
        'pages' => $pages,
    ];
}

function current_page(): int
{
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $page ?: 1;
}

/** The ?tag= filter, if it names a real tag. */
function tag_filter(): ?array
{
    $slug = $_GET['tag'] ?? null;
    return is_string($slug) && $slug !== '' ? DB::one('SELECT * FROM tags WHERE slug = ?', [$slug]) : null;
}

function page_home(): string
{
    $page = current_page();
    $tag = tag_filter();
    return view('discussions', [
        'title' => $tag ? $tag['name'] : null,
        'categories' => all_categories(),
        'category' => null,
        'tag' => $tag,
        'page' => $page,
    ] + discussion_list(null, $tag ? (int) $tag['id'] : null, $page));
}

function page_category(string $slug): string
{
    $category = DB::one('SELECT * FROM categories WHERE slug = ?', [$slug]);
    if ($category === null) {
        not_found();
    }
    $page = current_page();
    $tag = tag_filter();
    return view('discussions', [
        'title' => $category['name'],
        'categories' => all_categories(),
        'category' => $category,
        'tag' => $tag,
        'page' => $page,
    ] + discussion_list((int) $category['id'], $tag ? (int) $tag['id'] : null, $page));
}

/** /t/inking → all discussions with that tag. */
function page_tag(string $slug): string
{
    if (!DB::value('SELECT 1 FROM tags WHERE slug = ?', [$slug])) {
        not_found();
    }
    $_GET['tag'] = $slug;
    return page_home();
}

function page_tags(): string
{
    $tags = DB::all(
        'SELECT t.*, COUNT(d.id) AS discussion_count
         FROM tags t
         LEFT JOIN discussion_tags dt ON dt.tag_id = t.id
         LEFT JOIN discussions d ON d.id = dt.discussion_id AND d.is_hidden = 0
         GROUP BY t.id ORDER BY t.name COLLATE NOCASE'
    );
    return view('tags', ['title' => 'Tags', 'tags' => $tags]);
}

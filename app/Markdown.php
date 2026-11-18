<?php
declare(strict_types=1);
defined('AGORA') or exit;

/**
 * A small, safe Markdown renderer for posts.
 *
 * Safety rule: ALL text is HTML-escaped first; only the tags this file
 * creates ever reach the page. Links and images must be http(s), mailto,
 * or a path on this forum — "javascript:" and friends are never linked.
 *
 * Supports: paragraphs and line breaks, # headings, **bold**, *italic*,
 * ~~strike~~, `code`, ``` code blocks ```, > quotes, - lists, 1. lists,
 * [links](https://…), ![images](https://…), bare URLs, and --- rules.
 */
function markdown(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = str_replace("\t", '    ', $text);
    return markdown_blocks(explode("\n", $text), 0);
}

function markdown_blocks(array $lines, int $depth): string
{
    $html = '';
    $paragraph = [];
    $count = count($lines);

    $flush = function () use (&$paragraph, &$html): void {
        if ($paragraph) {
            $html .= '<p>' . implode("<br>\n", array_map('markdown_inline', $paragraph)) . "</p>\n";
            $paragraph = [];
        }
    };

    for ($i = 0; $i < $count; $i++) {
        $line = $lines[$i];

        // Fenced code block
        if (preg_match('/^\s*(```|~~~)\s*([\w+-]*)\s*$/', $line, $m)) {
            $flush();
            $fence = $m[1];
            $code = [];
            for ($i++; $i < $count && !preg_match('/^\s*' . preg_quote($fence, '/') . '\s*$/', $lines[$i]); $i++) {
                $code[] = $lines[$i];
            }
            $lang = $m[2] !== '' ? ' class="language-' . e($m[2]) . '"' : '';
            $html .= '<pre><code' . $lang . '>' . e(implode("\n", $code)) . "</code></pre>\n";
            continue;
        }

        // Blank line ends a paragraph
        if (trim($line) === '') {
            $flush();
            continue;
        }

        // Heading: # → h3, ## → h4, ### → h5 (keeps posts below the page title)
        if (preg_match('/^(#{1,3})\s+(.+?)\s*#*\s*$/', $line, $m)) {
            $flush();
            $level = strlen($m[1]) + 2;
            $html .= "<h$level>" . markdown_inline($m[2]) . "</h$level>\n";
            continue;
        }

        // Horizontal rule
        if (preg_match('/^\s*([-*_])(\s*\1){2,}\s*$/', $line)) {
            $flush();
            $html .= "<hr>\n";
            continue;
        }

        // Blockquote (can contain other Markdown; nesting limited)
        if (preg_match('/^\s*>/', $line)) {
            $flush();
            $quote = [];
            while ($i < $count && preg_match('/^\s*>\s?(.*)$/', $lines[$i], $m)) {
                $quote[] = $m[1];
                $i++;
            }
            $i--;
            $inner = $depth < 5 ? markdown_blocks($quote, $depth + 1) : '<p>' . markdown_inline(implode(' ', $quote)) . '</p>';
            $html .= "<blockquote>\n" . $inner . "</blockquote>\n";
            continue;
        }

        // Lists
        if (preg_match('/^\s*([-*+]|\d{1,9}[.)])\s+/', $line, $m)) {
            $flush();
            $ordered = !in_array($m[1], ['-', '*', '+'], true);
            $pattern = $ordered ? '/^\s*\d{1,9}[.)]\s+(.*)$/' : '/^\s*[-*+]\s+(.*)$/';
            $items = [];
            while ($i < $count) {
                if (preg_match($pattern, $lines[$i], $m)) {
                    $items[] = $m[1];
                } elseif ($items && trim($lines[$i]) !== '' && preg_match('/^\s{2,}\S/', $lines[$i])) {
                    $items[count($items) - 1] .= "\n" . trim($lines[$i]); // indented continuation
                } else {
                    break;
                }
                $i++;
            }
            $i--;
            $tag = $ordered ? 'ol' : 'ul';
            $html .= "<$tag>\n";
            foreach ($items as $item) {
                $html .= '<li>' . implode("<br>\n", array_map('markdown_inline', explode("\n", $item))) . "</li>\n";
            }
            $html .= "</$tag>\n";
            continue;
        }

        $paragraph[] = $line;
    }
    $flush();
    return $html;
}

/** Is this (already HTML-escaped) address safe to link to? */
function markdown_safe_url(string $url): bool
{
    return (bool) preg_match('#^(https?://[^\s]+|mailto:[^\s]+|/[^/\s][^\s]*|/)$#i', $url);
}

function markdown_inline(string $text): string
{
    $text = e($text);
    $saved = [];
    $keep = function (string $html) use (&$saved): string {
        $saved[] = $html;
        return "\x1A" . (count($saved) - 1) . "\x1A";
    };

    // `inline code` — nothing inside is formatted
    $text = preg_replace_callback('/(`+)(.+?)\1/', fn ($m) => $keep('<code>' . trim($m[2]) . '</code>'), $text);

    // ![alt](url)
    $text = preg_replace_callback('/!\[([^\]]*)\]\(\s*([^\s)]+)\s*\)/', function ($m) use ($keep) {
        if (!preg_match('#^https?://#i', $m[2]) && !str_starts_with($m[2], '/')) {
            return $m[0];
        }
        return $keep('<img src="' . $m[2] . '" alt="' . $m[1] . '" loading="lazy">');
    }, $text);

    // [text](url)
    $text = preg_replace_callback('/\[([^\]]+)\]\(\s*([^\s)]+)\s*\)/', function ($m) use ($keep) {
        if (!markdown_safe_url($m[2])) {
            return $m[0];
        }
        return $keep('<a href="' . $m[2] . '" rel="nofollow ugc noopener">') . $m[1] . $keep('</a>');
    }, $text);

    // Bare web addresses
    $text = preg_replace_callback('#(?<![\w/"=])(https?://[^\s<\x1A]+[^\s<\x1A.,;:!?\)\]\'"])#i', function ($m) use ($keep) {
        return $keep('<a href="' . $m[1] . '" rel="nofollow ugc noopener">' . $m[1] . '</a>');
    }, $text);

    // Emphasis
    $text = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<![\w])__(?=\S)(.+?)(?<=\S)__(?![\w])/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<![*\w])\*(?=\S)(.+?)(?<=\S)\*(?![*\w])/s', '<em>$1</em>', $text);
    $text = preg_replace('/(?<![\w])_(?=\S)(.+?)(?<=\S)_(?![\w])/s', '<em>$1</em>', $text);
    $text = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/s', '<del>$1</del>', $text);

    // Put saved pieces back (repeat: link text may contain saved code)
    for ($round = 0; $round < 3 && str_contains($text, "\x1A"); $round++) {
        $text = preg_replace_callback("/\x1A(\d+)\x1A/", fn ($m) => $saved[(int) $m[1]] ?? '', $text);
    }
    return $text;
}

/** Plain-text summary of a post, for lists and profiles. */
function markdown_excerpt(string $text, int $length = 200): string
{
    $text = preg_replace('/```.*?(```|$)/s', ' ', $text);
    $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $text);
    $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text);
    $text = preg_replace('/^\s*(?:>|#{1,3}\s|[-*+]\s|\d+[.)]\s)\s*/m', '', $text);
    $text = str_replace(['**', '__', '~~', '`'], '', $text);
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return mb_strimwidth($text, 0, $length, '…', 'UTF-8');
}

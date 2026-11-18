<?php
declare(strict_types=1);
defined('AGORA') or exit;

/**
 * Themes.
 *
 * A theme is ONE .css file with a header comment:
 *
 *   /* Agora theme
 *      Name: Botanical
 *      Author: Someone
 *      Description: Sage greens and terracotta.
 *   *\/
 *
 * Bundled themes live in themes/, uploaded ones in data/themes/.
 * The chosen theme, the admin's color tweaks, tag colors and custom CSS are
 * combined into one stylesheet served at /theme.css.
 */

/** The colors admins can tweak, per light/dark mode. Everything else is worked out from these. */
const THEME_COLOR_KEYS = ['bg' => 'Background', 'surface' => 'Cards & header', 'text' => 'Text', 'accent' => 'Accent'];

function theme_dirs(): array
{
    return ['bundled' => AGORA_ROOT . '/themes', 'uploaded' => AGORA_DATA . '/themes'];
}

/** All installed themes, keyed by slug. */
function available_themes(): array
{
    $themes = [];
    foreach (theme_dirs() as $source => $dir) {
        foreach (glob($dir . '/*.css') ?: [] as $file) {
            $slug = basename($file, '.css');
            if (!preg_match('/^[a-z0-9-]{1,40}$/', $slug) || isset($themes[$slug])) {
                continue;
            }
            $css = (string) file_get_contents($file);
            $meta = parse_theme_header($css);
            $themes[$slug] = $meta + [
                'slug' => $slug,
                'source' => $source,
                'file' => $file,
                'colors' => theme_colors($css),
            ];
        }
    }
    uasort($themes, fn ($a, $b) => [$a['slug'] !== 'agora', $a['name']] <=> [$b['slug'] !== 'agora', $b['name']]);
    return $themes;
}

function parse_theme_header(string $css): array
{
    $meta = ['name' => null, 'author' => '', 'description' => ''];
    if (preg_match('#^\s*/\*(.*?)\*/#s', $css, $m)) {
        foreach (['name', 'author', 'description'] as $key) {
            if (preg_match('/^\s*\*?\s*' . $key . '\s*:\s*(.+)$/mi', $m[1], $v)) {
                $meta[$key] = mb_substr(trim($v[1]), 0, 120);
            }
        }
    }
    return $meta;
}

/** Read the main color variables out of a theme's :root and dark blocks. */
function theme_colors(string $css): array
{
    $colors = ['light' => [], 'dark' => []];
    $blocks = [
        'light' => '/:root\s*\{([^}]*)\}/',
        'dark' => '/:root\[data-theme=["\']?dark["\']?\]\s*\{([^}]*)\}/',
    ];
    foreach ($blocks as $mode => $pattern) {
        if (preg_match($pattern, $css, $m)) {
            preg_match_all('/--([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{6})\b/', $m[1], $vars, PREG_SET_ORDER);
            foreach ($vars as [, $name, $value]) {
                $colors[$mode][$name] = strtolower($value);
            }
        }
    }
    return $colors;
}

function current_theme(): array
{
    $themes = available_themes();
    return $themes[setting('theme', 'agora')] ?? $themes['agora'] ?? ['slug' => 'agora', 'file' => null, 'colors' => ['light' => [], 'dark' => []]];
}

/** The admin's color tweaks: ['light' => ['accent' => '#…'], 'dark' => [...]] (may be empty). */
function theme_color_overrides(): array
{
    $saved = json_decode((string) setting('theme_colors', ''), true);
    return is_array($saved) ? $saved : [];
}

/** Default colors (the ones in assets/agora.css), used when a theme doesn't set them. */
function default_theme_colors(): array
{
    return [
        'light' => ['bg' => '#f7f5f2', 'surface' => '#ffffff', 'text' => '#22201d', 'accent' => '#b5532c'],
        'dark' => ['bg' => '#171614', 'surface' => '#211f1c', 'text' => '#ece8e2', 'accent' => '#e8875f'],
    ];
}

/** The 4 main colors actually in effect for a mode (theme + admin tweaks). */
function effective_colors(string $mode): array
{
    $theme = current_theme()['colors'][$mode] ?? [];
    $base = array_intersect_key($theme, THEME_COLOR_KEYS) + default_theme_colors()[$mode];
    return array_merge($base, array_intersect_key(theme_color_overrides()[$mode] ?? [], THEME_COLOR_KEYS));
}

/* ---------- Color math ---------- */

function hex_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function rgb_hex(array $rgb): string
{
    return '#' . implode('', array_map(fn ($c) => str_pad(dechex((int) round(max(0, min(255, $c)))), 2, '0', STR_PAD_LEFT), $rgb));
}

/** Blend two colors: $amount 0 = all $a, 1 = all $b. */
function mix_colors(string $a, string $b, float $amount): string
{
    $x = hex_rgb($a);
    $y = hex_rgb($b);
    return rgb_hex([$x[0] + ($y[0] - $x[0]) * $amount, $x[1] + ($y[1] - $x[1]) * $amount, $x[2] + ($y[2] - $x[2]) * $amount]);
}

function luminance(string $hex): float
{
    $c = array_map(function ($v) {
        $v /= 255;
        return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    }, hex_rgb($hex));
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function contrast_ratio(string $a, string $b): float
{
    $la = luminance($a);
    $lb = luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

function is_hex_color(mixed $value): bool
{
    return is_string($value) && (bool) preg_match('/^#[0-9a-f]{6}$/i', $value);
}

/** Work out the full set of color variables from the 4 main ones. */
function palette_vars(array $c, bool $dark): array
{
    $readableOnAccent = contrast_ratio($c['accent'], '#ffffff') >= contrast_ratio($c['accent'], '#111111') ? '#ffffff' : '#111111';
    // Light accent fill for badges: tint the background a little, but keep accent text on it readable (4.5:1).
    $soft = $c['bg'];
    foreach ([0.18, 0.14, 0.10, 0.07, 0.04] as $amount) {
        $candidate = mix_colors($c['bg'], $c['accent'], $dark ? $amount : $amount * 0.66);
        if (contrast_ratio($c['accent'], $candidate) >= 4.5) {
            $soft = $candidate;
            break;
        }
    }
    return [
        'bg' => $c['bg'],
        'surface' => $c['surface'],
        'surface-2' => mix_colors($c['surface'], $c['text'], 0.06),
        'text' => $c['text'],
        'text-muted' => mix_colors($c['text'], $c['bg'], 0.38),
        'border' => mix_colors($c['surface'], $c['text'], 0.14),
        'accent' => $c['accent'],
        'accent-hover' => mix_colors($c['accent'], $dark ? '#ffffff' : '#000000', 0.14),
        'accent-text' => $readableOnAccent,
        'accent-soft' => $soft,
    ];
}

/* ---------- The combined stylesheet ---------- */

function theme_css(): string
{
    $theme = current_theme();
    $css = "/* Agora theme stylesheet — generated */\n\n";
    if (!empty($theme['file']) && is_file($theme['file'])) {
        $css .= file_get_contents($theme['file']) . "\n\n";
    }

    $overrides = theme_color_overrides();
    foreach (['light' => ':root', 'dark' => ':root[data-theme="dark"]'] as $mode => $selector) {
        if (!empty($overrides[$mode])) {
            $css .= "/* Color tweaks from the admin panel ($mode) */\n$selector {\n";
            foreach (palette_vars(effective_colors($mode), $mode === 'dark') as $name => $value) {
                $css .= "  --$name: $value;\n";
            }
            $css .= "}\n\n";
        }
    }

    $tagCss = '';
    foreach (DB::all("SELECT slug, color FROM tags WHERE color IS NOT NULL AND color != ''") as $tag) {
        if (is_hex_color($tag['color']) && preg_match('/^[a-z0-9-]+$/', $tag['slug'])) {
            $tagCss .= '.tag[data-tag-slug="' . $tag['slug'] . '"]::before { content: ""; display: inline-block; width: .6em; height: .6em; border-radius: 50%; background: ' . $tag['color'] . "; }\n";
        }
    }
    if ($tagCss !== '') {
        $css .= "/* Tag colors */\n" . $tagCss . "\n";
    }

    $custom = trim((string) setting('custom_css', ''));
    if ($custom !== '') {
        $css .= "/* Custom CSS from the admin panel */\n" . $custom . "\n";
    }
    return $css;
}

/** Changes whenever anything in theme_css() could change, so browsers fetch the new version. */
function theme_css_version(): string
{
    $theme = current_theme();
    $mtime = !empty($theme['file']) && is_file($theme['file']) ? filemtime($theme['file']) : 0;
    return substr(md5(setting('appearance_version', '1') . '|' . ($theme['slug'] ?? '') . '|' . $mtime), 0, 10);
}

/** Call after changing anything that affects theme_css(). */
function bump_appearance_version(): void
{
    save_settings(['appearance_version' => (string) ((int) setting('appearance_version', '1') + 1)]);
}

function page_theme_css(): string
{
    header('Content-Type: text/css; charset=utf-8');
    header('Cache-Control: ' . (($_GET['v'] ?? '') === theme_css_version() ? 'public, max-age=31536000, immutable' : 'no-cache'));
    return theme_css();
}

/* ---------- Logo ---------- */

function logo_url(): ?string
{
    $logo = setting('logo', '');
    return $logo && preg_match('/^logo-[a-f0-9]+\.(png|webp)$/', $logo) ? url('/logo/' . $logo) : null;
}

function page_logo(string $file): string
{
    $path = AGORA_DATA . '/' . $file;
    if (!preg_match('/^logo-[a-f0-9]+\.(png|webp)$/', $file) || !is_file($path)) {
        not_found();
    }
    header('Content-Type: ' . (str_ends_with($file, '.webp') ? 'image/webp' : 'image/png'));
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($path);
    exit;
}

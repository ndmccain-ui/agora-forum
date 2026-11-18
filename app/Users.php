<?php
declare(strict_types=1);
defined('AGORA') or exit;

const AVATAR_SIZE = 256;
const AVATAR_MAX_BYTES = 4 * 1024 * 1024;

function avatar_dir(): string
{
    return AGORA_DATA . '/avatars';
}

/** A user's picture, or their initial in a circle. $user needs 'username' and 'avatar'. */
function avatar_html(?array $user, string $class = ''): string
{
    $class = trim('avatar ' . $class);
    $name = $user['username'] ?? '?';
    $file = $user['avatar'] ?? null;
    if ($file) {
        return '<img class="' . e($class) . '" src="' . e(url('/avatar/' . $file)) . '" alt="" loading="lazy">';
    }
    return '<span class="' . e($class) . '" aria-hidden="true">' . e(mb_strtoupper(mb_substr($name, 0, 1))) . '</span>';
}

/**
 * Save an uploaded profile picture. The image is re-drawn at 256×256,
 * which strips hidden data (like GPS location) and anything that isn't a real image.
 * Returns the new file name, or throws with a friendly message.
 */
function save_avatar_upload(array $upload, int $userId): string
{
    if (!function_exists('imagecreatetruecolor')) {
        throw new InvalidArgumentException('Profile pictures need the PHP GD extension, which this server doesn\'t have.');
    }
    $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('Choose a picture to upload.');
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || ($upload['size'] ?? 0) > AVATAR_MAX_BYTES) {
        throw new InvalidArgumentException('That picture is too big. Please use one under 4 MB.');
    }
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        throw new InvalidArgumentException('The upload didn\'t work. Please try again.');
    }

    $info = @getimagesize($upload['tmp_name']);
    $loaders = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_GIF => 'imagecreatefromgif',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];
    $loader = $info ? ($loaders[$info[2]] ?? null) : null;
    if (!$loader || !function_exists($loader)) {
        throw new InvalidArgumentException('Please upload a JPG, PNG, GIF or WebP picture.');
    }
    if ($info[0] * $info[1] > 40_000_000) {
        throw new InvalidArgumentException('That picture is too large. Please use a smaller one.');
    }

    $source = @$loader($upload['tmp_name']);
    if (!$source) {
        throw new InvalidArgumentException('That picture couldn\'t be read. Please try another.');
    }

    // Crop to a centered square, then scale.
    $w = imagesx($source);
    $h = imagesy($source);
    $side = min($w, $h);
    $avatar = imagecreatetruecolor(AVATAR_SIZE, AVATAR_SIZE);
    imagealphablending($avatar, false);
    imagesavealpha($avatar, true);
    imagefill($avatar, 0, 0, imagecolorallocatealpha($avatar, 0, 0, 0, 127));
    imagecopyresampled($avatar, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), AVATAR_SIZE, AVATAR_SIZE, $side, $side);

    if (!is_dir(avatar_dir()) && !mkdir(avatar_dir(), 0755, true)) {
        throw new RuntimeException('Could not create the avatars folder.');
    }
    $useWebp = function_exists('imagewebp');
    $name = $userId . '-' . bin2hex(random_bytes(6)) . ($useWebp ? '.webp' : '.png');
    $path = avatar_dir() . '/' . $name;
    $saved = $useWebp ? imagewebp($avatar, $path, 85) : imagepng($avatar, $path, 9);
    if (!$saved) {
        throw new RuntimeException('Could not save the picture.');
    }
    return $name;
}

function delete_avatar_file(?string $name): void
{
    if ($name && preg_match('/^[0-9]+-[a-f0-9]+\.(webp|png)$/', $name)) {
        @unlink(avatar_dir() . '/' . $name);
    }
}

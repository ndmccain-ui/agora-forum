<?php
/**
 * Agora Forum — front controller.
 * Every page request comes through this file.
 */

// Keep this check in old-PHP-compatible syntax so hosts on old PHP get a clear message.
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'Agora Forum needs PHP 8.1 or newer. This server is running PHP ' . PHP_VERSION . '.';
    exit;
}

define('AGORA', true);
define('AGORA_ROOT', __DIR__);

// PHP's built-in development server: serve static assets directly, block private folders.
if (PHP_SAPI === 'cli-server') {
    $agoraPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if (preg_match('#^/(app|data|templates|docs)(/|$)#', $agoraPath) || preg_match('#/\.#', $agoraPath)) {
        http_response_code(403);
        echo 'Forbidden';
        return true;
    }
    if (str_starts_with($agoraPath, '/assets/') && is_file(__DIR__ . $agoraPath)) {
        return false;
    }
}

require __DIR__ . '/app/bootstrap.php';

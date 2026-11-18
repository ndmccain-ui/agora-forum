<?php
declare(strict_types=1);
defined('AGORA') or exit;

const AGORA_VERSION = '0.9.0';
define('AGORA_DATA', AGORA_ROOT . '/data');
define('AGORA_DEBUG', PHP_SAPI === 'cli-server' || getenv('AGORA_DEBUG') === '1');

require AGORA_ROOT . '/app/helpers.php';
require AGORA_ROOT . '/app/Router.php';
require AGORA_ROOT . '/app/Database.php';
require AGORA_ROOT . '/app/Auth.php';
require AGORA_ROOT . '/app/Users.php';
require AGORA_ROOT . '/app/Mailer.php';
require AGORA_ROOT . '/app/Markdown.php';
require AGORA_ROOT . '/app/Reading.php';
require AGORA_ROOT . '/app/Theme.php';

ini_set('display_errors', AGORA_DEBUG ? '1' : '0');
error_reporting(E_ALL);

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e): void {
    error_log('[Agora] ' . $e);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $detail = AGORA_DEBUG ? $e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine() : null;
    try {
        echo view('message', [
            'title' => 'Something went wrong',
            'message' => 'The forum hit an unexpected error. Please try again in a moment.',
            'detail' => $detail,
        ], 500);
    } catch (Throwable) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Something went wrong.\n" . ($detail ?? '');
    }
});

send_security_headers();
start_session();

if (is_installed()) {
    DB::connect(AGORA_DATA . '/' . config('database'));
    DB::migrate();
    current_user(); // sign in from a "remember me" cookie before any output
}

$router = new Router();
require AGORA_ROOT . '/app/routes.php';

$path = request_path();
$installing = $path === '/install';

if (!is_installed() && !$installing) {
    redirect('/install');
}
if (is_installed() && $installing) {
    not_found();
}

echo $router->dispatch(request_method(), $path);

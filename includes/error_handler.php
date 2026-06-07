<?php
require_once __DIR__ . '/env.php';

$isProduction = (getenv('APP_ENV') === 'production');

ini_set('display_errors',         $isProduction ? '0' : '1');
ini_set('display_startup_errors', $isProduction ? '0' : '1');
error_reporting(E_ALL);

$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}
ini_set('log_errors',  '1');
ini_set('error_log',   $logDir . '/php_errors.log');

if (!$isProduction) {
    return; // Development: let PHP show errors normally
}

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    error_log(sprintf('[%s] Error [%d]: %s in %s:%d', date('Y-m-d H:i:s'), $errno, $errstr, $errfile, $errline));
    return true;
});

set_exception_handler(function (Throwable $e): void {
    error_log(sprintf(
        '[%s] Uncaught %s: %s in %s:%d',
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    if (!headers_sent()) {
        http_response_code(500);
    }
    $page500 = dirname(__DIR__) . '/errors/500.php';
    if (file_exists($page500)) {
        include $page500;
    } else {
        echo '<p style="font-family:sans-serif;text-align:center;margin-top:10vh">Щось пішло не так. Спробуйте пізніше.</p>';
    }
    exit;
});

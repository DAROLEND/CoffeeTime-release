<?php
require_once __DIR__ . '/env.php';

define('LIQPAY_PUBLIC_KEY',  getenv('LIQPAY_PUBLIC_KEY')  ?: '');
define('LIQPAY_PRIVATE_KEY', getenv('LIQPAY_PRIVATE_KEY') ?: '');
define('LIQPAY_SANDBOX', (int)(getenv('LIQPAY_SANDBOX') !== false ? getenv('LIQPAY_SANDBOX') : 1));

if (getenv('APP_URL')) {
    define('SITE_URL', rtrim(getenv('APP_URL'), '/'));
} else {
    $__proto = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $__proto = $_SERVER['HTTP_X_FORWARDED_PROTO'];
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $__proto = 'https';
    }
    $__host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('SITE_URL', $__proto . '://' . $__host . '/CoffeeTime-release');
    unset($__proto, $__host);
}

// Root-relative asset path — works on localhost, ngrok, any domain without changes
$__docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$__projRoot = rtrim(dirname(__DIR__), '/');
$__basePath = $__docRoot ? '/' . ltrim(str_replace($__docRoot, '', $__projRoot), '/') : '/CoffeeTime-release';
define('SITE_PATH', rtrim($__basePath, '/'));
unset($__docRoot, $__projRoot, $__basePath);

define('APP_ENV', getenv('APP_ENV') ?: 'production');

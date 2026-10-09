<?php
// ---------------------------------------------------------------
// Settings. On XAMPP the defaults below already work
// (user "root", empty password). On a web host or on Render you can
// either edit the values here or set them as environment variables.
// ---------------------------------------------------------------
function env_or(string $key, string $default): string {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

define('DB_HOST', env_or('DB_HOST', 'localhost'));
define('DB_PORT', env_or('DB_PORT', '3306'));
define('DB_NAME', env_or('DB_NAME', 'brand_shop'));
define('DB_USER', env_or('DB_USER', 'root'));
define('DB_PASS', env_or('DB_PASS', ''));
define('DB_SSL',  env_or('DB_SSL', '0') === '1');          // set to 1 for cloud databases that require an encrypted connection
// Change '0' to '1' only if you must run install.php again (for example to reset the admin password). Change it back afterwards.
define('ALLOW_REINSTALL', env_or('ALLOW_REINSTALL', '0') === '1');

function db_dsn(bool $withName = true): string {
    return 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ($withName ? ';dbname=' . DB_NAME : '') . ';charset=utf8mb4';
}
function db_pdo_options(): array {
    $o = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (DB_SSL) {
        $ca = env_or('DB_SSL_CA', '/etc/ssl/certs/ca-certificates.crt');
        if (is_file($ca)) $o[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        $o[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = env_or('DB_SSL_VERIFY', '0') === '1';
    }
    return $o;
}

// Web path of this folder, worked out automatically
// (e.g. "/onlineshopnow" on XAMPP, or "" when the shop is at the top of a domain).
$__doc  = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$__here = realpath(__DIR__);
$__base = '';
if ($__doc && $__here) {
    $__doc  = str_replace('\\', '/', $__doc);
    $__here = str_replace('\\', '/', $__here);
    if (stripos($__here, $__doc) === 0) {
        $__base = substr($__here, strlen($__doc));
    }
}
define('BASE_URL', rtrim($__base, '/'));

date_default_timezone_set('Africa/Nairobi');


<?php

if (!defined('RS_ROOT')) {
    define('RS_ROOT', dirname(__DIR__));
}

if (!defined('RS_BASE')) {

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $projectRoot = realpath(RS_ROOT);

    $documentRoot = str_replace('\\', '/', $documentRoot ?: '');
    $projectRoot = str_replace('\\', '/', $projectRoot ?: '');

    $basePath = '/';

    if (
        $documentRoot !== '' &&
        $projectRoot !== '' &&
        strpos($projectRoot, $documentRoot) === 0
    ) {
        $relative = substr($projectRoot, strlen($documentRoot));
        $basePath = rtrim($relative, '/') . '/';

        if ($basePath === '') {
            $basePath = '/';
        }
    }

    define('RS_BASE', $basePath);
}

if (!defined('RS_DB_HOST')) {
    define('RS_DB_HOST', 'localhost');
    define('RS_DB_NAME', 'recursoft');
    define('RS_DB_USER', 'root');
    define('RS_DB_PASS', '');
    define('RS_DB_CHARSET', 'utf8mb4');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => RS_BASE,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function rs_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . RS_DB_HOST .
           ';dbname=' . RS_DB_NAME .
           ';charset=' . RS_DB_CHARSET;

    try {

        $pdo = new PDO(
            $dsn,
            RS_DB_USER,
            RS_DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

    } catch (PDOException $e) {

        error_log('Error de conexión RecurSoft: ' . $e->getMessage());

        http_response_code(500);

        exit('No fue posible conectar con la base de datos. Verifique que MySQL esté activo.');
    }

    return $pdo;
}

$conexion = rs_pdo();


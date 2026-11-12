
<?php
/**
 * RecurSoft - Autenticación y control de acceso.
 */

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/validacion.php';

/**
 * Obtener los datos del usuario autenticado.
 */
function rs_usuario_sesion(): ?array
{
    return isset($_SESSION['rs_user']) && is_array($_SESSION['rs_user'])
        ? $_SESSION['rs_user']
        : null;
}

/**
 * Crear la sesión del usuario.
 */
function rs_login_usuario(array $usuario): void
{
    session_regenerate_id(true);
    unset($_SESSION['rs_csrf']);

    $_SESSION['rs_user'] = [
        'id_cuenta' => (int) $usuario['id_cuenta'],
        'id_administrador' => !empty($usuario['id_administrador'])
            ? (int) $usuario['id_administrador']
            : null,
        'id_empleado' => !empty($usuario['id_empleado'])
            ? (int) $usuario['id_empleado']
            : null,
        'id_rol' => (int) $usuario['id_rol'],
        'nombre_rol' => $usuario['nombre_rol'] ?? '',
        'nombre' => $usuario['nombre'] ?? '',
        'apellido' => $usuario['apellido'] ?? '',
        'correo' => $usuario['correo'] ?? ''
    ];
}

/**
 * Cerrar sesión.
 */
function rs_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]
        );
    }

    session_destroy();
}

/**
 * Comprobar si es administrador.
 */
function rs_es_admin(?array $user = null): bool
{
    $user = $user ?? rs_usuario_sesion();

    return $user !== null
        && (int) ($user['id_rol'] ?? 0) === 1;
}

/**
 * Comprobar si está autenticado.
 */
function rs_requiere_login(): array
{
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('Expires: 0');

    $user = rs_usuario_sesion();

    if (!$user || empty($user['id_cuenta'])) {
        rs_flash('error', 'Debe iniciar sesión para continuar.');
        rs_redirect('login.php');
    }

    $account = rs_one(
        "SELECT
            ca.id_cuenta,
            ca.id_empleado,
            ca.id_rol,
            ca.estado AS estado_cuenta,
            a.id_administrador,
            a.estado AS estado_administrador,
            e.estado AS estado_empleado
         FROM cuenta_acceso ca
         LEFT JOIN administrador a ON a.id_cuenta = ca.id_cuenta
         LEFT JOIN empleado e ON e.id_empleado = ca.id_empleado
         WHERE ca.id_cuenta = :id_cuenta
         LIMIT 1",
        ['id_cuenta' => (int) $user['id_cuenta']]
    );

    $role = (int) ($account['id_rol'] ?? 0);
    $accountActive = strcasecmp(
        trim((string) ($account['estado_cuenta'] ?? '')),
        'Activo'
    ) === 0;
    $roleValid = $role === (int) ($user['id_rol'] ?? 0)
        && (($role === 1
                && !empty($account['id_administrador'])
                && empty($account['id_empleado'])
                && strcasecmp(trim((string) $account['estado_administrador']), 'Activo') === 0)
            || ($role === 2
                && !empty($account['id_empleado'])
                && empty($account['id_administrador'])
                && strcasecmp(trim((string) $account['estado_empleado']), 'Activo') === 0));

    if (!$account || !$accountActive || !$roleValid) {
        rs_logout();
        rs_redirect('login.php');
    }

    return $user;
}

/**
 * Acceso exclusivo para administradores.
 */
function rs_requiere_admin(): array
{
    $user = rs_requiere_login();

    if ((int) ($user['id_rol'] ?? 0) !== 1) {
        rs_flash('error', 'No tienes permisos para acceder a esta sección.');
        rs_redirect('dashboardEM/index.php');
    }

    return $user;
}
/**
 * Acceso exclusivo para empleados.
 */
function rs_requiere_empleado(): array
{
    $user = rs_requiere_login();

    if (
        (int) ($user['id_rol'] ?? 0) !== 2 ||
        empty($user['id_empleado'])
    ) {
        rs_flash('error', 'No tiene permisos para acceder a esta sección.');
        rs_redirect('dashboardAD/index.php');
    }

    return $user;
}

/**
 * Datos para la barra de navegación.
 */
function rs_aplicar_navbar_usuario(): void
{
    $user = rs_usuario_sesion();

    if (!$user) {
        return;
    }

    $GLOBALS['navbarUserName'] = trim(
        ($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')
    );

    $GLOBALS['navbarUserRole'] = $user['nombre_rol'] ?? '';
}

/**
 * Crear hash de contraseña.
 */
function rs_password_hash_safe(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

/**
 * Verificar contraseña.
 */
function rs_password_verify_safe(string $plain, string $hash): bool
{
    return $hash !== '' && password_verify($plain, $hash);
}


<?php
/**
 * RecurSoft - Controlador de autenticación.
 */

require_once __DIR__ . '/../includes/auth.php';

$conexion = rs_pdo();

/**
 * INICIAR SESIÓN
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'login'
) {
    $identificador = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identificador === '' || $password === '') {
        rs_flash('error', 'Ingrese su correo o nombre de usuario y contraseña.');
        rs_redirect('login.php');
    }

    if (!rs_csrf_ok()) {
        rs_flash('error', 'La sesión del formulario expiró. Vuelva a intentar.');
        rs_redirect('login.php');
    }

    try {
        $columnaUsuarioDisponible = (int) rs_pdo()->query("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'cuenta_acceso'
              AND COLUMN_NAME = 'nombre_usuario'
        ")->fetchColumn() > 0;

        $condicionIdentificador = $columnaUsuarioDisponible
            ? '(ca.correo = :correo OR ca.nombre_usuario = :nombre_usuario)'
            : 'ca.correo = :correo';

        $parametrosIdentificador = ['correo' => $identificador];
        if ($columnaUsuarioDisponible) {
            $parametrosIdentificador['nombre_usuario'] = $identificador;
        }

        $sqlAdministrador = "
            SELECT
                ca.id_cuenta,
                ca.id_empleado,
                ca.id_rol,
                ca.correo,
                ca.password,
                ca.estado AS estado_cuenta,

                r.nombre_rol,

                a.id_administrador,
                a.estado AS estado_administrador,

                a.nombre,
                a.apellido

            FROM cuenta_acceso ca

            INNER JOIN rol r
                ON r.id_rol = ca.id_rol

            INNER JOIN administrador a
                ON a.id_cuenta = ca.id_cuenta

            WHERE LOWER(r.nombre_rol) = 'administrador'
              AND {$condicionIdentificador}
            LIMIT 1
        ";

        $stmt = $conexion->prepare($sqlAdministrador);
        $stmt->execute($parametrosIdentificador);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $sqlEmpleado = "
                SELECT
                    ca.id_cuenta,
                    ca.id_empleado,
                    ca.id_rol,
                    ca.correo,
                    ca.password,
                    ca.estado AS estado_cuenta,
                    r.nombre_rol,
                    NULL AS id_administrador,
                    e.estado AS estado_empleado,
                    e.nombre,
                    e.apellido
                FROM cuenta_acceso ca
                INNER JOIN rol r ON r.id_rol = ca.id_rol
                INNER JOIN empleado e ON e.id_empleado = ca.id_empleado
                WHERE LOWER(r.nombre_rol) = 'empleado'
                  AND {$condicionIdentificador}
                LIMIT 1
            ";

            $stmt = $conexion->prepare($sqlEmpleado);
            $stmt->execute($parametrosIdentificador);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$user) {
            rs_flash('error', 'Usuario o contraseña incorrectos.');
            rs_redirect('login.php');
        }

        if (!rs_password_verify_safe(
            $password,
            (string) $user['password']
        )) {
            rs_flash('error', 'Usuario o contraseña incorrectos.');
            rs_redirect('login.php');
        }

        // Validar estado de la cuenta.
        if (
            strcasecmp(trim((string) $user['estado_cuenta']), 'Activo') !== 0
        ) {
            rs_flash('error', 'Esta cuenta se encuentra inactiva.');
            rs_redirect('login.php');
        }

        $idRol = (int) $user['id_rol'];

        // Validar administrador.
        if (strcasecmp((string) $user['nombre_rol'], 'Administrador') === 0) {

            if (
                $idRol !== 1 ||
                empty($user['id_administrador']) ||
                !empty($user['id_empleado'])
            ) {
                rs_flash(
                    'error',
                    'La cuenta administrativa no tiene una configuración válida.'
                );

                rs_redirect('login.php');
            }

            if (
                strcasecmp(
                    trim((string) $user['estado_administrador']),
                    'Activo'
                ) !== 0
            ) {
                rs_flash('error', 'El administrador se encuentra inactivo.');
                rs_redirect('login.php');
            }
        }

        // Validar empleado.
        elseif (strcasecmp((string) $user['nombre_rol'], 'Empleado') === 0) {

            if (
                $idRol !== 2 ||
                empty($user['id_empleado']) ||
                !empty($user['id_administrador'])
            ) {
                rs_flash(
                    'error',
                    'La cuenta no tiene un empleado relacionado correctamente.'
                );

                rs_redirect('login.php');
            }

            if (
                strcasecmp(
                    trim((string) $user['estado_empleado']),
                    'Activo'
                ) !== 0
            ) {
                rs_flash('error', 'El empleado se encuentra inactivo.');
                rs_redirect('login.php');
            }
        }

        else {
            rs_flash('error', 'El rol de esta cuenta no tiene acceso.');
            rs_redirect('login.php');
        }

        // Crear sesión.
        rs_login_usuario($user);

        // Redirigir según el rol.
        if (strcasecmp((string) $user['nombre_rol'], 'Administrador') === 0) {
            rs_redirect('dashboardAD/index.php');
        }

        rs_redirect('dashboardEM/index.php');

    } catch (PDOException $e) {

        error_log(
            'Error de autenticación RecurSoft: ' . $e->getMessage()
        );

        rs_flash(
            'error',
            'Ocurrió un error al iniciar sesión. Verifique la configuración.'
        );

        rs_redirect('login.php');
    }
}

/**
 * CERRAR SESIÓN
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'logout'
) {
    if (!rs_csrf_ok()) {
        rs_flash('error', 'La sesión del formulario expiró. Vuelva a intentar.');
        rs_redirect('login.php');
    }

    rs_logout();
    rs_redirect('login.php?logout=success');
}

rs_redirect('login.php');

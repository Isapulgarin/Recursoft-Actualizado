
<?php

require_once __DIR__ . '/../includes/auth.php';

$usuarioSesion = rs_requiere_admin();
$conexion = rs_pdo();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rs_redirect('dashboardAD/administradores.php');
}

$csrf = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_administradores']) ||
    !hash_equals($_SESSION['csrf_administradores'], $csrf)
) {
    rs_flash('error', 'La sesión del formulario ha expirado.');
    rs_redirect('../dashboardAD/administradores.php');
}

$accion = $_POST['accion'] ?? '';

function rs_admin_redirigir(string $mensaje): void
{
    rs_redirect(
        'dashboardAD/administradores.php?mensaje=' .
        urlencode($mensaje)
    );
}

try {

    /* REGISTRAR */

    if ($accion === 'guardar') {

        $documento = trim($_POST['documento'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = strtolower(trim($_POST['correo'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (
            $documento === '' ||
            $nombre === '' ||
            $apellido === '' ||
            $correo === '' ||
            $password === ''
        ) {
            throw new Exception('Completa todos los campos obligatorios.');
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo electrónico no es válido.');
        }

        if (strlen($password) < 8) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres.');
        }

        $conexion->beginTransaction();

        $verificar = $conexion->prepare("
            SELECT id_cuenta
            FROM cuenta_acceso
            WHERE correo = :correo

            UNION

            SELECT id_cuenta
            FROM administrador
            WHERE documento = :documento
        ");

        $verificar->execute([
            ':correo' => $correo,
            ':documento' => $documento
        ]);

        if ($verificar->fetch()) {
            throw new Exception('El correo o documento ya está registrado.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $cuenta = $conexion->prepare("
            INSERT INTO cuenta_acceso
                (correo, password, estado, id_rol, id_empleado)
            VALUES
                (:correo, :password, 'Activo', 1, NULL)
        ");

        $cuenta->execute([
            ':correo' => $correo,
            ':password' => $hash
        ]);

        $idCuenta = (int) $conexion->lastInsertId();

        $admin = $conexion->prepare("
            INSERT INTO administrador
                (id_cuenta, documento, nombre, apellido, telefono, estado)
            VALUES
                (:id_cuenta, :documento, :nombre, :apellido, :telefono, 'Activo')
        ");

        $admin->execute([
            ':id_cuenta' => $idCuenta,
            ':documento' => $documento,
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':telefono' => $telefono
        ]);

        $conexion->commit();

        rs_admin_redirigir('Administrador registrado correctamente.');
    }

    /* EDITAR */

    elseif ($accion === 'editar') {

        $id = (int) ($_POST['id_administrador'] ?? 0);

        $documento = trim($_POST['documento'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = strtolower(trim($_POST['correo'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($id <= 0 || $documento === '' || $nombre === '' || $apellido === '') {
            throw new Exception('Los datos del administrador no son válidos.');
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo electrónico no es válido.');
        }

        $conexion->beginTransaction();

        $consulta = $conexion->prepare("
            SELECT id_cuenta
            FROM administrador
            WHERE id_administrador = :id
            FOR UPDATE
        ");

        $consulta->execute([':id' => $id]);

        $admin = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            throw new Exception('El administrador no existe.');
        }

        $idCuenta = (int) $admin['id_cuenta'];

        $duplicado = $conexion->prepare("
            SELECT COUNT(*)
            FROM administrador a
            INNER JOIN cuenta_acceso ca ON ca.id_cuenta = a.id_cuenta
            WHERE a.id_administrador <> :id
            AND (a.documento = :documento OR ca.correo = :correo)
        ");

        $duplicado->execute([
            ':id' => $id,
            ':documento' => $documento,
            ':correo' => $correo
        ]);

        if ((int) $duplicado->fetchColumn() > 0) {
            throw new Exception('El documento o correo ya está registrado.');
        }

        $actualizar = $conexion->prepare("
            UPDATE administrador
            SET documento = :documento,
                nombre = :nombre,
                apellido = :apellido,
                telefono = :telefono
            WHERE id_administrador = :id
        ");

        $actualizar->execute([
            ':documento' => $documento,
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':telefono' => $telefono,
            ':id' => $id
        ]);

        if ($password !== '') {

            if (strlen($password) < 8) {
                throw new Exception('La contraseña debe tener al menos 8 caracteres.');
            }

            $actualizarCuenta = $conexion->prepare("
                UPDATE cuenta_acceso
                SET correo = :correo,
                    password = :password
                WHERE id_cuenta = :id
            ");

            $actualizarCuenta->execute([
                ':correo' => $correo,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':id' => $idCuenta
            ]);

        } else {

            $actualizarCuenta = $conexion->prepare("
                UPDATE cuenta_acceso
                SET correo = :correo
                WHERE id_cuenta = :id
            ");

            $actualizarCuenta->execute([
                ':correo' => $correo,
                ':id' => $idCuenta
            ]);

        }

        $conexion->commit();

        rs_admin_redirigir('Administrador actualizado correctamente.');
    }

    /* CAMBIAR ESTADO */

    elseif ($accion === 'cambiar_estado') {

        $id = (int) ($_POST['id_administrador'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Identificador no válido.');
        }

        if (
            $id === (int) ($usuarioSesion['id_administrador'] ?? 0)
        ) {
            throw new Exception('No puedes desactivar tu propia cuenta.');
        }

        $conexion->beginTransaction();

        $consulta = $conexion->prepare("
            SELECT a.id_cuenta, a.estado
            FROM administrador a
            WHERE a.id_administrador = :id
            FOR UPDATE
        ");

        $consulta->execute([':id' => $id]);

        $admin = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            throw new Exception('El administrador no existe.');
        }

        $nuevoEstado = $admin['estado'] === 'Activo'
            ? 'Inactivo'
            : 'Activo';

        $actualizar = $conexion->prepare("
            UPDATE administrador
            SET estado = :estado
            WHERE id_administrador = :id
        ");

        $actualizar->execute([
            ':estado' => $nuevoEstado,
            ':id' => $id
        ]);

        $actualizarCuenta = $conexion->prepare("
            UPDATE cuenta_acceso
            SET estado = :estado
            WHERE id_cuenta = :id_cuenta
        ");

        $actualizarCuenta->execute([
            ':estado' => $nuevoEstado,
            ':id_cuenta' => $admin['id_cuenta']
        ]);

        $conexion->commit();

        rs_admin_redirigir('Estado del administrador actualizado.');
    }

    else {
        throw new Exception('Acción no reconocida.');
    }

} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('AdministradorController: ' . $e->getMessage());

    rs_admin_redirigir($e instanceof PDOException
        ? 'No se pudo completar la operación. Verifica los datos de la base de datos.'
        : $e->getMessage()
    );
}

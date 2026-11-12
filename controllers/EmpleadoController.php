
<?php
require_once __DIR__ . '/../includes/auth.php';
rs_requiere_admin();
$conexion = rs_pdo();

function regresar($mensaje) {
    rs_redirect('dashboardAD/empleados.php?mensaje=' . urlencode($mensaje));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    regresar('Solicitud no válida.');
}

$accion = $_POST['accion'] ?? '';

try {

    // ==========================================
    // REGISTRAR EMPLEADO
    // ==========================================
    if ($accion === 'guardar') {

        $documento = trim($_POST['documento'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $cargo = trim($_POST['cargo'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmarPassword = $_POST['confirmar_password'] ?? '';
        $estado = $_POST['estado'] ?? 'Activo';

        if (
            $documento === '' ||
            $nombre === '' ||
            $apellido === '' ||
            $cargo === '' ||
            $correo === '' ||
            $telefono === '' ||
            $password === '' ||
            $confirmarPassword === ''
        ) {
            regresar('Completa todos los campos obligatorios.');
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            regresar('El correo electrónico no es válido.');
        }

        if ($password !== $confirmarPassword) {
            regresar('Las contraseñas no coinciden.');
        }

        if (strlen($password) < 8) {
            regresar('La contraseña debe tener mínimo 8 caracteres.');
        }

        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            regresar('El estado seleccionado no es válido.');
        }

        // Verificar documento duplicado
        $consulta = $conexion->prepare("
            SELECT id_empleado
            FROM empleado
            WHERE documento = ?
        ");
        $consulta->execute([$documento]);

        if ($consulta->fetch()) {
            regresar('Ya existe un empleado con ese documento.');
        }

        // Verificar correo duplicado
        $consulta = $conexion->prepare("
            SELECT id_cuenta
            FROM cuenta_acceso
            WHERE correo = ?
        ");
        $consulta->execute([$correo]);

        if ($consulta->fetch()) {
            regresar('Ya existe una cuenta registrada con ese correo.');
        }

        $conexion->beginTransaction();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Registrar empleado directamente
        $insertarEmpleado = $conexion->prepare("
            INSERT INTO empleado (
                id_usuario,
                documento,
                nombre,
                apellido,
                cargo,
                telefono,
                correo,
                estado,
                password
            ) VALUES (
                NULL, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $insertarEmpleado->execute([
            $documento,
            $nombre,
            $apellido,
            $cargo,
            $telefono,
            $correo,
            $estado,
            $passwordHash
        ]);

        $idEmpleado = $conexion->lastInsertId();

        // Crear cuenta de acceso del empleado
        $insertarCuenta = $conexion->prepare("
            INSERT INTO cuenta_acceso (
                id_empleado,
                id_rol,
                correo,
                password,
                estado
            ) VALUES (?, 2, ?, ?, ?)
        ");

        $insertarCuenta->execute([
            $idEmpleado,
            $correo,
            $passwordHash,
            $estado
        ]);

        $conexion->commit();

        regresar('Empleado y cuenta de acceso registrados correctamente.');
    }


    // ==========================================
    // EDITAR EMPLEADO
    // ==========================================
    if ($accion === 'editar') {

        $idEmpleado = filter_input(INPUT_POST, 'id_empleado', FILTER_VALIDATE_INT);

        $documento = trim($_POST['documento'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $cargo = trim($_POST['cargo'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmarPassword = $_POST['confirmar_password'] ?? '';
        $estado = $_POST['estado'] ?? '';

        if (
            !$idEmpleado ||
            $documento === '' ||
            $nombre === '' ||
            $apellido === '' ||
            $cargo === '' ||
            $correo === '' ||
            $telefono === ''
        ) {
            regresar('Completa todos los campos obligatorios.');
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            regresar('El correo electrónico no es válido.');
        }

        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            regresar('El estado seleccionado no es válido.');
        }

        // La contraseña es opcional al editar
        if ($password !== '' || $confirmarPassword !== '') {

            if ($password !== $confirmarPassword) {
                regresar('Las contraseñas no coinciden.');
            }

            if (strlen($password) < 8) {
                regresar('La contraseña debe tener mínimo 8 caracteres.');
            }
        }

        // Verificar que exista el empleado
        $consulta = $conexion->prepare("
            SELECT id_empleado
            FROM empleado
            WHERE id_empleado = ?
        ");
        $consulta->execute([$idEmpleado]);

        if (!$consulta->fetch()) {
            regresar('El empleado que intentas editar no existe.');
        }

        // Verificar documento duplicado
        $consulta = $conexion->prepare("
            SELECT id_empleado
            FROM empleado
            WHERE documento = ?
            AND id_empleado != ?
        ");
        $consulta->execute([$documento, $idEmpleado]);

        if ($consulta->fetch()) {
            regresar('Otro empleado ya tiene ese documento.');
        }

        // Verificar correo duplicado en otras cuentas
        $consulta = $conexion->prepare("
            SELECT id_cuenta
            FROM cuenta_acceso
            WHERE correo = ?
            AND (id_empleado IS NULL OR id_empleado != ?)
        ");
        $consulta->execute([$correo, $idEmpleado]);

        if ($consulta->fetch()) {
            regresar('Ese correo ya está registrado en otra cuenta.');
        }

        $conexion->beginTransaction();

        // Actualizar datos del empleado
        $actualizar = $conexion->prepare("
            UPDATE empleado
            SET
                documento = ?,
                nombre = ?,
                apellido = ?,
                cargo = ?,
                telefono = ?,
                correo = ?,
                estado = ?
            WHERE id_empleado = ?
        ");

        $actualizar->execute([
            $documento,
            $nombre,
            $apellido,
            $cargo,
            $telefono,
            $correo,
            $estado,
            $idEmpleado
        ]);

        // Comprobar si tiene cuenta de acceso
        $consultaCuenta = $conexion->prepare("
            SELECT id_cuenta
            FROM cuenta_acceso
            WHERE id_empleado = ?
        ");
        $consultaCuenta->execute([$idEmpleado]);

        $cuenta = $consultaCuenta->fetch();

        if ($cuenta) {

            if ($password !== '') {

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $actualizarCuenta = $conexion->prepare("
                    UPDATE cuenta_acceso
                    SET correo = ?, password = ?, estado = ?
                    WHERE id_empleado = ?
                ");

                $actualizarCuenta->execute([
                    $correo,
                    $passwordHash,
                    $estado,
                    $idEmpleado
                ]);

                $actualizarPasswordEmpleado = $conexion->prepare("
                    UPDATE empleado SET password = ? WHERE id_empleado = ?
                ");
                $actualizarPasswordEmpleado->execute([$passwordHash, $idEmpleado]);

            } else {

                $actualizarCuenta = $conexion->prepare("
                    UPDATE cuenta_acceso
                    SET correo = ?, estado = ?
                    WHERE id_empleado = ?
                ");

                $actualizarCuenta->execute([
                    $correo,
                    $estado,
                    $idEmpleado
                ]);
            }

        } else {

            // Crear cuenta si el empleado todavía no tiene una
            if ($password === '') {
                $conexion->rollBack();
                regresar('Debes establecer una contraseña para crear la cuenta de acceso.');
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $actualizarPasswordEmpleado = $conexion->prepare("
                UPDATE empleado SET password = ? WHERE id_empleado = ?
            ");
            $actualizarPasswordEmpleado->execute([$passwordHash, $idEmpleado]);

            $insertarCuenta = $conexion->prepare("
                INSERT INTO cuenta_acceso (
                    id_empleado,
                    id_rol,
                    correo,
                    password,
                    estado
                ) VALUES (?, 2, ?, ?, ?)
            ");

            $insertarCuenta->execute([
                $idEmpleado,
                $correo,
                $passwordHash,
                $estado
            ]);
        }

        $conexion->commit();

        regresar('Empleado actualizado correctamente.');
    }


    // ==========================================
    // CAMBIAR ESTADO
    // ==========================================
    if ($accion === 'cambiar_estado') {

        $idEmpleado = filter_input(INPUT_POST, 'id_empleado', FILTER_VALIDATE_INT);

        if (!$idEmpleado) {
            regresar('El empleado seleccionado no es válido.');
        }

        $conexion->beginTransaction();

        $consulta = $conexion->prepare("
            SELECT estado
            FROM empleado
            WHERE id_empleado = ?
        ");
        $consulta->execute([$idEmpleado]);

        $empleado = $consulta->fetch();

        if (!$empleado) {
            $conexion->rollBack();
            regresar('No se encontró el empleado seleccionado.');
        }

        $nuevoEstado = $empleado['estado'] === 'Activo'
            ? 'Inactivo'
            : 'Activo';

        // Actualizar empleado
        $actualizarEmpleado = $conexion->prepare("
            UPDATE empleado
            SET estado = ?
            WHERE id_empleado = ?
        ");
        $actualizarEmpleado->execute([$nuevoEstado, $idEmpleado]);

        // Actualizar también su cuenta de acceso
        $actualizarCuenta = $conexion->prepare("
            UPDATE cuenta_acceso
            SET estado = ?
            WHERE id_empleado = ?
        ");
        $actualizarCuenta->execute([$nuevoEstado, $idEmpleado]);

        $conexion->commit();

        regresar('Estado del empleado actualizado correctamente.');
    }

    regresar('La acción solicitada no es válida.');

} catch (PDOException $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('Error en EmpleadoController.php: ' . $e->getMessage());

    regresar('Ocurrió un error al procesar la información. Revisa los datos y la base de datos.');
}

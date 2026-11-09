<?php
require_once __DIR__ . '/../config/conexion.php';

function regresar($mensaje) {
    header('Location: ../dashboardAD/empleados.php?mensaje=' . urlencode($mensaje));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    regresar('Solicitud no válida.');
}

$accion = $_POST['accion'] ?? '';

try {

    if ($accion === 'guardar') {

        $idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
        $documento = trim($_POST['documento'] ?? '');
        $cargo = trim($_POST['cargo'] ?? '');
        $estado = $_POST['estado'] ?? 'Activo';

        if (!$idUsuario || $documento === '' || $cargo === '') {
            regresar('Completa todos los campos obligatorios.');
        }

        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            regresar('El estado seleccionado no es válido.');
        }

        $consultaUsuario = $conexion->prepare("
            SELECT id_usuario, nombre, apellido, correo, telefono
            FROM usuario
            WHERE id_usuario = :id_usuario
        ");

        $consultaUsuario->execute([':id_usuario' => $idUsuario]);
        $usuario = $consultaUsuario->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            regresar('El usuario seleccionado no existe.');
        }

        $consultaDuplicado = $conexion->prepare("
            SELECT id_empleado
            FROM empleado
            WHERE id_usuario = :id_usuario
        ");

        $consultaDuplicado->execute([':id_usuario' => $idUsuario]);

        if ($consultaDuplicado->fetch()) {
            regresar('Este usuario ya se encuentra registrado como empleado.');
        }

        $consultaDocumento = $conexion->prepare("
            SELECT id_empleado
            FROM empleado
            WHERE documento = :documento
        ");

        $consultaDocumento->execute([':documento' => $documento]);

        if ($consultaDocumento->fetch()) {
            regresar('Ya existe un empleado con ese documento.');
        }

        $insertar = $conexion->prepare("
            INSERT INTO empleado (
                id_usuario,
                documento,
                nombre,
                apellido,
                cargo,
                telefono,
                correo,
                estado
            ) VALUES (
                :id_usuario,
                :documento,
                :nombre,
                :apellido,
                :cargo,
                :telefono,
                :correo,
                :estado
            )
        ");

        $insertar->execute([
            ':id_usuario' => $idUsuario,
            ':documento' => $documento,
            ':nombre' => $usuario['nombre'],
            ':apellido' => $usuario['apellido'],
            ':cargo' => $cargo,
            ':telefono' => $usuario['telefono'],
            ':correo' => $usuario['correo'],
            ':estado' => $estado
        ]);

        regresar('Empleado registrado correctamente.');
    }


    if ($accion === 'editar') {

        $idEmpleado = filter_input(INPUT_POST, 'id_empleado', FILTER_VALIDATE_INT);
        $idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
        $documento = trim($_POST['documento'] ?? '');
        $cargo = trim($_POST['cargo'] ?? '');
        $estado = $_POST['estado'] ?? '';

        if (!$idEmpleado || !$idUsuario || $documento === '' || $cargo === '') {
            regresar('Completa todos los campos obligatorios.');
        }

        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            regresar('El estado seleccionado no es válido.');
        }

        $consultaUsuario = $conexion->prepare("
            SELECT id_usuario, nombre, apellido, correo, telefono
            FROM usuario
            WHERE id_usuario = :id_usuario
        ");

        $consultaUsuario->execute([':id_usuario' => $idUsuario]);
        $usuario = $consultaUsuario->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            regresar('El usuario seleccionado no existe.');
        }

        $consultaEmpleado = $conexion->prepare("
            SELECT id_empleado
            FROM empleado
            WHERE id_empleado = :id_empleado
        ");

        $consultaEmpleado->execute([':id_empleado' => $idEmpleado]);

        if (!$consultaEmpleado->fetch()) {
            regresar('El empleado que intentas editar no existe.');
        }

        $consultaDuplicado = $conexion->prepare("
            SELECT id_empleado
            FROM empleado
            WHERE id_usuario = :id_usuario
            AND id_empleado != :id_empleado
        ");

        $consultaDuplicado->execute([
            ':id_usuario' => $idUsuario,
            ':id_empleado' => $idEmpleado
        ]);

        if ($consultaDuplicado->fetch()) {
            regresar('Este usuario ya está asociado a otro empleado.');
        }

        $consultaDocumento = $conexion->prepare("
            SELECT id_empleado
            FROM empleado
            WHERE documento = :documento
            AND id_empleado != :id_empleado
        ");

        $consultaDocumento->execute([
            ':documento' => $documento,
            ':id_empleado' => $idEmpleado
        ]);

        if ($consultaDocumento->fetch()) {
            regresar('Otro empleado ya tiene ese documento.');
        }

        $actualizar = $conexion->prepare("
            UPDATE empleado
            SET
                id_usuario = :id_usuario,
                documento = :documento,
                nombre = :nombre,
                apellido = :apellido,
                cargo = :cargo,
                telefono = :telefono,
                correo = :correo,
                estado = :estado
            WHERE id_empleado = :id_empleado
        ");

        $actualizar->execute([
            ':id_usuario' => $idUsuario,
            ':documento' => $documento,
            ':nombre' => $usuario['nombre'],
            ':apellido' => $usuario['apellido'],
            ':cargo' => $cargo,
            ':telefono' => $usuario['telefono'],
            ':correo' => $usuario['correo'],
            ':estado' => $estado,
            ':id_empleado' => $idEmpleado
        ]);

        regresar('Empleado actualizado correctamente.');
    }


    if ($accion === 'cambiar_estado') {

        $idEmpleado = filter_input(INPUT_POST, 'id_empleado', FILTER_VALIDATE_INT);

        if (!$idEmpleado) {
            regresar('El empleado seleccionado no es válido.');
        }

        $actualizar = $conexion->prepare("
            UPDATE empleado
            SET estado = CASE
                WHEN estado = 'Activo' THEN 'Inactivo'
                ELSE 'Activo'
            END
            WHERE id_empleado = :id_empleado
        ");

        $actualizar->execute([
            ':id_empleado' => $idEmpleado
        ]);

        if ($actualizar->rowCount() === 0) {
            regresar('No se encontró el empleado seleccionado.');
        }

        regresar('Estado del empleado actualizado correctamente.');
    }

    regresar('La acción solicitada no es válida.');

} catch (PDOException $e) {

    error_log('Error en EmpleadoController.php: ' . $e->getMessage());

    regresar('Ocurrió un error al procesar la información. Revisa los datos y la base de datos.');
}
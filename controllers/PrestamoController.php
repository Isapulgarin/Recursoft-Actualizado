
<?php

require_once __DIR__ . '/../includes/auth.php';
$usuarioSesion = rs_requiere_login();
$conexion = rs_pdo();

/* =====================================================
   FUNCIONES GENERALES
===================================================== */

function volverPrestamos($mensaje)
{
    $usuario = rs_usuario_sesion();
    $destino = (int) ($usuario['id_rol'] ?? 0) === 2
        ? 'dashboardEM/prestamos.php?'
        : 'dashboardAD/prestamos.php?';

    rs_redirect($destino . http_build_query([
        'mensaje' => $mensaje
    ]));
}

function fechaValida($fecha)
{
    if (!is_string($fecha) || $fecha === '') {
        return false;
    }

    $formato = DateTime::createFromFormat('!Y-m-d', $fecha);

    return $formato && $formato->format('Y-m-d') === $fecha;
}

function longitudTexto($texto)
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}

function idPositivo($valor)
{
    $id = filter_var($valor, FILTER_VALIDATE_INT);

    return ($id !== false && $id > 0) ? (int)$id : null;
}

/* =====================================================
   VALIDAR SESIÓN Y ROL
===================================================== */

$idRolSesion = (int)($usuarioSesion['id_rol'] ?? 0);
$idEmpleadoSesion = idPositivo($usuarioSesion['id_empleado'] ?? null);

$esAdministrador = $idRolSesion === 1;
$esEmpleado = $idRolSesion === 2;

if (!$esAdministrador && !$esEmpleado) {
    volverPrestamos('No tienes permisos para realizar esta operación.');
}

if ($esEmpleado) {
    if (!$idEmpleadoSesion) {
        volverPrestamos('Tu cuenta no tiene un empleado asociado.');
    }

    $validarSesion = $conexion->prepare("
        SELECT id_empleado
        FROM empleado
        WHERE id_empleado = :id
          AND estado = 'Activo'
        LIMIT 1
    ");

    $validarSesion->execute([':id' => $idEmpleadoSesion]);

    if (!$validarSesion->fetch(PDO::FETCH_ASSOC)) {
        volverPrestamos('El empleado asociado a tu cuenta no existe o está inactivo.');
    }
}

/* =====================================================
   VALIDACIONES DE RELACIONES
===================================================== */

function validarEmpleado($conexion, $idEmpleado)
{
    if ($idEmpleado === null) {
        return;
    }

    $consulta = $conexion->prepare("
        SELECT id_empleado
        FROM empleado
        WHERE id_empleado = :id
          AND estado = 'Activo'
        FOR UPDATE
    ");

    $consulta->execute([':id' => $idEmpleado]);

    if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('El empleado responsable no existe o está inactivo.');
    }
}

function validarUsuarioReceptor($conexion, $idUsuario)
{
    $consulta = $conexion->prepare("
        SELECT id_usuario
        FROM usuario
        WHERE id_usuario = :id
          AND estado = 'Activo'
          AND tipo_usuario IS NOT NULL
          AND tipo_usuario <> ''
        FOR UPDATE
    ");

    $consulta->execute([':id' => $idUsuario]);

    if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('La persona receptora no existe, está inactiva o no tiene tipo de usuario.');
    }
}

function obtenerRecursoBloqueado($conexion, $idRecurso)
{
    $consulta = $conexion->prepare("
        SELECT
            id_recurso,
            estado,
            disponibilidad
        FROM recurso
        WHERE id_recurso = :id
        FOR UPDATE
    ");

    $consulta->execute([':id' => $idRecurso]);

    $recurso = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$recurso) {
        throw new Exception('El recurso seleccionado no existe.');
    }

    return $recurso;
}

function recursoTienePrestamoActivo($conexion, $idRecurso, $idExcluir = null)
{
    $sql = "
        SELECT COUNT(*)
        FROM prestamo
        WHERE id_recurso = :recurso
          AND estado IN ('Activo', 'Vencido')
    ";

    $parametros = [':recurso' => $idRecurso];

    if ($idExcluir !== null) {
        $sql .= " AND id_prestamo <> :excluir";
        $parametros[':excluir'] = $idExcluir;
    }

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);

    return (int)$consulta->fetchColumn() > 0;
}

/* =====================================================
   ACTUALIZAR DISPONIBILIDAD DEL RECURSO
===================================================== */

function actualizarDisponibilidad($conexion, $idRecurso)
{
    $recurso = obtenerRecursoBloqueado($conexion, $idRecurso);

    /*
       Se respeta este orden de prioridad:

       1. Si está dañado, queda fuera de servicio.
       2. Si está en mantenimiento, conserva ese estado.
       3. Si está fuera de servicio, conserva ese estado.
       4. Si tiene préstamo activo, queda prestado.
       5. En los demás casos, queda disponible.
    */

    if ($recurso['estado'] === 'Dañado') {
        $disponibilidad = 'Fuera de servicio';
    } elseif ($recurso['disponibilidad'] === 'Mantenimiento') {
        $disponibilidad = 'Mantenimiento';
    } elseif ($recurso['disponibilidad'] === 'Fuera de servicio') {
        $disponibilidad = 'Fuera de servicio';
    } elseif (recursoTienePrestamoActivo($conexion, $idRecurso)) {
        $disponibilidad = 'Prestado';
    } else {
        $disponibilidad = 'Disponible';
    }

    $actualizar = $conexion->prepare("
        UPDATE recurso
        SET disponibilidad = :disponibilidad
        WHERE id_recurso = :id
    ");

    $actualizar->execute([
        ':disponibilidad' => $disponibilidad,
        ':id' => $idRecurso
    ]);
}

/* =====================================================
   COMPROBAR MÉTODO Y ACCIÓN
===================================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverPrestamos('Acceso no permitido.');
}

$accion = $_POST['accion'] ?? '';

try {

    /* =================================================
       REGISTRAR PRÉSTAMO
    ================================================= */

    if ($accion === 'guardar') {

        $idEmpleado = $esAdministrador
            ? null
            : $idEmpleadoSesion;

        $idUsuarioEntrega = idPositivo($_POST['id_usuario_entrega'] ?? null);
        $idRecurso = idPositivo($_POST['id_recurso'] ?? null);

        $fechaSolicitud = trim($_POST['fecha_solicitud'] ?? '');
        $fechaEntrega = trim($_POST['fecha_entrega'] ?? '');
        $fechaProgramada = trim($_POST['fecha_devolucion_programada'] ?? '');

        $estadoRecursoEntrega = trim($_POST['estado_recurso_entrega'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        $estadosRecurso = ['Bueno', 'Regular', 'Dañado'];

        if (!$idUsuarioEntrega || !$idRecurso) {
            volverPrestamos('Selecciona una persona y un recurso válidos.');
        }

        if (
            !fechaValida($fechaSolicitud) ||
            !fechaValida($fechaEntrega) ||
            !fechaValida($fechaProgramada)
        ) {
            volverPrestamos('Debes ingresar fechas válidas.');
        }

        if ($fechaEntrega < $fechaSolicitud) {
            volverPrestamos('La fecha de entrega no puede ser anterior a la solicitud.');
        }

        if ($fechaProgramada < $fechaEntrega) {
            volverPrestamos('La devolución programada no puede ser anterior a la entrega.');
        }

        if (
            $estadoRecursoEntrega !== '' &&
            !in_array($estadoRecursoEntrega, $estadosRecurso, true)
        ) {
            volverPrestamos('El estado del recurso al entregarlo no es válido.');
        }

        if ($estadoRecursoEntrega === 'Dañado') {
            volverPrestamos('No puedes registrar la entrega de un recurso como dañado.');
        }

        if (longitudTexto($observaciones) > 150) {
            volverPrestamos('Las observaciones no pueden superar los 150 caracteres.');
        }

        $conexion->beginTransaction();

        validarEmpleado($conexion, $idEmpleado);
        validarUsuarioReceptor($conexion, $idUsuarioEntrega);

        $recurso = obtenerRecursoBloqueado($conexion, $idRecurso);

        if ($recurso['estado'] === 'Dañado') {
            throw new Exception('No se puede prestar un recurso dañado.');
        }

        if ($recurso['disponibilidad'] !== 'Disponible') {
            throw new Exception('El recurso no está disponible para préstamo.');
        }

        if (recursoTienePrestamoActivo($conexion, $idRecurso)) {
            throw new Exception('El recurso ya tiene un préstamo activo.');
        }

        $insertar = $conexion->prepare("
            INSERT INTO prestamo (
                id_empleado,
                id_usuario_entrega,
                id_recurso,
                fecha_solicitud,
                fecha_entrega,
                fecha_devolucion_programada,
                fecha_devolucion_real,
                estado,
                estado_recurso_entrega,
                estado_recurso_devolucion,
                observaciones,
                observaciones_devolucion
            ) VALUES (
                :empleado,
                :usuario,
                :recurso,
                :solicitud,
                :entrega,
                :programada,
                NULL,
                'Activo',
                :estado_entrega,
                NULL,
                :observaciones,
                NULL
            )
        ");

        $insertar->execute([
            ':empleado' => $idEmpleado,
            ':usuario' => $idUsuarioEntrega,
            ':recurso' => $idRecurso,
            ':solicitud' => $fechaSolicitud,
            ':entrega' => $fechaEntrega,
            ':programada' => $fechaProgramada,
            ':estado_entrega' => $estadoRecursoEntrega !== ''
                ? $estadoRecursoEntrega
                : null,
            ':observaciones' => $observaciones !== ''
                ? $observaciones
                : null
        ]);

        actualizarDisponibilidad($conexion, $idRecurso);

        $conexion->commit();

        volverPrestamos('El préstamo se registró correctamente.');
    }

    /* =================================================
       EDITAR PRÉSTAMO
    ================================================= */

    elseif ($accion === 'editar') {

        $idPrestamo = idPositivo($_POST['id_prestamo'] ?? null);
        $idUsuarioEntrega = idPositivo($_POST['id_usuario_entrega'] ?? null);
        $idRecurso = idPositivo($_POST['id_recurso'] ?? null);

        $fechaSolicitud = trim($_POST['fecha_solicitud'] ?? '');
        $fechaEntrega = trim($_POST['fecha_entrega'] ?? '');
        $fechaProgramada = trim($_POST['fecha_devolucion_programada'] ?? '');
        $fechaReal = trim($_POST['fecha_devolucion_real'] ?? '');

        $estado = trim($_POST['estado'] ?? '');

        $estadoRecursoEntrega = trim($_POST['estado_recurso_entrega'] ?? '');
        $estadoRecursoDevolucion = trim($_POST['estado_recurso_devolucion'] ?? '');

        $observaciones = trim($_POST['observaciones'] ?? '');
        $observacionesDevolucion = trim($_POST['observaciones_devolucion'] ?? '');

        $estadosPermitidos = ['Pendiente', 'Activo', 'Vencido', 'Devuelto', 'Cancelado'];
        $estadosRecurso = ['Bueno', 'Regular', 'Dañado'];

        if (!$idPrestamo || !$idUsuarioEntrega || !$idRecurso) {
            volverPrestamos('Los datos del préstamo no son válidos.');
        }

        if (
            !fechaValida($fechaSolicitud) ||
            !fechaValida($fechaEntrega) ||
            !fechaValida($fechaProgramada)
        ) {
            volverPrestamos('Debes ingresar fechas válidas.');
        }

        if ($fechaEntrega < $fechaSolicitud) {
            volverPrestamos('La fecha de entrega no puede ser anterior a la solicitud.');
        }

        if ($fechaProgramada < $fechaEntrega) {
            volverPrestamos('La devolución programada no puede ser anterior a la entrega.');
        }

        if (!in_array($estado, $estadosPermitidos, true)) {
            volverPrestamos('El estado del préstamo no es válido.');
        }

        if ($fechaReal !== '' && !fechaValida($fechaReal)) {
            volverPrestamos('La fecha de devolución real no es válida.');
        }

        if ($estado === 'Devuelto' && $fechaReal === '') {
            $fechaReal = date('Y-m-d');
        }

        if ($estado !== 'Devuelto' && $fechaReal !== '') {
            volverPrestamos('La fecha real solo se registra cuando el préstamo está devuelto.');
        }

        if ($fechaReal !== '' && $fechaReal < $fechaEntrega) {
            volverPrestamos('La fecha de devolución real no puede ser anterior a la entrega.');
        }

        if (
            ($estadoRecursoEntrega !== '' &&
                !in_array($estadoRecursoEntrega, $estadosRecurso, true)) ||
            ($estadoRecursoDevolucion !== '' &&
                !in_array($estadoRecursoDevolucion, $estadosRecurso, true))
        ) {
            volverPrestamos('El estado del recurso seleccionado no es válido.');
        }

        if ($estadoRecursoEntrega === 'Dañado') {
            volverPrestamos('No puedes registrar un préstamo nuevo con el recurso dañado al entregarlo.');
        }

        if ($estado === 'Devuelto' && $estadoRecursoDevolucion === '') {
            volverPrestamos('Selecciona el estado del recurso al devolverlo.');
        }

        if (longitudTexto($observaciones) > 150) {
            volverPrestamos('Las observaciones del préstamo no pueden superar los 150 caracteres.');
        }

        if (longitudTexto($observacionesDevolucion) > 500) {
            volverPrestamos('Las observaciones de devolución no pueden superar los 500 caracteres.');
        }

        $conexion->beginTransaction();

        $consultaAnterior = $conexion->prepare("
            SELECT *
            FROM prestamo
            WHERE id_prestamo = :id
            FOR UPDATE
        ");

        $consultaAnterior->execute([':id' => $idPrestamo]);

        $anterior = $consultaAnterior->fetch(PDO::FETCH_ASSOC);

        if (!$anterior) {
            throw new Exception('El préstamo que intentas editar no existe.');
        }

        /*
           Un empleado solamente puede modificar sus propios préstamos.
        */

        if (
            $esEmpleado &&
            (int)$anterior['id_empleado'] !== $idEmpleadoSesion
        ) {
            throw new Exception('No tienes permiso para modificar este préstamo.');
        }

        $idEmpleado = $esAdministrador
            ? ($anterior['id_empleado'] !== null
                ? (int)$anterior['id_empleado']
                : null)
            : $idEmpleadoSesion;

        validarEmpleado($conexion, $idEmpleado);
        validarUsuarioReceptor($conexion, $idUsuarioEntrega);

        $idRecursoAnterior = (int)$anterior['id_recurso'];

        /*
           Bloquear los recursos en orden para reducir
           conflictos cuando se cambia el recurso del préstamo.
        */

        $idsRecursos = array_unique([$idRecursoAnterior, $idRecurso]);
        sort($idsRecursos);

        $recursosBloqueados = [];

        foreach ($idsRecursos as $idBloqueo) {
            $recursosBloqueados[$idBloqueo] = obtenerRecursoBloqueado(
                $conexion,
                $idBloqueo
            );
        }

        $recurso = $recursosBloqueados[$idRecurso];

        $ocupaRecurso = in_array($estado, ['Activo', 'Vencido'], true);

        if ($ocupaRecurso) {

            if ($recurso['estado'] === 'Dañado') {
                throw new Exception('No se puede asignar un recurso dañado.');
            }

            if (in_array($recurso['disponibilidad'], ['Mantenimiento', 'Fuera de servicio'], true)) {
                throw new Exception('El recurso está en mantenimiento o fuera de servicio.');
            }

            if (recursoTienePrestamoActivo($conexion, $idRecurso, $idPrestamo)) {
                throw new Exception('El recurso ya tiene otro préstamo activo.');
            }

            if (
                $idRecursoAnterior !== $idRecurso &&
                $recurso['disponibilidad'] !== 'Disponible'
            ) {
                throw new Exception('El nuevo recurso no está disponible.');
            }
        }

        $actualizar = $conexion->prepare("
            UPDATE prestamo SET
                id_empleado = :empleado,
                id_usuario_entrega = :usuario,
                id_recurso = :recurso,
                fecha_solicitud = :solicitud,
                fecha_entrega = :entrega,
                fecha_devolucion_programada = :programada,
                fecha_devolucion_real = :fecha_real,
                estado = :estado,
                estado_recurso_entrega = :estado_entrega,
                estado_recurso_devolucion = :estado_devolucion,
                observaciones = :observaciones,
                observaciones_devolucion = :observaciones_devolucion
            WHERE id_prestamo = :id
        ");

        $actualizar->execute([
            ':empleado' => $idEmpleado,
            ':usuario' => $idUsuarioEntrega,
            ':recurso' => $idRecurso,
            ':solicitud' => $fechaSolicitud,
            ':entrega' => $fechaEntrega,
            ':programada' => $fechaProgramada,
            ':fecha_real' => $fechaReal !== '' ? $fechaReal : null,
            ':estado' => $estado,
            ':estado_entrega' => $estadoRecursoEntrega !== ''
                ? $estadoRecursoEntrega
                : null,
            ':estado_devolucion' => $estadoRecursoDevolucion !== ''
                ? $estadoRecursoDevolucion
                : null,
            ':observaciones' => $observaciones !== ''
                ? $observaciones
                : null,
            ':observaciones_devolucion' => $observacionesDevolucion !== ''
                ? $observacionesDevolucion
                : null,
            ':id' => $idPrestamo
        ]);

        /*
           Al devolver el recurso se actualiza también
           su estado físico en la tabla recurso.
        */

        if ($estado === 'Devuelto' && $estadoRecursoDevolucion !== '') {

            $actualizarEstadoRecurso = $conexion->prepare("
                UPDATE recurso
                SET estado = :estado
                WHERE id_recurso = :id
            ");

            $actualizarEstadoRecurso->execute([
                ':estado' => $estadoRecursoDevolucion,
                ':id' => $idRecurso
            ]);
        }

        actualizarDisponibilidad($conexion, $idRecursoAnterior);

        if ($idRecursoAnterior !== $idRecurso) {
            actualizarDisponibilidad($conexion, $idRecurso);
        } else {
            actualizarDisponibilidad($conexion, $idRecurso);
        }

        $conexion->commit();

        volverPrestamos('El préstamo se actualizó correctamente.');
    }

    /* =================================================
       ELIMINAR PRÉSTAMO
    ================================================= */

    elseif ($accion === 'eliminar') {

        if (!$esAdministrador) {
            volverPrestamos('Solo el administrador puede eliminar préstamos.');
        }

        $idPrestamo = idPositivo($_POST['id_prestamo'] ?? null);

        if (!$idPrestamo) {
            volverPrestamos('El identificador del préstamo no es válido.');
        }

        $conexion->beginTransaction();

        $consulta = $conexion->prepare("
            SELECT id_prestamo, id_recurso, estado
            FROM prestamo
            WHERE id_prestamo = :id
            FOR UPDATE
        ");

        $consulta->execute([':id' => $idPrestamo]);

        $prestamo = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$prestamo) {
            throw new Exception('El préstamo que intentas eliminar no existe.');
        }

        /*
           Se conservan los préstamos que ya representan
           una entrega, devolución o vencimiento.
        */

        if (!in_array($prestamo['estado'], ['Pendiente', 'Cancelado'], true)) {
            throw new Exception('No se puede eliminar un préstamo activo, vencido o devuelto. Cambia su estado a Cancelado si corresponde.');
        }

        obtenerRecursoBloqueado($conexion, (int)$prestamo['id_recurso']);

        $eliminar = $conexion->prepare("
            DELETE FROM prestamo
            WHERE id_prestamo = :id
        ");

        $eliminar->execute([':id' => $idPrestamo]);

        actualizarDisponibilidad($conexion, (int)$prestamo['id_recurso']);

        $conexion->commit();

        volverPrestamos('El préstamo se eliminó correctamente.');
    }

    else {
        volverPrestamos('La acción solicitada no es válida.');
    }

} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('Error PrestamoController: ' . $e->getMessage());

    $mensaje = $e instanceof PDOException
        ? 'No se pudo completar la operación. Revisa la conexión, los datos y las relaciones de la base de datos.'
        : $e->getMessage();

    volverPrestamos($mensaje);
}

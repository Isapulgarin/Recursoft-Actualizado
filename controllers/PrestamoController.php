
<?php

require_once __DIR__ . '/../includes/auth.php';

$usuarioSesion = rs_requiere_login();
$conexion = rs_pdo();

/* =====================================================
   FUNCIONES GENERALES
===================================================== */

function volverPrestamos($mensaje)
{
    global $usuarioSesion;

    $rol = (int)($usuarioSesion['id_rol'] ?? 0);

    if ($rol === 2) {
        $ruta = '/RECURSOFT/dashboardEM/prestamos.php';
    } else {
        $ruta = '/RECURSOFT/dashboardAD/prestamos.php';
    }

    rs_redirect($ruta . '?mensaje=' . urlencode($mensaje));
    exit;
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

    return ($id !== false && $id > 0)
        ? (int)$id
        : null;
}

/* =====================================================
   VALIDAR SESIÓN Y ROL
===================================================== */

$idRolSesion = (int)($usuarioSesion['id_rol'] ?? 0);
$idCuentaSesion = idPositivo($usuarioSesion['id_cuenta'] ?? null);
$idEmpleadoSesion = idPositivo($usuarioSesion['id_empleado'] ?? null);

$esAdministrador = $idRolSesion === 1;
$esEmpleado = $idRolSesion === 2;

if (!$esAdministrador && !$esEmpleado) {
    volverPrestamos('No tienes permisos para realizar esta operación.');
}

if (!$idCuentaSesion) {
    volverPrestamos('No se pudo identificar la cuenta que inició sesión.');
}

/* =====================================================
   VALIDAR CUENTA AUTENTICADA
===================================================== */

$validarCuenta = $conexion->prepare("
    SELECT
        id_cuenta,
        id_rol,
        id_empleado,
        estado
    FROM cuenta_acceso
    WHERE id_cuenta = :id
    LIMIT 1
");

$validarCuenta->execute([
    ':id' => $idCuentaSesion
]);

$cuentaActual = $validarCuenta->fetch(PDO::FETCH_ASSOC);

if (
    !$cuentaActual ||
    (int)$cuentaActual['id_rol'] !== $idRolSesion ||
    $cuentaActual['estado'] !== 'Activo'
) {
    volverPrestamos('La cuenta que inició sesión no es válida o está inactiva.');
}

/*
 * USAR EL ID CONFIRMADO EN LA BASE DE DATOS.
 *
 * Este será el responsable que se almacenará en el préstamo.
 */

$idCuentaResponsable = (int)$cuentaActual['id_cuenta'];

/* =====================================================
   VALIDAR EMPLEADO DE LA SESIÓN
===================================================== */

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

    $validarSesion->execute([
        ':id' => $idEmpleadoSesion
    ]);

    if (!$validarSesion->fetch(PDO::FETCH_ASSOC)) {
        volverPrestamos('El empleado asociado a tu cuenta no existe o está inactivo.');
    }

    if (
        (int)$cuentaActual['id_empleado'] !== $idEmpleadoSesion
    ) {
        volverPrestamos('La relación entre tu cuenta y tu empleado no es válida.');
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

    $consulta->execute([
        ':id' => $idEmpleado
    ]);

    if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('El empleado asociado no existe o está inactivo.');
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

    $consulta->execute([
        ':id' => $idUsuario
    ]);

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
            disponibilidad,
            stock_total,
            stock_disponible
        FROM recurso
        WHERE id_recurso = :id
        FOR UPDATE
    ");

    $consulta->execute([
        ':id' => $idRecurso
    ]);

    $recurso = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$recurso) {
        throw new Exception('El recurso seleccionado no existe.');
    }

    return $recurso;
}

/* =====================================================
   UNIDADES RESERVADAS
===================================================== */

function unidadesReservadas($conexion, $idRecurso, $idExcluir = null)
{
    $sql = "
        SELECT COALESCE(SUM(cantidad), 0)
        FROM prestamo
        WHERE id_recurso = :recurso
          AND estado IN ('Activo', 'Vencido')
    ";

    $parametros = [
        ':recurso' => $idRecurso
    ];

    if ($idExcluir !== null) {
        $sql .= " AND id_prestamo <> :excluir";
        $parametros[':excluir'] = $idExcluir;
    }

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);

    return (int)$consulta->fetchColumn();
}

/* =====================================================
   ACTUALIZAR DISPONIBILIDAD
===================================================== */

function actualizarDisponibilidad($conexion, $idRecurso)
{
    $recurso = obtenerRecursoBloqueado($conexion, $idRecurso);

    $reservadas = unidadesReservadas(
        $conexion,
        $idRecurso
    );

    $disponibles = max(
        0,
        (int)$recurso['stock_total'] - $reservadas
    );

    if ($recurso['estado'] === 'Dañado') {
        $disponibilidad = 'Fuera de servicio';
    } elseif ($recurso['disponibilidad'] === 'Mantenimiento') {
        $disponibilidad = 'Mantenimiento';
    } elseif ($recurso['disponibilidad'] === 'Fuera de servicio') {
        $disponibilidad = 'Fuera de servicio';
    } elseif ($disponibles === 0) {
        $disponibilidad = 'Prestado';
    } else {
        $disponibilidad = 'Disponible';
    }

    $actualizar = $conexion->prepare("
        UPDATE recurso
        SET stock_disponible = :stock_disponible,
            disponibilidad = :disponibilidad
        WHERE id_recurso = :id
    ");

    $actualizar->execute([
        ':stock_disponible' => in_array(
            $disponibilidad,
            ['Mantenimiento', 'Fuera de servicio'],
            true
        ) ? 0 : $disponibles,

        ':disponibilidad' => $disponibilidad,
        ':id' => $idRecurso
    ]);
}

/* =====================================================
   COMPROBAR MÉTODO Y CSRF
===================================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverPrestamos('Acceso no permitido.');
}

if (!rs_csrf_ok()) {
    volverPrestamos('La sesión del formulario expiró. Vuelve a intentarlo.');
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

        $idUsuarioEntrega = idPositivo(
            $_POST['id_usuario_entrega'] ?? null
        );

        $idRecurso = idPositivo(
            $_POST['id_recurso'] ?? null
        );

        $cantidad = idPositivo(
            $_POST['cantidad'] ?? 1
        );

        $fechaSolicitud = trim($_POST['fecha_solicitud'] ?? '');
        $fechaEntrega = trim($_POST['fecha_entrega'] ?? '');
        $fechaProgramada = trim($_POST['fecha_devolucion_programada'] ?? '');

        $estadoRecursoEntrega = trim(
            $_POST['estado_recurso_entrega'] ?? ''
        );

        $observaciones = trim(
            $_POST['observaciones'] ?? ''
        );

        $estadosRecurso = [
            'Bueno',
            'Regular',
            'Dañado'
        ];

        if (!$idUsuarioEntrega || !$idRecurso || !$cantidad) {
            throw new Exception('Selecciona una persona, un recurso y una cantidad válidos.');
        }

        if (
            !fechaValida($fechaSolicitud) ||
            !fechaValida($fechaEntrega) ||
            !fechaValida($fechaProgramada)
        ) {
            throw new Exception('Debes ingresar fechas válidas.');
        }

        if ($fechaEntrega < $fechaSolicitud) {
            throw new Exception('La fecha de entrega no puede ser anterior a la solicitud.');
        }

        if ($fechaProgramada < $fechaEntrega) {
            throw new Exception('La devolución programada no puede ser anterior a la entrega.');
        }

        if (
            $estadoRecursoEntrega !== '' &&
            !in_array($estadoRecursoEntrega, $estadosRecurso, true)
        ) {
            throw new Exception('El estado del recurso al entregarlo no es válido.');
        }

        if ($estadoRecursoEntrega === 'Dañado') {
            throw new Exception('No puedes registrar la entrega de un recurso como dañado.');
        }

        if (longitudTexto($observaciones) > 150) {
            throw new Exception('Las observaciones no pueden superar los 150 caracteres.');
        }

        $conexion->beginTransaction();

        validarEmpleado($conexion, $idEmpleado);

        validarUsuarioReceptor(
            $conexion,
            $idUsuarioEntrega
        );

        $recurso = obtenerRecursoBloqueado(
            $conexion,
            $idRecurso
        );

        if ($recurso['estado'] === 'Dañado') {
            throw new Exception('No se puede prestar un recurso dañado.');
        }

        if (
            in_array(
                $recurso['disponibilidad'],
                ['Mantenimiento', 'Fuera de servicio'],
                true
            )
        ) {
            throw new Exception('El recurso está en mantenimiento o fuera de servicio.');
        }

        $disponibles = max(
            0,
            (int)$recurso['stock_total'] -
            unidadesReservadas($conexion, $idRecurso)
        );

        if ($cantidad > $disponibles) {
            throw new Exception('La cantidad solicitada supera las unidades disponibles.');
        }

        /*
         * REGISTRO DEL RESPONSABLE:
         *
         * Se utiliza $idCuentaResponsable, obtenido de la
         * cuenta validada en la base de datos.
         *
         * No se utiliza un campo enviado desde el formulario.
         */

        if ($idCuentaResponsable <= 0) {
            throw new Exception('No se pudo identificar al responsable del préstamo.');
        }

        $insertar = $conexion->prepare("
            INSERT INTO prestamo (
                id_empleado,
                id_usuario_entrega,
                id_cuenta_responsable,
                id_recurso,
                cantidad,
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
                :responsable,
                :recurso,
                :cantidad,
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
            ':responsable' => $idCuentaResponsable,
            ':recurso' => $idRecurso,
            ':cantidad' => $cantidad,
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

        $idNuevoPrestamo = (int)$conexion->lastInsertId();

        if ($idNuevoPrestamo <= 0) {
            throw new Exception('No se pudo confirmar el registro del préstamo.');
        }

        actualizarDisponibilidad(
            $conexion,
            $idRecurso
        );

        rs_registrar_historial(
            $idNuevoPrestamo,
            'Prestamo registrado',
            'Cantidad: ' . $cantidad,
            $idCuentaResponsable
        );

        $conexion->commit();

        volverPrestamos('El préstamo se registró correctamente.');
    }

    /* =================================================
       EDITAR PRÉSTAMO
    ================================================= */

    elseif ($accion === 'editar') {

        $idPrestamo = idPositivo(
            $_POST['id_prestamo'] ?? null
        );

        $idUsuarioEntrega = idPositivo(
            $_POST['id_usuario_entrega'] ?? null
        );

        $idRecurso = idPositivo(
            $_POST['id_recurso'] ?? null
        );

        $cantidad = idPositivo(
            $_POST['cantidad'] ?? 1
        );

        $fechaSolicitud = trim($_POST['fecha_solicitud'] ?? '');
        $fechaEntrega = trim($_POST['fecha_entrega'] ?? '');
        $fechaProgramada = trim($_POST['fecha_devolucion_programada'] ?? '');
        $fechaReal = trim($_POST['fecha_devolucion_real'] ?? '');

        $estado = trim($_POST['estado'] ?? '');

        $estadoRecursoEntrega = trim(
            $_POST['estado_recurso_entrega'] ?? ''
        );

        $estadoRecursoDevolucion = trim(
            $_POST['estado_recurso_devolucion'] ?? ''
        );

        $observaciones = trim(
            $_POST['observaciones'] ?? ''
        );

        $observacionesDevolucion = trim(
            $_POST['observaciones_devolucion'] ?? ''
        );

        $estadosPermitidos = [
            'Pendiente',
            'Activo',
            'Vencido',
            'Devuelto',
            'Cancelado'
        ];

        $estadosRecurso = [
            'Bueno',
            'Regular',
            'Dañado'
        ];

        if (!$idPrestamo || !$idUsuarioEntrega || !$idRecurso || !$cantidad) {
            throw new Exception('Los datos del préstamo no son válidos.');
        }

        if (
            !fechaValida($fechaSolicitud) ||
            !fechaValida($fechaEntrega) ||
            !fechaValida($fechaProgramada)
        ) {
            throw new Exception('Debes ingresar fechas válidas.');
        }

        if ($fechaEntrega < $fechaSolicitud) {
            throw new Exception('La fecha de entrega no puede ser anterior a la solicitud.');
        }

        if ($fechaProgramada < $fechaEntrega) {
            throw new Exception('La fecha de devolución programada no puede ser anterior a la entrega.');
        }

        if (!in_array($estado, $estadosPermitidos, true)) {
            throw new Exception('El estado del préstamo no es válido.');
        }

        if ($fechaReal !== '' && !fechaValida($fechaReal)) {
            throw new Exception('La fecha de devolución real no es válida.');
        }

        if ($estado === 'Devuelto' && $fechaReal === '') {
            $fechaReal = date('Y-m-d');
        }

        if ($estado !== 'Devuelto' && $fechaReal !== '') {
            throw new Exception('La fecha real solo se registra cuando el préstamo está devuelto.');
        }

        if ($fechaReal !== '' && $fechaReal < $fechaEntrega) {
            throw new Exception('La fecha de devolución real no puede ser anterior a la entrega.');
        }

        if (
            ($estadoRecursoEntrega !== '' &&
                !in_array($estadoRecursoEntrega, $estadosRecurso, true)) ||

            ($estadoRecursoDevolucion !== '' &&
                !in_array($estadoRecursoDevolucion, $estadosRecurso, true))
        ) {
            throw new Exception('El estado del recurso seleccionado no es válido.');
        }

        if ($estadoRecursoEntrega === 'Dañado') {
            throw new Exception('No puedes registrar un préstamo nuevo con el recurso dañado al entregarlo.');
        }

        if ($estado === 'Devuelto' && $estadoRecursoDevolucion === '') {
            throw new Exception('Selecciona el estado del recurso al devolverlo.');
        }

        if (longitudTexto($observaciones) > 150) {
            throw new Exception('Las observaciones del préstamo no pueden superar los 150 caracteres.');
        }

        if (longitudTexto($observacionesDevolucion) > 500) {
            throw new Exception('Las observaciones de devolución no pueden superar los 500 caracteres.');
        }

        $conexion->beginTransaction();

        $consultaAnterior = $conexion->prepare("
            SELECT *
            FROM prestamo
            WHERE id_prestamo = :id
            FOR UPDATE
        ");

        $consultaAnterior->execute([
            ':id' => $idPrestamo
        ]);

        $anterior = $consultaAnterior->fetch(PDO::FETCH_ASSOC);

        if (!$anterior) {
            throw new Exception('El préstamo que intentas editar no existe.');
        }

        if (
            $anterior['estado'] === 'Devuelto' &&
            $estado !== 'Devuelto'
        ) {
            throw new Exception('Un préstamo devuelto no puede volver a un estado activo.');
        }

        if (
            $anterior['estado'] === 'Devuelto' &&
            (
                (int)$anterior['id_recurso'] !== $idRecurso ||
                (int)$anterior['cantidad'] !== $cantidad
            )
        ) {
            throw new Exception('No puedes modificar el recurso o la cantidad de un préstamo ya devuelto.');
        }

        if (
            $esEmpleado &&
            (int)$anterior['id_empleado'] !== $idEmpleadoSesion
        ) {
            throw new Exception('No tienes permiso para modificar este préstamo.');
        }

        $idEmpleado = $esAdministrador
            ? (
                $anterior['id_empleado'] !== null
                    ? (int)$anterior['id_empleado']
                    : null
            )
            : $idEmpleadoSesion;

        validarEmpleado($conexion, $idEmpleado);

        validarUsuarioReceptor(
            $conexion,
            $idUsuarioEntrega
        );

        $idRecursoAnterior = (int)$anterior['id_recurso'];

        $idsRecursos = array_unique([
            $idRecursoAnterior,
            $idRecurso
        ]);

        sort($idsRecursos);

        $recursosBloqueados = [];

        foreach ($idsRecursos as $idBloqueo) {

            $recursosBloqueados[$idBloqueo] =
                obtenerRecursoBloqueado(
                    $conexion,
                    $idBloqueo
                );

        }

        $recurso = $recursosBloqueados[$idRecurso];

        $ocupaRecurso = in_array(
            $estado,
            ['Activo', 'Vencido'],
            true
        );

        if ($ocupaRecurso) {

            if ($recurso['estado'] === 'Dañado') {
                throw new Exception('No se puede asignar un recurso dañado.');
            }

            if (
                in_array(
                    $recurso['disponibilidad'],
                    ['Mantenimiento', 'Fuera de servicio'],
                    true
                )
            ) {
                throw new Exception('El recurso está en mantenimiento o fuera de servicio.');
            }

            $disponibles = max(
                0,
                (int)$recurso['stock_total'] -
                unidadesReservadas(
                    $conexion,
                    $idRecurso,
                    $idPrestamo
                )
            );

            if ($cantidad > $disponibles) {
                throw new Exception('La cantidad solicitada supera las unidades disponibles.');
            }
        }

        /*
         * NO MODIFICAR id_cuenta_responsable.
         *
         * El responsable original se conserva al editar.
         */

        $actualizar = $conexion->prepare("
            UPDATE prestamo SET

                id_empleado = :empleado,
                id_usuario_entrega = :usuario,
                id_recurso = :recurso,
                cantidad = :cantidad,
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
            ':cantidad' => $cantidad,
            ':solicitud' => $fechaSolicitud,
            ':entrega' => $fechaEntrega,
            ':programada' => $fechaProgramada,

            ':fecha_real' => $fechaReal !== ''
                ? $fechaReal
                : null,

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
         * ACTUALIZAR ESTADO FÍSICO DEL RECURSO
         */

        if (
            $estado === 'Devuelto' &&
            $estadoRecursoDevolucion !== ''
        ) {

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

        /*
         * REGISTRAR DEVOLUCIÓN
         */

        if (
            $estado === 'Devuelto' &&
            $anterior['estado'] !== 'Devuelto'
        ) {

            $registrarDevolucion = $conexion->prepare("
                INSERT INTO devolucion (
                    id_prestamo,
                    cantidad_devuelta,
                    fecha_devolucion,
                    fecha_hora,
                    id_cuenta_registra,
                    estado_recurso,
                    observaciones
                ) VALUES (
                    :prestamo,
                    :cantidad,
                    :fecha,
                    CURRENT_TIMESTAMP,
                    :cuenta,
                    :estado_recurso,
                    :observaciones
                )
            ");

            $registrarDevolucion->execute([
                ':prestamo' => $idPrestamo,
                ':cantidad' => (int)$anterior['cantidad'],
                ':fecha' => $fechaReal,
                ':cuenta' => $idCuentaResponsable,
                ':estado_recurso' => $estadoRecursoDevolucion,

                ':observaciones' => $observacionesDevolucion !== ''
                    ? $observacionesDevolucion
                    : null
            ]);

        }

        actualizarDisponibilidad(
            $conexion,
            $idRecursoAnterior
        );

        if ($idRecursoAnterior !== $idRecurso) {

            actualizarDisponibilidad(
                $conexion,
                $idRecurso
            );

        }

        rs_registrar_historial(
            $idPrestamo,
            'Prestamo actualizado',
            'Estado: ' . $anterior['estado'] . ' -> ' . $estado .
                '; cantidad: ' . (int)$anterior['cantidad'] . ' -> ' . $cantidad,
            $idCuentaResponsable
        );

        $conexion->commit();

        volverPrestamos('El préstamo se actualizó correctamente.');
    }

    /* =================================================
       ELIMINAR PRÉSTAMO
    ================================================= */

    elseif ($accion === 'eliminar') {

        if (!$esAdministrador) {
            throw new Exception('Solo el administrador puede eliminar préstamos.');
        }

        $idPrestamo = idPositivo(
            $_POST['id_prestamo'] ?? null
        );

        if (!$idPrestamo) {
            throw new Exception('El identificador del préstamo no es válido.');
        }

        $conexion->beginTransaction();

        $consulta = $conexion->prepare("
            SELECT
                id_prestamo,
                id_recurso,
                estado
            FROM prestamo
            WHERE id_prestamo = :id
            FOR UPDATE
        ");

        $consulta->execute([
            ':id' => $idPrestamo
        ]);

        $prestamo = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$prestamo) {
            throw new Exception('El préstamo que intentas eliminar no existe.');
        }

        if (
            !in_array(
                $prestamo['estado'],
                ['Pendiente', 'Cancelado'],
                true
            )
        ) {
            throw new Exception('No se puede eliminar un préstamo activo, vencido o devuelto.');
        }

        obtenerRecursoBloqueado(
            $conexion,
            (int)$prestamo['id_recurso']
        );

        $eliminar = $conexion->prepare("
            DELETE FROM prestamo
            WHERE id_prestamo = :id
        ");

        $eliminar->execute([
            ':id' => $idPrestamo
        ]);

        actualizarDisponibilidad(
            $conexion,
            (int)$prestamo['id_recurso']
        );

        $conexion->commit();

        volverPrestamos('El préstamo se eliminó correctamente.');
    }

    else {

        throw new Exception('La acción solicitada no es válida.');

    }

} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log(
        'Error PrestamoController: ' . $e->getMessage()
    );

    $mensaje = $e instanceof PDOException
        ? 'No se pudo completar la operación. Revisa la conexión, los datos y las relaciones de la base de datos.'
        : $e->getMessage();

    volverPrestamos($mensaje);
}

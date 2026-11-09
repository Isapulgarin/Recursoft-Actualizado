
<?php
require_once __DIR__ . '/../config/conexion.php';

function volverPrestamos($mensaje) {
    header('Location: ../dashboardAD/prestamos.php?' . http_build_query([
        'mensaje' => $mensaje
    ]));
    exit;
}

function fechaValida($fecha) {
    if (!$fecha) {
        return false;
    }

    $formato = DateTime::createFromFormat('!Y-m-d', $fecha);

    return $formato && $formato->format('Y-m-d') === $fecha;
}

function validarEmpleado($conexion, $idEmpleado) {
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

function validarUsuarioReceptor($conexion, $idUsuario) {
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
        throw new Exception('La persona que recibe el recurso no existe, no está activa o no tiene un tipo de usuario registrado.');
    }
}

function recursoTienePrestamoActivo($conexion, $idRecurso, $idExcluir = null) {
    $sql = "
        SELECT COUNT(*)
        FROM prestamo
        WHERE id_recurso = :id_recurso
        AND estado IN ('Activo', 'Vencido')
    ";

    $parametros = [':id_recurso' => $idRecurso];

    if ($idExcluir !== null) {
        $sql .= " AND id_prestamo <> :id_excluir";
        $parametros[':id_excluir'] = $idExcluir;
    }

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);

    return (int)$consulta->fetchColumn() > 0;
}

function actualizarDisponibilidad($conexion, $idRecurso) {

    $consultaRecurso = $conexion->prepare("
        SELECT disponibilidad
        FROM recurso
        WHERE id_recurso = :id
        FOR UPDATE
    ");

    $consultaRecurso->execute([
        ':id' => $idRecurso
    ]);

    $recurso = $consultaRecurso->fetch(PDO::FETCH_ASSOC);

    if (!$recurso) {
        throw new Exception('No se encontró el recurso.');
    }

    $ocupado = recursoTienePrestamoActivo($conexion, $idRecurso);

    if ($ocupado) {
        $disponibilidad = 'Prestado';
    } elseif (
        in_array(
            $recurso['disponibilidad'],
            ['Mantenimiento', 'Fuera de servicio'],
            true
        )
    ) {
        $disponibilidad = $recurso['disponibilidad'];
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverPrestamos('Acceso no permitido.');
}

$accion = $_POST['accion'] ?? '';

try {

    if ($accion === 'guardar') {

        $idEmpleado = filter_var(
            $_POST['id_empleado'] ?? '',
            FILTER_VALIDATE_INT
        );

        $idUsuarioEntrega = filter_var(
            $_POST['id_usuario_entrega'] ?? '',
            FILTER_VALIDATE_INT
        );

        $idRecurso = filter_var(
            $_POST['id_recurso'] ?? '',
            FILTER_VALIDATE_INT
        );

        $fechaSolicitud = trim($_POST['fecha_solicitud'] ?? '');
        $fechaEntrega = trim($_POST['fecha_entrega'] ?? '');
        $fechaProgramada = trim($_POST['fecha_devolucion_programada'] ?? '');

        $estadoRecursoEntrega = trim($_POST['estado_recurso_entrega'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        $estadosRecurso = ['Bueno', 'Regular', 'Dañado'];

        if (!$idEmpleado || !$idUsuarioEntrega || !$idRecurso) {
            volverPrestamos('Selecciona la persona que recibe, el empleado responsable y el recurso.');
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
            volverPrestamos('La fecha de devolución programada no puede ser anterior a la entrega.');
        }

        if (
            $estadoRecursoEntrega !== '' &&
            !in_array($estadoRecursoEntrega, $estadosRecurso, true)
        ) {
            volverPrestamos('El estado del recurso al entregarlo no es válido.');
        }

        if (strlen($observaciones) > 500) {
            volverPrestamos('Las observaciones no pueden superar los 500 caracteres.');
        }

        $conexion->beginTransaction();

        validarEmpleado($conexion, $idEmpleado);
        validarUsuarioReceptor($conexion, $idUsuarioEntrega);

        $consultaRecurso = $conexion->prepare("
            SELECT id_recurso, disponibilidad, estado
            FROM recurso
            WHERE id_recurso = :id
            FOR UPDATE
        ");

        $consultaRecurso->execute([':id' => $idRecurso]);

        $recurso = $consultaRecurso->fetch(PDO::FETCH_ASSOC);

        if (!$recurso) {
            throw new Exception('El recurso seleccionado no existe.');
        }

        if ($recurso['disponibilidad'] !== 'Disponible') {
            throw new Exception('El recurso seleccionado no está disponible.');
        }

        if ($recurso['estado'] === 'Dañado') {
            throw new Exception('No se puede prestar un recurso que está dañado.');
        }

        $consulta = $conexion->prepare("
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

        $consulta->execute([
            ':empleado' => $idEmpleado,
            ':usuario' => $idUsuarioEntrega,
            ':recurso' => $idRecurso,
            ':solicitud' => $fechaSolicitud,
            ':entrega' => $fechaEntrega,
            ':programada' => $fechaProgramada,
            ':estado_entrega' => $estadoRecursoEntrega !== '' ? $estadoRecursoEntrega : null,
            ':observaciones' => $observaciones
        ]);

        $actualizar = $conexion->prepare("
            UPDATE recurso
            SET disponibilidad = 'Prestado'
            WHERE id_recurso = :id
        ");

        $actualizar->execute([':id' => $idRecurso]);

        $conexion->commit();

        volverPrestamos('El préstamo se registró correctamente.');
    }

    elseif ($accion === 'editar') {

        $idPrestamo = filter_var(
            $_POST['id_prestamo'] ?? '',
            FILTER_VALIDATE_INT
        );

        $idEmpleado = filter_var(
            $_POST['id_empleado'] ?? '',
            FILTER_VALIDATE_INT
        );

        $idUsuarioEntrega = filter_var(
            $_POST['id_usuario_entrega'] ?? '',
            FILTER_VALIDATE_INT
        );

        $idRecurso = filter_var(
            $_POST['id_recurso'] ?? '',
            FILTER_VALIDATE_INT
        );

        $fechaSolicitud = trim($_POST['fecha_solicitud'] ?? '');
        $fechaEntrega = trim($_POST['fecha_entrega'] ?? '');
        $fechaProgramada = trim($_POST['fecha_devolucion_programada'] ?? '');
        $fechaReal = trim($_POST['fecha_devolucion_real'] ?? '');

        $estado = trim($_POST['estado'] ?? '');
        $estadoRecursoEntrega = trim($_POST['estado_recurso_entrega'] ?? '');
        $estadoRecursoDevolucion = trim($_POST['estado_recurso_devolucion'] ?? '');

        $observaciones = trim($_POST['observaciones'] ?? '');
        $observacionesDevolucion = trim($_POST['observaciones_devolucion'] ?? '');

        $estadosPermitidos = [
            'Pendiente',
            'Activo',
            'Vencido',
            'Devuelto',
            'Cancelado'
        ];

        $estadosRecurso = ['Bueno', 'Regular', 'Dañado'];

        if (!$idPrestamo || !$idEmpleado || !$idUsuarioEntrega || !$idRecurso) {
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
            volverPrestamos('El estado seleccionado no es válido.');
        }

        if ($fechaReal !== '' && !fechaValida($fechaReal)) {
            volverPrestamos('La fecha de devolución real no es válida.');
        }

        if ($estado === 'Devuelto' && $fechaReal === '') {
            $fechaReal = date('Y-m-d');
        }

        if ($estado !== 'Devuelto' && $fechaReal !== '') {
            volverPrestamos('La fecha real solo debe registrarse cuando el préstamo esté devuelto.');
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

        if (strlen($observaciones) > 500 || strlen($observacionesDevolucion) > 500) {
            volverPrestamos('Las observaciones no pueden superar los 500 caracteres.');
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

        validarEmpleado($conexion, $idEmpleado);
        validarUsuarioReceptor($conexion, $idUsuarioEntrega);

        $consultaRecurso = $conexion->prepare("
            SELECT id_recurso, disponibilidad, estado
            FROM recurso
            WHERE id_recurso = :id
            FOR UPDATE
        ");

        $consultaRecurso->execute([':id' => $idRecurso]);

        $recurso = $consultaRecurso->fetch(PDO::FETCH_ASSOC);

        if (!$recurso) {
            throw new Exception('El recurso seleccionado no existe.');
        }

        $ocupaRecurso = in_array($estado, ['Activo', 'Vencido'], true);

        if ($ocupaRecurso) {
            $esMismoRecurso = (int)$anterior['id_recurso'] === $idRecurso;

            if (!$esMismoRecurso && $recurso['disponibilidad'] !== 'Disponible') {
                throw new Exception('El nuevo recurso no está disponible.');
            }

            if ($recurso['estado'] === 'Dañado') {
                throw new Exception('No se puede asignar un recurso dañado.');
            }

            if (recursoTienePrestamoActivo($conexion, $idRecurso, $idPrestamo)) {
                throw new Exception('El recurso ya tiene otro préstamo activo.');
            }
        }

        $consulta = $conexion->prepare("
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

        $consulta->execute([
            ':empleado' => $idEmpleado,
            ':usuario' => $idUsuarioEntrega,
            ':recurso' => $idRecurso,
            ':solicitud' => $fechaSolicitud,
            ':entrega' => $fechaEntrega,
            ':programada' => $fechaProgramada,
            ':fecha_real' => $fechaReal !== '' ? $fechaReal : null,
            ':estado' => $estado,
            ':estado_entrega' => $estadoRecursoEntrega !== '' ? $estadoRecursoEntrega : null,
            ':estado_devolucion' => $estadoRecursoDevolucion !== '' ? $estadoRecursoDevolucion : null,
            ':observaciones' => $observaciones,
            ':observaciones_devolucion' => $observacionesDevolucion,
            ':id' => $idPrestamo
        ]);

        actualizarDisponibilidad($conexion, (int)$anterior['id_recurso']);

        if ((int)$anterior['id_recurso'] !== $idRecurso) {
            actualizarDisponibilidad($conexion, $idRecurso);
        } else {
            actualizarDisponibilidad($conexion, $idRecurso);
        }

        $conexion->commit();

        volverPrestamos('El préstamo se actualizó correctamente.');
    }

    elseif ($accion === 'eliminar') {

        $idPrestamo = filter_var(
            $_POST['id_prestamo'] ?? '',
            FILTER_VALIDATE_INT
        );

        if (!$idPrestamo) {
            volverPrestamos('El identificador del préstamo no es válido.');
        }

        $conexion->beginTransaction();

        $consulta = $conexion->prepare("
            SELECT id_prestamo, id_recurso
            FROM prestamo
            WHERE id_prestamo = :id
            FOR UPDATE
        ");

        $consulta->execute([':id' => $idPrestamo]);

        $prestamo = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$prestamo) {
            throw new Exception('El préstamo que intentas eliminar no existe.');
        }

        $bloquearRecurso = $conexion->prepare("
            SELECT id_recurso
            FROM recurso
            WHERE id_recurso = :id
            FOR UPDATE
        ");

        $bloquearRecurso->execute([
            ':id' => $prestamo['id_recurso']
        ]);

        if (!$bloquearRecurso->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('No se encontró el recurso asociado.');
        }

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
        ? 'No se pudo completar la operación. Verifica los campos y las relaciones de la base de datos.'
        : $e->getMessage();

    volverPrestamos($mensaje);
}

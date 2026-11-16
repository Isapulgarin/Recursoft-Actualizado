
<?php

require_once __DIR__ . '/../includes/auth.php';

$usuarioSesion = rs_requiere_admin();
$conexion = rs_pdo();

$pageTitle = 'Préstamos';
$dashboardType = 'AD';
$activePage = 'prestamos';

function h($valor)
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/* =====================================================
   SESIÓN Y RESPONSABLE AUTENTICADO
===================================================== */

$idRolSesion = (int)($usuarioSesion['id_rol'] ?? 0);
$idCuentaSesion = (int)($usuarioSesion['id_cuenta'] ?? 0);

if ($idRolSesion !== 1 || $idCuentaSesion <= 0) {
    http_response_code(403);
    exit('No tienes permisos para acceder a esta página.');
}

/* =====================================================
   DATOS DEL RESPONSABLE DE LA SESIÓN
===================================================== */

$stmtResponsableSesion = $conexion->prepare("
    SELECT
        ca.id_cuenta,
        ca.nombre_usuario,
        ca.correo,
        ca.id_rol,

        CASE
            WHEN ca.id_rol = 1 THEN 'Administrador'
            WHEN ca.id_rol = 2 THEN 'Empleado'
            ELSE 'Usuario'
        END AS rol_nombre,

        CASE
            WHEN ca.id_rol = 1 THEN
                COALESCE(
                    NULLIF(TRIM(CONCAT(a.nombre, ' ', a.apellido)), ''),
                    ca.nombre_usuario
                )
            WHEN ca.id_rol = 2 THEN
                COALESCE(
                    NULLIF(TRIM(CONCAT(e.nombre, ' ', e.apellido)), ''),
                    ca.nombre_usuario
                )
            ELSE ca.nombre_usuario
        END AS nombre_responsable,

        CASE
            WHEN ca.id_rol = 1 THEN a.telefono
            WHEN ca.id_rol = 2 THEN e.telefono
            ELSE NULL
        END AS telefono_responsable,

        CASE
            WHEN ca.id_rol = 1 THEN 'Administrador'
            WHEN ca.id_rol = 2 THEN e.cargo
            ELSE 'Usuario'
        END AS cargo_responsable

    FROM cuenta_acceso ca

    LEFT JOIN administrador a
        ON a.id_cuenta = ca.id_cuenta

    LEFT JOIN empleado e
        ON e.id_empleado = ca.id_empleado

    WHERE ca.id_cuenta = :id_cuenta
      AND ca.estado = 'Activo'

    LIMIT 1
");

$stmtResponsableSesion->execute([
    ':id_cuenta' => $idCuentaSesion
]);

$responsableSesion = $stmtResponsableSesion->fetch(PDO::FETCH_ASSOC);

if (!$responsableSesion) {
    http_response_code(403);
    exit('No se pudieron verificar los datos de la cuenta autenticada.');
}

/* =====================================================
   PERSONAS QUE RECIBEN LOS RECURSOS
===================================================== */

$stmtUsuarios = $conexion->prepare("
    SELECT
        id_usuario,
        nombre,
        apellido,
        tipo_usuario,
        correo,
        telefono,
        estado
    FROM usuario
    WHERE estado = 'Activo'
      AND tipo_usuario IS NOT NULL
      AND tipo_usuario <> ''
    ORDER BY nombre ASC
");

$stmtUsuarios->execute();

$usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   RECURSOS
===================================================== */

$stmtRecursos = $conexion->prepare("
    SELECT
        id_recurso,
        codigo_interno,
        nombre_recurso,
        stock_total,
        stock_disponible,
        estado,
        disponibilidad
    FROM recurso
    ORDER BY nombre_recurso ASC
");

$stmtRecursos->execute();

$recursos = $stmtRecursos->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   FILTROS
===================================================== */

$buscar = trim($_GET['buscar'] ?? '');
$estadoFiltro = trim($_GET['estado'] ?? '');
$fechaDesde = trim($_GET['desde'] ?? '');

$estadosPermitidos = [
    'Activo',
    'Pendiente',
    'Vencido',
    'Devuelto',
    'Cancelado'
];

if (!in_array($estadoFiltro, $estadosPermitidos, true)) {
    $estadoFiltro = '';
}

$formatoFecha = DateTime::createFromFormat('!Y-m-d', $fechaDesde);

if (
    $fechaDesde !== '' &&
    (!$formatoFecha || $formatoFecha->format('Y-m-d') !== $fechaDesde)
) {
    $fechaDesde = '';
}

$where = [];
$params = [];

if ($buscar !== '') {

    $where[] = "(
        CONCAT(u.nombre, ' ', u.apellido) LIKE :buscar1
        OR CONCAT(e.nombre, ' ', e.apellido) LIKE :buscar2
        OR r.nombre_recurso LIKE :buscar3
        OR r.codigo_interno LIKE :buscar4
        OR CAST(p.id_prestamo AS CHAR) LIKE :buscar5
        OR ca.correo LIKE :buscar6
        OR ca.nombre_usuario LIKE :buscar7
        OR CONCAT(a.nombre, ' ', a.apellido) LIKE :buscar8
        OR CONCAT(er.nombre, ' ', er.apellido) LIKE :buscar9
    )";

    $valorBuscar = "%{$buscar}%";

    $params[':buscar1'] = $valorBuscar;
    $params[':buscar2'] = $valorBuscar;
    $params[':buscar3'] = $valorBuscar;
    $params[':buscar4'] = $valorBuscar;
    $params[':buscar5'] = $valorBuscar;
    $params[':buscar6'] = $valorBuscar;
    $params[':buscar7'] = $valorBuscar;
    $params[':buscar8'] = $valorBuscar;
    $params[':buscar9'] = $valorBuscar;
}

if ($estadoFiltro !== '') {
    $where[] = 'p.estado = :estado';
    $params[':estado'] = $estadoFiltro;
}

if ($fechaDesde !== '') {
    $where[] = 'p.fecha_solicitud >= :desde';
    $params[':desde'] = $fechaDesde;
}

$sqlWhere = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

/* =====================================================
   LISTADO DE PRÉSTAMOS
===================================================== */

$sqlPrestamos = "
    SELECT
        p.id_prestamo,
        p.id_empleado,
        p.id_usuario_entrega,
        p.id_cuenta_responsable,
        p.id_recurso,
        p.cantidad,
        p.fecha_solicitud,
        p.fecha_entrega,
        p.fecha_devolucion_programada,
        p.fecha_devolucion_real,
        p.estado,
        p.estado_recurso_entrega,
        p.estado_recurso_devolucion,
        p.observaciones,
        p.observaciones_devolucion,

        /* PERSONA RECEPTORA */

        u.nombre AS receptor_nombre,
        u.apellido AS receptor_apellido,
        u.tipo_usuario AS receptor_tipo,
        u.correo AS receptor_correo,
        u.telefono AS receptor_telefono,

        /* EMPLEADO ASOCIADO */

        e.nombre AS empleado_nombre,
        e.apellido AS empleado_apellido,
        e.cargo AS empleado_cargo,

        /* CUENTA RESPONSABLE */

        ca.id_cuenta AS responsable_id_cuenta,
        ca.nombre_usuario AS responsable_usuario,
        ca.correo AS responsable_correo,
        ca.id_rol AS responsable_id_rol,

        CASE
            WHEN ca.id_rol = 1 THEN
                COALESCE(
                    NULLIF(TRIM(CONCAT(a.nombre, ' ', a.apellido)), ''),
                    ca.nombre_usuario
                )

            WHEN ca.id_rol = 2 THEN
                COALESCE(
                    NULLIF(TRIM(CONCAT(er.nombre, ' ', er.apellido)), ''),
                    ca.nombre_usuario
                )

            ELSE
                COALESCE(ca.nombre_usuario, 'No registrado')
        END AS responsable_nombre,

        CASE
            WHEN ca.id_rol = 1 THEN 'Administrador'
            WHEN ca.id_rol = 2 THEN 'Empleado'
            ELSE 'No registrado'
        END AS responsable_rol,

        CASE
            WHEN ca.id_rol = 1 THEN 'Administrador'
            WHEN ca.id_rol = 2 THEN er.cargo
            ELSE NULL
        END AS responsable_cargo,

        CASE
            WHEN ca.id_rol = 1 THEN a.telefono
            WHEN ca.id_rol = 2 THEN er.telefono
            ELSE NULL
        END AS responsable_telefono,

        /* RECURSO */

        r.nombre_recurso,
        r.codigo_interno

    FROM prestamo p

    LEFT JOIN usuario u
        ON p.id_usuario_entrega = u.id_usuario

    LEFT JOIN empleado e
        ON p.id_empleado = e.id_empleado

    LEFT JOIN cuenta_acceso ca
        ON p.id_cuenta_responsable = ca.id_cuenta

    LEFT JOIN administrador a
        ON a.id_cuenta = ca.id_cuenta

    LEFT JOIN empleado er
        ON er.id_empleado = ca.id_empleado

    LEFT JOIN recurso r
        ON p.id_recurso = r.id_recurso

    $sqlWhere

    ORDER BY p.id_prestamo DESC
";

$stmtPrestamos = $conexion->prepare($sqlPrestamos);
$stmtPrestamos->execute($params);

$prestamos = $stmtPrestamos->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   CONTADORES
===================================================== */

$totalPrestamos = count($prestamos);
$activos = 0;
$pendientes = 0;
$vencidos = 0;

foreach ($prestamos as $p) {

    if ($p['estado'] === 'Activo') {
        $activos++;
    }

    if ($p['estado'] === 'Pendiente') {
        $pendientes++;
    }

    if ($p['estado'] === 'Vencido') {
        $vencidos++;
    }
}

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/slider.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<link rel="stylesheet" href="../CSS/prestamos.css">

<main class="main-content">

<div class="prestamos-page">

    <div class="page-heading">

        <div>
            <h1>Gestión de préstamos</h1>
            <p>Administra la entrega y devolución de los recursos institucionales.</p>
        </div>

        <button
            type="button"
            class="btn btn-primary btn-prestamo-principal"
            data-bs-toggle="modal"
            data-bs-target="#modalPrestamo"
            onclick="prepararNuevoPrestamo()">

            <i class="bi bi-plus-circle"></i>
            Registrar préstamo

        </button>

    </div>

    <?php if (!empty($_GET['mensaje'])): ?>

        <div class="alert alert-info alert-dismissible fade show">

            <?= h($_GET['mensaje']) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>

    <div class="alert alert-light border">

        <i class="bi bi-shield-check"></i>

        <strong>Sesión de administrador:</strong>

        <?= h($responsableSesion['nombre_responsable']) ?>

        <span class="text-muted">
            (<?= h($responsableSesion['correo']) ?>)
        </span>

        Tienes acceso a todos los préstamos.

    </div>

    <!-- RESUMEN -->

    <div class="resumen-prestamos">

        <div class="prestamo-card">

            <div class="prestamo-icono">
                <i class="bi bi-journal-text"></i>
            </div>

            <div>
                <span>Total de préstamos</span>
                <h3><?= $totalPrestamos ?></h3>
            </div>

        </div>

        <div class="prestamo-card">

            <div class="prestamo-icono">
                <i class="bi bi-box-arrow-up-right"></i>
            </div>

            <div>
                <span>Activos</span>
                <h3><?= $activos ?></h3>
            </div>

        </div>

        <div class="prestamo-card">

            <div class="prestamo-icono">
                <i class="bi bi-clock-history"></i>
            </div>

            <div>
                <span>Pendientes</span>
                <h3><?= $pendientes ?></h3>
            </div>

        </div>

        <div class="prestamo-card">

            <div class="prestamo-icono">
                <i class="bi bi-exclamation-circle"></i>
            </div>

            <div>
                <span>Vencidos</span>
                <h3><?= $vencidos ?></h3>
            </div>

        </div>

    </div>

    <!-- FILTROS -->

    <section class="filtros-prestamos">

        <div class="filtros-prestamos-header">

            <h5 class="filtros-prestamos-titulo">
                <i class="bi bi-funnel"></i>
                Filtrar préstamos
            </h5>

        </div>

        <form method="GET" class="row g-3 align-items-end">

            <div class="col-md-5">

                <label class="form-label">Buscar</label>

                <input
                    type="text"
                    name="buscar"
                    class="form-control"
                    placeholder="Persona, responsable o recurso..."
                    value="<?= h($buscar) ?>">

            </div>

            <div class="col-md-3">

                <label class="form-label">Fecha desde</label>

                <input
                    type="date"
                    name="desde"
                    class="form-control"
                    value="<?= h($fechaDesde) ?>">

            </div>

            <div class="col-md-2">

                <label class="form-label">Estado</label>

                <select name="estado" class="form-select">

                    <option value="">Todos</option>

                    <?php foreach ($estadosPermitidos as $estadoOpcion): ?>

                        <option
                            value="<?= h($estadoOpcion) ?>"
                            <?= $estadoFiltro === $estadoOpcion ? 'selected' : '' ?>>

                            <?= h($estadoOpcion) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-2 d-flex gap-2">

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i>
                </button>

                <a href="prestamos.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>

            </div>

        </form>

    </section>

    <!-- TABLA -->

    <section class="tabla-prestamos-contenedor">

        <div class="tabla-prestamos-header">

            <h5>Listado de préstamos</h5>

            <span class="contador-prestamos">
                <?= $totalPrestamos ?> registros
            </span>

        </div>

        <div class="table-responsive">

            <table class="table tabla-prestamos align-middle">

                <thead>

                    <tr>
                        <th>Código</th>
                        <th>Persona que recibe</th>
                        <th>Recurso</th>
                        <th>Fecha de entrega</th>
                        <th>Devolución programada</th>
                        <th>Entregado por</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (empty($prestamos)): ?>

                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No hay préstamos registrados.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($prestamos as $p): ?>

                        <?php
                        $claseEstado = strtolower($p['estado'] ?? '');

                        $nombreResponsable = $p['responsable_nombre']
                            ?? 'No registrado';
                        ?>

                        <tr>

                            <td>
                                <strong>#<?= h($p['id_prestamo']) ?></strong>
                            </td>

                            <td>

                                <div class="identidad-prestamo">

                                    <strong>
                                        <?= h(
                                            ($p['receptor_nombre'] ?? '') . ' ' .
                                            ($p['receptor_apellido'] ?? '')
                                        ) ?>
                                    </strong>

                                    <small><?= h($p['receptor_tipo']) ?></small>
                                    <small><?= h($p['receptor_correo']) ?></small>
                                    <small><?= h($p['receptor_telefono']) ?></small>

                                </div>

                            </td>

                            <td>

                                <div class="recurso-prestamo">

                                    <strong><?= h($p['nombre_recurso']) ?></strong>

                                    <small>
                                        Código: <?= h($p['codigo_interno']) ?>
                                    </small>

                                    <small>
                                        Cantidad: <?= h($p['cantidad']) ?>
                                    </small>

                                </div>

                            </td>

                            <td><?= h($p['fecha_entrega']) ?></td>

                            <td><?= h($p['fecha_devolucion_programada']) ?></td>

                            <td>

                                <div class="identidad-prestamo">

                                    <strong><?= h($nombreResponsable) ?></strong>

                                    <small>
                                        <?= h($p['responsable_rol'] ?? 'No registrado') ?>
                                    </small>

                                    <?php if (!empty($p['responsable_correo'])): ?>
                                        <small><?= h($p['responsable_correo']) ?></small>
                                    <?php endif; ?>

                                    <?php if (!empty($p['responsable_telefono'])): ?>
                                        <small><?= h($p['responsable_telefono']) ?></small>
                                    <?php endif; ?>

                                    <?php if (!empty($p['responsable_cargo'])): ?>
                                        <small><?= h($p['responsable_cargo']) ?></small>
                                    <?php endif; ?>

                                    <?php if (empty($p['responsable_id_cuenta'])): ?>
                                        <small class="text-muted">
                                            Responsable histórico no registrado
                                        </small>
                                    <?php endif; ?>

                                </div>

                            </td>

                            <td>

                                <span class="estado-prestamo <?= h($claseEstado) ?>">
                                    <?= h($p['estado']) ?>
                                </span>

                            </td>

                            <td>

                                <div class="acciones-prestamo">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        title="Editar préstamo"
                                        onclick='editarPrestamo(<?= json_encode(
                                            $p,
                                            JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_TAG |
                                            JSON_HEX_AMP
                                        ) ?>)'>

                                        <i class="bi bi-pencil-square"></i>

                                    </button>

                                    <?php if (in_array($p['estado'], ['Pendiente', 'Cancelado'], true)): ?>

                                        <form
                                            action="../controllers/PrestamoController.php"
                                            method="POST"
                                            onsubmit="return confirm('¿Estás seguro de eliminar este préstamo?');">

                                            <?= rs_csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="eliminar">

                                            <input
                                                type="hidden"
                                                name="id_prestamo"
                                                value="<?= h($p['id_prestamo']) ?>">

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Eliminar préstamo">

                                                <i class="bi bi-trash"></i>

                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>

</main>

<!-- MODAL -->

<div
    class="modal fade modal-prestamo"
    id="modalPrestamo"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content">

            <form
                action="../controllers/PrestamoController.php"
                method="POST">

                <?= rs_csrf_field() ?>

                <div class="modal-header">

                    <div>
                        <h5 class="modal-title" id="tituloModalPrestamo">
                            Registrar préstamo
                        </h5>

                        <small>Completa la información del préstamo.</small>
                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="accion"
                        id="accionPrestamo"
                        value="guardar">

                    <input
                        type="hidden"
                        name="id_prestamo"
                        id="id_prestamo">

                    <h6 class="mb-3">
                        <i class="bi bi-person"></i>
                        Información de quien recibe el recurso
                    </h6>

                    <div class="mb-3">

                        <label for="id_usuario_entrega" class="form-label">
                            Seleccionar persona *
                        </label>

                        <select
                            name="id_usuario_entrega"
                            id="id_usuario_entrega"
                            class="form-select"
                            required>

                            <option value="">Seleccionar persona</option>

                            <?php foreach ($usuarios as $usuario): ?>

                                <option value="<?= h($usuario['id_usuario']) ?>">

                                    <?= h($usuario['nombre'] . ' ' . $usuario['apellido']) ?>
                                    - <?= h($usuario['tipo_usuario']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="row g-3 mb-4">

                        <div class="col-md-6">
                            <label class="form-label">Tipo de usuario</label>
                            <input type="text" id="receptorTipo" class="form-control" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Correo</label>
                            <input type="text" id="receptorCorreo" class="form-control" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" id="receptorTelefono" class="form-control" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <input type="text" id="receptorEstado" class="form-control" readonly>
                        </div>

                    </div>

                    <hr>

                    <h6 class="mb-3">
                        <i class="bi bi-person-check"></i>
                        Información de quien entrega el recurso
                    </h6>

                    <div class="alert alert-light border">

                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-person-badge fs-4"></i>
                            <strong>Responsable de la sesión actual</strong>
                        </div>

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label">Nombre</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= h($responsableSesion['nombre_responsable']) ?>"
                                    readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Rol</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= h($responsableSesion['rol_nombre']) ?>"
                                    readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Correo</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= h($responsableSesion['correo']) ?>"
                                    readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Teléfono</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= h($responsableSesion['telefono_responsable'] ?? 'No registrado') ?>"
                                    readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Cargo</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= h($responsableSesion['cargo_responsable'] ?? 'No registrado') ?>"
                                    readonly>
                            </div>

                        </div>

                    </div>

                    <hr>

                    <h6 class="mb-3">
                        <i class="bi bi-box-seam"></i>
                        Información del recurso
                    </h6>

                    <div class="mb-3">

                        <label for="id_recurso" class="form-label">
                            Recurso *
                        </label>

                        <select
                            name="id_recurso"
                            id="id_recurso"
                            class="form-select"
                            required>

                            <option value="">Seleccionar recurso</option>

                            <?php foreach ($recursos as $recurso): ?>

                                <option
                                    value="<?= h($recurso['id_recurso']) ?>"
                                    data-disponibilidad="<?= h($recurso['disponibilidad']) ?>"
                                    data-disponible="<?= h($recurso['stock_disponible']) ?>">

                                    <?= h($recurso['nombre_recurso']) ?>
                                    - <?= h($recurso['codigo_interno']) ?>
                                    (<?= h($recurso['stock_disponible']) ?> disponibles)

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <small class="text-muted">
                            El sistema comprobará la disponibilidad al guardar.
                        </small>

                    </div>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="cantidad" class="form-label">
                                Cantidad de unidades *
                            </label>

                            <input
                                type="number"
                                name="cantidad"
                                id="cantidad"
                                class="form-control"
                                min="1"
                                step="1"
                                value="1"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label for="estado_recurso_entrega" class="form-label">
                                Estado al entregar
                            </label>

                            <select
                                name="estado_recurso_entrega"
                                id="estado_recurso_entrega"
                                class="form-select">

                                <option value="">Seleccionar estado</option>
                                <option value="Bueno">Bueno</option>
                                <option value="Regular">Regular</option>
                                <option value="Dañado">Dañado</option>

                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="fecha_solicitud" class="form-label">
                                Fecha de solicitud *
                            </label>

                            <input
                                type="date"
                                name="fecha_solicitud"
                                id="fecha_solicitud"
                                class="form-control"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label for="fecha_entrega" class="form-label">
                                Fecha de entrega *
                            </label>

                            <input
                                type="date"
                                name="fecha_entrega"
                                id="fecha_entrega"
                                class="form-control"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label for="fecha_devolucion_programada" class="form-label">
                                Fecha de devolución programada *
                            </label>

                            <input
                                type="date"
                                name="fecha_devolucion_programada"
                                id="fecha_devolucion_programada"
                                class="form-control"
                                required>
                        </div>

                    </div>

                    <div class="mt-3" id="grupoFechaReal" style="display:none;">

                        <label for="fecha_devolucion_real" class="form-label">
                            Fecha de devolución real
                        </label>

                        <input
                            type="date"
                            name="fecha_devolucion_real"
                            id="fecha_devolucion_real"
                            class="form-control">

                    </div>

                    <div class="mt-3" id="grupoEstadoPrestamo" style="display:none;">

                        <label for="estado" class="form-label">
                            Estado del préstamo
                        </label>

                        <select name="estado" id="estado" class="form-select">
                            <option value="Pendiente">Pendiente</option>
                            <option value="Activo">Activo</option>
                            <option value="Vencido">Vencido</option>
                            <option value="Devuelto">Devuelto</option>
                            <option value="Cancelado">Cancelado</option>
                        </select>

                    </div>

                    <div class="mt-3" id="grupoEstadoDevolucion" style="display:none;">

                        <label for="estado_recurso_devolucion" class="form-label">
                            Estado del recurso al devolverlo
                        </label>

                        <select
                            name="estado_recurso_devolucion"
                            id="estado_recurso_devolucion"
                            class="form-select">

                            <option value="">Seleccionar estado</option>
                            <option value="Bueno">Bueno</option>
                            <option value="Regular">Regular</option>
                            <option value="Dañado">Dañado</option>

                        </select>

                    </div>

                    <div class="mt-3">

                        <label for="observaciones" class="form-label">
                            Observaciones del préstamo
                        </label>

                        <textarea
                            name="observaciones"
                            id="observaciones"
                            class="form-control"
                            rows="3"
                            maxlength="150"
                            placeholder="Información adicional..."></textarea>

                    </div>

                    <div
                        class="mt-3"
                        id="grupoObservacionesDevolucion"
                        style="display:none;">

                        <label for="observaciones_devolucion" class="form-label">
                            Observaciones de devolución
                        </label>

                        <textarea
                            name="observaciones_devolucion"
                            id="observaciones_devolucion"
                            class="form-control"
                            rows="3"
                            maxlength="500"
                            placeholder="Novedades al devolver el recurso..."></textarea>

                    </div>

                    <div class="aviso-prestamo mt-3">

                        <i class="bi bi-info-circle"></i>

                        La persona receptora se selecciona de los usuarios registrados.
                        El responsable se identifica automáticamente según la cuenta que inició sesión.

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-prestamo-cancelar"
                        data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary btn-prestamo-principal">

                        <i class="bi bi-save"></i>
                        Guardar préstamo

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<script>

const usuariosRecursoft = <?= json_encode(
    $usuarios,
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_TAG |
    JSON_HEX_AMP
) ?>;

const selectorReceptor = document.getElementById('id_usuario_entrega');

function mostrarDatosReceptor(idUsuario) {

    const usuario = usuariosRecursoft.find(
        u => String(u.id_usuario) === String(idUsuario)
    );

    document.getElementById('receptorTipo').value =
        usuario ? usuario.tipo_usuario || '' : '';

    document.getElementById('receptorCorreo').value =
        usuario ? usuario.correo || '' : '';

    document.getElementById('receptorTelefono').value =
        usuario ? usuario.telefono || '' : '';

    document.getElementById('receptorEstado').value =
        usuario ? usuario.estado || '' : '';
}

selectorReceptor.addEventListener('change', function () {
    mostrarDatosReceptor(this.value);
});

function obtenerFechaLocal() {

    const hoy = new Date();

    return new Date(
        hoy.getTime() - hoy.getTimezoneOffset() * 60000
    ).toISOString().slice(0, 10);
}

function prepararNuevoPrestamo() {

    document.getElementById('tituloModalPrestamo').textContent =
        'Registrar préstamo';

    document.getElementById('accionPrestamo').value = 'guardar';
    document.getElementById('id_prestamo').value = '';

    selectorReceptor.value = '';
    mostrarDatosReceptor('');

    document.getElementById('id_recurso').value = '';
    document.getElementById('cantidad').value = '1';

    const fechaLocal = obtenerFechaLocal();

    document.getElementById('fecha_solicitud').value = fechaLocal;
    document.getElementById('fecha_entrega').value = fechaLocal;
    document.getElementById('fecha_devolucion_programada').value = '';

    document.getElementById('fecha_devolucion_real').value = '';
    document.getElementById('estado_recurso_entrega').value = '';
    document.getElementById('estado_recurso_devolucion').value = '';
    document.getElementById('observaciones').value = '';
    document.getElementById('observaciones_devolucion').value = '';

    document.getElementById('grupoEstadoPrestamo').style.display = 'none';
    document.getElementById('grupoFechaReal').style.display = 'none';
    document.getElementById('grupoEstadoDevolucion').style.display = 'none';
    document.getElementById('grupoObservacionesDevolucion').style.display = 'none';

    document.getElementById('estado').value = 'Activo';
}

function editarPrestamo(p) {

    prepararNuevoPrestamo();

    document.getElementById('tituloModalPrestamo').textContent =
        'Editar préstamo';

    document.getElementById('accionPrestamo').value = 'editar';
    document.getElementById('id_prestamo').value = p.id_prestamo;

    selectorReceptor.value = p.id_usuario_entrega || '';
    mostrarDatosReceptor(p.id_usuario_entrega);

    document.getElementById('id_recurso').value = p.id_recurso || '';
    document.getElementById('cantidad').value = p.cantidad || '1';

    document.getElementById('fecha_solicitud').value =
        p.fecha_solicitud || '';

    document.getElementById('fecha_entrega').value =
        p.fecha_entrega || '';

    document.getElementById('fecha_devolucion_programada').value =
        p.fecha_devolucion_programada || '';

    document.getElementById('fecha_devolucion_real').value =
        p.fecha_devolucion_real || '';

    document.getElementById('estado').value =
        p.estado || 'Activo';

    document.getElementById('estado_recurso_entrega').value =
        p.estado_recurso_entrega || '';

    document.getElementById('estado_recurso_devolucion').value =
        p.estado_recurso_devolucion || '';

    document.getElementById('observaciones').value =
        p.observaciones || '';

    document.getElementById('observaciones_devolucion').value =
        p.observaciones_devolucion || '';

    document.getElementById('grupoEstadoPrestamo').style.display = 'block';
    document.getElementById('grupoFechaReal').style.display = 'block';
    document.getElementById('grupoEstadoDevolucion').style.display = 'block';
    document.getElementById('grupoObservacionesDevolucion').style.display = 'block';

    const modal = bootstrap.Modal.getOrCreateInstance(
        document.getElementById('modalPrestamo')
    );

    modal.show();
}

</script>

<?php

require_once __DIR__ . '/../includes/footer.php';
require_once __DIR__ . '/../includes/scripts.php';

?>

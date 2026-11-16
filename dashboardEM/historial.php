<?php
require_once __DIR__ . '/../includes/auth.php';
$usuarioSesion = rs_requiere_empleado();
$empleadoId = (int) $usuarioSesion['id_empleado'];

$desde = trim((string) ($_GET['desde'] ?? ''));
$hasta = trim((string) ($_GET['hasta'] ?? ''));
$validarFecha = static function (string $fecha): bool {
    $formato = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $formato && $formato->format('Y-m-d') === $fecha;
};
if ($desde !== '' && !$validarFecha($desde)) {
    $desde = '';
}
if ($hasta !== '' && !$validarFecha($hasta)) {
    $hasta = '';
}

$where = ['p.id_empleado = :empleado'];
$params = ['empleado' => $empleadoId];
if ($desde !== '') {
    $where[] = 'h.fecha_hora >= :desde';
    $params['desde'] = $desde . ' 00:00:00';
}
if ($hasta !== '') {
    $where[] = 'h.fecha_hora < DATE_ADD(:hasta, INTERVAL 1 DAY)';
    $params['hasta'] = $hasta;
}

$historial = rs_all(
    'SELECT h.id_historial, h.id_prestamo, h.fecha_hora, h.accion, h.detalle,
            r.codigo_interno, r.nombre_recurso,
            u.nombre AS receptor_nombre, u.apellido AS receptor_apellido
     FROM historial_prestamo h
     INNER JOIN prestamo p ON p.id_prestamo = h.id_prestamo
     LEFT JOIN recurso r ON r.id_recurso = p.id_recurso
     LEFT JOIN usuario u ON u.id_usuario = p.id_usuario_entrega
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY h.fecha_hora DESC, h.id_historial DESC',
    $params
);

$pageTitle = 'Mi Historial';
$dashboardType = 'EM';
$activePage = 'historial';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sliderEM.php';
require_once __DIR__ . '/../includes/navbarEM.php';
?>

<main class="main-content">
    <div class="page-heading section-gap">
        <h1>Mi historial</h1>
        <p>Consulta las operaciones de préstamo realizadas desde tu cuenta.</p>
    </div>

    <section class="card-rs toolbar-card section-gap">
        <form class="form-visual row g-2" method="GET">
            <div class="col-12 col-md-5">
                <label class="form-label" for="histEmpDesde">Desde</label>
                <input class="form-control" id="histEmpDesde" name="desde" type="date" value="<?= rs_h($desde) ?>">
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label" for="histEmpHasta">Hasta</label>
                <input class="form-control" id="histEmpHasta" name="hasta" type="date" value="<?= rs_h($hasta) ?>">
            </div>
            <div class="col-12 col-md-2 d-flex align-items-end">
                <button class="btn btn-rs w-100" type="submit">Ver</button>
            </div>
        </form>
    </section>

    <section class="card-rs">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Folio</th>
                        <th>Acción</th>
                        <th>Persona receptora</th>
                        <th>Recurso</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$historial): ?>
                        <?php rs_empty_table_row(6, 'No hay acciones registradas para el filtro seleccionado.'); ?>
                    <?php else: ?>
                        <?php foreach ($historial as $evento): ?>
                            <tr>
                                <td><?= rs_h($evento['fecha_hora']) ?></td>
                                <td>#<?= (int) $evento['id_prestamo'] ?></td>
                                <td><?= rs_h($evento['accion']) ?></td>
                                <td><?= rs_h(trim(($evento['receptor_nombre'] ?? '') . ' ' . ($evento['receptor_apellido'] ?? ''))) ?></td>
                                <td><?= rs_h(trim(($evento['codigo_interno'] ?? '') . ' - ' . ($evento['nombre_recurso'] ?? ''))) ?></td>
                                <td><?= rs_h($evento['detalle'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
require_once __DIR__ . '/../includes/scripts.php';

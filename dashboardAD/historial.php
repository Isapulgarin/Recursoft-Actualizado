<?php
require_once __DIR__ . '/../includes/auth.php';
rs_requiere_admin();

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

$where = [];
$params = [];
if ($desde !== '') {
    $where[] = 'h.fecha_hora >= :desde';
    $params['desde'] = $desde . ' 00:00:00';
}
if ($hasta !== '') {
    $where[] = 'h.fecha_hora < DATE_ADD(:hasta, INTERVAL 1 DAY)';
    $params['hasta'] = $hasta;
}

$sql = 'SELECT h.id_historial, h.id_prestamo, h.fecha_hora, h.accion, h.detalle,
               ca.correo, r.codigo_interno, r.nombre_recurso,
               u.nombre AS receptor_nombre, u.apellido AS receptor_apellido
        FROM historial_prestamo h
        LEFT JOIN cuenta_acceso ca ON ca.id_cuenta = h.id_cuenta_accion
        LEFT JOIN prestamo p ON p.id_prestamo = h.id_prestamo
        LEFT JOIN usuario u ON u.id_usuario = p.id_usuario_entrega
        LEFT JOIN recurso r ON r.id_recurso = p.id_recurso';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY h.fecha_hora DESC, h.id_historial DESC';
$historial = rs_all($sql, $params);

$pageTitle = 'Historial de Préstamos';
$dashboardType = 'AD';
$activePage = 'historial';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/slider.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="main-content">
    <div class="page-heading section-gap">
        <h1>Historial de préstamos</h1>
        <p>Consulta las acciones registradas en las operaciones de préstamo.</p>
    </div>

    <section class="card-rs toolbar-card section-gap">
        <form class="form-visual row g-2" method="GET">
            <div class="col-12 col-md-4">
                <label class="form-label" for="histDesde">Desde</label>
                <input class="form-control" id="histDesde" name="desde" type="date" value="<?= rs_h($desde) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="histHasta">Hasta</label>
                <input class="form-control" id="histHasta" name="hasta" type="date" value="<?= rs_h($hasta) ?>">
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end gap-2">
                <button class="btn btn-rs w-100" type="submit">Consultar</button>
                <a class="btn btn-rs-outline" href="historial.php">Limpiar</a>
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
                        <th>Cuenta responsable</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$historial): ?>
                        <?php rs_empty_table_row(7, 'No hay acciones registradas para el filtro seleccionado.'); ?>
                    <?php else: ?>
                        <?php foreach ($historial as $evento): ?>
                            <tr>
                                <td><?= rs_h($evento['fecha_hora']) ?></td>
                                <td>#<?= (int) $evento['id_prestamo'] ?></td>
                                <td><?= rs_h($evento['accion']) ?></td>
                                <td><?= rs_h(trim(($evento['receptor_nombre'] ?? '') . ' ' . ($evento['receptor_apellido'] ?? ''))) ?></td>
                                <td><?= rs_h(trim(($evento['codigo_interno'] ?? '') . ' - ' . ($evento['nombre_recurso'] ?? ''))) ?></td>
                                <td><?= rs_h($evento['correo'] ?? 'Cuenta no disponible') ?></td>
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

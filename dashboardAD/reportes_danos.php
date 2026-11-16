<?php
require_once __DIR__ . '/../includes/auth.php';

rs_requiere_admin();
$reportes = rs_all(
    'SELECT d.id_reporte_dano, d.tipo_dano, d.fecha_incidente,
            d.prioridad, d.descripcion, d.estado, d.fecha_reporte,
            r.codigo_interno, r.nombre_recurso, ca.correo
     FROM reporte_dano d
     INNER JOIN recurso r ON r.id_recurso = d.id_recurso
     INNER JOIN cuenta_acceso ca ON ca.id_cuenta = d.id_cuenta_reporta
     ORDER BY d.fecha_reporte DESC, d.id_reporte_dano DESC'
);

$pageTitle = 'Reportes de daños';
$dashboardType = 'AD';
$activePage = 'reportes_danos';

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/slider.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="main-content">
    <div class="page-heading section-gap">
        <div>
            <h1>Reportes de daños</h1>
            <p>Revisa las incidencias reportadas por los empleados.</p>
        </div>
    </div>

    <?php rs_flash_html(); ?>

    <section class="card-rs">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Recurso</th>
                        <th>Reportado por</th>
                        <th>Incidente</th>
                        <th>Prioridad</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Actualizar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$reportes): ?>
                        <?php rs_empty_table_row(8, 'No hay reportes de daños.'); ?>
                    <?php else: ?>
                        <?php foreach ($reportes as $reporte): ?>
                            <tr>
                                <td>#<?= (int) $reporte['id_reporte_dano'] ?></td>
                                <td><?= rs_h($reporte['codigo_interno'] . ' - ' . $reporte['nombre_recurso']) ?></td>
                                <td><?= rs_h($reporte['correo']) ?></td>
                                <td><?= rs_h($reporte['fecha_incidente'] ?? '') ?></td>
                                <td><?= rs_h($reporte['prioridad']) ?></td>
                                <td><?= nl2br(rs_h($reporte['descripcion'])) ?></td>
                                <td><?= rs_h($reporte['estado']) ?></td>
                                <td>
                                    <form method="POST" action="../controllers/ReporteDanoController.php"
                                          class="d-flex gap-2">
                                        <?= rs_csrf_field() ?>
                                        <input type="hidden" name="accion" value="actualizar">
                                        <input type="hidden" name="id_reporte_dano"
                                               value="<?= (int) $reporte['id_reporte_dano'] ?>">
                                        <select name="estado" class="form-select form-select-sm"
                                                aria-label="Estado del reporte">
                                            <?php foreach (['Reportado', 'En revisión', 'Atendido', 'Descartado'] as $estado): ?>
                                                <option value="<?= rs_h($estado) ?>"
                                                    <?= $reporte['estado'] === $estado ? 'selected' : '' ?>>
                                                    <?= rs_h($estado) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-primary" type="submit">Guardar</button>
                                    </form>
                                </td>
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

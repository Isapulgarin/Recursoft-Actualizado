<?php
require_once __DIR__ . '/../includes/auth.php';

$usuarioSesion = rs_requiere_empleado();
$conexion = rs_pdo();
$pageTitle = 'Reportar daños';
$dashboardType = 'EM';
$activePage = 'reporte_danos';

$recursos = rs_all(
    'SELECT id_recurso, codigo_interno, nombre_recurso
     FROM recurso
     ORDER BY nombre_recurso, codigo_interno'
);

$reportes = rs_all(
    'SELECT d.id_reporte_dano, d.tipo_dano, d.fecha_incidente,
            d.prioridad, d.descripcion, d.estado, d.fecha_reporte,
            r.codigo_interno, r.nombre_recurso
     FROM reporte_dano d
     INNER JOIN recurso r ON r.id_recurso = d.id_recurso
     WHERE d.id_cuenta_reporta = :cuenta
     ORDER BY d.fecha_reporte DESC, d.id_reporte_dano DESC',
    ['cuenta' => (int) $usuarioSesion['id_cuenta']]
);

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sliderEM.php';
require_once __DIR__ . '/../includes/navbarEM.php';
?>

<main class="main-content">
    <div class="page-heading section-gap">
        <h1>Reportar daños</h1>
        <p>Registra una incidencia para que el administrador pueda revisarla.</p>
    </div>

    <?php rs_flash_html(); ?>

    <section class="card-rs p-3 p-md-4 section-gap">
        <form class="form-visual row g-3" method="POST" action="../controllers/ReporteDanoController.php">
            <?= rs_csrf_field() ?>
            <input type="hidden" name="accion" value="crear">

            <div class="col-12 col-md-6">
                <label class="form-label" for="idRecursoDano">Recurso</label>
                <select class="form-select" id="idRecursoDano" name="id_recurso" required>
                    <option value="">Seleccione un recurso</option>
                    <?php foreach ($recursos as $recurso): ?>
                        <option value="<?= (int) $recurso['id_recurso'] ?>">
                            <?= rs_h($recurso['codigo_interno'] . ' - ' . $recurso['nombre_recurso']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="fechaIncidente">Fecha del incidente</label>
                <input class="form-control" id="fechaIncidente" name="fecha_incidente"
                       type="date" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="tipoDano">Tipo</label>
                <select class="form-select" id="tipoDano" name="tipo_dano" required>
                    <option value="Fisico">Físico</option>
                    <option value="Funcional">Funcional</option>
                    <option value="Accesorio perdido">Pérdida de accesorio</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" for="prioridadDano">Prioridad</label>
                <select class="form-select" id="prioridadDano" name="prioridad" required>
                    <option value="Baja">Baja</option>
                    <option value="Media" selected>Media</option>
                    <option value="Alta">Alta</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label" for="descDano">Descripción</label>
                <textarea class="form-control" id="descDano" name="descripcion"
                          rows="4" maxlength="2000" minlength="10"
                          placeholder="Describa el daño observado" required></textarea>
            </div>

            <div class="col-12">
                <button class="btn btn-rs" type="submit">Enviar reporte</button>
            </div>
        </form>
    </section>

    <section class="card-rs">
        <div class="card-header py-3">Mis reportes</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Recurso</th>
                        <th>Incidente</th>
                        <th>Prioridad</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$reportes): ?>
                        <?php rs_empty_table_row(6, 'Todavía no has enviado reportes.'); ?>
                    <?php else: ?>
                        <?php foreach ($reportes as $reporte): ?>
                            <tr>
                                <td>#<?= (int) $reporte['id_reporte_dano'] ?></td>
                                <td><?= rs_h($reporte['codigo_interno'] . ' - ' . $reporte['nombre_recurso']) ?></td>
                                <td><?= rs_h($reporte['fecha_incidente'] ?? '') ?></td>
                                <td><?= rs_h($reporte['prioridad']) ?></td>
                                <td><?= nl2br(rs_h($reporte['descripcion'])) ?></td>
                                <td><?= rs_h($reporte['estado']) ?></td>
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

<?php

require_once __DIR__ . '/../includes/auth.php';
rs_requiere_admin();

$pageTitle = 'Recursos';
$dashboardType = 'AD';
$activePage = 'recursos';

require_once __DIR__ . '/../config/conexion.php';

$mensaje = $_GET['mensaje'] ?? '';
$buscar = trim((string) ($_GET['buscar'] ?? ''));
$categoriaFiltro = (string) ($_GET['categoria'] ?? '');
$estadoFiltro = (string) ($_GET['estado'] ?? '');
$disponibilidadFiltro = (string) ($_GET['disponibilidad'] ?? '');

$recursos = [];
$categorias = [];
$estadisticas = [
    'total' => 0,
    'disponibles' => 0,
    'prestados' => 0,
    'mantenimiento' => 0,
];
$errorConsulta = false;

$estadosValidos = ['', 'Bueno', 'Regular', 'Dañado'];
$disponibilidadesValidas = ['', 'Disponible', 'Prestado', 'Mantenimiento', 'Fuera de servicio'];

if (!in_array($estadoFiltro, $estadosValidos, true)) {
    $estadoFiltro = '';
}

if (!in_array($disponibilidadFiltro, $disponibilidadesValidas, true)) {
    $disponibilidadFiltro = '';
}

if ($categoriaFiltro !== '' && !ctype_digit((string) $categoriaFiltro)) {
    $categoriaFiltro = '';
}

function rs_h($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rs_csrf_ok()) {
        rs_flash('error', 'La sesión del formulario expiró. Vuelva a intentarlo.');
        rs_redirect('dashboardAD/recursos.php');
    }

    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'guardar' || $accion === 'editar') {
            $codigoInterno = trim((string) ($_POST['codigo_interno'] ?? ''));
            $nombreRecurso = trim((string) ($_POST['nombre_recurso'] ?? ''));
            $idCategoria = (int) ($_POST['id_categoria'] ?? 0);
            $stockTotal = (int) ($_POST['stock_total'] ?? 0);
            $stockDisponible = (int) ($_POST['stock_disponible'] ?? $stockTotal);
            $estado = trim((string) ($_POST['estado'] ?? ''));
            $disponibilidad = trim((string) ($_POST['disponibilidad'] ?? ''));
            $serial = trim((string) ($_POST['serial'] ?? ''));
            $marca = trim((string) ($_POST['marca'] ?? ''));
            $modelo = trim((string) ($_POST['modelo'] ?? ''));
            $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
            $fechaAdquisicion = trim((string) ($_POST['fecha_adquisicion'] ?? ''));

            if ($codigoInterno === '' || $nombreRecurso === '' || $idCategoria <= 0 || $stockTotal < 1) {
                throw new InvalidArgumentException('Completa los campos obligatorios del recurso.');
            }

            if (!in_array($estado, ['Bueno', 'Regular', 'Dañado'], true)) {
                throw new InvalidArgumentException('Selecciona un estado físico válido.');
            }

            if (!in_array($disponibilidad, ['Disponible', 'Prestado', 'Mantenimiento', 'Fuera de servicio'], true)) {
                throw new InvalidArgumentException('Selecciona una disponibilidad válida.');
            }

            if ($fechaAdquisicion !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaAdquisicion)) {
                throw new InvalidArgumentException('La fecha de adquisición no es válida.');
            }

            $stockDisponible = max(0, min($stockTotal, $stockDisponible));

            $sqlDuplicado = 'SELECT id_recurso FROM recurso WHERE codigo_interno = :codigo_interno';
            $paramsDuplicado = [':codigo_interno' => $codigoInterno];

            if ($accion === 'editar') {
                $idRecurso = (int) ($_POST['id_recurso'] ?? 0);
                if ($idRecurso <= 0) {
                    throw new InvalidArgumentException('No se indicó el recurso a editar.');
                }
                $sqlDuplicado .= ' AND id_recurso <> :id_recurso';
                $paramsDuplicado[':id_recurso'] = $idRecurso;
            }

            $sqlDuplicado .= ' LIMIT 1';
            $duplicado = $conexion->prepare($sqlDuplicado);
            $duplicado->execute($paramsDuplicado);

            if ($duplicado->fetchColumn()) {
                throw new InvalidArgumentException('Ya existe un recurso con ese código interno.');
            }

            if ($accion === 'guardar') {
                $stmt = $conexion->prepare("
                    INSERT INTO recurso (
                        id_categoria,
                        codigo_interno,
                        nombre_recurso,
                        descripcion,
                        serial,
                        marca,
                        modelo,
                        estado,
                        stock_total,
                        stock_disponible,
                        disponibilidad,
                        fecha_adquisicion,
                        fecha_registro
                    ) VALUES (
                        :id_categoria,
                        :codigo_interno,
                        :nombre_recurso,
                        :descripcion,
                        :serial,
                        :marca,
                        :modelo,
                        :estado,
                        :stock_total,
                        :stock_disponible,
                        :disponibilidad,
                        :fecha_adquisicion,
                        CURDATE()
                    )
                ");

                $stmt->execute([
                    ':id_categoria' => $idCategoria,
                    ':codigo_interno' => $codigoInterno,
                    ':nombre_recurso' => $nombreRecurso,
                    ':descripcion' => $descripcion,
                    ':serial' => $serial,
                    ':marca' => $marca,
                    ':modelo' => $modelo,
                    ':estado' => $estado,
                    ':stock_total' => $stockTotal,
                    ':stock_disponible' => $stockDisponible,
                    ':disponibilidad' => $disponibilidad,
                    ':fecha_adquisicion' => $fechaAdquisicion !== '' ? $fechaAdquisicion : null,
                ]);

                rs_flash('ok', 'Recurso registrado correctamente.');
                rs_redirect('dashboardAD/recursos.php?mensaje=' . urlencode('Recurso registrado correctamente.'));
            }

            $idRecurso = (int) ($_POST['id_recurso'] ?? 0);
            if ($idRecurso <= 0) {
                throw new InvalidArgumentException('No se indicó el recurso a editar.');
            }

            $stmt = $conexion->prepare("
                UPDATE recurso
                SET id_categoria = :id_categoria,
                    codigo_interno = :codigo_interno,
                    nombre_recurso = :nombre_recurso,
                    descripcion = :descripcion,
                    serial = :serial,
                    marca = :marca,
                    modelo = :modelo,
                    estado = :estado,
                    stock_total = :stock_total,
                    stock_disponible = :stock_disponible,
                    disponibilidad = :disponibilidad,
                    fecha_adquisicion = :fecha_adquisicion
                WHERE id_recurso = :id_recurso
            ");

            $stmt->execute([
                ':id_categoria' => $idCategoria,
                ':codigo_interno' => $codigoInterno,
                ':nombre_recurso' => $nombreRecurso,
                ':descripcion' => $descripcion,
                ':serial' => $serial,
                ':marca' => $marca,
                ':modelo' => $modelo,
                ':estado' => $estado,
                ':stock_total' => $stockTotal,
                ':stock_disponible' => $stockDisponible,
                ':disponibilidad' => $disponibilidad,
                ':fecha_adquisicion' => $fechaAdquisicion !== '' ? $fechaAdquisicion : null,
                ':id_recurso' => $idRecurso,
            ]);

            rs_flash('ok', 'Recurso actualizado correctamente.');
            rs_redirect('dashboardAD/recursos.php?mensaje=' . urlencode('Recurso actualizado correctamente.'));
        }

        if ($accion === 'eliminar') {
            $idRecurso = (int) ($_POST['id_recurso'] ?? 0);
            if ($idRecurso <= 0) {
                throw new InvalidArgumentException('No se indicó el recurso a eliminar.');
            }

            $conexion->prepare('DELETE FROM recurso WHERE id_recurso = :id_recurso LIMIT 1')->execute([':id_recurso' => $idRecurso]);
            rs_flash('ok', 'Recurso eliminado correctamente.');
            rs_redirect('dashboardAD/recursos.php?mensaje=' . urlencode('Recurso eliminado correctamente.'));
        }

        throw new InvalidArgumentException('La acción solicitada no es válida.');
    } catch (Throwable $e) {
        error_log('Error en RecursoController: ' . $e->getMessage());
        rs_flash('error', $e->getMessage());
        rs_redirect('dashboardAD/recursos.php');
    }
}

try {
    $sql = "
        SELECT
            r.id_recurso,
            r.id_categoria,
            r.codigo_interno,
            r.nombre_recurso,
            r.descripcion,
            r.serial,
            r.marca,
            r.modelo,
            r.estado,
            r.stock_total,
            r.stock_disponible,
            r.disponibilidad,
            r.fecha_registro,
            r.fecha_adquisicion,
            c.nombre_categoria
        FROM recurso r
        LEFT JOIN categoria_recurso c ON c.id_categoria = r.id_categoria
        WHERE 1 = 1
    ";

    $params = [];

    if ($buscar !== '') {
        $sql .= ' AND (r.codigo_interno LIKE :buscar OR r.nombre_recurso LIKE :buscar OR r.serial LIKE :buscar OR r.marca LIKE :buscar OR r.modelo LIKE :buscar OR r.descripcion LIKE :buscar)';
        $params[':buscar'] = '%' . $buscar . '%';
    }

    if ($categoriaFiltro !== '') {
        $sql .= ' AND r.id_categoria = :categoria';
        $params[':categoria'] = (int) $categoriaFiltro;
    }

    if ($estadoFiltro !== '') {
        $sql .= ' AND r.estado = :estado';
        $params[':estado'] = $estadoFiltro;
    }

    if ($disponibilidadFiltro !== '') {
        $sql .= ' AND r.disponibilidad = :disponibilidad';
        $params[':disponibilidad'] = $disponibilidadFiltro;
    }

    $sql .= ' ORDER BY r.id_recurso DESC';

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categoriasStmt = $conexion->prepare('SELECT id_categoria, nombre_categoria FROM categoria_recurso ORDER BY nombre_categoria ASC');
    $categoriasStmt->execute();
    $categorias = $categoriasStmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = $conexion->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN disponibilidad = 'Disponible' THEN 1 ELSE 0 END) AS disponibles,
            SUM(CASE WHEN disponibilidad = 'Prestado' THEN 1 ELSE 0 END) AS prestados,
            SUM(CASE WHEN disponibilidad = 'Mantenimiento' THEN 1 ELSE 0 END) AS mantenimiento
        FROM recurso"
    );

    $row = $stats->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $estadisticas = [
            'total' => (int) ($row['total'] ?? 0),
            'disponibles' => (int) ($row['disponibles'] ?? 0),
            'prestados' => (int) ($row['prestados'] ?? 0),
            'mantenimiento' => (int) ($row['mantenimiento'] ?? 0),
        ];
    }
} catch (PDOException $e) {
    error_log('Error al cargar recursos: ' . $e->getMessage());
    $errorConsulta = true;
}

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/slider.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<link rel="stylesheet" href="../CSS/recursos.css">

<main class="main-content recursos-page">
    <div class="page-heading d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 section-gap">
        <div>
            <span class="titulo-etiqueta"><i class="bi bi-box-seam"></i> INVENTARIO INSTITUCIONAL</span>
            <h1>Gestión de recursos</h1>
            <p>Administra y controla los bienes registrados en RecurSoft.</p>
        </div>
        <button class="btn btn-rs" type="button" id="btnNuevoRecurso" data-bs-toggle="modal" data-bs-target="#modalRecurso">
            <i class="bi bi-plus-lg me-2"></i> Nuevo recurso
        </button>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <?= rs_h($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorConsulta): ?>
        <div class="alert alert-danger">No fue posible cargar los recursos o las categorías. Revisa la conexión y los nombres de las columnas.</div>
    <?php else: ?>
        <div class="resumen-recursos">
            <div class="resumen-card"><div class="resumen-icono azul"><i class="bi bi-box-seam"></i></div><div><span>Total de recursos</span><strong><?= $estadisticas['total'] ?></strong></div></div>
            <div class="resumen-card"><div class="resumen-icono verde"><i class="bi bi-check-circle"></i></div><div><span>Disponibles</span><strong><?= $estadisticas['disponibles'] ?></strong></div></div>
            <div class="resumen-card"><div class="resumen-icono naranja"><i class="bi bi-arrow-left-right"></i></div><div><span>Prestados</span><strong><?= $estadisticas['prestados'] ?></strong></div></div>
            <div class="resumen-card"><div class="resumen-icono rojo"><i class="bi bi-tools"></i></div><div><span>En mantenimiento</span><strong><?= $estadisticas['mantenimiento'] ?></strong></div></div>
        </div>

        <section class="filtros-recursos">
            <div class="filtros-recursos-header">
                <div class="filtros-recursos-icono"><i class="bi bi-funnel-fill"></i></div>
                <div>
                    <h5>Buscar y filtrar recursos</h5>
                    <p>Encuentra recursos por su información y características.</p>
                </div>
            </div>

            <form method="GET" action="recursos.php">
                <div class="row g-3">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label" for="buscarRecurso">Buscar recurso</label>
                        <div class="campo-icono"><i class="bi bi-search"></i><input class="form-control" id="buscarRecurso" name="buscar" type="search" placeholder="Código, nombre, serial o marca..." value="<?= rs_h($buscar) ?>"></div>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="filtroCategoria">Categoría</label>
                        <select class="form-select" id="filtroCategoria" name="categoria">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= (int) $categoria['id_categoria'] ?>" <?= $categoriaFiltro === (string) $categoria['id_categoria'] ? 'selected' : '' ?>><?= rs_h($categoria['nombre_categoria']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label" for="filtroEstado">Estado físico</label>
                        <select class="form-select" id="filtroEstado" name="estado">
                            <option value="">Todos</option>
                            <?php foreach (['Bueno', 'Regular', 'Dañado'] as $estado): ?>
                                <option value="<?= rs_h($estado) ?>" <?= $estadoFiltro === $estado ? 'selected' : '' ?>><?= rs_h($estado) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label" for="filtroDisponibilidad">Disponibilidad</label>
                        <select class="form-select" id="filtroDisponibilidad" name="disponibilidad">
                            <option value="">Todas</option>
                            <?php foreach (['Disponible', 'Prestado', 'Mantenimiento', 'Fuera de servicio'] as $disponibilidad): ?>
                                <option value="<?= rs_h($disponibilidad) ?>" <?= $disponibilidadFiltro === $disponibilidad ? 'selected' : '' ?>><?= rs_h($disponibilidad) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filtros-recursos-footer">
                    <span><i class="bi bi-info-circle me-1"></i> Puedes combinar varios filtros.</span>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="recursos.php" class="btn-limpiar"><i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar</a>
                        <button type="submit" class="btn-buscar"><i class="bi bi-search me-1"></i> Buscar recursos</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="tabla-recursos">
            <div class="tabla-recursos-header">
                <div>
                    <h5><i class="bi bi-list-ul me-2"></i> Recursos registrados</h5>
                    <p>Listado de bienes institucionales.</p>
                </div>
                <span class="contador-recursos"><?= count($recursos) ?> resultados</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Recurso</th>
                            <th>Categoría</th>
                            <th>Serial</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Estado físico</th>
                            <th>Stock total</th>
                            <th>Stock disponible</th>
                            <th>Disponibilidad</th>
                            <th>Fecha registro</th>
                            <th>Fecha adquisición</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recursos)): ?>
                            <?php foreach ($recursos as $recurso): ?>
                                <tr>
                                    <td><span class="codigo-recurso"><?= rs_h($recurso['codigo_interno']) ?></span></td>
                                    <td>
                                        <div class="recurso-nombre">
                                            <strong><?= rs_h($recurso['nombre_recurso']) ?></strong>
                                            <small><?= rs_h($recurso['descripcion'] ?: 'Sin descripción') ?></small>
                                        </div>
                                    </td>
                                    <td><span class="categoria-recurso"><?= rs_h($recurso['nombre_categoria'] ?? 'Sin categoría') ?></span></td>
                                    <td><?= rs_h($recurso['serial'] ?: 'Sin serial') ?></td>
                                    <td><?= rs_h($recurso['marca'] ?: 'Sin marca') ?></td>
                                    <td><?= rs_h($recurso['modelo'] ?: 'Sin modelo') ?></td>
                                    <td><span class="estado-recurso"><?= rs_h($recurso['estado']) ?></span></td>
                                    <td><strong><?= (int) $recurso['stock_total'] ?></strong></td>
                                    <td><strong><?= (int) $recurso['stock_disponible'] ?></strong></td>
                                    <td><span class="disponibilidad-recurso"><?= rs_h($recurso['disponibilidad']) ?></span></td>
                                    <td><?= rs_h($recurso['fecha_registro'] ?: 'No registrada') ?></td>
                                    <td><?= rs_h($recurso['fecha_adquisicion'] ?: 'No registrada') ?></td>
                                    <td>
                                        <div class="acciones-recurso">
                                            <button type="button" class="btn-recurso-editar btnEditarRecurso" title="Editar recurso" data-bs-toggle="modal" data-bs-target="#modalRecurso" data-id="<?= (int) $recurso['id_recurso'] ?>" data-categoria="<?= (int) $recurso['id_categoria'] ?>" data-codigo="<?= rs_h($recurso['codigo_interno']) ?>" data-nombre="<?= rs_h($recurso['nombre_recurso']) ?>" data-descripcion="<?= rs_h($recurso['descripcion']) ?>" data-serial="<?= rs_h($recurso['serial']) ?>" data-marca="<?= rs_h($recurso['marca']) ?>" data-modelo="<?= rs_h($recurso['modelo']) ?>" data-estado="<?= rs_h($recurso['estado']) ?>" data-disponibilidad="<?= rs_h($recurso['disponibilidad']) ?>" data-stock-total="<?= (int) $recurso['stock_total'] ?>" data-stock-disponible="<?= (int) $recurso['stock_disponible'] ?>" data-fecha="<?= rs_h($recurso['fecha_adquisicion']) ?>"><i class="bi bi-pencil-square"></i></button>
                                            <form action="../controllers/RecursoController.php" method="POST" onsubmit="return confirm('¿Deseas eliminar este recurso?');">
                                                <?= rs_csrf_field() ?>
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_recurso" value="<?= (int) $recurso['id_recurso'] ?>">
                                                <button type="submit" class="btn-recurso-eliminar" title="Eliminar recurso"><i class="bi bi-trash3"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="13" class="text-center py-4 text-muted">No hay recursos registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

<div class="modal fade" id="modalRecurso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="tituloModalRecurso"><i class="bi bi-plus-circle me-2"></i> Registrar recurso</h2>
                    <p class="mb-0 mt-1" id="subtituloModalRecurso">Ingresa la información del nuevo recurso institucional.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formRecurso" action="../controllers/RecursoController.php" method="POST">
                <?= rs_csrf_field() ?>
                <input type="hidden" name="accion" id="accionRecurso" value="guardar">
                <input type="hidden" name="id_recurso" id="id_recurso">
                <input type="hidden" name="stock_disponible" id="stock_disponible" value="0">

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="codigo_interno" class="form-label">Código interno *</label>
                            <input type="text" class="form-control" id="codigo_interno" name="codigo_interno" maxlength="50" required>
                        </div>
                        <div class="col-md-8">
                            <label for="nombre_recurso" class="form-label">Nombre del recurso *</label>
                            <input type="text" class="form-control" id="nombre_recurso" name="nombre_recurso" maxlength="100" required>
                        </div>
                        <div class="col-md-6">
                            <label for="id_categoria" class="form-label">Categoría *</label>
                            <select class="form-select" id="id_categoria" name="id_categoria" required>
                                <option value="">Selecciona una categoría</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= (int) $categoria['id_categoria'] ?>"><?= rs_h($categoria['nombre_categoria']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_adquisicion" class="form-label">Fecha de adquisición</label>
                            <input type="date" class="form-control" id="fecha_adquisicion" name="fecha_adquisicion">
                        </div>
                        <div class="col-md-4">
                            <label for="serial" class="form-label">Serial</label>
                            <input type="text" class="form-control" id="serial" name="serial" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label for="marca" class="form-label">Marca</label>
                            <input type="text" class="form-control" id="marca" name="marca" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label for="modelo" class="form-label">Modelo</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" maxlength="50">
                        </div>
                        <div class="col-12">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="150"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="stock_total" class="form-label">Stock total *</label>
                            <input type="number" class="form-control" id="stock_total" name="stock_total" min="1" step="1" required>
                        </div>
                        <div class="col-md-6">
                            <label for="stock_disponible_visual" class="form-label">Stock disponible</label>
                            <input type="number" class="form-control" id="stock_disponible_visual" min="0" step="1" value="0" readonly>
                            <small class="text-muted" id="ayudaStock">Al registrar, las unidades disponibles comienzan siendo iguales al stock total.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="estado" class="form-label">Estado físico *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="">Selecciona el estado</option>
                                <option value="Bueno">Bueno</option>
                                <option value="Regular">Regular</option>
                                <option value="Dañado">Dañado</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="disponibilidad" class="form-label">Disponibilidad *</label>
                            <select class="form-select" id="disponibilidad" name="disponibilidad" required>
                                <option value="">Selecciona la disponibilidad</option>
                                <option value="Disponible">Disponible</option>
                                <option value="Prestado">Prestado</option>
                                <option value="Mantenimiento">Mantenimiento</option>
                                <option value="Fuera de servicio">Fuera de servicio</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-modal-guardar" id="btnGuardarRecurso"><i class="bi bi-check-circle me-2"></i> Registrar recurso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const formulario = document.getElementById('formRecurso');
    const modal = document.getElementById('modalRecurso');
    const accion = document.getElementById('accionRecurso');
    const idRecurso = document.getElementById('id_recurso');
    const titulo = document.getElementById('tituloModalRecurso');
    const subtitulo = document.getElementById('subtituloModalRecurso');
    const botonGuardar = document.getElementById('btnGuardarRecurso');
    const stockTotal = document.getElementById('stock_total');
    const stockDisponibleVisual = document.getElementById('stock_disponible_visual');
    const stockDisponibleReal = document.getElementById('stock_disponible');
    const ayudaStock = document.getElementById('ayudaStock');
    let enviando = false;

    function prepararRegistro() {
        formulario.reset();
        accion.value = 'guardar';
        idRecurso.value = '';
        stockTotal.value = '';
        stockDisponibleVisual.value = 0;
        stockDisponibleReal.value = '0';
        botonGuardar.disabled = false;
        titulo.innerHTML = '<i class="bi bi-plus-circle me-2"></i> Registrar recurso';
        subtitulo.textContent = 'Ingresa la información del nuevo recurso institucional.';
        botonGuardar.innerHTML = '<i class="bi bi-check-circle me-2"></i> Registrar recurso';
        ayudaStock.textContent = 'Al registrar, las unidades disponibles comienzan siendo iguales al stock total.';
    }

    function prepararEdicion(boton) {
        formulario.reset();
        accion.value = 'editar';
        idRecurso.value = boton.dataset.id || '';
        document.getElementById('codigo_interno').value = boton.dataset.codigo || '';
        document.getElementById('nombre_recurso').value = boton.dataset.nombre || '';
        document.getElementById('id_categoria').value = boton.dataset.categoria || '';
        document.getElementById('fecha_adquisicion').value = boton.dataset.fecha || '';
        document.getElementById('serial').value = boton.dataset.serial || '';
        document.getElementById('marca').value = boton.dataset.marca || '';
        document.getElementById('modelo').value = boton.dataset.modelo || '';
        document.getElementById('descripcion').value = boton.dataset.descripcion || '';
        document.getElementById('estado').value = boton.dataset.estado || '';
        document.getElementById('disponibilidad').value = boton.dataset.disponibilidad || '';
        stockTotal.value = boton.dataset.stockTotal || '1';
        stockDisponibleVisual.value = boton.dataset.stockDisponible || '0';
        stockDisponibleReal.value = boton.dataset.stockDisponible || '0';
        titulo.innerHTML = '<i class="bi bi-pencil-square me-2"></i> Editar recurso';
        subtitulo.textContent = 'Actualiza la información del recurso seleccionado.';
        botonGuardar.innerHTML = '<i class="bi bi-check-circle me-2"></i> Guardar cambios';
        ayudaStock.textContent = 'El stock disponible se conserva para no alterar las unidades controladas por los préstamos.';
    }

    stockTotal.addEventListener('input', function () {
        if (accion.value === 'guardar') {
            const cantidad = Math.max(0, parseInt(this.value, 10) || 0);
            stockDisponibleVisual.value = cantidad;
            stockDisponibleReal.value = String(cantidad);
        }
    });

    document.getElementById('btnNuevoRecurso').addEventListener('click', prepararRegistro);
    document.querySelectorAll('.btnEditarRecurso').forEach(function (boton) {
        boton.addEventListener('click', function () {
            prepararEdicion(this);
        });
    });

    formulario.addEventListener('submit', function (evento) {
        if (enviando) {
            evento.preventDefault();
            return;
        }
        if (!formulario.checkValidity()) {
            return;
        }
        const total = parseInt(stockTotal.value, 10);
        if (!Number.isInteger(total) || total < 1) {
            evento.preventDefault();
            alert('El stock total debe ser como mínimo 1.');
            stockTotal.focus();
            return;
        }
        enviando = true;
        botonGuardar.disabled = true;
        botonGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Procesando...';
    });

    modal.addEventListener('hidden.bs.modal', function () {
        prepararRegistro();
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
require_once __DIR__ . '/../includes/scripts.php';
?>

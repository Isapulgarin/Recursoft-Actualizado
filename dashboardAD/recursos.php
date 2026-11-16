
<?php
/**
 * RecurSoft - Gestión de recursos.
 */

// 1. Autenticación antes de generar HTML.
require_once __DIR__ . '/../includes/auth.php';

rs_requiere_admin();

// 2. Configuración de la página.
$pageTitle = 'Recursos';
$dashboardType = 'AD';
$activePage = 'recursos';

// auth.php ya carga conexion.php.
$conexion = rs_pdo();

$mensaje = $_GET['mensaje'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');
$categoriaFiltro = $_GET['categoria'] ?? '';
$estadoFiltro = $_GET['estado'] ?? '';
$disponibilidadFiltro = $_GET['disponibilidad'] ?? '';

$recursos = [];
$categorias = [];
$estadisticas = [
    'total' => 0,
    'disponibles' => 0,
    'prestados' => 0,
    'mantenimiento' => 0
];

$errorConsulta = false;

$estadosValidos = ['', 'Bueno', 'Regular', 'Dañado'];

$disponibilidadesValidas = [
    '',
    'Disponible',
    'Prestado',
    'Mantenimiento',
    'Fuera de servicio'
];

if (!in_array($estadoFiltro, $estadosValidos, true)) {
    $estadoFiltro = '';
}

if (!in_array($disponibilidadFiltro, $disponibilidadesValidas, true)) {
    $disponibilidadFiltro = '';
}

if (
    $categoriaFiltro !== '' &&
    !ctype_digit((string) $categoriaFiltro)
) {
    $categoriaFiltro = '';
}

/**
 * Escapar valores para mostrarlos en HTML.
 */
function h($valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

try {

    // CONSULTA DE RECURSOS CON FILTROS.
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

        LEFT JOIN categoria_recurso c
            ON r.id_categoria = c.id_categoria

        WHERE 1 = 1
    ";

    $parametros = [];

    if ($buscar !== '') {

        $sql .= "
            AND (
                r.codigo_interno LIKE :codigo
                OR r.nombre_recurso LIKE :nombre
                OR r.serial LIKE :serial
                OR r.marca LIKE :marca
                OR r.modelo LIKE :modelo
                OR r.descripcion LIKE :descripcion
            )
        ";

        $termino = '%' . $buscar . '%';

        $parametros[':codigo'] = $termino;
        $parametros[':nombre'] = $termino;
        $parametros[':serial'] = $termino;
        $parametros[':marca'] = $termino;
        $parametros[':modelo'] = $termino;
        $parametros[':descripcion'] = $termino;
    }

    if ($categoriaFiltro !== '') {
        $sql .= " AND r.id_categoria = :categoria";
        $parametros[':categoria'] = (int) $categoriaFiltro;
    }

    if ($estadoFiltro !== '') {
        $sql .= " AND r.estado = :estado";
        $parametros[':estado'] = $estadoFiltro;
    }

    if ($disponibilidadFiltro !== '') {
        $sql .= " AND r.disponibilidad = :disponibilidad";
        $parametros[':disponibilidad'] = $disponibilidadFiltro;
    }

    $sql .= " ORDER BY r.id_recurso DESC";

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);

    $recursos = $consulta->fetchAll(PDO::FETCH_ASSOC);


    // CONSULTAR CATEGORÍAS.
    $consultaCategorias = $conexion->prepare("
        SELECT
            id_categoria,
            nombre_categoria
        FROM categoria_recurso
        ORDER BY nombre_categoria ASC
    ");

    $consultaCategorias->execute();

    $categorias = $consultaCategorias->fetchAll(PDO::FETCH_ASSOC);


    // ESTADÍSTICAS GENERALES SIN APLICAR FILTROS.
    $consultaEstadisticas = $conexion->query("
        SELECT
            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN disponibilidad = 'Disponible' THEN 1
                    ELSE 0
                END
            ) AS disponibles,

            SUM(
                CASE
                    WHEN disponibilidad = 'Prestado' THEN 1
                    ELSE 0
                END
            ) AS prestados,

            SUM(
                CASE
                    WHEN disponibilidad = 'Mantenimiento' THEN 1
                    ELSE 0
                END
            ) AS mantenimiento

        FROM recurso
    ");

    $resultadoEstadisticas = $consultaEstadisticas->fetch(PDO::FETCH_ASSOC);

    if ($resultadoEstadisticas) {
        $estadisticas = [
            'total' => (int) ($resultadoEstadisticas['total'] ?? 0),
            'disponibles' => (int) ($resultadoEstadisticas['disponibles'] ?? 0),
            'prestados' => (int) ($resultadoEstadisticas['prestados'] ?? 0),
            'mantenimiento' => (int) ($resultadoEstadisticas['mantenimiento'] ?? 0)
        ];
    }

} catch (PDOException $e) {

    error_log(
        'Error al cargar recursos de RecurSoft: ' . $e->getMessage()
    );

    $errorConsulta = true;
}


// 3. Cargar estructura visual después de las consultas.
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/slider.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<link rel="stylesheet" href="../CSS/recursos.css">

<main class="main-content recursos-page">

    <!-- ENCABEZADO -->

    <div class="page-heading d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 section-gap">

        <div>
            <span class="titulo-etiqueta">
                <i class="bi bi-box-seam"></i>
                INVENTARIO INSTITUCIONAL
            </span>

            <h1>Gestión de recursos</h1>

            <p>Administra y controla los bienes registrados en RecurSoft.</p>
        </div>

        <button
            class="btn btn-rs"
            type="button"
            id="btnNuevoRecurso"
            data-bs-toggle="modal"
            data-bs-target="#modalRecurso"
        >
            <i class="bi bi-plus-lg me-2"></i>
            Nuevo recurso
        </button>

    </div>


    <!-- MENSAJES -->

    <?php if ($mensaje !== ''): ?>

        <div class="alert alert-info alert-dismissible fade show">
            <?= h($mensaje) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>

    <?php endif; ?>


    <?php if ($errorConsulta): ?>

        <div class="alert alert-danger">
            No fue posible cargar los recursos o las categorías.
            Revisa la conexión con la base de datos y los nombres de las columnas.
        </div>

    <?php else: ?>


        <!-- TARJETAS DE RESUMEN -->

        <div class="resumen-recursos">

            <div class="resumen-card">
                <div class="resumen-icono azul">
                    <i class="bi bi-box-seam"></i>
                </div>

                <div>
                    <span>Total de recursos</span>
                    <strong><?= $estadisticas['total'] ?></strong>
                </div>
            </div>


            <div class="resumen-card">
                <div class="resumen-icono verde">
                    <i class="bi bi-check-circle"></i>
                </div>

                <div>
                    <span>Disponibles</span>
                    <strong><?= $estadisticas['disponibles'] ?></strong>
                </div>
            </div>


            <div class="resumen-card">
                <div class="resumen-icono naranja">
                    <i class="bi bi-arrow-left-right"></i>
                </div>

                <div>
                    <span>Prestados</span>
                    <strong><?= $estadisticas['prestados'] ?></strong>
                </div>
            </div>


            <div class="resumen-card">
                <div class="resumen-icono rojo">
                    <i class="bi bi-tools"></i>
                </div>

                <div>
                    <span>En mantenimiento</span>
                    <strong><?= $estadisticas['mantenimiento'] ?></strong>
                </div>
            </div>

        </div>


        <!-- FILTROS -->

        <section class="filtros-recursos">

            <div class="filtros-recursos-header">

                <div class="filtros-recursos-icono">
                    <i class="bi bi-funnel-fill"></i>
                </div>

                <div>
                    <h5>Buscar y filtrar recursos</h5>
                    <p>Encuentra recursos por su información y características.</p>
                </div>

            </div>


            <form method="GET" action="recursos.php">

                <div class="row g-3">

                    <div class="col-lg-4 col-md-6">

                        <label class="form-label" for="buscarRecurso">
                            Buscar recurso
                        </label>

                        <div class="campo-icono">
                            <i class="bi bi-search"></i>

                            <input
                                class="form-control"
                                id="buscarRecurso"
                                name="buscar"
                                type="search"
                                placeholder="Código, nombre, serial o marca..."
                                value="<?= h($buscar) ?>"
                            >
                        </div>

                    </div>


                    <div class="col-lg-2 col-md-6">

                        <label class="form-label" for="filtroCategoria">
                            Categoría
                        </label>

                        <select
                            class="form-select"
                            id="filtroCategoria"
                            name="categoria"
                        >

                            <option value="">Todas</option>

                            <?php foreach ($categorias as $categoria): ?>

                                <option
                                    value="<?= (int) $categoria['id_categoria'] ?>"
                                    <?= $categoriaFiltro === (string) $categoria['id_categoria'] ? 'selected' : '' ?>
                                >
                                    <?= h($categoria['nombre_categoria']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-lg-3 col-md-6">

                        <label class="form-label" for="filtroEstado">
                            Estado físico
                        </label>

                        <select
                            class="form-select"
                            id="filtroEstado"
                            name="estado"
                        >

                            <option value="">Todos</option>

                            <?php foreach (['Bueno', 'Regular', 'Dañado'] as $estado): ?>

                                <option
                                    value="<?= h($estado) ?>"
                                    <?= $estadoFiltro === $estado ? 'selected' : '' ?>
                                >
                                    <?= h($estado) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-lg-3 col-md-6">

                        <label class="form-label" for="filtroDisponibilidad">
                            Disponibilidad
                        </label>

                        <select
                            class="form-select"
                            id="filtroDisponibilidad"
                            name="disponibilidad"
                        >

                            <option value="">Todas</option>

                            <?php foreach (array_slice($disponibilidadesValidas, 1) as $disponibilidad): ?>

                                <option
                                    value="<?= h($disponibilidad) ?>"
                                    <?= $disponibilidadFiltro === $disponibilidad ? 'selected' : '' ?>
                                >
                                    <?= h($disponibilidad) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>


                <div class="filtros-recursos-footer">

                    <span>
                        <i class="bi bi-info-circle me-1"></i>
                        Puedes combinar varios filtros.
                    </span>

                    <div class="d-flex gap-2 flex-wrap">

                        <a href="recursos.php" class="btn-limpiar">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                            Limpiar
                        </a>

                        <button type="submit" class="btn-buscar">
                            <i class="bi bi-search me-1"></i>
                            Buscar recursos
                        </button>

                    </div>

                </div>

            </form>

        </section>


        <!-- TABLA DE RECURSOS -->

        <section class="tabla-recursos">

            <div class="tabla-recursos-header">

                <div>
                    <h5>
                        <i class="bi bi-list-ul me-2"></i>
                        Recursos registrados
                    </h5>

                    <p>Listado de bienes institucionales.</p>
                </div>

                <span class="contador-recursos">
                    <?= count($recursos) ?> resultados
                </span>

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

                                <td>
                                    <span class="codigo-recurso">
                                        <?= h($recurso['codigo_interno']) ?>
                                    </span>
                                </td>


                                <td>
                                    <div class="recurso-nombre">

                                        <strong>
                                            <?= h($recurso['nombre_recurso']) ?>
                                        </strong>

                                        <small>
                                            <?= h(
                                                $recurso['descripcion']
                                                ?: 'Sin descripción'
                                            ) ?>
                                        </small>

                                    </div>
                                </td>


                                <td>
                                    <span class="categoria-recurso">
                                        <?= h(
                                            $recurso['nombre_categoria']
                                            ?? 'Sin categoría'
                                        ) ?>
                                    </span>
                                </td>


                                <td>
                                    <?= h($recurso['serial'] ?: 'Sin serial') ?>
                                </td>


                                <td>
                                    <?= h($recurso['marca'] ?: 'Sin marca') ?>
                                </td>


                                <td>
                                    <?= h($recurso['modelo'] ?: 'Sin modelo') ?>
                                </td>


                                <td>
                                    <span class="estado-recurso">
                                        <?= h($recurso['estado']) ?>
                                    </span>
                                </td>


                                <td>
                                    <strong>
                                        <?= (int) $recurso['stock_total'] ?>
                                    </strong>
                                </td>


                                <td>
                                    <strong>
                                        <?= (int) $recurso['stock_disponible'] ?>
                                    </strong>
                                </td>


                                <td>
                                    <span class="disponibilidad-recurso">
                                        <?= h($recurso['disponibilidad']) ?>
                                    </span>
                                </td>


                                <td>
                                    <?= h(
                                        $recurso['fecha_registro']
                                        ?: 'No registrada'
                                    ) ?>
                                </td>


                                <td>
                                    <?= h(
                                        $recurso['fecha_adquisicion']
                                        ?: 'No registrada'
                                    ) ?>
                                </td>


                                <td>

                                    <div class="acciones-recurso">

                                        <!-- EDITAR -->

                                        <button
                                            type="button"
                                            class="btn-recurso-editar btnEditarRecurso"
                                            title="Editar recurso"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalRecurso"

                                            data-id="<?= (int) $recurso['id_recurso'] ?>"
                                            data-categoria="<?= (int) $recurso['id_categoria'] ?>"
                                            data-codigo="<?= h($recurso['codigo_interno']) ?>"
                                            data-nombre="<?= h($recurso['nombre_recurso']) ?>"
                                            data-descripcion="<?= h($recurso['descripcion']) ?>"
                                            data-serial="<?= h($recurso['serial']) ?>"
                                            data-marca="<?= h($recurso['marca']) ?>"
                                            data-modelo="<?= h($recurso['modelo']) ?>"
                                            data-estado="<?= h($recurso['estado']) ?>"
                                            data-disponibilidad="<?= h($recurso['disponibilidad']) ?>"
                                            data-stock-total="<?= (int) $recurso['stock_total'] ?>"
                                            data-stock-disponible="<?= (int) $recurso['stock_disponible'] ?>"
                                            data-fecha="<?= h($recurso['fecha_adquisicion']) ?>"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                        </button>


                                        <!-- ELIMINAR -->

                                        <form
                                            action="../controllers/RecursoController.php"
                                            method="POST"
                                            onsubmit="return confirm('¿Deseas eliminar este recurso? Esta acción no se puede deshacer.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="eliminar"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_recurso"
                                                value="<?= (int) $recurso['id_recurso'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-recurso-eliminar"
                                                title="Eliminar recurso"
                                            >
                                                <i class="bi bi-trash3"></i>
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="13">

                                <div class="tabla-recursos-vacia">

                                    <i class="bi bi-box-seam"></i>

                                    <h6>No se encontraron recursos</h6>

                                    <p>
                                        Registra un recurso o modifica los filtros de búsqueda.
                                    </p>

                                </div>

                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    <?php endif; ?>

</main>


<!-- MODAL DE REGISTRO Y EDICIÓN -->

<div
    class="modal fade"
    id="modalRecurso"
    tabindex="-1"
    aria-labelledby="tituloModalRecurso"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">

        <div class="modal-content modal-recurso">


            <!-- ENCABEZADO DEL MODAL -->

            <div class="modal-header">

                <div>

                    <h2 class="modal-title fs-5" id="tituloModalRecurso">
                        <i class="bi bi-plus-circle me-2"></i>
                        Registrar recurso
                    </h2>

                    <p class="mb-0 mt-1" id="subtituloModalRecurso">
                        Ingresa la información del nuevo recurso institucional.
                    </p>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar"
                ></button>

            </div>


            <!-- FORMULARIO -->

            <form
                id="formRecurso"
                action="../controllers/RecursoController.php"
                method="POST"
            >
                <?= rs_csrf_field() ?>

                <input
                    type="hidden"
                    name="accion"
                    id="accionRecurso"
                    value="guardar"
                >

                <input
                    type="hidden"
                    name="id_recurso"
                    id="id_recurso"
                >

                <input
                    type="hidden"
                    name="stock_disponible"
                    id="stock_disponible"
                    value="0"
                >


                <div class="modal-body">


                    <!-- INFORMACIÓN GENERAL -->

                    <div class="seccion-formulario">
                        <i class="bi bi-card-list"></i>
                        Información general
                    </div>


                    <div class="row g-3">

                        <div class="col-md-4">

                            <label for="codigo_interno" class="form-label">
                                Código interno *
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="codigo_interno"
                                name="codigo_interno"
                                maxlength="50"
                                required
                            >

                        </div>


                        <div class="col-md-8">

                            <label for="nombre_recurso" class="form-label">
                                Nombre del recurso *
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="nombre_recurso"
                                name="nombre_recurso"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label for="id_categoria" class="form-label">
                                Categoría *
                            </label>

                            <select
                                class="form-select"
                                id="id_categoria"
                                name="id_categoria"
                                required
                            >

                                <option value="">
                                    Selecciona una categoría
                                </option>

                                <?php foreach ($categorias as $categoria): ?>

                                    <option value="<?= (int) $categoria['id_categoria'] ?>">
                                        <?= h($categoria['nombre_categoria']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label for="fecha_adquisicion" class="form-label">
                                Fecha de adquisición
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                id="fecha_adquisicion"
                                name="fecha_adquisicion"
                            >

                        </div>

                    </div>


                    <!-- IDENTIFICACIÓN -->

                    <div class="seccion-formulario mt-4">
                        <i class="bi bi-upc-scan"></i>
                        Identificación del recurso
                    </div>


                    <div class="row g-3">

                        <div class="col-md-4">

                            <label for="serial" class="form-label">
                                Serial
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="serial"
                                name="serial"
                                maxlength="100"
                            >

                        </div>


                        <div class="col-md-4">

                            <label for="marca" class="form-label">
                                Marca
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="marca"
                                name="marca"
                                maxlength="50"
                            >

                        </div>


                        <div class="col-md-4">

                            <label for="modelo" class="form-label">
                                Modelo
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="modelo"
                                name="modelo"
                                maxlength="50"
                            >

                        </div>


                        <div class="col-12">

                            <label for="descripcion" class="form-label">
                                Descripción
                            </label>

                            <textarea
                                class="form-control"
                                id="descripcion"
                                name="descripcion"
                                rows="3"
                                maxlength="150"
                            ></textarea>

                        </div>

                    </div>


                    <!-- INVENTARIO -->

                    <div class="seccion-formulario mt-4">
                        <i class="bi bi-boxes"></i>
                        Control de inventario
                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label for="stock_total" class="form-label">
                                Stock total *
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                id="stock_total"
                                name="stock_total"
                                min="1"
                                step="1"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label for="stock_disponible_visual" class="form-label">
                                Stock disponible
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                id="stock_disponible_visual"
                                min="0"
                                step="1"
                                value="0"
                                readonly
                            >

                            <small class="text-muted" id="ayudaStock">
                                Al registrar, las unidades disponibles comienzan siendo iguales al stock total.
                            </small>

                        </div>

                    </div>


                    <!-- ESTADO Y DISPONIBILIDAD -->

                    <div class="seccion-formulario mt-4">
                        <i class="bi bi-clipboard-check"></i>
                        Estado y disponibilidad
                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label for="estado" class="form-label">
                                Estado físico *
                            </label>

                            <select
                                class="form-select"
                                id="estado"
                                name="estado"
                                required
                            >

                                <option value="">
                                    Selecciona el estado
                                </option>

                                <option value="Bueno">Bueno</option>
                                <option value="Regular">Regular</option>
                                <option value="Dañado">Dañado</option>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label for="disponibilidad" class="form-label">
                                Disponibilidad *
                            </label>

                            <select
                                class="form-select"
                                id="disponibilidad"
                                name="disponibilidad"
                                required
                            >

                                <option value="">
                                    Selecciona la disponibilidad
                                </option>

                                <option value="Disponible">Disponible</option>
                                <option value="Prestado">Prestado</option>
                                <option value="Mantenimiento">Mantenimiento</option>
                                <option value="Fuera de servicio">Fuera de servicio</option>

                            </select>

                        </div>

                    </div>


                    <p class="texto-obligatorio mt-3 mb-0">
                        * Los campos marcados son obligatorios.
                    </p>

                    <p class="text-muted mt-2 mb-0">
                        El stock disponible debe mantenerse sincronizado con los préstamos y las devoluciones.
                    </p>

                </div>


                <!-- BOTONES -->

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn-modal-cancelar"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn-modal-guardar"
                        id="btnGuardarRecurso"
                    >
                        <i class="bi bi-check-circle me-2"></i>
                        Registrar recurso
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- JAVASCRIPT -->

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
    const stockDisponible = document.getElementById('stock_disponible_visual');
    const stockDisponibleReal = document.getElementById('stock_disponible');

    const ayudaStock = document.getElementById('ayudaStock');

    let enviando = false;


    /**
     * Preparar el formulario para registrar.
     */
    function prepararRegistro() {

        formulario.reset();

        accion.value = 'guardar';
        idRecurso.value = '';

        enviando = false;

        botonGuardar.disabled = false;

        stockDisponible.value = 0;
        stockDisponibleReal.value = '0';

        stockDisponible.dataset.original = '0';

        ayudaStock.textContent =
            'Al registrar, las unidades disponibles comienzan siendo iguales al stock total.';

        titulo.innerHTML =
            '<i class="bi bi-plus-circle me-2"></i> Registrar recurso';

        subtitulo.textContent =
            'Ingresa la información del nuevo recurso institucional.';

        botonGuardar.innerHTML =
            '<i class="bi bi-check-circle me-2"></i> Registrar recurso';
    }


    /**
     * Preparar el formulario para editar.
     */
    function prepararEdicion(boton) {

        formulario.reset();

        accion.value = 'editar';

        idRecurso.value = boton.dataset.id || '';

        document.getElementById('codigo_interno').value =
            boton.dataset.codigo || '';

        document.getElementById('nombre_recurso').value =
            boton.dataset.nombre || '';

        document.getElementById('id_categoria').value =
            boton.dataset.categoria || '';

        document.getElementById('fecha_adquisicion').value =
            boton.dataset.fecha || '';

        document.getElementById('serial').value =
            boton.dataset.serial || '';

        document.getElementById('marca').value =
            boton.dataset.marca || '';

        document.getElementById('modelo').value =
            boton.dataset.modelo || '';

        document.getElementById('descripcion').value =
            boton.dataset.descripcion || '';

        document.getElementById('estado').value =
            boton.dataset.estado || '';

        document.getElementById('disponibilidad').value =
            boton.dataset.disponibilidad || '';

        stockTotal.value =
            boton.dataset.stockTotal || '1';

        stockDisponible.value =
            boton.dataset.stockDisponible || '0';
        stockDisponibleReal.value =
            boton.dataset.stockDisponible || '0';

        stockDisponible.dataset.original =
            boton.dataset.stockDisponible || '0';

        ayudaStock.textContent =
            'El stock disponible se conserva para no alterar las unidades controladas por los préstamos.';

        enviando = false;

        botonGuardar.disabled = false;

        titulo.innerHTML =
            '<i class="bi bi-pencil-square me-2"></i> Editar recurso';

        subtitulo.textContent =
            'Actualiza la información del recurso seleccionado.';

        botonGuardar.innerHTML =
            '<i class="bi bi-check-circle me-2"></i> Guardar cambios';
    }


    /**
     * Al cambiar la cantidad total durante un registro,
     * actualizar visualmente la cantidad disponible.
     *
     * En edición no se modifica automáticamente porque
     * puede haber unidades prestadas.
     */
    stockTotal.addEventListener('input', function () {

        if (accion.value === 'guardar') {

            const cantidad = Math.max(
                0,
                parseInt(this.value, 10) || 0
            );

            stockDisponible.value = cantidad;
            stockDisponibleReal.value = String(cantidad);
        }

    });


    /**
     * Botón nuevo recurso.
     */
    document
        .getElementById('btnNuevoRecurso')
        .addEventListener('click', prepararRegistro);


    /**
     * Botones editar.
     */
    document
        .querySelectorAll('.btnEditarRecurso')
        .forEach(function (boton) {

            boton.addEventListener('click', function () {
                prepararEdicion(this);
            });

        });


    /**
     * Evitar doble envío del formulario.
     */
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

        botonGuardar.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span> Procesando...';

    });


    /**
     * Limpiar el modal al cerrarlo.
     */
    modal.addEventListener('hidden.bs.modal', function () {

        prepararRegistro();

    });

});
</script>


<?php

require_once __DIR__ . '/../includes/footer.php';
require_once __DIR__ . '/../includes/scripts.php';

?>

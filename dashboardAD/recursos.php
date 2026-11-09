<?php

$pageTitle = 'Recursos';
$dashboardType = 'AD';
$activePage = 'recursos';

require_once __DIR__ . '/../config/conexion.php';

$mensaje = $_GET['mensaje'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');
$categoriaFiltro = $_GET['categoria'] ?? '';
$estadoFiltro = $_GET['estado'] ?? '';
$disponibilidadFiltro = $_GET['disponibilidad'] ?? '';

$recursos = [];
$categorias = [];
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

if ($categoriaFiltro !== '' && !ctype_digit((string) $categoriaFiltro)) {
    $categoriaFiltro = '';
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
            )
        ";

        $termino = '%' . $buscar . '%';

        $parametros[':codigo'] = $termino;
        $parametros[':nombre'] = $termino;
        $parametros[':serial'] = $termino;
        $parametros[':marca'] = $termino;
        $parametros[':modelo'] = $termino;
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

    $consultaCategorias = $conexion->prepare("
        SELECT id_categoria, nombre_categoria
        FROM categoria_recurso
        ORDER BY nombre_categoria ASC
    ");

    $consultaCategorias->execute();

    $categorias = $consultaCategorias->fetchAll(PDO::FETCH_ASSOC);

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

    <?php if ($mensaje !== ''): ?>

        <div class="alert alert-info alert-dismissible fade show" role="alert">

            <i class="bi bi-info-circle me-2"></i>

            <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>

    <?php if ($errorConsulta): ?>

        <div class="alert alert-danger">
            No fue posible cargar los recursos o las categorías.
            Revisa la conexión y los nombres de las columnas.
        </div>

    <?php else: ?>

        <div class="resumen-recursos">

            <div class="resumen-card">

                <div class="resumen-icono azul">
                    <i class="bi bi-box-seam"></i>
                </div>

                <div>
                    <span>Total de recursos</span>
                    <strong><?= count($recursos) ?></strong>
                </div>

            </div>

            <div class="resumen-card">

                <div class="resumen-icono verde">
                    <i class="bi bi-check-circle"></i>
                </div>

                <div>
                    <span>Disponibles</span>

                    <strong>
                        <?= count(array_filter(
                            $recursos,
                            fn($r) => $r['disponibilidad'] === 'Disponible'
                        )) ?>
                    </strong>
                </div>

            </div>

            <div class="resumen-card">

                <div class="resumen-icono naranja">
                    <i class="bi bi-arrow-left-right"></i>
                </div>

                <div>
                    <span>Prestados</span>

                    <strong>
                        <?= count(array_filter(
                            $recursos,
                            fn($r) => $r['disponibilidad'] === 'Prestado'
                        )) ?>
                    </strong>
                </div>

            </div>

            <div class="resumen-card">

                <div class="resumen-icono rojo">
                    <i class="bi bi-tools"></i>
                </div>

                <div>
                    <span>En mantenimiento</span>

                    <strong>
                        <?= count(array_filter(
                            $recursos,
                            fn($r) => $r['disponibilidad'] === 'Mantenimiento'
                        )) ?>
                    </strong>
                </div>

            </div>

        </div>

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

                        <label for="buscarRecurso" class="form-label">
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
                                value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>"
                            >

                        </div>

                    </div>

                    <div class="col-lg-2 col-md-6">

                        <label for="filtroCategoria" class="form-label">
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
                                    <?= htmlspecialchars(
                                        $categoria['nombre_categoria'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-lg-3 col-md-6">

                        <label for="filtroEstado" class="form-label">
                            Estado físico
                        </label>

                        <select
                            class="form-select"
                            id="filtroEstado"
                            name="estado"
                        >

                            <option value="">Todos</option>

                            <option value="Bueno" <?= $estadoFiltro === 'Bueno' ? 'selected' : '' ?>>
                                Bueno
                            </option>

                            <option value="Regular" <?= $estadoFiltro === 'Regular' ? 'selected' : '' ?>>
                                Regular
                            </option>

                            <option value="Dañado" <?= $estadoFiltro === 'Dañado' ? 'selected' : '' ?>>
                                Dañado
                            </option>

                        </select>

                    </div>

                    <div class="col-lg-3 col-md-6">

                        <label for="filtroDisponibilidad" class="form-label">
                            Disponibilidad
                        </label>

                        <select
                            class="form-select"
                            id="filtroDisponibilidad"
                            name="disponibilidad"
                        >

                            <option value="">Todas</option>

                            <option value="Disponible" <?= $disponibilidadFiltro === 'Disponible' ? 'selected' : '' ?>>
                                Disponible
                            </option>

                            <option value="Prestado" <?= $disponibilidadFiltro === 'Prestado' ? 'selected' : '' ?>>
                                Prestado
                            </option>

                            <option value="Mantenimiento" <?= $disponibilidadFiltro === 'Mantenimiento' ? 'selected' : '' ?>>
                                Mantenimiento
                            </option>

                            <option value="Fuera de servicio" <?= $disponibilidadFiltro === 'Fuera de servicio' ? 'selected' : '' ?>>
                                Fuera de servicio
                            </option>

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
                            <th>Estado físico</th>
                            <th>Disponibilidad</th>
                            <th>Acciones</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!empty($recursos)): ?>

                        <?php foreach ($recursos as $recurso): ?>

                            <tr>

                                <td>
                                    <span class="codigo-recurso">
                                        <?= htmlspecialchars(
                                            $recurso['codigo_interno'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                </td>

                                <td>

                                    <div class="recurso-nombre">

                                        <strong>
                                            <?= htmlspecialchars(
                                                $recurso['nombre_recurso'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= htmlspecialchars(
                                                trim(
                                                    ($recurso['marca'] ?? '') . ' ' .
                                                    ($recurso['modelo'] ?? '')
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </small>

                                    </div>

                                </td>

                                <td>
                                    <span class="categoria-recurso">
                                        <?= htmlspecialchars(
                                            $recurso['nombre_categoria'] ?? 'Sin categoría',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $recurso['serial'] ?: 'Sin serial',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <span class="estado-recurso">
                                        <?= htmlspecialchars(
                                            $recurso['estado'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="disponibilidad-recurso">
                                        <?= htmlspecialchars(
                                            $recurso['disponibilidad'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                </td>

                                <td>

                                    <div class="acciones-recurso">

                                        <button
                                            type="button"
                                            class="btn-recurso-editar btnEditarRecurso"
                                            title="Editar recurso"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalRecurso"
                                            data-id="<?= (int) $recurso['id_recurso'] ?>"
                                            data-categoria="<?= (int) $recurso['id_categoria'] ?>"
                                            data-codigo="<?= htmlspecialchars($recurso['codigo_interno'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-nombre="<?= htmlspecialchars($recurso['nombre_recurso'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-descripcion="<?= htmlspecialchars($recurso['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-serial="<?= htmlspecialchars($recurso['serial'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-marca="<?= htmlspecialchars($recurso['marca'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-modelo="<?= htmlspecialchars($recurso['modelo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-estado="<?= htmlspecialchars($recurso['estado'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-disponibilidad="<?= htmlspecialchars($recurso['disponibilidad'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-fecha="<?= htmlspecialchars($recurso['fecha_adquisicion'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

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

                            <td colspan="7">

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

<div
    class="modal fade"
    id="modalRecurso"
    tabindex="-1"
    aria-labelledby="tituloModalRecurso"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">

        <div class="modal-content modal-recurso">

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

            <form
                id="formRecurso"
                action="../controllers/RecursoController.php"
                method="POST"
            >

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

                <div class="modal-body">

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
                                placeholder="Ej. REC-001"
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
                                placeholder="Nombre del recurso"
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
                                        <?= htmlspecialchars(
                                            $categoria['nombre_categoria'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
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
                                placeholder="Número de serial"
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
                                placeholder="Marca"
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
                                placeholder="Modelo"
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
                                placeholder="Describe las características del recurso..."
                            ></textarea>

                        </div>

                    </div>

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

                </div>

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

<script>

document.addEventListener('DOMContentLoaded', function () {

    const formulario = document.getElementById('formRecurso');
    const modal = document.getElementById('modalRecurso');

    const accion = document.getElementById('accionRecurso');
    const idRecurso = document.getElementById('id_recurso');

    const titulo = document.getElementById('tituloModalRecurso');
    const subtitulo = document.getElementById('subtituloModalRecurso');
    const botonGuardar = document.getElementById('btnGuardarRecurso');

    const campos = {
        codigo_interno: 'codigo',
        nombre_recurso: 'nombre',
        id_categoria: 'categoria',
        fecha_adquisicion: 'fecha',
        serial: 'serial',
        marca: 'marca',
        modelo: 'modelo',
        descripcion: 'descripcion',
        estado: 'estado',
        disponibilidad: 'disponibilidad'
    };

    function prepararRegistro() {

        formulario.reset();

        accion.value = 'guardar';
        idRecurso.value = '';

        titulo.innerHTML = '<i class="bi bi-plus-circle me-2"></i> Registrar recurso';

        subtitulo.textContent =
            'Ingresa la información del nuevo recurso institucional.';

        botonGuardar.innerHTML =
            '<i class="bi bi-check-circle me-2"></i> Registrar recurso';

    }

    document.getElementById('btnNuevoRecurso').addEventListener('click', prepararRegistro);

    document.querySelectorAll('.btnEditarRecurso').forEach(function (boton) {

        boton.addEventListener('click', function () {

            formulario.reset();

            accion.value = 'editar';
            idRecurso.value = this.dataset.id;

            Object.entries(campos).forEach(function ([campo, atributo]) {

                document.getElementById(campo).value =
                    boton.dataset[atributo] || '';

            });

            titulo.innerHTML =
                '<i class="bi bi-pencil-square me-2"></i> Editar recurso';

            subtitulo.textContent =
                'Actualiza la información del recurso seleccionado.';

            botonGuardar.innerHTML =
                '<i class="bi bi-check-circle me-2"></i> Guardar cambios';

        });

    });

    modal.addEventListener('hidden.bs.modal', prepararRegistro);

});

</script>

<?php

require_once __DIR__ . '/../includes/footer.php';
require_once __DIR__ . '/../includes/scripts.php';

?>
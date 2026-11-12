
<?php
require_once __DIR__ . '/../includes/auth.php';
rs_requiere_admin();
$conexion = rs_pdo();

// Escapar datos para mostrarlos de forma segura
function limpiar($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$empleados = [];
$errorConsulta = '';

$buscar = trim($_GET['buscar'] ?? '');
$estado = trim($_GET['estado'] ?? '');

$totalEmpleados = 0;
$totalActivos = 0;
$totalInactivos = 0;

try {

    // ==========================================
    // CONSULTAR EMPLEADOS
    // ==========================================

    $sql = "SELECT *
            FROM empleado
            WHERE 1=1";

    $parametros = [];

    if ($buscar !== '') {

        $sql .= " AND (
            nombre LIKE :nombre
            OR apellido LIKE :apellido
            OR documento LIKE :documento
            OR correo LIKE :correo
            OR cargo LIKE :cargo
        )";

        $texto = "%{$buscar}%";

        $parametros[':nombre'] = $texto;
        $parametros[':apellido'] = $texto;
        $parametros[':documento'] = $texto;
        $parametros[':correo'] = $texto;
        $parametros[':cargo'] = $texto;
    }

    if (in_array($estado, ['Activo', 'Inactivo'], true)) {
        $sql .= " AND estado = :estado";
        $parametros[':estado'] = $estado;
    }

    $sql .= " ORDER BY id_empleado DESC";

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);

    $empleados = $consulta->fetchAll(PDO::FETCH_ASSOC);


    // ==========================================
    // CONTADORES
    // ==========================================

    $totalEmpleados = (int)$conexion
        ->query("SELECT COUNT(*) FROM empleado")
        ->fetchColumn();

    $totalActivos = (int)$conexion
        ->query("SELECT COUNT(*) FROM empleado WHERE estado = 'Activo'")
        ->fetchColumn();

    $totalInactivos = (int)$conexion
        ->query("SELECT COUNT(*) FROM empleado WHERE estado = 'Inactivo'")
        ->fetchColumn();


} catch (PDOException $e) {

    error_log("Error en empleados.php: " . $e->getMessage());

    $errorConsulta = 'No fue posible cargar la información de los empleados.';
}
?>

<?php include __DIR__ . '/../includes/head.php'; ?>

<link rel="stylesheet" href="../CSS/empleados.css">

<?php include __DIR__ . '/../includes/slider.php'; ?>

<div class="main-content">

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid empleados-page">

        <!-- ENCABEZADO -->
        <div class="page-heading">

            <div>

                <span class="empleados-etiqueta">
                    <i class="bi bi-person-badge"></i>
                    ADMINISTRACIÓN
                </span>

                <h2>Gestión de empleados</h2>

                <p>
                    Administra la información laboral y las cuentas de acceso
                    de los empleados registrados en RecurSoft.
                </p>

            </div>

            <button
                class="btn btn-primary btn-registrar"
                data-bs-toggle="modal"
                data-bs-target="#modalEmpleado">

                <i class="bi bi-person-plus"></i>
                Registrar empleado

            </button>

        </div>


        <!-- MENSAJES -->
        <?php if ($errorConsulta): ?>

            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                <?= limpiar($errorConsulta) ?>
            </div>

        <?php endif; ?>


        <?php if (isset($_GET['mensaje'])): ?>

            <div class="alert alert-info alert-dismissible fade show">

                <?= limpiar($_GET['mensaje']) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <!-- TARJETAS DE RESUMEN -->
        <div class="row resumen-empleados">

            <div class="col-md-4 mb-3">

                <div class="empleado-card">

                    <div class="empleado-card-icono icono-azul">
                        <i class="bi bi-people-fill"></i>
                    </div>

                    <div>
                        <span>Total empleados</span>
                        <h3><?= $totalEmpleados ?></h3>
                    </div>

                </div>

            </div>


            <div class="col-md-4 mb-3">

                <div class="empleado-card">

                    <div class="empleado-card-icono icono-verde">
                        <i class="bi bi-person-check-fill"></i>
                    </div>

                    <div>
                        <span>Empleados activos</span>
                        <h3><?= $totalActivos ?></h3>
                    </div>

                </div>

            </div>


            <div class="col-md-4 mb-3">

                <div class="empleado-card">

                    <div class="empleado-card-icono icono-gris">
                        <i class="bi bi-person-x-fill"></i>
                    </div>

                    <div>
                        <span>Empleados inactivos</span>
                        <h3><?= $totalInactivos ?></h3>
                    </div>

                </div>

            </div>

        </div>


        <!-- FILTROS -->
        <div class="filtros-empleados">

            <div class="filtros-header">

                <div class="filtros-titulo">

                    <div class="filtros-icono">
                        <i class="bi bi-funnel"></i>
                    </div>

                    <div>
                        <h5>Buscar empleados</h5>
                        <p>Utiliza los filtros para encontrar un empleado.</p>
                    </div>

                </div>

                <span class="filtros-etiqueta">FILTROS</span>

            </div>


            <form method="GET" class="form-filtros">

                <div class="row g-3">

                    <div class="col-lg-8 col-md-7">

                        <label class="form-label">
                            Buscar empleado
                        </label>

                        <div class="buscador-input">

                            <i class="bi bi-search"></i>

                            <input
                                type="text"
                                name="buscar"
                                class="form-control"
                                placeholder="Nombre, documento, correo o cargo"
                                value="<?= limpiar($buscar) ?>">

                        </div>

                    </div>


                    <div class="col-lg-4 col-md-5">

                        <label class="form-label">
                            Estado
                        </label>

                        <div class="filtro-select">

                            <i class="bi bi-toggle-on"></i>

                            <select name="estado" class="form-select">

                                <option value="">Todos los estados</option>

                                <option
                                    value="Activo"
                                    <?= $estado === 'Activo' ? 'selected' : '' ?>>
                                    Activo
                                </option>

                                <option
                                    value="Inactivo"
                                    <?= $estado === 'Inactivo' ? 'selected' : '' ?>>
                                    Inactivo
                                </option>

                            </select>

                        </div>

                    </div>

                </div>


                <div class="filtros-footer">

                    <div class="filtros-info">
                        <i class="bi bi-info-circle"></i>
                        Filtra los registros según tus necesidades.
                    </div>

                    <div class="filtros-botones">

                        <a
                            href="empleados.php"
                            class="btn-limpiar-filtros">

                            <i class="bi bi-arrow-counterclockwise"></i>
                            Limpiar

                        </a>

                        <button
                            type="submit"
                            class="btn-aplicar-filtros">

                            <i class="bi bi-search"></i>
                            Aplicar filtros

                        </button>

                    </div>

                </div>

            </form>

        </div>


        <!-- TABLA DE EMPLEADOS -->
        <div class="tabla-empleados-contenedor">

            <div class="tabla-empleados-encabezado">

                <div>
                    <h5>Listado de empleados</h5>
                    <p>Información de los empleados registrados en el sistema.</p>
                </div>

                <span class="contador-empleados">

                    <i class="bi bi-people"></i>
                    <?= count($empleados) ?> registros

                </span>

            </div>


            <div class="table-responsive">

                <table class="table tabla-empleados">

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Empleado</th>
                            <th>Documento</th>
                            <th>Cargo</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php if (!empty($empleados)): ?>

                        <?php foreach ($empleados as $empleado): ?>

                            <tr>

                                <!-- ID -->
                                <td>

                                    <span class="id-empleado">
                                        #<?= limpiar($empleado['id_empleado']) ?>
                                    </span>

                                </td>


                                <!-- EMPLEADO -->
                                <td>

                                    <div class="empleado-identidad">

                                        <div class="empleado-avatar">
                                            <?= limpiar(strtoupper(substr($empleado['nombre'] ?? 'E', 0, 1))) ?>
                                        </div>

                                        <div>

                                            <strong>
                                                <?= limpiar($empleado['nombre']) ?>
                                                <?= limpiar($empleado['apellido']) ?>
                                            </strong>

                                            <small>
                                                Empleado #<?= limpiar($empleado['id_empleado']) ?>
                                            </small>

                                        </div>

                                    </div>

                                </td>


                                <!-- DOCUMENTO -->
                                <td>
                                    <?= limpiar($empleado['documento']) ?>
                                </td>


                                <!-- CARGO -->
                                <td>

                                    <span class="cargo-empleado">
                                        <?= limpiar($empleado['cargo']) ?>
                                    </span>

                                </td>


                                <!-- CORREO -->
                                <td>

                                    <span class="dato-empleado">

                                        <i class="bi bi-envelope"></i>

                                        <?= limpiar($empleado['correo']) ?>

                                    </span>

                                </td>


                                <!-- TELEFONO -->
                                <td>

                                    <span class="dato-empleado">

                                        <i class="bi bi-telephone"></i>

                                        <?= limpiar($empleado['telefono']) ?>

                                    </span>

                                </td>


                                <!-- ESTADO -->
                                <td>

                                    <?php if (($empleado['estado'] ?? '') === 'Activo'): ?>

                                        <span class="estado-empleado activo">
                                            <span></span>
                                            Activo
                                        </span>

                                    <?php else: ?>

                                        <span class="estado-empleado inactivo">
                                            <span></span>
                                            Inactivo
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ACCIONES -->
                                <td>

                                    <div class="acciones-empleado">

                                        <button
                                            type="button"
                                            class="btn-accion editar btn-editar-empleado"
                                            title="Editar empleado"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditarEmpleado"

                                            data-id="<?= limpiar($empleado['id_empleado']) ?>"
                                            data-documento="<?= limpiar($empleado['documento']) ?>"
                                            data-nombre="<?= limpiar($empleado['nombre']) ?>"
                                            data-apellido="<?= limpiar($empleado['apellido']) ?>"
                                            data-cargo="<?= limpiar($empleado['cargo']) ?>"
                                            data-correo="<?= limpiar($empleado['correo']) ?>"
                                            data-telefono="<?= limpiar($empleado['telefono']) ?>"
                                            data-estado="<?= limpiar($empleado['estado']) ?>">

                                            <i class="bi bi-pencil-square"></i>

                                        </button>


                                        <form
                                            action="../controllers/EmpleadoController.php"
                                            method="POST"
                                            onsubmit="return confirm('¿Deseas cambiar el estado de este empleado?');">

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="cambiar_estado">

                                            <input
                                                type="hidden"
                                                name="id_empleado"
                                                value="<?= limpiar($empleado['id_empleado']) ?>">

                                            <button
                                                type="submit"
                                                class="btn-accion <?= ($empleado['estado'] ?? '') === 'Activo' ? 'desactivar' : 'activar' ?>"
                                                title="<?= ($empleado['estado'] ?? '') === 'Activo' ? 'Desactivar' : 'Activar' ?>">

                                                <i class="bi <?= ($empleado['estado'] ?? '') === 'Activo' ? 'bi-person-dash' : 'bi-person-check' ?>"></i>

                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                    <?php else: ?>

                        <tr>

                            <td colspan="8">

                                <div class="tabla-vacia">

                                    <i class="bi bi-person-badge"></i>

                                    <h6>No hay empleados para mostrar</h6>

                                    <p>
                                        No se encontraron registros con los filtros seleccionados.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<!-- ==========================================
     MODAL REGISTRAR EMPLEADO
========================================== -->

<div class="modal fade" id="modalEmpleado" tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content modal-empleado">

            <div class="modal-header">

                <div>
                    <h5 class="modal-title">Registrar empleado</h5>

                    <small>
                        Ingresa los datos personales, laborales y de acceso.
                    </small>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>


            <form
                action="../controllers/EmpleadoController.php"
                method="POST"
                autocomplete="off">

                <input type="hidden" name="accion" value="guardar">


                <div class="modal-body">

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Nombre
                            </label>

                            <input
                                type="text"
                                name="nombre"
                                class="form-control"
                                maxlength="50"
                                placeholder="Nombre del empleado"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Apellido
                            </label>

                            <input
                                type="text"
                                name="apellido"
                                class="form-control"
                                maxlength="50"
                                placeholder="Apellido del empleado"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Documento
                            </label>

                            <input
                                type="text"
                                name="documento"
                                class="form-control"
                                maxlength="20"
                                placeholder="Número de documento"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Cargo
                            </label>

                            <input
                                type="text"
                                name="cargo"
                                class="form-control"
                                maxlength="50"
                                placeholder="Cargo del empleado"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Correo electrónico
                            </label>

                            <input
                                type="email"
                                name="correo"
                                class="form-control"
                                maxlength="100"
                                placeholder="empleado@institucion.edu"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Teléfono
                            </label>

                            <input
                                type="text"
                                name="telefono"
                                class="form-control"
                                maxlength="20"
                                placeholder="Número de teléfono"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Contraseña
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                minlength="8"
                                placeholder="Mínimo 8 caracteres"
                                autocomplete="new-password"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Confirmar contraseña
                            </label>

                            <input
                                type="password"
                                name="confirmar_password"
                                class="form-control"
                                minlength="8"
                                placeholder="Repite la contraseña"
                                autocomplete="new-password"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Estado
                            </label>

                            <select
                                name="estado"
                                class="form-select"
                                required>

                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>

                            </select>

                        </div>

                    </div>

                    <div class="alert alert-light border mt-4 mb-0">

                        <i class="bi bi-info-circle me-2"></i>

                        Al registrar el empleado, RecurSoft creará automáticamente
                        su cuenta de acceso con el rol de Empleado.

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-cancelar"
                        data-bs-dismiss="modal">

                        Cancelar

                    </button>

                    <button type="submit" class="btn btn-primary">

                        <i class="bi bi-person-plus"></i>
                        Registrar empleado

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ==========================================
     MODAL EDITAR EMPLEADO
========================================== -->

<div class="modal fade" id="modalEditarEmpleado" tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content modal-empleado">

            <div class="modal-header">

                <div>

                    <h5 class="modal-title">Editar empleado</h5>

                    <small>
                        Actualiza la información del empleado registrado.
                    </small>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>


            <form
                action="../controllers/EmpleadoController.php"
                method="POST"
                autocomplete="off">

                <input type="hidden" name="accion" value="editar">

                <input
                    type="hidden"
                    name="id_empleado"
                    id="editar_id_empleado">


                <div class="modal-body">

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Nombre
                            </label>

                            <input
                                type="text"
                                name="nombre"
                                id="editar_nombre"
                                class="form-control"
                                maxlength="50"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Apellido
                            </label>

                            <input
                                type="text"
                                name="apellido"
                                id="editar_apellido"
                                class="form-control"
                                maxlength="50"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Documento
                            </label>

                            <input
                                type="text"
                                name="documento"
                                id="editar_documento"
                                class="form-control"
                                maxlength="20"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Cargo
                            </label>

                            <input
                                type="text"
                                name="cargo"
                                id="editar_cargo"
                                class="form-control"
                                maxlength="50"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Correo electrónico
                            </label>

                            <input
                                type="email"
                                name="correo"
                                id="editar_correo"
                                class="form-control"
                                maxlength="100"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Teléfono
                            </label>

                            <input
                                type="text"
                                name="telefono"
                                id="editar_telefono"
                                class="form-control"
                                maxlength="20"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Nueva contraseña
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                minlength="8"
                                placeholder="Dejar vacío para conservarla"
                                autocomplete="new-password">

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Confirmar nueva contraseña
                            </label>

                            <input
                                type="password"
                                name="confirmar_password"
                                class="form-control"
                                minlength="8"
                                placeholder="Repite la nueva contraseña"
                                autocomplete="new-password">

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Estado
                            </label>

                            <select
                                name="estado"
                                id="editar_estado"
                                class="form-select"
                                required>

                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>

                            </select>

                        </div>

                    </div>

                    <div class="alert alert-light border mt-4 mb-0">

                        <i class="bi bi-shield-lock me-2"></i>

                        Si no deseas cambiar la contraseña, deja los dos campos vacíos.

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-cancelar"
                        data-bs-dismiss="modal">

                        Cancelar

                    </button>

                    <button type="submit" class="btn btn-primary">

                        <i class="bi bi-check-circle"></i>
                        Guardar cambios

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ==========================================
     JAVASCRIPT PARA CARGAR DATOS EN EDICIÓN
========================================== -->

<script>

document.querySelectorAll('.btn-editar-empleado').forEach(boton => {

    boton.addEventListener('click', function () {

        document.getElementById('editar_id_empleado').value =
            this.dataset.id;

        document.getElementById('editar_nombre').value =
            this.dataset.nombre;

        document.getElementById('editar_apellido').value =
            this.dataset.apellido;

        document.getElementById('editar_documento').value =
            this.dataset.documento;

        document.getElementById('editar_cargo').value =
            this.dataset.cargo;

        document.getElementById('editar_correo').value =
            this.dataset.correo;

        document.getElementById('editar_telefono').value =
            this.dataset.telefono;

        document.getElementById('editar_estado').value =
            this.dataset.estado;

    });

});

</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php include __DIR__ . '/../includes/scripts.php'; ?>

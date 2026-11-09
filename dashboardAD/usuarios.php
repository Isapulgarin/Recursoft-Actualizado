
<?php
require_once __DIR__ . '/../config/conexion.php';

function limpiar($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$usuarios = [];
$errorConsulta = '';
$totalUsuarios = 0;
$totalActivos = 0;
$totalInactivos = 0;

$buscar = trim($_GET['buscar'] ?? '');
$tipoFiltro = trim($_GET['tipo_usuario'] ?? '');
$estadoFiltro = trim($_GET['estado'] ?? '');

try {
    $sql = "SELECT * FROM usuario WHERE 1=1";
    $parametros = [];

    if ($buscar !== '') {
        $sql .= " AND (
            nombre LIKE :nombre
            OR apellido LIKE :apellido
            OR correo LIKE :correo
            OR telefono LIKE :telefono
        )";

        $texto = "%{$buscar}%";
        $parametros[':nombre'] = $texto;
        $parametros[':apellido'] = $texto;
        $parametros[':correo'] = $texto;
        $parametros[':telefono'] = $texto;
    }

    if ($tipoFiltro !== '') {
        $sql .= " AND tipo_usuario = :tipo_usuario";
        $parametros[':tipo_usuario'] = $tipoFiltro;
    }

    if ($estadoFiltro !== '') {
        $sql .= " AND estado = :estado";
        $parametros[':estado'] = $estadoFiltro;
    }

    $sql .= " ORDER BY id_usuario DESC";

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);
    $usuarios = $consulta->fetchAll(PDO::FETCH_ASSOC);

    $totalUsuarios = (int)$conexion
        ->query("SELECT COUNT(*) FROM usuario WHERE tipo_usuario IS NOT NULL")
        ->fetchColumn();

    $totalActivos = (int)$conexion
        ->query("SELECT COUNT(*) FROM usuario WHERE tipo_usuario IS NOT NULL AND estado = 'Activo'")
        ->fetchColumn();

    $totalInactivos = (int)$conexion
        ->query("SELECT COUNT(*) FROM usuario WHERE tipo_usuario IS NOT NULL AND estado = 'Inactivo'")
        ->fetchColumn();

} catch (PDOException $e) {
    error_log('Error en usuarios.php: ' . $e->getMessage());
    $errorConsulta = 'No fue posible cargar los usuarios. Verifica la estructura de la tabla usuario.';
}

$pageTitle = 'Gestión de usuarios';
$dashboardType = 'AD';
$activePage = 'usuarios';
?>

<?php include __DIR__ . '/../includes/head.php'; ?>
<link rel="stylesheet" href="../CSS/usuarios.css">
<?php include __DIR__ . '/../includes/slider.php'; ?>

<div class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid usuarios-page">

        <div class="page-heading">
            <div>
                <span class="usuarios-etiqueta">
                    <i class="bi bi-people"></i> ADMINISTRACIÓN
                </span>
                <h2>Gestión de usuarios</h2>
                <p>Registra y administra las personas que pueden recibir recursos institucionales.</p>
            </div>

            <button class="btn btn-primary btn-registrar"
                    data-bs-toggle="modal"
                    data-bs-target="#modalUsuario">
                <i class="bi bi-person-plus"></i>
                Registrar usuario
            </button>
        </div>

        <?php if ($errorConsulta !== ''): ?>
            <div class="alert alert-danger">
                <?= limpiar($errorConsulta) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['mensaje'])): ?>
            <div class="alert alert-info">
                <?= limpiar($_GET['mensaje']) ?>
            </div>
        <?php endif; ?>

        <div class="row resumen-usuarios">
            <div class="col-md-4 mb-3">
                <div class="usuario-card">
                    <div class="usuario-card-icono icono-azul">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <span>Total usuarios</span>
                        <h3><?= $totalUsuarios ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="usuario-card">
                    <div class="usuario-card-icono icono-verde">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                    <div>
                        <span>Usuarios activos</span>
                        <h3><?= $totalActivos ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="usuario-card">
                    <div class="usuario-card-icono icono-gris">
                        <i class="bi bi-person-x-fill"></i>
                    </div>
                    <div>
                        <span>Usuarios inactivos</span>
                        <h3><?= $totalInactivos ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="filtros-usuarios">
            <div class="filtros-header">
                <div class="filtros-titulo">
                    <div class="filtros-icono">
                        <i class="bi bi-funnel"></i>
                    </div>
                    <div>
                        <h5>Buscar usuarios</h5>
                        <p>Filtra por nombre, tipo o estado.</p>
                    </div>
                </div>
                <span class="filtros-etiqueta">FILTROS</span>
            </div>

            <form method="GET" class="form-filtros">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label">Buscar usuario</label>
                        <input type="text"
                               name="buscar"
                               class="form-control"
                               placeholder="Nombre, apellido, correo o teléfono"
                               value="<?= limpiar($buscar) ?>">
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Tipo de usuario</label>
                        <select name="tipo_usuario" class="form-select">
                            <option value="">Todos los tipos</option>
                            <?php foreach (['Estudiante', 'Docente', 'Administrativo', 'Otro'] as $tipo): ?>
                                <option value="<?= limpiar($tipo) ?>"
                                    <?= $tipoFiltro === $tipo ? 'selected' : '' ?>>
                                    <?= limpiar($tipo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            <option value="Activo" <?= $estadoFiltro === 'Activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="Inactivo" <?= $estadoFiltro === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>
                </div>

                <div class="filtros-footer">
                    <div class="filtros-info">
                        <i class="bi bi-info-circle"></i>
                        Filtra los registros según tus necesidades.
                    </div>
                    <div class="filtros-botones">
                        <a href="usuarios.php" class="btn-limpiar-filtros">
                            <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                        </a>
                        <button type="submit" class="btn-aplicar-filtros">
                            <i class="bi bi-search"></i> Aplicar filtros
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="tabla-usuarios-contenedor">
            <div class="tabla-usuarios-encabezado">
                <div>
                    <h5>Listado de usuarios</h5>
                    <p>Personas registradas para recibir recursos.</p>
                </div>
                <span class="contador-usuarios">
                    <i class="bi bi-people"></i>
                    <?= count($usuarios) ?> registros
                </span>
            </div>

            <div class="table-responsive">
                <table class="table tabla-usuarios">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Tipo</th>
                            <th>Correo electrónico</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php
                    $usuarios = array_filter($usuarios, function ($u) {
                        return !empty($u['tipo_usuario']);
                    });
                    ?>

                    <?php if (!empty($usuarios)): ?>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td>
                                    <span class="id-usuario">
                                        #<?= limpiar($usuario['id_usuario']) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="usuario-identidad">
                                        <div class="usuario-avatar">
                                            <?= limpiar(strtoupper(substr($usuario['nombre'] ?? 'U', 0, 1))) ?>
                                        </div>
                                        <div>
                                            <strong>
                                                <?= limpiar($usuario['nombre']) ?>
                                                <?= limpiar($usuario['apellido']) ?>
                                            </strong>
                                            <small>Persona registrada</small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="rol-usuario">
                                        <?= limpiar($usuario['tipo_usuario']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="dato-usuario">
                                        <i class="bi bi-envelope"></i>
                                        <?= limpiar($usuario['correo']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="dato-usuario">
                                        <i class="bi bi-telephone"></i>
                                        <?= limpiar($usuario['telefono']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if (($usuario['estado'] ?? '') === 'Activo'): ?>
                                        <span class="estado-usuario activo"><span></span> Activo</span>
                                    <?php else: ?>
                                        <span class="estado-usuario inactivo"><span></span> Inactivo</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="acciones-usuario">
                                        <button type="button"
                                                class="btn-accion editar btn-editar-usuario"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditarUsuario"
                                                data-id="<?= limpiar($usuario['id_usuario']) ?>"
                                                data-nombre="<?= limpiar($usuario['nombre']) ?>"
                                                data-apellido="<?= limpiar($usuario['apellido']) ?>"
                                                data-tipo="<?= limpiar($usuario['tipo_usuario']) ?>"
                                                data-correo="<?= limpiar($usuario['correo']) ?>"
                                                data-telefono="<?= limpiar($usuario['telefono']) ?>"
                                                data-estado="<?= limpiar($usuario['estado']) ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <form action="../controllers/UsuarioController.php"
                                              method="POST"
                                              onsubmit="return confirm('¿Estás seguro de eliminar esta persona?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id_usuario"
                                                   value="<?= limpiar($usuario['id_usuario']) ?>">
                                            <button type="submit" class="btn-accion eliminar">
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
                                <div class="tabla-vacia">
                                    <i class="bi bi-people"></i>
                                    <h6>No hay usuarios para mostrar</h6>
                                    <p>No se encontraron personas con los filtros seleccionados.</p>
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

<!-- MODAL REGISTRAR -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-usuario">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Registrar usuario</h5>
                    <small>Ingresa los datos de la persona.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form action="../controllers/UsuarioController.php" method="POST">
                <input type="hidden" name="accion" value="guardar">

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" maxlength="50" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Apellido</label>
                            <input type="text" name="apellido" class="form-control" maxlength="50" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tipo de usuario</label>
                            <select name="tipo_usuario" class="form-select" required>
                                <option value="">Seleccionar tipo</option>
                                <option value="Estudiante">Estudiante</option>
                                <option value="Docente">Docente</option>
                                <option value="Administrativo">Administrativo</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select" required>
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" name="correo" class="form-control" maxlength="100" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="telefono" class="form-control" maxlength="20" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Registrar usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-usuario">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Editar usuario</h5>
                    <small>Actualiza la información de la persona.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form action="../controllers/UsuarioController.php" method="POST">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id_usuario" id="editar_id">

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="editar_nombre" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Apellido</label>
                            <input type="text" name="apellido" id="editar_apellido" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tipo de usuario</label>
                            <select name="tipo_usuario" id="editar_tipo" class="form-select" required>
                                <option value="Estudiante">Estudiante</option>
                                <option value="Docente">Docente</option>
                                <option value="Administrativo">Administrativo</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select name="estado" id="editar_estado" class="form-select" required>
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" name="correo" id="editar_correo" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="telefono" id="editar_telefono" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-editar-usuario').forEach(boton => {
    boton.addEventListener('click', function () {
        document.getElementById('editar_id').value = this.dataset.id;
        document.getElementById('editar_nombre').value = this.dataset.nombre;
        document.getElementById('editar_apellido').value = this.dataset.apellido;
        document.getElementById('editar_tipo').value = this.dataset.tipo;
        document.getElementById('editar_correo').value = this.dataset.correo;
        document.getElementById('editar_telefono').value = this.dataset.telefono;
        document.getElementById('editar_estado').value = this.dataset.estado;
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/scripts.php'; ?>

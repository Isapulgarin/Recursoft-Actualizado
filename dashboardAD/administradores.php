
<?php

$pageTitle = 'Administradores';
$dashboardType = 'AD';
$activePage = 'administradores';

require_once __DIR__ . '/../includes/auth.php';

$usuarioSesion = rs_requiere_admin();
$conexion = rs_pdo();

$mensaje = $_GET['mensaje'] ?? '';
$error = '';

$administradores = [];

try {
    $sql = "
        SELECT
            a.id_administrador,
            a.id_cuenta,
            a.documento,
            a.nombre,
            a.apellido,
            a.telefono,
            ca.correo,
            a.estado,
            ca.estado AS estado_cuenta
        FROM administrador a
        INNER JOIN cuenta_acceso ca
            ON a.id_cuenta = ca.id_cuenta
        WHERE ca.id_rol = 1
        ORDER BY a.id_administrador DESC
    ";

    $consulta = $conexion->query($sql);
    $administradores = $consulta->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Error administradores: ' . $e->getMessage());
    $error = 'No fue posible consultar los administradores.';
}

if (empty($_SESSION['csrf_administradores'])) {
    $_SESSION['csrf_administradores'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_administradores'];

function rs_escape_admin($valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/slider.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<main class="main-content">

    <div class="container-fluid py-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

            <div>
                <h1>Gestión de administradores</h1>
                <p>Administra las cuentas de acceso del personal administrador.</p>
            </div>

            <button
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalAdministrador"
                id="btnNuevoAdmin"
            >
                <i class="bi bi-plus-lg"></i>
                Nuevo administrador
            </button>

        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-info">
                <?= rs_escape_admin($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?= rs_escape_admin($error) ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">

            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Administradores registrados</h5>
            </div>

            <div class="card-body table-responsive">

                <table class="table table-hover align-middle">

                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($administradores): ?>

                        <?php foreach ($administradores as $admin): ?>

                            <tr>

                                <td>
                                    <?= rs_escape_admin($admin['documento']) ?>
                                </td>

                                <td>
                                    <?= rs_escape_admin(
                                        $admin['nombre'] . ' ' . $admin['apellido']
                                    ) ?>
                                </td>

                                <td>
                                    <?= rs_escape_admin($admin['correo']) ?>
                                </td>

                                <td>
                                    <?= rs_escape_admin($admin['telefono']) ?>
                                </td>

                                <td>
                                    <?php if ($admin['estado'] === 'Activo'): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>

                                <td>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary btnEditarAdmin"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalAdministrador"
                                        data-id="<?= (int) $admin['id_administrador'] ?>"
                                        data-documento="<?= rs_escape_admin($admin['documento']) ?>"
                                        data-nombre="<?= rs_escape_admin($admin['nombre']) ?>"
                                        data-apellido="<?= rs_escape_admin($admin['apellido']) ?>"
                                        data-telefono="<?= rs_escape_admin($admin['telefono']) ?>"
                                        data-correo="<?= rs_escape_admin($admin['correo']) ?>"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <form
                                        action="../controllers/AdministradorController.php"
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('¿Cambiar el estado de este administrador?')"
                                    >

                                        <input type="hidden" name="csrf_token" value="<?= rs_escape_admin($csrf) ?>">
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="id_administrador" value="<?= (int) $admin['id_administrador'] ?>">

                                        <button class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-power"></i>
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="text-center py-4">
                                No hay administradores registrados.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>
        </div>

    </div>

</main>

<!-- MODAL -->

<div class="modal fade" id="modalAdministrador" tabindex="-1">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="tituloAdmin">
                    Registrar administrador
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form
                action="../controllers/AdministradorController.php"
                method="POST"
                id="formAdministrador"
            >

                <div class="modal-body">

                    <input type="hidden" name="csrf_token" value="<?= rs_escape_admin($csrf) ?>">
                    <input type="hidden" name="accion" id="accionAdmin" value="guardar">
                    <input type="hidden" name="id_administrador" id="id_administrador">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Documento *</label>
                            <input type="text" name="documento" id="documento" class="form-control" maxlength="30" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" maxlength="80" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Apellido *</label>
                            <input type="text" name="apellido" id="apellido" class="form-control" maxlength="80" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" id="telefono" class="form-control" maxlength="20">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Correo electrónico *</label>
                            <input type="email" name="correo" id="correo" class="form-control" maxlength="120" required>
                        </div>

                        <div class="col-md-6" id="campoPassword">
                            <label class="form-label">Contraseña *</label>
                            <input type="password" name="password" id="password" class="form-control" minlength="8">
                        </div>

                    </div>

                    <small class="text-muted d-block mt-3">
                        La contraseña debe tener al menos 8 caracteres.
                        Al editar, déjala vacía si no deseas cambiarla.
                    </small>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Guardar administrador
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const formulario = document.getElementById('formAdministrador');
    const modal = document.getElementById('modalAdministrador');

    function limpiarFormulario() {

        formulario.reset();

        document.getElementById('accionAdmin').value = 'guardar';
        document.getElementById('id_administrador').value = '';
        document.getElementById('password').required = true;

        document.getElementById('tituloAdmin').textContent =
            'Registrar administrador';
    }

    document.getElementById('btnNuevoAdmin').addEventListener('click', limpiarFormulario);

    document.querySelectorAll('.btnEditarAdmin').forEach(function (boton) {

        boton.addEventListener('click', function () {

            limpiarFormulario();

            document.getElementById('accionAdmin').value = 'editar';
            document.getElementById('id_administrador').value = this.dataset.id;

            document.getElementById('documento').value = this.dataset.documento;
            document.getElementById('nombre').value = this.dataset.nombre;
            document.getElementById('apellido').value = this.dataset.apellido;
            document.getElementById('telefono').value = this.dataset.telefono;
            document.getElementById('correo').value = this.dataset.correo;

            document.getElementById('password').required = false;

            document.getElementById('tituloAdmin').textContent =
                'Editar administrador';

        });

    });

    modal.addEventListener('hidden.bs.modal', limpiarFormulario);

});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
require_once __DIR__ . '/../includes/scripts.php';
?>

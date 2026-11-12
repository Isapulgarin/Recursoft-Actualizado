
<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Iniciar sesión';
$dashboardType = 'login';
$bodyClass = 'login-page';

$error = '';
if (!empty($_SESSION['rs_flash']['mensaje'])) {
    $error = (string) $_SESSION['rs_flash']['mensaje'];
    unset($_SESSION['rs_flash']);
}

require_once __DIR__ . '/includes/head.php';
?>

<div class="container-fluid px-0">
    <div class="row g-0 login-grid">

        <div class="col-lg-6 d-none d-lg-flex login-brand-panel">
            <div class="px-lg-5">
                <img src="<?php echo rs_h(RS_BASE); ?>IMG/logo.png" alt="Logotipo RecurSoft">

                <h1 class="display-6 fw-bold mb-3">RecurSoft</h1>

                <p class="fs-5 mb-4">
                    Sistema de Gestión de Recursos Institucionales
                </p>

                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="bi bi-check2-circle me-2"></i>
                        Control de inventario y préstamos
                    </li>

                    <li class="mb-2">
                        <i class="bi bi-check2-circle me-2"></i>
                        Paneles para administrador y empleado
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-lg-6 d-flex align-items-center justify-content-center p-3 p-md-4">

            <div class="login-card">

                <div class="d-flex d-lg-none align-items-center gap-3 mb-3">
                    <img
                        src="<?php echo rs_h(RS_BASE); ?>IMG/logo.png"
                        alt="Logotipo RecurSoft"
                        width="56"
                        height="56"
                        class="rounded-3"
                    >

                    <div>
                        <strong>RecurSoft</strong>
                        <div class="small text-secondary">
                            Gestión institucional
                        </div>
                    </div>
                </div>

                <p class="mb-3">
                    <a
                        class="small text-decoration-none"
                        href="<?php echo rs_h(RS_BASE); ?>index.php"
                    >
                        <i class="bi bi-arrow-left me-1"></i>
                        Volver al inicio
                    </a>
                </p>

                <h2 class="h4 fw-bold text-primary mb-1">
                    Iniciar sesión
                </h2>

                <p class="text-secondary mb-4">
                    Ingrese sus credenciales institucionales.
                </p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo rs_h(RS_BASE); ?>controllers/auth.php" autocomplete="on">
                    <input type="hidden" name="action" value="login">
                    <?php echo rs_csrf_field(); ?>

                    <div class="mb-3">

                        <label class="form-label" for="usuario">
                            Correo o nombre de usuario
                        </label>

                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person"></i>
                            </span>

                            <input
                                class="form-control"
                                type="text"
                                id="usuario"
                                name="usuario"
                                placeholder="usuario@institucion.edu o usuario"
                                value="<?php echo htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>

                    </div>

                    <div class="mb-3">

                        <label class="form-label" for="password">
                            Contraseña
                        </label>

                        <div class="password-wrap">

                            <input
                                class="form-control"
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Ingrese su contraseña"
                                required
                            >

                            <button
                                class="btn-eye"
                                type="button"
                                id="togglePassword"
                                aria-label="Mostrar contraseña"
                            >
                                <i class="bi bi-eye"></i>
                            </button>

                        </div>

                    </div>

                    <button class="btn btn-rs w-100" type="submit">
                        Iniciar sesión
                    </button>

                </form>

            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const boton = document.getElementById('togglePassword');
    const campo = document.getElementById('password');

    if (boton && campo) {

        boton.addEventListener('click', function () {

            const mostrar = campo.type === 'password';

            campo.type = mostrar ? 'text' : 'password';

            boton.innerHTML = mostrar
                ? '<i class="bi bi-eye-slash"></i>'
                : '<i class="bi bi-eye"></i>';

            boton.setAttribute(
                'aria-label',
                mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'
            );
        });
    }

});
</script>

<?php require_once __DIR__ . '/includes/scripts.php'; ?>

<?php
/**
 * Sidebar del Empleado.
 */
$activePage = $activePage ?? '';
?>
<aside class="offcanvas-lg offcanvas-start sidebar-rs" tabindex="-1" id="sidebarRecursoft" aria-labelledby="sidebarLabelEmployee">
    <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title" id="sidebarLabelEmployee">RecurSoft</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarRecursoft" aria-label="Cerrar menú"></button>
    </div>
    <div class="offcanvas-body">
        <div class="sidebar-brand">
            <img src="<?php echo rs_h(RS_BASE); ?>IMG/logo.png" alt="Logotipo RecurSoft">
            <div class="sidebar-text">
                <div class="sidebar-brand-title">RecurSoft</div>
                <div class="sidebar-brand-sub">Panel empleado</div>
            </div>
        </div>

        <nav class="sidebar-nav" aria-label="Menú empleado">
            <span class="sidebar-label">Principal</span>
            <a class="nav-link <?php echo rs_is_active('dashboard', $activePage); ?>" href="index.php">
                <i class="bi bi-speedometer2"></i><span class="sidebar-text">Dashboard</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('perfil', $activePage); ?>" href="perfil.php">
                <i class="bi bi-person-circle"></i><span class="sidebar-text">Mi Perfil</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('recursos', $activePage); ?>" href="recursos.php">
                <i class="bi bi-grid"></i><span class="sidebar-text">Catálogo de Recursos</span>
            </a>

            <span class="sidebar-label">Mis trámites</span>
            <a class="nav-link <?php echo rs_is_active('prestamos', $activePage); ?>" href="prestamos.php">
                <i class="bi bi-journal-check"></i><span class="sidebar-text">Mis Préstamos</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('devoluciones', $activePage); ?>" href="devoluciones.php">
                <i class="bi bi-arrow-return-left"></i><span class="sidebar-text">Devoluciones</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('reporte_danos', $activePage); ?>" href="reporte_danos.php">
                <i class="bi bi-exclamation-triangle"></i><span class="sidebar-text">Reportar Daños</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('historial', $activePage); ?>" href="historial.php">
                <i class="bi bi-clock-history"></i><span class="sidebar-text">Mi Historial</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('chatbot', $activePage); ?>" href="chatbot.php">
                <i class="bi bi-chat-dots"></i><span class="sidebar-text">Chatbot y Asistencia</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <form method="POST" action="<?php echo rs_h(RS_BASE); ?>controllers/auth.php">
                <input type="hidden" name="action" value="logout">
                <?php echo rs_csrf_field(); ?>
                <button class="nav-link logout" type="submit">
                <i class="bi bi-box-arrow-left"></i><span class="sidebar-text">Cerrar sesión</span>
                </button>
            </form>
        </div>
    </div>
</aside>

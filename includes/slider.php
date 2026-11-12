<?php
/**
 * Sidebar del Administrador.
 */
$activePage = $activePage ?? '';
?>
<aside class="offcanvas-lg offcanvas-start sidebar-rs" tabindex="-1" id="sidebarRecursoft" aria-labelledby="sidebarLabelAdmin">
    <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title" id="sidebarLabelAdmin">RecurSoft</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarRecursoft" aria-label="Cerrar menú"></button>
    </div>
    <div class="offcanvas-body">
        <div class="sidebar-brand">
            <img src="<?php echo rs_h(RS_BASE); ?>IMG/logo.png" alt="Logotipo RecurSoft">
            <div class="sidebar-text">
                <div class="sidebar-brand-title">RecurSoft</div>
                <div class="sidebar-brand-sub">Panel administrador</div>
            </div>
        </div>

        <nav class="sidebar-nav" aria-label="Menú administrador">
            <span class="sidebar-label">Principal</span>
            <a class="nav-link <?php echo rs_is_active('dashboard', $activePage); ?>" href="index.php">
                <i class="bi bi-speedometer2"></i><span class="sidebar-text">Dashboard</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('usuarios', $activePage); ?>" href="usuarios.php">
                <i class="bi bi-people"></i><span class="sidebar-text">Usuarios</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('recursos', $activePage); ?>" href="recursos.php">
                <i class="bi bi-box-seam"></i><span class="sidebar-text">Recursos</span>
            </a>

            <span class="sidebar-label">Operaciones</span>
            <a class="nav-link <?php echo rs_is_active('prestamos', $activePage); ?>" href="prestamos.php">
                <i class="bi bi-clipboard-check"></i><span class="sidebar-text">Préstamos</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('devoluciones', $activePage); ?>" href="devoluciones.php">
                <i class="bi bi-arrow-return-left"></i><span class="sidebar-text">Devoluciones</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('mantenimiento', $activePage); ?>" href="mantenimiento.php">
                <i class="bi bi-tools"></i><span class="sidebar-text">Mantenimientos</span>
            </a>

            <span class="sidebar-label">Catálogos</span>
            <a class="nav-link <?php echo rs_is_active('empleados', $activePage); ?>" href="empleados.php">
                <i class="bi bi-person-badge"></i><span class="sidebar-text">Empleados</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('empleados', $activePage); ?>" href="administradores.php">
                <i class="bi bi-person-badge"></i><span class="sidebar-text">Administradores</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('categorias', $activePage); ?>" href="categorias.php">
                <i class="bi bi-tags"></i><span class="sidebar-text">Categorías</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('proveedores', $activePage); ?>" href="proveedores.php">
                <i class="bi bi-truck"></i><span class="sidebar-text">Proveedores</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('compras', $activePage); ?>" href="compras.php">
                <i class="bi bi-bag-check"></i><span class="sidebar-text">Compras y Adquisiciones</span>
            </a>

            <span class="sidebar-label">Información</span>
            <a class="nav-link <?php echo rs_is_active('historial', $activePage); ?>" href="historial.php">
                <i class="bi bi-clock-history"></i><span class="sidebar-text">Historial de Préstamos</span>
            </a>
            <a class="nav-link <?php echo rs_is_active('reportes', $activePage); ?>" href="reportes.php">
                <i class="bi bi-graph-up"></i><span class="sidebar-text">Reportes</span>
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

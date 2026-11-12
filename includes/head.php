<?php
/**
 * Encabezado HTML compartido.
 */
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$requestedDirectory = basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
if ($requestedDirectory === 'dashboardAD') {
    rs_requiere_admin();
} elseif ($requestedDirectory === 'dashboardEM') {
    rs_requiere_empleado();
}

$pageTitle = $pageTitle ?? 'RecurSoft';
$dashboardType = $dashboardType ?? 'login';
$bodyClass = $bodyClass ?? '';

if ($dashboardType === 'AD') {
    rs_requiere_admin();
    header('Cache-Control: no-store, private');
    $bodyClass .= ' role-admin';
} elseif ($dashboardType === 'EM') {
    rs_requiere_empleado();
    header('Cache-Control: no-store, private');
    $bodyClass .= ' role-employee';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="RecurSoft - Sistema de Gestión de Recursos Institucionales">
    <meta name="theme-color" content="#000000">
    <title><?php echo rs_h($pageTitle); ?> | RecurSoft</title>

    <link rel="icon" href="<?php echo rs_h(RS_BASE); ?>IMG/logo.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo rs_h(RS_BASE); ?>CSS/EstilosRecursoft.css">
    <link rel="stylesheet" href="<?php echo rs_h(RS_BASE); ?>CSS/componentes.css">
    <?php if ($dashboardType === 'AD'): ?>
        <link rel="stylesheet" href="<?php echo rs_h(RS_BASE); ?>CSS/Administrador.css">
    <?php elseif ($dashboardType === 'EM'): ?>
        <link rel="stylesheet" href="<?php echo rs_h(RS_BASE); ?>CSS/Empleado.css">
    <?php endif; ?>
</head>
<body class="<?php echo rs_h(trim($bodyClass)); ?>">
<?php if ($dashboardType !== 'login'): ?>
<div class="app-shell d-flex">
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/auth.php';

$usuario = rs_requiere_login();
$conexion = rs_pdo();

function volverReportesDano(string $mensaje): void
{
    $destino = (int) (rs_usuario_sesion()['id_rol'] ?? 0) === 1
        ? 'dashboardAD/reportes_danos.php'
        : 'dashboardEM/reporte_danos.php';
    rs_flash('error', $mensaje);
    rs_redirect($destino);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverReportesDano('La solicitud no es válida.');
}

if (!rs_csrf_ok()) {
    volverReportesDano('La sesión del formulario expiró. Vuelve a intentarlo.');
}

$accion = $_POST['accion'] ?? '';

try {
    if ($accion === 'crear') {
        if ((int) $usuario['id_rol'] !== 2) {
            volverReportesDano('Solo un empleado puede registrar un reporte.');
        }

        $idRecurso = filter_var($_POST['id_recurso'] ?? null, FILTER_VALIDATE_INT);
        $tipo = trim((string) ($_POST['tipo_dano'] ?? ''));
        $fecha = trim((string) ($_POST['fecha_incidente'] ?? ''));
        $prioridad = trim((string) ($_POST['prioridad'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $tipos = ['Fisico', 'Funcional', 'Accesorio perdido', 'Otro'];
        $prioridades = ['Baja', 'Media', 'Alta'];
        $fechaValida = DateTime::createFromFormat('!Y-m-d', $fecha);

        if (
            $idRecurso === false || $idRecurso <= 0 ||
            !in_array($tipo, $tipos, true) ||
            !in_array($prioridad, $prioridades, true) ||
            !$fechaValida || $fechaValida->format('Y-m-d') !== $fecha ||
            $fecha > date('Y-m-d') ||
            mb_strlen($descripcion, 'UTF-8') < 10 ||
            mb_strlen($descripcion, 'UTF-8') > 2000
        ) {
            volverReportesDano('Revisa los datos del reporte; uno o más valores no son válidos.');
        }

        $insertar = $conexion->prepare(
            "INSERT INTO reporte_dano
                (id_cuenta_reporta, id_recurso, tipo_dano, fecha_incidente,
                 prioridad, descripcion)
             VALUES
                (:cuenta, :recurso, :tipo, :fecha, :prioridad, :descripcion)"
        );
        $insertar->execute([
            ':cuenta' => (int) $usuario['id_cuenta'],
            ':recurso' => $idRecurso,
            ':tipo' => $tipo,
            ':fecha' => $fecha,
            ':prioridad' => $prioridad,
            ':descripcion' => $descripcion
        ]);

        rs_flash('ok', 'El reporte de daños fue enviado al administrador.');
        rs_redirect('dashboardEM/reporte_danos.php');
    }

    if ($accion === 'actualizar') {
        if ((int) $usuario['id_rol'] !== 1) {
            volverReportesDano('Solo un administrador puede actualizar el estado del reporte.');
        }

        $id = filter_var($_POST['id_reporte_dano'] ?? null, FILTER_VALIDATE_INT);
        $estado = trim((string) ($_POST['estado'] ?? ''));
        $estados = ['Reportado', 'En revisión', 'Atendido', 'Descartado'];

        if ($id === false || $id <= 0 || !in_array($estado, $estados, true)) {
            volverReportesDano('El reporte o el estado seleccionado no son válidos.');
        }

        $actualizar = $conexion->prepare(
            'UPDATE reporte_dano SET estado = :estado WHERE id_reporte_dano = :id'
        );
        $actualizar->execute([':estado' => $estado, ':id' => $id]);

        if ($actualizar->rowCount() === 0) {
            $existe = $conexion->prepare(
                'SELECT 1 FROM reporte_dano WHERE id_reporte_dano = :id'
            );
            $existe->execute([':id' => $id]);
            if (!$existe->fetchColumn()) {
                volverReportesDano('El reporte seleccionado ya no existe.');
            }
        }

        rs_flash('ok', 'El estado del reporte fue actualizado.');
        rs_redirect('dashboardAD/reportes_danos.php');
    }

    volverReportesDano('La operación solicitada no es válida.');
} catch (Throwable $e) {
    error_log('Error en ReporteDanoController: ' . $e->getMessage());
    volverReportesDano('No fue posible guardar el reporte. Intenta nuevamente.');
}

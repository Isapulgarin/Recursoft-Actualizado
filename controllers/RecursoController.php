
<?php

require_once __DIR__ . '/../config/conexion.php';

function volverRecursos(string $mensaje): void
{
    header('Location: ../dashboardAD/recursos.php?mensaje=' . urlencode($mensaje));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverRecursos('Solicitud no válida.');
}

$accion = $_POST['accion'] ?? '';

try {

    if ($accion === 'guardar') {

        $codigo = trim($_POST['codigo_interno'] ?? '');
        $nombre = trim($_POST['nombre_recurso'] ?? '');
        $categoria = (int) ($_POST['id_categoria'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $serial = trim($_POST['serial'] ?? '');
        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $disponibilidad = trim($_POST['disponibilidad'] ?? '');
        $fechaAdquisicion = trim($_POST['fecha_adquisicion'] ?? '');

        if ($codigo === '' || $nombre === '' || $categoria <= 0 || $estado === '' || $disponibilidad === '') {
            volverRecursos('Completa todos los campos obligatorios.');
        }

        if (
            mb_strlen($codigo) > 50 ||
            mb_strlen($nombre) > 100 ||
            mb_strlen($descripcion) > 150 ||
            mb_strlen($serial) > 100 ||
            mb_strlen($marca) > 50 ||
            mb_strlen($modelo) > 50
        ) {
            volverRecursos('Uno o varios campos superan el tamaño permitido.');
        }

        $estadosValidos = ['Bueno', 'Regular', 'Dañado'];
        $disponibilidadesValidas = ['Disponible', 'Prestado', 'Mantenimiento'];

        if (!in_array($estado, $estadosValidos, true) ||
            !in_array($disponibilidad, $disponibilidadesValidas, true)) {
            volverRecursos('El estado o la disponibilidad no son válidos.');
        }

        $consultaCategoria = $conexion->prepare(
            "SELECT id_categoria FROM categoria_recurso WHERE id_categoria = :id"
        );
        $consultaCategoria->execute([':id' => $categoria]);

        if (!$consultaCategoria->fetch()) {
            volverRecursos('La categoría seleccionada no existe.');
        }

        $sql = "
            INSERT INTO recurso (
                id_categoria,
                codigo_interno,
                nombre_recurso,
                descripcion,
                serial,
                marca,
                modelo,
                estado,
                disponibilidad,
                fecha_registro,
                fecha_adquisicion
            ) VALUES (
                :categoria,
                :codigo,
                :nombre,
                :descripcion,
                :serial,
                :marca,
                :modelo,
                :estado,
                :disponibilidad,
                CURDATE(),
                :fecha_adquisicion
            )
        ";

        $consulta = $conexion->prepare($sql);

        $consulta->execute([
            ':categoria' => $categoria,
            ':codigo' => $codigo,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ':serial' => $serial !== '' ? $serial : null,
            ':marca' => $marca !== '' ? $marca : null,
            ':modelo' => $modelo !== '' ? $modelo : null,
            ':estado' => $estado,
            ':disponibilidad' => $disponibilidad,
            ':fecha_adquisicion' => $fechaAdquisicion !== '' ? $fechaAdquisicion : null
        ]);

        volverRecursos('Recurso registrado correctamente.');
    }

    if ($accion === 'editar') {

        $id = (int) ($_POST['id_recurso'] ?? 0);
        $codigo = trim($_POST['codigo_interno'] ?? '');
        $nombre = trim($_POST['nombre_recurso'] ?? '');
        $categoria = (int) ($_POST['id_categoria'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $serial = trim($_POST['serial'] ?? '');
        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $disponibilidad = trim($_POST['disponibilidad'] ?? '');
        $fechaAdquisicion = trim($_POST['fecha_adquisicion'] ?? '');

        if ($id <= 0 || $codigo === '' || $nombre === '' || $categoria <= 0 || $estado === '' || $disponibilidad === '') {
            volverRecursos('Los datos del recurso están incompletos.');
        }

        if (
            mb_strlen($codigo) > 50 ||
            mb_strlen($nombre) > 100 ||
            mb_strlen($descripcion) > 150 ||
            mb_strlen($serial) > 100 ||
            mb_strlen($marca) > 50 ||
            mb_strlen($modelo) > 50
        ) {
            volverRecursos('Uno o varios campos superan el tamaño permitido.');
        }

        $estadosValidos = ['Bueno', 'Regular', 'Dañado'];
        $disponibilidadesValidas = ['Disponible', 'Prestado', 'Mantenimiento'];

        if (!in_array($estado, $estadosValidos, true) ||
            !in_array($disponibilidad, $disponibilidadesValidas, true)) {
            volverRecursos('El estado o la disponibilidad no son válidos.');
        }

        $consultaCategoria = $conexion->prepare(
            "SELECT id_categoria FROM categoria_recurso WHERE id_categoria = :id"
        );
        $consultaCategoria->execute([':id' => $categoria]);

        if (!$consultaCategoria->fetch()) {
            volverRecursos('La categoría seleccionada no existe.');
        }

        $sql = "
            UPDATE recurso SET
                id_categoria = :categoria,
                codigo_interno = :codigo,
                nombre_recurso = :nombre,
                descripcion = :descripcion,
                serial = :serial,
                marca = :marca,
                modelo = :modelo,
                estado = :estado,
                disponibilidad = :disponibilidad,
                fecha_adquisicion = :fecha_adquisicion
            WHERE id_recurso = :id
        ";

        $consulta = $conexion->prepare($sql);

        $consulta->execute([
            ':categoria' => $categoria,
            ':codigo' => $codigo,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ':serial' => $serial !== '' ? $serial : null,
            ':marca' => $marca !== '' ? $marca : null,
            ':modelo' => $modelo !== '' ? $modelo : null,
            ':estado' => $estado,
            ':disponibilidad' => $disponibilidad,
            ':fecha_adquisicion' => $fechaAdquisicion !== '' ? $fechaAdquisicion : null,
            ':id' => $id
        ]);

        volverRecursos('Recurso actualizado correctamente.');
    }

    if ($accion === 'eliminar') {

        $id = (int) ($_POST['id_recurso'] ?? 0);

        if ($id <= 0) {
            volverRecursos('El recurso seleccionado no es válido.');
        }

        $consulta = $conexion->prepare(
            "DELETE FROM recurso WHERE id_recurso = :id"
        );

        $consulta->execute([':id' => $id]);

        if ($consulta->rowCount() === 0) {
            volverRecursos('No se encontró el recurso que deseas eliminar.');
        }

        volverRecursos('Recurso eliminado correctamente.');
    }

    volverRecursos('La operación solicitada no es válida.');

} catch (PDOException $e) {

    error_log('Error en RecursoController: ' . $e->getMessage());

    volverRecursos(
        $accion === 'eliminar'
            ? 'No fue posible eliminar el recurso. Puede tener préstamos u otros registros relacionados.'
            : 'No fue posible guardar los cambios. Revisa los datos e intenta nuevamente.'
    );
}

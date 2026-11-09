
<?php
require_once __DIR__ . '/../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboardAD/usuarios.php');
    exit;
}

function volver($mensaje) {
    header('Location: ../dashboardAD/usuarios.php?mensaje=' . urlencode($mensaje));
    exit;
}

try {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar' || $accion === 'editar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $tipoUsuario = trim($_POST['tipo_usuario'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $estado = trim($_POST['estado'] ?? 'Activo');

        if (
            $nombre === '' ||
            $apellido === '' ||
            $tipoUsuario === '' ||
            $correo === '' ||
            $telefono === ''
        ) {
            volver('Completa todos los campos obligatorios.');
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            volver('Ingresa un correo electrónico válido.');
        }

        $tiposPermitidos = [
            'Estudiante',
            'Docente',
            'Administrativo',
            'Otro'
        ];

        if (!in_array($tipoUsuario, $tiposPermitidos, true)) {
            volver('Selecciona un tipo de usuario válido.');
        }

        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            volver('Selecciona un estado válido.');
        }

        if ($accion === 'guardar') {
            $validarCorreo = $conexion->prepare(
                "SELECT COUNT(*) FROM usuario WHERE correo = :correo"
            );
            $validarCorreo->execute([':correo' => $correo]);

            if ($validarCorreo->fetchColumn() > 0) {
                volver('Ese correo ya está registrado.');
            }

            $sql = "INSERT INTO usuario
                    (id_rol, tipo_usuario, nombre, apellido, correo,
                     telefono, contraseña, estado)
                    VALUES
                    (NULL, :tipo_usuario, :nombre, :apellido, :correo,
                     :telefono, NULL, :estado)";

            $consulta = $conexion->prepare($sql);
            $consulta->execute([
                ':tipo_usuario' => $tipoUsuario,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':correo' => $correo,
                ':telefono' => $telefono,
                ':estado' => $estado
            ]);

            volver('Usuario registrado correctamente.');
        }

        if ($accion === 'editar') {
            $idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);

            if (!$idUsuario) {
                volver('El usuario seleccionado no es válido.');
            }

            $validarCorreo = $conexion->prepare(
                "SELECT COUNT(*) FROM usuario
                 WHERE correo = :correo AND id_usuario <> :id_usuario"
            );

            $validarCorreo->execute([
                ':correo' => $correo,
                ':id_usuario' => $idUsuario
            ]);

            if ($validarCorreo->fetchColumn() > 0) {
                volver('Ese correo ya está registrado por otra persona.');
            }

            $sql = "UPDATE usuario SET
                        tipo_usuario = :tipo_usuario,
                        nombre = :nombre,
                        apellido = :apellido,
                        correo = :correo,
                        telefono = :telefono,
                        estado = :estado
                    WHERE id_usuario = :id_usuario";

            $consulta = $conexion->prepare($sql);
            $consulta->execute([
                ':tipo_usuario' => $tipoUsuario,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':correo' => $correo,
                ':telefono' => $telefono,
                ':estado' => $estado,
                ':id_usuario' => $idUsuario
            ]);

            volver('Usuario actualizado correctamente.');
        }
    }

    if ($accion === 'eliminar') {
        $idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);

        if (!$idUsuario) {
            volver('El usuario seleccionado no es válido.');
        }

        $consulta = $conexion->prepare(
            "DELETE FROM usuario WHERE id_usuario = :id_usuario"
        );

        $consulta->execute([':id_usuario' => $idUsuario]);

        volver('Usuario eliminado correctamente.');
    }

    volver('La acción solicitada no es válida.');

} catch (PDOException $e) {
    error_log('Error UsuarioController: ' . $e->getMessage());

    if ($e->getCode() === '23000') {
        volver('No se puede eliminar este usuario porque tiene registros relacionados.');
    }

    volver('Ocurrió un error al procesar el usuario. Revisa la base de datos.');
} catch (Throwable $e) {
    error_log('Error UsuarioController: ' . $e->getMessage());
    volver('Ocurrió un error inesperado.');
}

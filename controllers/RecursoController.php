
<?php

require_once __DIR__ . '/../includes/auth.php';

$usuarioSesion = rs_requiere_admin();
$conexion = rs_pdo();

/*
|--------------------------------------------------------------------------
| Redireccionar al módulo de recursos
|--------------------------------------------------------------------------
*/
function rs_volver_recursos(string $mensaje): void
{
    header(
        'Location: ../dashboardAD/recursos.php?mensaje=' .
        urlencode($mensaje)
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Verificar método y token CSRF
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rs_volver_recursos('Solicitud no válida.');
}

$tokenSesion = $_SESSION['csrf_recursos'] ?? '';
$tokenFormulario = $_POST['csrf_token'] ?? '';

if (
    $tokenSesion === '' ||
    $tokenFormulario === '' ||
    !hash_equals($tokenSesion, $tokenFormulario)
) {
    rs_volver_recursos(
        'La sesión del formulario expiró. Intenta nuevamente.'
    );
}

/*
|--------------------------------------------------------------------------
| Funciones de validación
|--------------------------------------------------------------------------
*/
function rs_validar_longitudes(
    string $codigo,
    string $nombre,
    string $descripcion,
    string $serial,
    string $marca,
    string $modelo
): bool {
    return
        mb_strlen($codigo) <= 50 &&
        mb_strlen($nombre) <= 100 &&
        mb_strlen($descripcion) <= 150 &&
        mb_strlen($serial) <= 100 &&
        mb_strlen($marca) <= 50 &&
        mb_strlen($modelo) <= 50;
}

function rs_validar_fecha(string $fecha): bool
{
    if ($fecha === '') {
        return true;
    }

    $formato = DateTime::createFromFormat('!Y-m-d', $fecha);

    return $formato &&
        $formato->format('Y-m-d') === $fecha;
}

function rs_validar_estado_disponibilidad(
    string $estado,
    string $disponibilidad
): bool {
    $estados = [
        'Bueno',
        'Regular',
        'Dañado'
    ];

    $disponibilidades = [
        'Disponible',
        'Prestado',
        'Mantenimiento',
        'Fuera de servicio'
    ];

    if (
        !in_array($estado, $estados, true) ||
        !in_array($disponibilidad, $disponibilidades, true)
    ) {
        return false;
    }

    if (
        $estado === 'Dañado' &&
        in_array(
            $disponibilidad,
            ['Disponible', 'Prestado'],
            true
        )
    ) {
        return false;
    }

    return true;
}

function rs_validar_categoria(
    PDO $conexion,
    int $categoria
): bool {
    $consulta = $conexion->prepare("
        SELECT id_categoria
        FROM categoria_recurso
        WHERE id_categoria = :id
        LIMIT 1
    ");

    $consulta->execute([':id' => $categoria]);

    return (bool) $consulta->fetchColumn();
}

function rs_codigo_existe(
    PDO $conexion,
    string $codigo,
    ?int $idExcluir = null
): bool {
    $sql = "
        SELECT id_recurso
        FROM recurso
        WHERE codigo_interno = :codigo
    ";

    $parametros = [
        ':codigo' => $codigo
    ];

    if ($idExcluir !== null) {
        $sql .= " AND id_recurso <> :id";
        $parametros[':id'] = $idExcluir;
    }

    $sql .= " LIMIT 1";

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);

    return (bool) $consulta->fetchColumn();
}

/*
|--------------------------------------------------------------------------
| Obtener información enviada
|--------------------------------------------------------------------------
*/
$accion = $_POST['accion'] ?? '';

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

$stockRecibido = $_POST['stock_total'] ?? '';

try {

    /*
    |--------------------------------------------------------------------------
    | REGISTRAR RECURSO
    |--------------------------------------------------------------------------
    */
    if ($accion === 'guardar') {

        if (
            $codigo === '' ||
            $nombre === '' ||
            $categoria <= 0 ||
            $estado === '' ||
            $disponibilidad === '' ||
            $stockRecibido === ''
        ) {
            rs_volver_recursos(
                'Completa todos los campos obligatorios.'
            );
        }

        if (
            !ctype_digit((string) $stockRecibido) ||
            (int) $stockRecibido < 1
        ) {
            rs_volver_recursos(
                'La cantidad de unidades debe ser un número entero mayor que cero.'
            );
        }

        $stockTotal = (int) $stockRecibido;

        if (!rs_validar_longitudes(
            $codigo,
            $nombre,
            $descripcion,
            $serial,
            $marca,
            $modelo
        )) {
            rs_volver_recursos(
                'Uno o varios campos superan el tamaño permitido.'
            );
        }

        if (!rs_validar_fecha($fechaAdquisicion)) {
            rs_volver_recursos(
                'La fecha de adquisición no es válida.'
            );
        }

        if (!rs_validar_estado_disponibilidad(
            $estado,
            $disponibilidad
        )) {
            rs_volver_recursos(
                'La combinación de estado físico y disponibilidad no es válida.'
            );
        }

        if (!rs_validar_categoria($conexion, $categoria)) {
            rs_volver_recursos(
                'La categoría seleccionada no existe.'
            );
        }

        if (rs_codigo_existe($conexion, $codigo)) {
            rs_volver_recursos(
                'Ya existe un recurso registrado con ese código interno.'
            );
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
                stock_total,
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
                :stock_total,
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
            ':descripcion' => $descripcion !== ''
                ? $descripcion : null,
            ':serial' => $serial !== ''
                ? $serial : null,
            ':marca' => $marca !== ''
                ? $marca : null,
            ':modelo' => $modelo !== ''
                ? $modelo : null,
            ':estado' => $estado,
            ':stock_total' => $stockTotal,
            ':disponibilidad' => $disponibilidad,
            ':fecha_adquisicion' => $fechaAdquisicion !== ''
                ? $fechaAdquisicion : null
        ]);

        rs_volver_recursos(
            'Recurso registrado correctamente.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EDITAR RECURSO
    |--------------------------------------------------------------------------
    */
    if ($accion === 'editar') {

        $id = (int) ($_POST['id_recurso'] ?? 0);

        if (
            $id <= 0 ||
            $codigo === '' ||
            $nombre === '' ||
            $categoria <= 0 ||
            $estado === '' ||
            $disponibilidad === '' ||
            $stockRecibido === ''
        ) {
            rs_volver_recursos(
                'Los datos del recurso están incompletos.'
            );
        }

        if (
            !ctype_digit((string) $stockRecibido) ||
            (int) $stockRecibido < 1
        ) {
            rs_volver_recursos(
                'La cantidad de unidades debe ser un número entero mayor que cero.'
            );
        }

        $stockTotal = (int) $stockRecibido;

        if (!rs_validar_longitudes(
            $codigo,
            $nombre,
            $descripcion,
            $serial,
            $marca,
            $modelo
        )) {
            rs_volver_recursos(
                'Uno o varios campos superan el tamaño permitido.'
            );
        }

        if (!rs_validar_fecha($fechaAdquisicion)) {
            rs_volver_recursos(
                'La fecha de adquisición no es válida.'
            );
        }

        if (!rs_validar_estado_disponibilidad(
            $estado,
            $disponibilidad
        )) {
            rs_volver_recursos(
                'La combinación de estado físico y disponibilidad no es válida.'
            );
        }

        if (!rs_validar_categoria($conexion, $categoria)) {
            rs_volver_recursos(
                'La categoría seleccionada no existe.'
            );
        }

        if (rs_codigo_existe($conexion, $codigo, $id)) {
            rs_volver_recursos(
                'Ese código interno ya está registrado en otro recurso.'
            );
        }

        /*
         * Verificar el recurso y bloquear su registro
         * durante la actualización.
         */
        $conexion->beginTransaction();

        $consultaRecurso = $conexion->prepare("
            SELECT id_recurso
            FROM recurso
            WHERE id_recurso = :id
            FOR UPDATE
        ");

        $consultaRecurso->execute([
            ':id' => $id
        ]);

        if (!$consultaRecurso->fetch(PDO::FETCH_ASSOC)) {
            $conexion->rollBack();

            rs_volver_recursos(
                'El recurso que deseas editar no existe.'
            );
        }

        /*
         * Contar préstamos que todavía no han sido devueltos.
         * Cada registro representa una unidad prestada.
         */
        $consultaPrestamos = $conexion->prepare("
            SELECT COUNT(*)
            FROM prestamo
            WHERE id_recurso = :id
            AND estado IN ('Activo', 'Vencido')
        ");

        $consultaPrestamos->execute([
            ':id' => $id
        ]);

        $unidadesPrestadas = (int) $consultaPrestamos->fetchColumn();

        if ($stockTotal < $unidadesPrestadas) {
            $conexion->rollBack();

            rs_volver_recursos(
                'No puedes establecer una cantidad menor a las unidades que actualmente están prestadas: ' .
                $unidadesPrestadas . '.'
            );
        }

        if ($estado === 'Dañado' && $unidadesPrestadas > 0) {
            $conexion->rollBack();

            rs_volver_recursos(
                'No puedes marcar el recurso como dañado mientras tenga unidades prestadas.'
            );
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
                stock_total = :stock_total,
                disponibilidad = :disponibilidad,
                fecha_adquisicion = :fecha_adquisicion
            WHERE id_recurso = :id
        ";

        $consulta = $conexion->prepare($sql);

        $consulta->execute([
            ':categoria' => $categoria,
            ':codigo' => $codigo,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion !== ''
                ? $descripcion : null,
            ':serial' => $serial !== ''
                ? $serial : null,
            ':marca' => $marca !== ''
                ? $marca : null,
            ':modelo' => $modelo !== ''
                ? $modelo : null,
            ':estado' => $estado,
            ':stock_total' => $stockTotal,
            ':disponibilidad' => $disponibilidad,
            ':fecha_adquisicion' => $fechaAdquisicion !== ''
                ? $fechaAdquisicion : null,
            ':id' => $id
        ]);

        $conexion->commit();

        rs_volver_recursos(
            'Recurso actualizado correctamente.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR RECURSO
    |--------------------------------------------------------------------------
    */
    if ($accion === 'eliminar') {

        $id = (int) ($_POST['id_recurso'] ?? 0);

        if ($id <= 0) {
            rs_volver_recursos(
                'El recurso seleccionado no es válido.'
            );
        }

        $consultaRecurso = $conexion->prepare("
            SELECT id_recurso
            FROM recurso
            WHERE id_recurso = :id
            LIMIT 1
        ");

        $consultaRecurso->execute([
            ':id' => $id
        ]);

        if (!$consultaRecurso->fetch(PDO::FETCH_ASSOC)) {
            rs_volver_recursos(
                'No se encontró el recurso que deseas eliminar.'
            );
        }

        $consultaPrestamos = $conexion->prepare("
            SELECT COUNT(*)
            FROM prestamo
            WHERE id_recurso = :id
        ");

        $consultaPrestamos->execute([
            ':id' => $id
        ]);

        if ((int) $consultaPrestamos->fetchColumn() > 0) {
            rs_volver_recursos(
                'No puedes eliminar este recurso porque tiene préstamos registrados. Puedes cambiar su disponibilidad a Fuera de servicio.'
            );
        }

        $consulta = $conexion->prepare("
            DELETE FROM recurso
            WHERE id_recurso = :id
        ");

        $consulta->execute([
            ':id' => $id
        ]);

        rs_volver_recursos(
            'Recurso eliminado correctamente.'
        );
    }

    rs_volver_recursos(
        'La operación solicitada no es válida.'
    );

} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log(
        'Error en RecursoController: ' . $e->getMessage()
    );

    rs_volver_recursos(
        'No fue posible completar la operación. Revisa los datos e intenta nuevamente.'
    );
}
 
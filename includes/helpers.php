<?php
/**
 * RecurSoft - Funciones de apoyo para vistas y persistencia.
 */

if (!function_exists('rs_h')) {
    function rs_h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rs_is_active')) {
    function rs_is_active($page, $current)
    {
        return $page === $current ? 'active' : '';
    }
}

if (!function_exists('rs_empty_table_row')) {
    function rs_empty_table_row($colspan, $message = 'No hay registros disponibles')
    {
        $colspan = (int) $colspan;
        $message = rs_h($message);
        echo "<tr><td colspan=\"{$colspan}\" class=\"empty-cell\"><i class=\"bi bi-inbox d-block fs-3 text-secondary mb-1\"></i>{$message}</td></tr>";
    }
}

if (!function_exists('rs_empty_state')) {
    function rs_empty_state($icon, $title, $text)
    {
        echo '<div class="empty-state">';
        echo '<i class="bi ' . rs_h($icon) . '"></i>';
        echo '<h3>' . rs_h($title) . '</h3>';
        echo '<p class="mb-0">' . rs_h($text) . '</p>';
        echo '</div>';
    }
}

function rs_post(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function rs_get(string $key, $default = '')
{
    return $_GET[$key] ?? $default;
}

function rs_trim($value): string
{
    return trim((string) $value);
}

function rs_csrf_token(): string
{
    if (empty($_SESSION['rs_csrf'])) {
        $_SESSION['rs_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['rs_csrf'];
}

function rs_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . rs_h(rs_csrf_token()) . '">';
}

function rs_csrf_ok(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && isset($_SESSION['rs_csrf']) && hash_equals($_SESSION['rs_csrf'], $token);
}

function rs_flash(string $tipo, string $mensaje): void
{
    $_SESSION['rs_flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function rs_flash_html(): void
{
    if (empty($_SESSION['rs_flash'])) {
        return;
    }
    $flash = $_SESSION['rs_flash'];
    unset($_SESSION['rs_flash']);
    $clase = ($flash['tipo'] ?? '') === 'ok' ? 'alert-rs-ok' : 'alert-rs-error';
    $icono = ($flash['tipo'] ?? '') === 'ok' ? 'bi-check-circle' : 'bi-exclamation-triangle';
    echo '<div class="alert alert-rs ' . $clase . ' d-flex align-items-start gap-2" role="alert">';
    echo '<i class="bi ' . $icono . ' mt-1"></i><div>' . rs_h($flash['mensaje']) . '</div></div>';
}

function rs_redirect(string $ruta): void
{
    if (!preg_match('#^https?://#i', $ruta) && strpos($ruta, '/') !== 0) {
        $ruta = RS_BASE . ltrim($ruta, '/');
    }
    header('Location: ' . $ruta);
    exit;
}

function rs_old(array $old, string $key, $default = '')
{
    return $old[$key] ?? $default;
}

function rs_es_activo($estado): bool
{
    $valor = strtolower(trim((string) $estado));
    return in_array($valor, ['1', 'activo', 'activa', 'si', 'sí'], true);
}

function rs_estado_texto($estado): string
{
    return rs_es_activo($estado) ? 'Activo' : 'Inactivo';
}

function rs_estado_opuesto($estado): string
{
    return rs_es_activo($estado) ? 'Inactivo' : 'Activo';
}

function rs_badge_estado($estado): string
{
    if (rs_es_activo($estado)) {
        return '<span class="badge-rs badge-disponible">Activo</span>';
    }
    return '<span class="badge-rs badge-inactivo">Inactivo</span>';
}

function rs_badge_disponibilidad($valor): string
{
    $valor = trim((string) $valor);
    $mapa = [
        'Disponible' => 'badge-disponible',
        'Prestado' => 'badge-prestado',
        'En mantenimiento' => 'badge-mantenimiento',
        'No disponible' => 'badge-inactivo',
    ];
    $clase = $mapa[$valor] ?? 'badge-inactivo';
    return '<span class="badge-rs ' . $clase . '">' . rs_h($valor !== '' ? $valor : 'Sin dato') . '</span>';
}

function rs_badge_proceso($valor): string
{
    $valor = trim((string) $valor);
    $clase = 'badge-inactivo';
    $bajo = strtolower($valor);
    if (in_array($bajo, ['activo', 'disponible', 'recibida', 'completado', 'devuelto', 'óptima', 'optima', 'autorizada'], true)) {
        $clase = 'badge-disponible';
    } elseif (in_array($bajo, ['pendiente', 'programado', 'solicitada', 'vencido', 'en proceso', 'con observaciones'], true)) {
        $clase = 'badge-pendiente';
    } elseif (in_array($bajo, ['prestado', 'mantenimiento', 'en mantenimiento', 'dañado', 'danado'], true)) {
        $clase = 'badge-prestado';
    }
    return '<span class="badge-rs ' . $clase . '">' . rs_h($valor !== '' ? $valor : 'Sin dato') . '</span>';
}

function rs_all(string $sql, array $params = []): array
{
    $st = rs_pdo()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function rs_one(string $sql, array $params = []): ?array
{
    $st = rs_pdo()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function rs_exec(string $sql, array $params = []): bool
{
    $st = rs_pdo()->prepare($sql);
    return $st->execute($params);
}

function rs_last_id(): string
{
    return rs_pdo()->lastInsertId();
}

function rs_existe(string $tabla, string $columna, $valor, $excluirId = null, string $columnaId = ''): bool
{
    static $permitidas = [
        'usuario' => 'id_usuario',
        'empleado' => 'id_empleado',
        'categoria_recurso' => 'id_categoria',
        'recurso' => 'id_recurso',
        'prestamo' => 'id_prestamo',
        'devolucion' => 'id_devolucion',
        'mantenimiento' => 'id_mantenimiento',
        'proveedor' => 'id_proveedor',
        'compra' => 'id_compra',
        'detalle_compra' => 'id_detalle_compra',
        'historial_prestamo' => 'id_historial',
        'reporte' => 'id_reporte',
        'rol' => 'id_rol',
    ];

    if (!isset($permitidas[$tabla])) {
        return false;
    }

    $columnasOk = [
        'correo', 'documento', 'NIT', 'codigo_interno', 'serial', 'nombre_categoria',
        'id_usuario', 'id_prestamo', 'id_empleado', 'id_recurso', 'id_proveedor', 'id_compra', 'id_categoria',
    ];
    if (!in_array($columna, $columnasOk, true)) {
        return false;
    }

    $idCol = $columnaId !== '' ? $columnaId : $permitidas[$tabla];
    $sql = "SELECT 1 FROM `{$tabla}` WHERE `{$columna}` = ?";
    $params = [$valor];
    if ($excluirId !== null && $excluirId !== '') {
        $sql .= " AND `{$idCol}` <> ?";
        $params[] = $excluirId;
    }
    $sql .= ' LIMIT 1';
    return rs_one($sql, $params) !== null;
}

function rs_registro(string $tabla, $id): ?array
{
    static $ids = [
        'usuario' => 'id_usuario',
        'empleado' => 'id_empleado',
        'categoria_recurso' => 'id_categoria',
        'recurso' => 'id_recurso',
        'prestamo' => 'id_prestamo',
        'devolucion' => 'id_devolucion',
        'mantenimiento' => 'id_mantenimiento',
        'proveedor' => 'id_proveedor',
        'compra' => 'id_compra',
        'detalle_compra' => 'id_detalle_compra',
        'historial_prestamo' => 'id_historial',
        'reporte' => 'id_reporte',
        'rol' => 'id_rol',
    ];
    if (!isset($ids[$tabla])) {
        return null;
    }
    $col = $ids[$tabla];
    return rs_one("SELECT * FROM `{$tabla}` WHERE `{$col}` = ? LIMIT 1", [(int) $id]);
}

function rs_roles(): array
{
    return rs_all('SELECT * FROM rol ORDER BY nombre_rol');
}

function rs_registrar_historial(
    $idPrestamo,
    string $accion,
    string $detalle = '',
    ?int $idCuentaAccion = null
): void
{
    $registrado = rs_exec(
        'INSERT INTO historial_prestamo (id_prestamo, id_cuenta_accion, fecha_accion, fecha_hora, accion, detalle) VALUES (?, ?, CURDATE(), CURRENT_TIMESTAMP, ?, ?)',
        [
            (int) $idPrestamo,
            $idCuentaAccion,
            mb_substr($accion, 0, 50),
            mb_substr($detalle, 0, 500)
        ]
    );

    if (!$registrado) {
        throw new RuntimeException('No fue posible registrar el evento en el historial del préstamo.');
    }
}

function rs_actualizar_disponibilidad($idRecurso, string $disponibilidad): void
{
    rs_exec('UPDATE recurso SET disponibilidad = ? WHERE id_recurso = ?', [$disponibilidad, (int) $idRecurso]);
}

function rs_recurso_libre($idRecurso, $exceptoPrestamo = null): bool
{
    $recurso = rs_registro('recurso', $idRecurso);
    if (!$recurso) {
        return false;
    }
    if (!rs_es_activo($recurso['estado']) && !in_array(strtolower((string) $recurso['estado']), ['bueno', 'regular', 'optimo', 'óptimo', 'nuevo'], true)) {
        if (in_array(strtolower((string) $recurso['estado']), ['inactivo', 'dañado', 'danado', 'fuera de servicio', '0'], true)) {
            return false;
        }
    }

    $disp = strtolower(trim((string) $recurso['disponibilidad']));
    if (in_array($disp, ['prestado', 'en mantenimiento', 'no disponible'], true)) {
        return false;
    }

    $sql = "SELECT id_prestamo FROM prestamo
            WHERE id_recurso = ?
              AND estado IN ('Pendiente','Activo','Vencido')";
    $params = [(int) $idRecurso];
    if ($exceptoPrestamo) {
        $sql .= ' AND id_prestamo <> ?';
        $params[] = (int) $exceptoPrestamo;
    }
    $sql .= ' LIMIT 1';
    if (rs_one($sql, $params)) {
        return false;
    }

    $mant = rs_one(
        "SELECT id_mantenimiento FROM mantenimiento
         WHERE id_recurso = ? AND estado IN ('Programado','En proceso','Reportado')
         LIMIT 1",
        [(int) $idRecurso]
    );

    return $mant === null;
}

function rs_marcar_prestamos_vencidos(): void
{
    rs_exec(
        "UPDATE prestamo
         SET estado = 'Vencido'
         WHERE estado IN ('Activo','Pendiente')
           AND fecha_devolucion_programada IS NOT NULL
           AND fecha_devolucion_programada < CURDATE()
           AND fecha_devolucion_real IS NULL"
    );
}

function rs_opciones_html(array $filas, string $idCol, string $texto, $seleccionado = '', string $placeholder = 'Seleccione'): string
{
    $html = '<option value="">' . rs_h($placeholder) . '</option>';
    foreach ($filas as $fila) {
        $id = $fila[$idCol] ?? '';
        $sel = ((string) $id === (string) $seleccionado) ? ' selected' : '';
        $html .= '<option value="' . rs_h($id) . '"' . $sel . '>' . rs_h($texto instanceof Closure ? $texto($fila) : ($fila[$texto] ?? '')) . '</option>';
    }
    return $html;
}

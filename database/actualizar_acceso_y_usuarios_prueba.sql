USE recursoft;

SET @columna_usuario_existe = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cuenta_acceso'
      AND COLUMN_NAME = 'nombre_usuario'
);

SET @sentencia = IF(
    @columna_usuario_existe = 0,
    'ALTER TABLE cuenta_acceso ADD COLUMN nombre_usuario VARCHAR(50) NULL AFTER id_cuenta',
    'DO 0'
);
PREPARE sentencia FROM @sentencia;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;

SET @indice_usuario_existe = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cuenta_acceso'
      AND INDEX_NAME = 'uq_cuenta_acceso_nombre_usuario'
);

SET @sentencia = IF(
    @indice_usuario_existe = 0,
    'CREATE UNIQUE INDEX uq_cuenta_acceso_nombre_usuario ON cuenta_acceso (nombre_usuario)',
    'DO 0'
);
PREPARE sentencia FROM @sentencia;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;

START TRANSACTION;

INSERT INTO cuenta_acceso (
    id_empleado,
    id_rol,
    correo,
    password,
    estado,
    nombre_usuario
)
SELECT
    NULL,
    r.id_rol,
    'admin@recursoft.com',
    '$2y$10$5xN7iYHvLaj1zm/W6K8tSuVnGnaDIDQFH62PlxYfvg61pKQyd8M9O',
    'Activo',
    'admin'
FROM rol r
WHERE LOWER(r.nombre_rol) = 'administrador'
  AND NOT EXISTS (
      SELECT 1
      FROM cuenta_acceso ca
      WHERE ca.correo = 'admin@recursoft.com'
         OR ca.nombre_usuario = 'admin'
  )
ORDER BY r.id_rol
LIMIT 1;
SET @id_cuenta_admin = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);

INSERT INTO administrador (
    id_cuenta,
    nombre,
    apellido,
    estado
)
SELECT
    @id_cuenta_admin,
    'Administrador RecurSoft',
    '',
    'Activo'
WHERE @id_cuenta_admin IS NOT NULL;

INSERT INTO empleado (
    id_usuario,
    documento,
    nombre,
    apellido,
    cargo,
    telefono,
    correo,
    estado,
    password
)
SELECT
    NULL,
    NULL,
    'Empleado RecurSoft',
    '',
    'Empleado',
    NULL,
    'empleado@recursoft.com',
    'Activo',
    '$2y$10$opT19qclO9U.QsdWqY8qP.uF78OTfPnyTrj11xyjeEp71KKCqOPxe'
WHERE NOT EXISTS (
    SELECT 1
    FROM empleado e
    WHERE e.correo = 'empleado@recursoft.com'
)
AND NOT EXISTS (
    SELECT 1
    FROM cuenta_acceso ca
    WHERE ca.correo = 'empleado@recursoft.com'
       OR ca.nombre_usuario = 'empleado'
);
SET @id_empleado_prueba = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);

INSERT INTO cuenta_acceso (
    id_empleado,
    id_rol,
    correo,
    password,
    estado,
    nombre_usuario
)
SELECT
    @id_empleado_prueba,
    r.id_rol,
    'empleado@recursoft.com',
    '$2y$10$opT19qclO9U.QsdWqY8qP.uF78OTfPnyTrj11xyjeEp71KKCqOPxe',
    'Activo',
    'empleado'
FROM rol r
WHERE @id_empleado_prueba IS NOT NULL
  AND LOWER(r.nombre_rol) = 'empleado'
  AND NOT EXISTS (
      SELECT 1
      FROM cuenta_acceso ca
      WHERE ca.correo = 'empleado@recursoft.com'
         OR ca.nombre_usuario = 'empleado'
  )
ORDER BY r.id_rol
LIMIT 1;

COMMIT;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-11-2026 a las 19:30:57
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `recursoft`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administrador`
--

CREATE TABLE `administrador` (
  `id_administrador` int(11) NOT NULL,
  `id_cuenta` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `apellido` varchar(80) NOT NULL,
  `documento` varchar(30) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `administrador`
--

INSERT INTO `administrador` (`id_administrador`, `id_cuenta`, `nombre`, `apellido`, `documento`, `telefono`, `estado`) VALUES
(2, 2, 'Administrador RecurSoft', '', NULL, NULL, 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria_recurso`
--

CREATE TABLE `categoria_recurso` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(100) DEFAULT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categoria_recurso`
--

INSERT INTO `categoria_recurso` (`id_categoria`, `nombre_categoria`, `descripcion`, `estado`) VALUES
(1, 'equipos de computo', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compra`
--

CREATE TABLE `compra` (
  `id_compra` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `fecha_compra` date DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `estado` varchar(30) DEFAULT NULL,
  `observaciones` varchar(150) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuenta_acceso`
--

CREATE TABLE `cuenta_acceso` (
  `id_cuenta` int(11) NOT NULL,
  `nombre_usuario` varchar(50) DEFAULT NULL,
  `id_empleado` int(11) DEFAULT NULL,
  `id_rol` int(11) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cuenta_acceso`
--

INSERT INTO `cuenta_acceso` (`id_cuenta`, `nombre_usuario`, `id_empleado`, `id_rol`, `correo`, `password`, `estado`) VALUES
(2, 'admin', NULL, 1, 'admin@recursoft.com', '$2y$10$5xN7iYHvLaj1zm/W6K8tSuVnGnaDIDQFH62PlxYfvg61pKQyd8M9O', 'Activo'),
(3, 'empleado', 5, 2, 'empleado@recursoft.com', '$2y$10$opT19qclO9U.QsdWqY8qP.uF78OTfPnyTrj11xyjeEp71KKCqOPxe', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_compra`
--

CREATE TABLE `detalle_compra` (
  `id_detalle_compra` int(11) NOT NULL,
  `id_compra` int(11) DEFAULT NULL,
  `id_recurso` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `costo_unitario` decimal(10,2) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `devolucion`
--

CREATE TABLE `devolucion` (
  `id_devolucion` int(11) NOT NULL,
  `id_prestamo` int(11) DEFAULT NULL,
  `cantidad_devuelta` int(11) NOT NULL DEFAULT 1,
  `fecha_devolucion` date DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `id_cuenta_registra` int(11) DEFAULT NULL,
  `estado_recurso` varchar(50) DEFAULT NULL,
  `observaciones` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `devolucion`
--
DELIMITER $$
CREATE TRIGGER `trg_devolucion_actualizar` AFTER INSERT ON `devolucion` FOR EACH ROW BEGIN
    DECLARE v_id_recurso INT;
    DECLARE v_cantidad_prestada INT;
    DECLARE v_total_devuelto INT DEFAULT 0;

    SELECT id_recurso, cantidad
    INTO v_id_recurso, v_cantidad_prestada
    FROM prestamo
    WHERE id_prestamo = NEW.id_prestamo;

    -- Solo se reincorporan al stock las unidades en buen estado.
    IF LOWER(TRIM(COALESCE(NEW.estado_recurso, ''))) IN
       ('bueno', 'buen estado', 'disponible') THEN

        UPDATE recurso
        SET
            stock_disponible = LEAST(
                stock_total,
                stock_disponible + NEW.cantidad_devuelta
            ),
            disponibilidad = CASE
                WHEN LEAST(
                    stock_total,
                    stock_disponible + NEW.cantidad_devuelta
                ) > 0 THEN 'Disponible'
                ELSE 'Prestado'
            END
        WHERE id_recurso = v_id_recurso;

    ELSE

        -- Un recurso devuelto dañado no vuelve al stock disponible.
        UPDATE recurso
        SET disponibilidad = CASE
            WHEN LOWER(COALESCE(NEW.estado_recurso, ''))
                 LIKE '%mantenimiento%'
                THEN 'En mantenimiento'
            ELSE 'Dañado'
        END
        WHERE id_recurso = v_id_recurso;

    END IF;

    SELECT COALESCE(SUM(cantidad_devuelta), 0)
    INTO v_total_devuelto
    FROM devolucion
    WHERE id_prestamo = NEW.id_prestamo;

    UPDATE prestamo
    SET
        estado = CASE
            WHEN v_total_devuelto >= v_cantidad_prestada
                THEN 'Devuelto'
            ELSE estado
        END,
        fecha_devolucion_real = CASE
            WHEN v_total_devuelto >= v_cantidad_prestada
                THEN COALESCE(fecha_devolucion_real, CURDATE())
            ELSE fecha_devolucion_real
        END,
        estado_recurso_devolucion = NEW.estado_recurso,
        observaciones_devolucion = NEW.observaciones
    WHERE id_prestamo = NEW.id_prestamo;

END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_devolucion_validar` BEFORE INSERT ON `devolucion` FOR EACH ROW BEGIN
    DECLARE v_cantidad_prestada INT DEFAULT NULL;
    DECLARE v_cantidad_devuelta INT DEFAULT 0;
    DECLARE v_estado_prestamo VARCHAR(30);
    DECLARE v_encontrado INT DEFAULT 1;

    DECLARE CONTINUE HANDLER FOR NOT FOUND
        SET v_encontrado = 0;

    IF NEW.id_prestamo IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Debe indicar el prestamo que se devuelve';
    END IF;

    IF NEW.cantidad_devuelta IS NULL
       OR NEW.cantidad_devuelta <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La cantidad devuelta debe ser mayor que cero';
    END IF;

    SELECT cantidad, estado
    INTO v_cantidad_prestada, v_estado_prestamo
    FROM prestamo
    WHERE id_prestamo = NEW.id_prestamo
    FOR UPDATE;

    IF v_encontrado = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El prestamo indicado no existe';
    END IF;

    IF LOWER(COALESCE(v_estado_prestamo, '')) IN
       ('devuelto', 'cancelado', 'rechazado') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Este prestamo ya no admite devoluciones';
    END IF;

    SELECT COALESCE(SUM(cantidad_devuelta), 0)
    INTO v_cantidad_devuelta
    FROM devolucion
    WHERE id_prestamo = NEW.id_prestamo;

    IF v_cantidad_devuelta + NEW.cantidad_devuelta
       > v_cantidad_prestada THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La devolucion supera la cantidad prestada';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleado`
--

CREATE TABLE `empleado` (
  `id_empleado` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `documento` varchar(20) DEFAULT NULL,
  `nombre` varchar(50) DEFAULT NULL,
  `apellido` varchar(50) DEFAULT NULL,
  `cargo` varchar(50) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empleado`
--

INSERT INTO `empleado` (`id_empleado`, `id_usuario`, `documento`, `nombre`, `apellido`, `cargo`, `telefono`, `correo`, `estado`, `password`) VALUES
(4, NULL, '1038519304', 'jeronimo', 'cordoba', 'administrador', '3242709729', 'corjero@gmail.com', 'Activo', ''),
(5, NULL, NULL, 'Empleado RecurSoft', '', 'Empleado', NULL, 'empleado@recursoft.com', 'Activo', '$2y$10$opT19qclO9U.QsdWqY8qP.uF78OTfPnyTrj11xyjeEp71KKCqOPxe');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_prestamo`
--

CREATE TABLE `historial_prestamo` (
  `id_historial` int(11) NOT NULL,
  `id_prestamo` int(11) DEFAULT NULL,
  `id_cuenta_accion` int(11) DEFAULT NULL,
  `fecha_accion` date DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `accion` varchar(50) DEFAULT NULL,
  `detalle` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_prestamo`
--

INSERT INTO `historial_prestamo` (`id_historial`, `id_prestamo`, `id_cuenta_accion`, `fecha_accion`, `fecha_hora`, `accion`, `detalle`) VALUES
(2, 1, 2, '2026-11-12', '2026-11-12 22:36:01', 'Prestamo actualizado', 'Estado: Activo -> Activo; cantidad: 1 -> 5'),
(3, 1, 2, '2026-11-12', '2026-11-12 22:36:10', 'Prestamo actualizado', 'Estado: Activo -> Activo; cantidad: 5 -> 5'),
(4, 1, 2, '2026-11-12', '2026-11-12 22:46:27', 'Prestamo actualizado', 'Estado: Activo -> Activo; cantidad: 5 -> 5'),
(5, 1, 2, '2026-11-12', '2026-11-12 22:46:32', 'Prestamo actualizado', 'Estado: Activo -> Activo; cantidad: 5 -> 5'),
(6, 1, 2, '2026-11-12', '2026-11-12 22:46:51', 'Prestamo actualizado', 'Estado: Activo -> Activo; cantidad: 5 -> 5'),
(7, 1, 2, '2026-11-12', '2026-11-12 22:50:48', 'Prestamo actualizado', 'Estado: Activo -> Activo; cantidad: 5 -> 2'),
(8, 3, 2, '2026-11-12', '2026-11-12 23:04:32', 'Prestamo registrado', 'Cantidad: 7');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mantenimiento`
--

CREATE TABLE `mantenimiento` (
  `id_mantenimiento` int(11) NOT NULL,
  `id_recurso` int(11) DEFAULT NULL,
  `tipo_mantenimiento` varchar(50) DEFAULT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT NULL,
  `estado` varchar(30) DEFAULT NULL,
  `observaciones` varchar(150) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamo`
--

CREATE TABLE `prestamo` (
  `id_prestamo` int(11) NOT NULL,
  `id_empleado` int(11) DEFAULT NULL,
  `id_usuario_entrega` int(11) DEFAULT NULL,
  `id_cuenta_responsable` int(11) DEFAULT NULL,
  `id_recurso` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `fecha_solicitud` date DEFAULT NULL,
  `fecha_entrega` date DEFAULT NULL,
  `fecha_devolucion_programada` date DEFAULT NULL,
  `fecha_devolucion_real` date DEFAULT NULL,
  `estado` varchar(30) DEFAULT NULL,
  `observaciones` varchar(150) DEFAULT NULL,
  `estado_recurso_entrega` varchar(30) DEFAULT NULL,
  `estado_recurso_devolucion` varchar(30) DEFAULT NULL,
  `observaciones_devolucion` varchar(500) DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `prestamo`
--

INSERT INTO `prestamo` (`id_prestamo`, `id_empleado`, `id_usuario_entrega`, `id_cuenta_responsable`, `id_recurso`, `cantidad`, `fecha_solicitud`, `fecha_entrega`, `fecha_devolucion_programada`, `fecha_devolucion_real`, `estado`, `observaciones`, `estado_recurso_entrega`, `estado_recurso_devolucion`, `observaciones_devolucion`) VALUES
(1, 4, 3, NULL, 1, 2, '2026-11-09', '2026-11-09', '2026-11-09', NULL, 'Activo', 'melo caremelo', 'Bueno', 'Bueno', NULL),
(3, NULL, 3, 2, 1, 7, '2026-11-12', '2026-11-12', '2026-11-12', NULL, 'Activo', NULL, 'Bueno', NULL, NULL);

--
-- Disparadores `prestamo`
--
DELIMITER $$
CREATE TRIGGER `trg_prestamo_actualizar_stock` AFTER INSERT ON `prestamo` FOR EACH ROW BEGIN
    IF LOWER(COALESCE(NEW.estado, '')) NOT IN
       ('cancelado', 'rechazado') THEN

        UPDATE recurso
        SET
            disponibilidad = CASE
                WHEN stock_disponible - NEW.cantidad > 0
                    THEN 'Disponible'
                ELSE 'Prestado'
            END,
            stock_disponible = stock_disponible - NEW.cantidad
        WHERE id_recurso = NEW.id_recurso;

    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_prestamo_validar` BEFORE INSERT ON `prestamo` FOR EACH ROW BEGIN
    DECLARE v_stock INT DEFAULT NULL;
    DECLARE v_estado VARCHAR(30);
    DECLARE v_disponibilidad VARCHAR(30);
    DECLARE v_encontrado INT DEFAULT 1;

    DECLARE CONTINUE HANDLER FOR NOT FOUND
        SET v_encontrado = 0;

    IF NEW.cantidad IS NULL OR NEW.cantidad <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La cantidad del prestamo debe ser mayor que cero';
    END IF;

    IF NEW.id_recurso IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Debe seleccionar un recurso';
    END IF;

    SELECT stock_disponible, estado, disponibilidad
    INTO v_stock, v_estado, v_disponibilidad
    FROM recurso
    WHERE id_recurso = NEW.id_recurso
    FOR UPDATE;

    IF v_encontrado = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El recurso seleccionado no existe';
    END IF;

    IF v_stock < NEW.cantidad THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No hay suficientes unidades disponibles';
    END IF;

    IF LOWER(COALESCE(v_estado, '')) IN
       ('danado', 'dañado', 'inactivo', 'baja')
       OR LOWER(COALESCE(v_disponibilidad, '')) IN
       ('danado', 'dañado', 'en mantenimiento', 'inactivo', 'baja') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El recurso no esta habilitado para prestamos';
    END IF;

    IF NEW.fecha_entrega IS NOT NULL
       AND NEW.fecha_solicitud IS NOT NULL
       AND NEW.fecha_entrega < NEW.fecha_solicitud THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La fecha de entrega no puede ser anterior a la solicitud';
    END IF;

    IF NEW.fecha_devolucion_programada IS NOT NULL
       AND NEW.fecha_entrega IS NOT NULL
       AND NEW.fecha_devolucion_programada < NEW.fecha_entrega THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La fecha de devolucion no puede ser anterior a la entrega';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedor`
--

CREATE TABLE `proveedor` (
  `id_proveedor` int(11) NOT NULL,
  `nombre_empresa` varchar(100) DEFAULT NULL,
  `NIT` varchar(30) DEFAULT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `direccion` varchar(150) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recurso`
--

CREATE TABLE `recurso` (
  `id_recurso` int(11) NOT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `codigo_interno` varchar(50) DEFAULT NULL,
  `nombre_recurso` varchar(100) DEFAULT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `serial` varchar(100) DEFAULT NULL,
  `marca` varchar(50) DEFAULT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `estado` varchar(30) DEFAULT NULL,
  `stock_total` int(11) NOT NULL DEFAULT 1,
  `stock_disponible` int(11) NOT NULL DEFAULT 0,
  `disponibilidad` varchar(30) DEFAULT NULL,
  `fecha_registro` date DEFAULT NULL,
  `fecha_adquisicion` date DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `recurso`
--

INSERT INTO `recurso` (`id_recurso`, `id_categoria`, `codigo_interno`, `nombre_recurso`, `descripcion`, `serial`, `marca`, `modelo`, `estado`, `stock_total`, `stock_disponible`, `disponibilidad`, `fecha_registro`, `fecha_adquisicion`) VALUES
(1, 1, '101', 'Portátil Lenovo V14', 'Computador portátil asignado para uso académico en aulas de tecnología, procesador Intel Core i5, 8GB RAM.', 'PF3X8L9A', 'lenovo', 'V14 G2', 'Bueno', 7, 3, 'Disponible', '2026-11-06', '2026-11-06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reporte`
--

CREATE TABLE `reporte` (
  `id_reporte` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `tipo_reporte` varchar(50) DEFAULT NULL,
  `fecha_generacion` date DEFAULT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `formato` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reporte_dano`
--

CREATE TABLE `reporte_dano` (
  `id_reporte_dano` int(11) NOT NULL,
  `id_cuenta_reporta` int(11) NOT NULL,
  `id_recurso` int(11) NOT NULL,
  `tipo_dano` varchar(50) NOT NULL,
  `prioridad` varchar(20) NOT NULL DEFAULT 'Media',
  `descripcion` text NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'Reportado',
  `fecha_reporte` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `reporte_dano`
--
DELIMITER $$
CREATE TRIGGER `trg_reporte_dano_validar` BEFORE INSERT ON `reporte_dano` FOR EACH ROW BEGIN
    DECLARE v_rol VARCHAR(50) DEFAULT NULL;
    DECLARE v_encontrado INT DEFAULT 1;

    DECLARE CONTINUE HANDLER FOR NOT FOUND
        SET v_encontrado = 0;

    SELECT r.nombre_rol
    INTO v_rol
    FROM cuenta_acceso ca
    INNER JOIN rol r
        ON r.id_rol = ca.id_rol
    WHERE ca.id_cuenta = NEW.id_cuenta_reporta
      AND ca.estado = 'Activo';

    IF v_encontrado = 0 OR v_rol <> 'Empleado' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Solo una cuenta activa de empleado puede reportar danos';
    END IF;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

CREATE TABLE `rol` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) DEFAULT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`id_rol`, `nombre_rol`, `descripcion`, `estado`) VALUES
(1, 'Administrador', NULL, NULL),
(2, 'Empleado', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) DEFAULT NULL,
  `tipo_usuario` varchar(30) DEFAULT NULL,
  `nombre` varchar(50) DEFAULT NULL,
  `apellido` varchar(50) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `contraseña` varchar(100) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `id_rol`, `tipo_usuario`, `nombre`, `apellido`, `correo`, `telefono`, `contraseña`, `estado`) VALUES
(2, 2, NULL, 'danil', 'oquendo serna', 'danieloquenser@gmail.com', '3028384939', '$2y$10$tX3Aw2L4yJvoSiUhjMdqVe3IYeKsxfk7//IHJA2dDEcHGD7g2ft.6', 'Activo'),
(3, 1, 'Docente', 'martha', 'rua', 'ipulgarinrua@gmail.com', '3182346729', '$2y$10$JvuS5HSZqreaucTRP2drM.Bi9.apdT2H3VIs.YfyHDckD3tAvZrqK', 'Activo');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_compras`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_compras` (
`id_compra` int(11)
,`id_proveedor` int(11)
,`fecha_compra` date
,`total` decimal(10,2)
,`estado` varchar(30)
,`observaciones` varchar(150)
,`nombre_empresa` varchar(100)
,`lineas` bigint(21)
,`total_detalle` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_historial_prestamos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_historial_prestamos` (
`id_historial` int(11)
,`id_prestamo` int(11)
,`fecha_accion` date
,`fecha_hora` datetime
,`id_cuenta_accion` int(11)
,`accion` varchar(50)
,`detalle` varchar(500)
,`id_recurso` int(11)
,`id_usuario_entrega` int(11)
,`estado_prestamo` varchar(30)
,`codigo_interno` varchar(50)
,`nombre_recurso` varchar(100)
,`correo_cuenta_accion` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_inventario_general`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_inventario_general` (
`id_recurso` int(11)
,`codigo_interno` varchar(50)
,`nombre_recurso` varchar(100)
,`estado` varchar(30)
,`disponibilidad` varchar(30)
,`stock_total` int(11)
,`stock_disponible` int(11)
,`marca` varchar(50)
,`modelo` varchar(50)
,`fecha_adquisicion` date
,`id_categoria` int(11)
,`nombre_categoria` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_mantenimientos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_mantenimientos` (
`id_mantenimiento` int(11)
,`id_recurso` int(11)
,`tipo_mantenimiento` varchar(50)
,`descripcion` varchar(150)
,`fecha_inicio` date
,`fecha_fin` date
,`costo` decimal(10,2)
,`estado` varchar(30)
,`codigo_interno` varchar(50)
,`nombre_recurso` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_prestamos_activos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_prestamos_activos` (
`id_prestamo` int(11)
,`id_empleado` int(11)
,`id_usuario_entrega` int(11)
,`id_recurso` int(11)
,`cantidad` int(11)
,`fecha_solicitud` date
,`fecha_entrega` date
,`fecha_devolucion_programada` date
,`estado` varchar(30)
,`receptor_nombre` varchar(50)
,`receptor_apellido` varchar(50)
,`codigo_interno` varchar(50)
,`nombre_recurso` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_prestamos_vencidos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_prestamos_vencidos` (
`id_prestamo` int(11)
,`id_empleado` int(11)
,`id_usuario_entrega` int(11)
,`id_recurso` int(11)
,`cantidad` int(11)
,`fecha_entrega` date
,`fecha_devolucion_programada` date
,`estado` varchar(30)
,`receptor_nombre` varchar(50)
,`receptor_apellido` varchar(50)
,`codigo_interno` varchar(50)
,`nombre_recurso` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_reportes_dano`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_reportes_dano` (
`id_reporte_dano` int(11)
,`id_cuenta_reporta` int(11)
,`id_recurso` int(11)
,`tipo_dano` varchar(50)
,`prioridad` varchar(20)
,`descripcion` text
,`estado` varchar(30)
,`fecha_reporte` datetime
,`fecha_actualizacion` datetime
,`correo_reportante` varchar(100)
,`codigo_interno` varchar(50)
,`nombre_recurso` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_compras`
--
DROP TABLE IF EXISTS `v_compras`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_compras`  AS SELECT `c`.`id_compra` AS `id_compra`, `c`.`id_proveedor` AS `id_proveedor`, `c`.`fecha_compra` AS `fecha_compra`, `c`.`total` AS `total`, `c`.`estado` AS `estado`, `c`.`observaciones` AS `observaciones`, `p`.`nombre_empresa` AS `nombre_empresa`, count(`dc`.`id_detalle_compra`) AS `lineas`, coalesce(sum(`dc`.`cantidad` * `dc`.`costo_unitario`),0) AS `total_detalle` FROM ((`compra` `c` left join `proveedor` `p` on(`p`.`id_proveedor` = `c`.`id_proveedor`)) left join `detalle_compra` `dc` on(`dc`.`id_compra` = `c`.`id_compra`)) GROUP BY `c`.`id_compra`, `c`.`id_proveedor`, `c`.`fecha_compra`, `c`.`total`, `c`.`estado`, `c`.`observaciones`, `p`.`nombre_empresa` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_historial_prestamos`
--
DROP TABLE IF EXISTS `v_historial_prestamos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_historial_prestamos`  AS SELECT `h`.`id_historial` AS `id_historial`, `h`.`id_prestamo` AS `id_prestamo`, `h`.`fecha_accion` AS `fecha_accion`, `h`.`fecha_hora` AS `fecha_hora`, `h`.`id_cuenta_accion` AS `id_cuenta_accion`, `h`.`accion` AS `accion`, `h`.`detalle` AS `detalle`, `p`.`id_recurso` AS `id_recurso`, `p`.`id_usuario_entrega` AS `id_usuario_entrega`, `p`.`estado` AS `estado_prestamo`, `r`.`codigo_interno` AS `codigo_interno`, `r`.`nombre_recurso` AS `nombre_recurso`, `ca`.`correo` AS `correo_cuenta_accion` FROM (((`historial_prestamo` `h` left join `prestamo` `p` on(`p`.`id_prestamo` = `h`.`id_prestamo`)) left join `recurso` `r` on(`r`.`id_recurso` = `p`.`id_recurso`)) left join `cuenta_acceso` `ca` on(`ca`.`id_cuenta` = `h`.`id_cuenta_accion`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_inventario_general`
--
DROP TABLE IF EXISTS `v_inventario_general`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_inventario_general`  AS SELECT `r`.`id_recurso` AS `id_recurso`, `r`.`codigo_interno` AS `codigo_interno`, `r`.`nombre_recurso` AS `nombre_recurso`, `r`.`estado` AS `estado`, `r`.`disponibilidad` AS `disponibilidad`, `r`.`stock_total` AS `stock_total`, `r`.`stock_disponible` AS `stock_disponible`, `r`.`marca` AS `marca`, `r`.`modelo` AS `modelo`, `r`.`fecha_adquisicion` AS `fecha_adquisicion`, `c`.`id_categoria` AS `id_categoria`, `c`.`nombre_categoria` AS `nombre_categoria` FROM (`recurso` `r` left join `categoria_recurso` `c` on(`c`.`id_categoria` = `r`.`id_categoria`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_mantenimientos`
--
DROP TABLE IF EXISTS `v_mantenimientos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_mantenimientos`  AS SELECT `m`.`id_mantenimiento` AS `id_mantenimiento`, `m`.`id_recurso` AS `id_recurso`, `m`.`tipo_mantenimiento` AS `tipo_mantenimiento`, `m`.`descripcion` AS `descripcion`, `m`.`fecha_inicio` AS `fecha_inicio`, `m`.`fecha_fin` AS `fecha_fin`, `m`.`costo` AS `costo`, `m`.`estado` AS `estado`, `r`.`codigo_interno` AS `codigo_interno`, `r`.`nombre_recurso` AS `nombre_recurso` FROM (`mantenimiento` `m` left join `recurso` `r` on(`r`.`id_recurso` = `m`.`id_recurso`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_prestamos_activos`
--
DROP TABLE IF EXISTS `v_prestamos_activos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prestamos_activos`  AS SELECT `p`.`id_prestamo` AS `id_prestamo`, `p`.`id_empleado` AS `id_empleado`, `p`.`id_usuario_entrega` AS `id_usuario_entrega`, `p`.`id_recurso` AS `id_recurso`, `p`.`cantidad` AS `cantidad`, `p`.`fecha_solicitud` AS `fecha_solicitud`, `p`.`fecha_entrega` AS `fecha_entrega`, `p`.`fecha_devolucion_programada` AS `fecha_devolucion_programada`, `p`.`estado` AS `estado`, `u`.`nombre` AS `receptor_nombre`, `u`.`apellido` AS `receptor_apellido`, `r`.`codigo_interno` AS `codigo_interno`, `r`.`nombre_recurso` AS `nombre_recurso` FROM ((`prestamo` `p` left join `usuario` `u` on(`u`.`id_usuario` = `p`.`id_usuario_entrega`)) left join `recurso` `r` on(`r`.`id_recurso` = `p`.`id_recurso`)) WHERE `p`.`estado` in ('Activo','Vencido') ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_prestamos_vencidos`
--
DROP TABLE IF EXISTS `v_prestamos_vencidos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_prestamos_vencidos`  AS SELECT `p`.`id_prestamo` AS `id_prestamo`, `p`.`id_empleado` AS `id_empleado`, `p`.`id_usuario_entrega` AS `id_usuario_entrega`, `p`.`id_recurso` AS `id_recurso`, `p`.`cantidad` AS `cantidad`, `p`.`fecha_entrega` AS `fecha_entrega`, `p`.`fecha_devolucion_programada` AS `fecha_devolucion_programada`, `p`.`estado` AS `estado`, `u`.`nombre` AS `receptor_nombre`, `u`.`apellido` AS `receptor_apellido`, `r`.`codigo_interno` AS `codigo_interno`, `r`.`nombre_recurso` AS `nombre_recurso` FROM ((`prestamo` `p` left join `usuario` `u` on(`u`.`id_usuario` = `p`.`id_usuario_entrega`)) left join `recurso` `r` on(`r`.`id_recurso` = `p`.`id_recurso`)) WHERE `p`.`estado` = 'Vencido' OR `p`.`estado` in ('Activo','Pendiente') AND `p`.`fecha_devolucion_programada` < curdate() AND `p`.`fecha_devolucion_real` is null ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_reportes_dano`
--
DROP TABLE IF EXISTS `v_reportes_dano`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_reportes_dano`  AS SELECT `d`.`id_reporte_dano` AS `id_reporte_dano`, `d`.`id_cuenta_reporta` AS `id_cuenta_reporta`, `d`.`id_recurso` AS `id_recurso`, `d`.`tipo_dano` AS `tipo_dano`, `d`.`prioridad` AS `prioridad`, `d`.`descripcion` AS `descripcion`, `d`.`estado` AS `estado`, `d`.`fecha_reporte` AS `fecha_reporte`, `d`.`fecha_actualizacion` AS `fecha_actualizacion`, `ca`.`correo` AS `correo_reportante`, `r`.`codigo_interno` AS `codigo_interno`, `r`.`nombre_recurso` AS `nombre_recurso` FROM ((`reporte_dano` `d` join `cuenta_acceso` `ca` on(`ca`.`id_cuenta` = `d`.`id_cuenta_reporta`)) join `recurso` `r` on(`r`.`id_recurso` = `d`.`id_recurso`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `administrador`
--
ALTER TABLE `administrador`
  ADD PRIMARY KEY (`id_administrador`),
  ADD UNIQUE KEY `id_cuenta` (`id_cuenta`),
  ADD UNIQUE KEY `documento` (`documento`);

--
-- Indices de la tabla `categoria_recurso`
--
ALTER TABLE `categoria_recurso`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `uq_categoria_nombre` (`nombre_categoria`);

--
-- Indices de la tabla `compra`
--
ALTER TABLE `compra`
  ADD PRIMARY KEY (`id_compra`),
  ADD KEY `id_proveedor` (`id_proveedor`),
  ADD KEY `idx_compra_proveedor_fecha` (`id_proveedor`,`fecha_compra`);

--
-- Indices de la tabla `cuenta_acceso`
--
ALTER TABLE `cuenta_acceso`
  ADD PRIMARY KEY (`id_cuenta`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD UNIQUE KEY `id_empleado` (`id_empleado`),
  ADD UNIQUE KEY `uq_cuenta_acceso_nombre_usuario` (`nombre_usuario`),
  ADD KEY `fk_cuenta_rol` (`id_rol`);

--
-- Indices de la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  ADD PRIMARY KEY (`id_detalle_compra`),
  ADD KEY `id_compra` (`id_compra`),
  ADD KEY `id_recurso` (`id_recurso`);

--
-- Indices de la tabla `devolucion`
--
ALTER TABLE `devolucion`
  ADD PRIMARY KEY (`id_devolucion`),
  ADD KEY `id_prestamo` (`id_prestamo`),
  ADD KEY `fk_devolucion_cuenta_registra` (`id_cuenta_registra`);

--
-- Indices de la tabla `empleado`
--
ALTER TABLE `empleado`
  ADD PRIMARY KEY (`id_empleado`),
  ADD UNIQUE KEY `uq_empleado_documento` (`documento`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `historial_prestamo`
--
ALTER TABLE `historial_prestamo`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_prestamo` (`id_prestamo`),
  ADD KEY `fk_historial_cuenta_accion` (`id_cuenta_accion`),
  ADD KEY `idx_historial_prestamo_fecha` (`id_prestamo`,`fecha_hora`);

--
-- Indices de la tabla `mantenimiento`
--
ALTER TABLE `mantenimiento`
  ADD PRIMARY KEY (`id_mantenimiento`),
  ADD KEY `id_recurso` (`id_recurso`),
  ADD KEY `idx_mantenimiento_recurso_estado` (`id_recurso`,`estado`);

--
-- Indices de la tabla `prestamo`
--
ALTER TABLE `prestamo`
  ADD PRIMARY KEY (`id_prestamo`),
  ADD KEY `id_empleado` (`id_empleado`),
  ADD KEY `id_recurso` (`id_recurso`),
  ADD KEY `idx_prestamo_recurso_estado` (`id_recurso`,`estado`),
  ADD KEY `idx_prestamo_empleado_estado` (`id_empleado`,`estado`),
  ADD KEY `idx_prestamo_receptor_estado` (`id_usuario_entrega`,`estado`),
  ADD KEY `idx_prestamo_estado_fecha` (`estado`,`fecha_devolucion_programada`),
  ADD KEY `idx_prestamo_cuenta_responsable` (`id_cuenta_responsable`);

--
-- Indices de la tabla `proveedor`
--
ALTER TABLE `proveedor`
  ADD PRIMARY KEY (`id_proveedor`),
  ADD UNIQUE KEY `uq_proveedor_nit` (`NIT`);

--
-- Indices de la tabla `recurso`
--
ALTER TABLE `recurso`
  ADD PRIMARY KEY (`id_recurso`),
  ADD UNIQUE KEY `uq_recurso_codigo_interno` (`codigo_interno`),
  ADD KEY `id_categoria` (`id_categoria`);

--
-- Indices de la tabla `reporte`
--
ALTER TABLE `reporte`
  ADD PRIMARY KEY (`id_reporte`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `reporte_dano`
--
ALTER TABLE `reporte_dano`
  ADD PRIMARY KEY (`id_reporte_dano`),
  ADD KEY `idx_reporte_dano_cuenta_fecha` (`id_cuenta_reporta`,`fecha_reporte`),
  ADD KEY `idx_reporte_dano_recurso_estado` (`id_recurso`,`estado`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `uq_rol_nombre` (`nombre_rol`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuario_correo` (`correo`),
  ADD KEY `id_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `administrador`
--
ALTER TABLE `administrador`
  MODIFY `id_administrador` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `categoria_recurso`
--
ALTER TABLE `categoria_recurso`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `compra`
--
ALTER TABLE `compra`
  MODIFY `id_compra` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cuenta_acceso`
--
ALTER TABLE `cuenta_acceso`
  MODIFY `id_cuenta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  MODIFY `id_detalle_compra` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `devolucion`
--
ALTER TABLE `devolucion`
  MODIFY `id_devolucion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `empleado`
--
ALTER TABLE `empleado`
  MODIFY `id_empleado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `historial_prestamo`
--
ALTER TABLE `historial_prestamo`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `mantenimiento`
--
ALTER TABLE `mantenimiento`
  MODIFY `id_mantenimiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `prestamo`
--
ALTER TABLE `prestamo`
  MODIFY `id_prestamo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proveedor`
--
ALTER TABLE `proveedor`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recurso`
--
ALTER TABLE `recurso`
  MODIFY `id_recurso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reporte`
--
ALTER TABLE `reporte`
  MODIFY `id_reporte` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reporte_dano`
--
ALTER TABLE `reporte_dano`
  MODIFY `id_reporte_dano` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rol`
--
ALTER TABLE `rol`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `administrador`
--
ALTER TABLE `administrador`
  ADD CONSTRAINT `fk_administrador_cuenta` FOREIGN KEY (`id_cuenta`) REFERENCES `cuenta_acceso` (`id_cuenta`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `compra`
--
ALTER TABLE `compra`
  ADD CONSTRAINT `compra_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id_proveedor`);

--
-- Filtros para la tabla `cuenta_acceso`
--
ALTER TABLE `cuenta_acceso`
  ADD CONSTRAINT `fk_cuenta_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  ADD CONSTRAINT `fk_cuenta_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`);

--
-- Filtros para la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  ADD CONSTRAINT `detalle_compra_ibfk_1` FOREIGN KEY (`id_compra`) REFERENCES `compra` (`id_compra`),
  ADD CONSTRAINT `detalle_compra_ibfk_2` FOREIGN KEY (`id_recurso`) REFERENCES `recurso` (`id_recurso`);

--
-- Filtros para la tabla `devolucion`
--
ALTER TABLE `devolucion`
  ADD CONSTRAINT `devolucion_ibfk_1` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`),
  ADD CONSTRAINT `fk_devolucion_cuenta_registra` FOREIGN KEY (`id_cuenta_registra`) REFERENCES `cuenta_acceso` (`id_cuenta`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `empleado`
--
ALTER TABLE `empleado`
  ADD CONSTRAINT `empleado_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `historial_prestamo`
--
ALTER TABLE `historial_prestamo`
  ADD CONSTRAINT `fk_historial_cuenta_accion` FOREIGN KEY (`id_cuenta_accion`) REFERENCES `cuenta_acceso` (`id_cuenta`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `historial_prestamo_ibfk_1` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);

--
-- Filtros para la tabla `mantenimiento`
--
ALTER TABLE `mantenimiento`
  ADD CONSTRAINT `mantenimiento_ibfk_1` FOREIGN KEY (`id_recurso`) REFERENCES `recurso` (`id_recurso`);

--
-- Filtros para la tabla `prestamo`
--
ALTER TABLE `prestamo`
  ADD CONSTRAINT `fk_prestamo_cuenta_responsable` FOREIGN KEY (`id_cuenta_responsable`) REFERENCES `cuenta_acceso` (`id_cuenta`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_prestamo_usuario_entrega` FOREIGN KEY (`id_usuario_entrega`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `prestamo_ibfk_1` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  ADD CONSTRAINT `prestamo_ibfk_2` FOREIGN KEY (`id_recurso`) REFERENCES `recurso` (`id_recurso`);

--
-- Filtros para la tabla `recurso`
--
ALTER TABLE `recurso`
  ADD CONSTRAINT `recurso_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categoria_recurso` (`id_categoria`);

--
-- Filtros para la tabla `reporte`
--
ALTER TABLE `reporte`
  ADD CONSTRAINT `reporte_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `reporte_dano`
--
ALTER TABLE `reporte_dano`
  ADD CONSTRAINT `fk_reporte_dano_cuenta` FOREIGN KEY (`id_cuenta_reporta`) REFERENCES `cuenta_acceso` (`id_cuenta`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reporte_dano_recurso` FOREIGN KEY (`id_recurso`) REFERENCES `recurso` (`id_recurso`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

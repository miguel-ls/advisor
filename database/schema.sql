-- ADVISOR-ERP Database Schema Creation Script for MySQL

CREATE DATABASE IF NOT EXISTS advisor_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE advisor_db;

-- Table: usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(120) NOT NULL,
    email VARCHAR(100) NOT NULL,
    dos_factores_activo TINYINT(1) DEFAULT 1,
    dos_factores_secret VARCHAR(50) DEFAULT '123456', -- Secret / 6-digit PIN for 2FA
    estado CHAR(1) DEFAULT 'A',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: empresas
CREATE TABLE IF NOT EXISTS empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ruc VARCHAR(15) NOT NULL UNIQUE,
    razon_social VARCHAR(200) NOT NULL,
    direccion VARCHAR(250) DEFAULT '',
    telefono VARCHAR(20) DEFAULT '',
    estado CHAR(1) DEFAULT 'A',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: empresa_periodos (Años y Meses por Empresa)
CREATE TABLE IF NOT EXISTS empresa_periodos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    anio INT NOT NULL,
    mes INT NOT NULL, -- 1 a 12
    estado CHAR(1) DEFAULT 'A', -- 'A' Abierto, 'C' Cerrado
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ep_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_empresa_periodo (empresa_id, anio, mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: usuario_empresas (Relación Mantenimiento Usuario - Empresa)
CREATE TABLE IF NOT EXISTS usuario_empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    empresa_id INT NOT NULL,
    CONSTRAINT fk_ue_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_ue_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_usuario_empresa (usuario_id, empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: modulos (Ventas, Logística, Tesorería, Contabilidad, Mantenimiento)
CREATE TABLE IF NOT EXISTS modulos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(50) DEFAULT 'fa-folder',
    orden INT DEFAULT 0,
    estado CHAR(1) DEFAULT 'A'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: menus (Opciones dinámicas por Módulo)
CREATE TABLE IF NOT EXISTS menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modulo_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    pagina_url VARCHAR(200) NOT NULL, -- Configuración de página que se abrirá
    icono VARCHAR(50) DEFAULT 'fa-file-alt',
    orden INT DEFAULT 0,
    estado CHAR(1) DEFAULT 'A',
    CONSTRAINT fk_menu_modulo FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: usuario_permisos (Mantenimiento Usuario - Modulo - Menu)
CREATE TABLE IF NOT EXISTS usuario_permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    modulo_id INT NOT NULL,
    menu_id INT NOT NULL,
    acceso TINYINT(1) DEFAULT 1,
    CONSTRAINT fk_up_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_up_modulo FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,
    CONSTRAINT fk_up_menu FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    UNIQUE KEY uq_usuario_menu (usuario_id, modulo_id, menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: configuracion_framework (Personalización Framework, tema de color)
CREATE TABLE IF NOT EXISTS configuracion_framework (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tema_color VARCHAR(50) DEFAULT 'rojo-granate', -- 'rojo-granate' o 'azul-mandalorian'
    nombre_sistema VARCHAR(100) DEFAULT 'ADVISOR-ERP',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: clientes (Mantenimiento de Clientes para los módulos)
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    codigo VARCHAR(20) NOT NULL,
    tipo_entidad VARCHAR(20) DEFAULT 'CLIENTE',
    razon_social VARCHAR(200) NOT NULL,
    direccion VARCHAR(250) DEFAULT '',
    ruc_dni VARCHAR(20) DEFAULT '',
    representante VARCHAR(100) DEFAULT '',
    tipo_doc VARCHAR(10) DEFAULT 'RUC',
    estado CHAR(1) DEFAULT 'A', -- 'A' Activo, 'B' Baja
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cliente_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: CNM_ENTIDAD (Entidad / Clientes según formato spClientes)
CREATE TABLE IF NOT EXISTS CNM_ENTIDAD (
    Emp_cCodigo CHAR(3) NOT NULL,
    Ent_cCodEntidad VARCHAR(20) NOT NULL,
    Ten_cTipoEntidad CHAR(1) NOT NULL DEFAULT 'C',
    Ent_cPersona VARCHAR(120) NOT NULL,
    Ent_cDireccion VARCHAR(120) DEFAULT '',
    Ent_nRuc VARCHAR(15) DEFAULT '',
    Ent_cRepresentante VARCHAR(80) DEFAULT '',
    Ent_cTipoDoc CHAR(3) DEFAULT 'RUC',
    Ent_cFlagPersona CHAR(1) DEFAULT 'J',
    Ent_cEstadoEntidad CHAR(1) DEFAULT '1',
    Ent_cEstado CHAR(1) DEFAULT 'A',
    Ent_cDeleted CHAR(1) DEFAULT '',
    Ent_cUserCrea VARCHAR(20) DEFAULT '',
    Ent_dFechaCrea DATETIME DEFAULT CURRENT_TIMESTAMP,
    Ent_cUserModifica VARCHAR(20) DEFAULT NULL,
    Ent_dFechaModifica DATETIME DEFAULT NULL,
    Ent_cEquipoUser VARCHAR(50) DEFAULT '',
    Ent_ValidaLC CHAR(1) DEFAULT '0',
    PRIMARY KEY (Emp_cCodigo, Ten_cTipoEntidad, Ent_cCodEntidad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial Seed Data
-- 1. Default Admin & Users
-- BCRYPT hash for password "admin123" is: $2y$10$ZmYd06EZP3voP/nktn7OOOedomX5e3Ius1jMs9Hf9yl.GhM7dMBQG
INSERT INTO usuarios (id, username, password_hash, nombre_completo, email, dos_factores_activo, dos_factores_secret, estado)
VALUES
(1, 'admin', '$2y$10$ZmYd06EZP3voP/nktn7OOOedomX5e3Ius1jMs9Hf9yl.GhM7dMBQG', 'Administrador General', 'admin@advisor.com', 1, '123456', 'A'),
(2, 'ventas_user', '$2y$10$ZmYd06EZP3voP/nktn7OOOedomX5e3Ius1jMs9Hf9yl.GhM7dMBQG', 'Usuario Ventas', 'ventas@advisor.com', 1, '123456', 'A'),
(3, 'logistica_user', '$2y$10$ZmYd06EZP3voP/nktn7OOOedomX5e3Ius1jMs9Hf9yl.GhM7dMBQG', 'Usuario Logística', 'logistica@advisor.com', 1, '123456', 'A')
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- 2. Default Empresas
INSERT INTO empresas (id, ruc, razon_social, direccion, telefono, estado)
VALUES
(1, '20100088881', 'ADVISOR CONSULTING S.A.C.', 'Av. Javier Prado Este 456, Lima', '01-4455667', 'A'),
(2, '20555444331', 'CORP ADVISOR LOGISTICS S.A.', 'Av. Argentina 1230, Callao', '01-9988776', 'A')
ON DUPLICATE KEY UPDATE id=id;

-- 3. Default Empresa Periodos (Años y Meses)
INSERT INTO empresa_periodos (empresa_id, anio, mes, estado) VALUES
(1, 2025, 1, 'A'), (1, 2025, 2, 'A'), (1, 2025, 3, 'A'), (1, 2025, 4, 'A'),
(1, 2025, 5, 'A'), (1, 2025, 6, 'A'), (1, 2025, 7, 'A'), (1, 2025, 8, 'A'),
(1, 2025, 9, 'A'), (1, 2025, 10, 'A'), (1, 2025, 11, 'A'), (1, 2025, 12, 'A'),
(2, 2025, 1, 'A'), (2, 2025, 2, 'A'), (2, 2025, 3, 'A'), (2, 2025, 4, 'A')
ON DUPLICATE KEY UPDATE id=id;

-- 4. Default Usuario Empresas
INSERT INTO usuario_empresas (usuario_id, empresa_id) VALUES
(1, 1), (1, 2),
(2, 1),
(3, 2)
ON DUPLICATE KEY UPDATE id=id;

-- 5. Default Modulos
INSERT INTO modulos (id, codigo, nombre, icono, orden, estado) VALUES
(1, 'SISTEMA', 'Mantenimiento del Sistema', 'fas fa-cogs', 1, 'A'),
(2, 'VENTAS', 'Módulo de Ventas', 'fas fa-shopping-cart', 2, 'A'),
(3, 'LOGISTICA', 'Módulo de Logística', 'fas fa-truck', 3, 'A'),
(4, 'TESORERIA', 'Módulo de Tesorería', 'fas fa-wallet', 4, 'A'),
(5, 'CONTABILIDAD', 'Módulo de Contabilidad', 'fas fa-calculator', 5, 'A')
ON DUPLICATE KEY UPDATE id=id;

-- 6. Default Menus (Configurados con página que abrirá en frontend/views/...)
INSERT INTO menus (id, modulo_id, nombre, pagina_url, icono, orden, estado) VALUES
-- Mantenimiento del Sistema
(1, 1, 'Mantenimiento de Usuarios', 'usuarios.php', 'fas fa-users-cog', 1, 'A'),
(2, 1, 'Mantenimiento de Empresas', 'empresas.php', 'fas fa-building', 2, 'A'),
(3, 1, 'Años y Meses de Empresas', 'empresa_periodos.php', 'fas fa-calendar-alt', 3, 'A'),
(4, 1, 'Mantenimiento de Módulos', 'modulos.php', 'fas fa-cubes', 4, 'A'),
(5, 1, 'Mantenimiento de Menús', 'menus.php', 'fas fa-list-ul', 5, 'A'),
(6, 1, 'Permisos Usuarios - Menús', 'usuario_permisos.php', 'fas fa-user-shield', 6, 'A'),
(7, 1, 'Personalización Framework', 'configuracion.php', 'fas fa-paint-brush', 7, 'A'),

-- Módulo Ventas
(8, 2, 'Mantenimiento de Clientes', 'clientes.php', 'fas fa-user-tie', 1, 'A'),
(9, 2, 'Facturación y Cotizaciones', 'ventas_facturacion.php', 'fas fa-file-invoice-dollar', 2, 'A'),

-- Módulo Logística
(10, 3, 'Control de Inventarios', 'logistica_inventario.php', 'fas fa-boxes', 1, 'A'),
(11, 3, 'Ordenes de Compra', 'logistica_ordenes.php', 'fas fa-clipboard-list', 2, 'A'),

-- Módulo Tesorería
(12, 4, 'Caja y Bancos', 'tesoreria_caja.php', 'fas fa-university', 1, 'A'),

-- Módulo Contabilidad
(13, 5, 'Libro Diario / Mayor', 'contabilidad_libros.php', 'fas fa-book', 1, 'A')
ON DUPLICATE KEY UPDATE id=id;

-- 7. Assign Permissions
-- Admin user (1) has all menus
INSERT INTO usuario_permisos (usuario_id, modulo_id, menu_id, acceso)
SELECT 1, modulo_id, id, 1 FROM menus
ON DUPLICATE KEY UPDATE acceso=1;

-- Ventas user (2) has access to Ventas module (8, 9)
INSERT INTO usuario_permisos (usuario_id, modulo_id, menu_id, acceso) VALUES
(2, 2, 8, 1),
(2, 2, 9, 1)
ON DUPLICATE KEY UPDATE acceso=1;

-- Logística user (3) has access to Logística module (10, 11)
INSERT INTO usuario_permisos (usuario_id, modulo_id, menu_id, acceso) VALUES
(3, 3, 10, 1),
(3, 3, 11, 1)
ON DUPLICATE KEY UPDATE acceso=1;

-- 8. Configuration
INSERT INTO configuracion_framework (id, tema_color, nombre_sistema)
VALUES (1, 'rojo-granate', 'ADVISOR-ERP')
ON DUPLICATE KEY UPDATE id=1;

-- 9. Sample Clientes
INSERT INTO clientes (empresa_id, codigo, tipo_entidad, razon_social, direccion, ruc_dni, representante, tipo_doc, estado) VALUES
(1, 'CLI001', 'CLIENTE', 'DISTRIBUIDORA LIMA S.A.C.', 'Av. Industrial 890, Ate', '20499887711', 'Juan Pérez', 'RUC', 'A'),
(1, 'CLI002', 'CLIENTE', 'COMERCIAL SANTA ROSA E.I.R.L.', 'Jr. Cusco 432, Lima', '20123456789', 'Maria Gomez', 'RUC', 'A'),
(1, 'CLI003', 'CLIENTE', 'SERVICIOS DIGITALES S.A.', 'Av. Larco 550, Miraflores', '20987654321', 'Carlos Ruiz', 'RUC', 'B')
ON DUPLICATE KEY UPDATE id=id;

<?php
// setup_db.php - Utility to initialize local SQLite database for offline sandbox verification

$dbFile = __DIR__ . '/database/advisor_dev.sqlite';
if (file_exists($dbFile)) {
    unlink($dbFile);
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create SQLite tables corresponding to schema
$pdo->exec("
CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    nombre_completo TEXT NOT NULL,
    email TEXT NOT NULL,
    dos_factores_activo INTEGER DEFAULT 1,
    dos_factores_secret TEXT DEFAULT '123456',
    estado TEXT DEFAULT 'A',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE CNM_ENTIDAD (
    Emp_cCodigo TEXT NOT NULL,
    Ent_cCodEntidad TEXT NOT NULL,
    Ten_cTipoEntidad TEXT DEFAULT 'C',
    Ent_cPersona TEXT NOT NULL,
    Ent_cDireccion TEXT DEFAULT '',
    Ent_nRuc TEXT DEFAULT '',
    Ent_cRepresentante TEXT DEFAULT '',
    Ent_cTipoDoc TEXT DEFAULT 'RUC',
    Ent_cFlagPersona TEXT DEFAULT 'J',
    Ent_cEstadoEntidad TEXT DEFAULT '1',
    Ent_cEstado TEXT DEFAULT 'A',
    Ent_cDeleted TEXT DEFAULT '',
    Ent_cUserCrea TEXT DEFAULT '',
    Ent_dFechaCrea DATETIME DEFAULT CURRENT_TIMESTAMP,
    Ent_cUserModifica TEXT DEFAULT NULL,
    Ent_dFechaModifica DATETIME DEFAULT NULL,
    Ent_cEquipoUser TEXT DEFAULT '',
    Ent_ValidaLC TEXT DEFAULT '0',
    PRIMARY KEY (Emp_cCodigo, Ten_cTipoEntidad, Ent_cCodEntidad)
);

CREATE TABLE empresas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ruc TEXT NOT NULL UNIQUE,
    razon_social TEXT NOT NULL,
    direccion TEXT DEFAULT '',
    telefono TEXT DEFAULT '',
    estado TEXT DEFAULT 'A',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE empresa_periodos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    empresa_id INTEGER NOT NULL,
    anio INTEGER NOT NULL,
    mes INTEGER NOT NULL,
    estado TEXT DEFAULT 'A',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE usuario_empresas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    empresa_id INTEGER NOT NULL
);

CREATE TABLE modulos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo TEXT NOT NULL UNIQUE,
    nombre TEXT NOT NULL,
    icono TEXT DEFAULT 'fa-folder',
    orden INTEGER DEFAULT 0,
    estado TEXT DEFAULT 'A'
);

CREATE TABLE menus (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    modulo_id INTEGER NOT NULL,
    nombre TEXT NOT NULL,
    pagina_url TEXT NOT NULL,
    icono TEXT DEFAULT 'fa-file-alt',
    orden INTEGER DEFAULT 0,
    estado TEXT DEFAULT 'A'
);

CREATE TABLE usuario_permisos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    modulo_id INTEGER NOT NULL,
    menu_id INTEGER NOT NULL,
    acceso INTEGER DEFAULT 1
);

CREATE TABLE configuracion_framework (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tema_color TEXT DEFAULT 'rojo-granate',
    nombre_sistema TEXT DEFAULT 'ADVISOR-ERP',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    empresa_id INTEGER NOT NULL,
    codigo TEXT NOT NULL,
    tipo_entidad TEXT DEFAULT 'CLIENTE',
    razon_social TEXT NOT NULL,
    direccion TEXT DEFAULT '',
    ruc_dni TEXT DEFAULT '',
    representante TEXT DEFAULT '',
    tipo_doc TEXT DEFAULT 'RUC',
    estado TEXT DEFAULT 'A',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// Insert Seed Data (password_hash for 'admin123' is $2y$10$ZmYd06EZP3voP/nktn7OOOedomX5e3Ius1jMs9Hf9yl.GhM7dMBQG)
$passHash = password_hash('admin123', PASSWORD_BCRYPT);
$stmtUser = $pdo->prepare("
INSERT INTO usuarios (id, username, password_hash, nombre_completo, email, dos_factores_activo, dos_factores_secret, estado)
VALUES
(1, 'admin', :hash, 'Administrador General', 'admin@advisor.com', 1, '123456', 'A'),
(2, 'ventas_user', :hash, 'Usuario Ventas', 'ventas@advisor.com', 1, '123456', 'A'),
(3, 'logistica_user', :hash, 'Usuario Logística', 'logistica@advisor.com', 1, '123456', 'A');
");
$stmtUser->execute([':hash' => $passHash]);

$pdo->exec("
INSERT INTO empresas (id, ruc, razon_social, direccion, telefono, estado)
VALUES
(1, '20100088881', 'ADVISOR CONSULTING S.A.C.', 'Av. Javier Prado Este 456, Lima', '01-4455667', 'A'),
(2, '20555444331', 'CORP ADVISOR LOGISTICS S.A.', 'Av. Argentina 1230, Callao', '01-9988776', 'A');

INSERT INTO empresa_periodos (empresa_id, anio, mes, estado) VALUES
(1, 2025, 1, 'A'), (1, 2025, 2, 'A'), (1, 2025, 3, 'A'), (1, 2025, 4, 'A'),
(1, 2025, 5, 'A'), (1, 2025, 6, 'A'), (1, 2025, 7, 'A'), (1, 2025, 8, 'A'),
(1, 2025, 9, 'A'), (1, 2025, 10, 'A'), (1, 2025, 11, 'A'), (1, 2025, 12, 'A'),
(2, 2025, 1, 'A'), (2, 2025, 2, 'A'), (2, 2025, 3, 'A'), (2, 2025, 4, 'A');

INSERT INTO usuario_empresas (usuario_id, empresa_id) VALUES
(1, 1), (1, 2),
(2, 1),
(3, 2);

INSERT INTO modulos (id, codigo, nombre, icono, orden, estado) VALUES
(1, 'SISTEMA', 'Mantenimiento del Sistema', 'fas fa-cogs', 1, 'A'),
(2, 'VENTAS', 'Módulo de Ventas', 'fas fa-shopping-cart', 2, 'A'),
(3, 'LOGISTICA', 'Módulo de Logística', 'fas fa-truck', 3, 'A'),
(4, 'TESORERIA', 'Módulo de Tesorería', 'fas fa-wallet', 4, 'A'),
(5, 'CONTABILIDAD', 'Módulo de Contabilidad', 'fas fa-calculator', 5, 'A');

INSERT INTO menus (id, modulo_id, nombre, pagina_url, icono, orden, estado) VALUES
(1, 1, 'Mantenimiento de Usuarios', 'usuarios.php', 'fas fa-users-cog', 1, 'A'),
(2, 1, 'Mantenimiento de Empresas', 'empresas.php', 'fas fa-building', 2, 'A'),
(3, 1, 'Años y Meses de Empresas', 'empresa_periodos.php', 'fas fa-calendar-alt', 3, 'A'),
(4, 1, 'Mantenimiento de Módulos', 'modulos.php', 'fas fa-cubes', 4, 'A'),
(5, 1, 'Mantenimiento de Menús', 'menus.php', 'fas fa-list-ul', 5, 'A'),
(6, 1, 'Permisos Usuarios - Menús', 'usuario_permisos.php', 'fas fa-user-shield', 6, 'A'),
(7, 1, 'Personalización Framework', 'configuracion.php', 'fas fa-paint-brush', 7, 'A'),
(8, 2, 'Mantenimiento de Clientes', 'clientes.php', 'fas fa-user-tie', 1, 'A'),
(9, 2, 'Facturación y Cotizaciones', 'ventas_facturacion.php', 'fas fa-file-invoice-dollar', 2, 'A'),
(10, 3, 'Control de Inventarios', 'logistica_inventario.php', 'fas fa-boxes', 1, 'A'),
(11, 3, 'Ordenes de Compra', 'logistica_ordenes.php', 'fas fa-clipboard-list', 2, 'A'),
(12, 4, 'Caja y Bancos', 'tesoreria_caja.php', 'fas fa-university', 1, 'A'),
(13, 5, 'Libro Diario / Mayor', 'contabilidad_libros.php', 'fas fa-book', 1, 'A');

INSERT INTO usuario_permisos (usuario_id, modulo_id, menu_id, acceso)
SELECT 1, modulo_id, id, 1 FROM menus;

INSERT INTO usuario_permisos (usuario_id, modulo_id, menu_id, acceso) VALUES
(2, 2, 8, 1),
(2, 2, 9, 1),
(3, 3, 10, 1),
(3, 3, 11, 1);

INSERT INTO configuracion_framework (id, tema_color, nombre_sistema)
VALUES (1, 'rojo-granate', 'ADVISOR-ERP');

INSERT INTO clientes (empresa_id, codigo, tipo_entidad, razon_social, direccion, ruc_dni, representante, tipo_doc, estado) VALUES
(1, 'CLI001', 'CLIENTE', 'DISTRIBUIDORA LIMA S.A.C.', 'Av. Industrial 890, Ate', '20499887711', 'Juan Pérez', 'RUC', 'A'),
(1, 'CLI002', 'CLIENTE', 'COMERCIAL SANTA ROSA E.I.R.L.', 'Jr. Cusco 432, Lima', '20123456789', 'Maria Gomez', 'RUC', 'A'),
(1, 'CLI003', 'CLIENTE', 'SERVICIOS DIGITALES S.A.', 'Av. Larco 550, Miraflores', '20987654321', 'Carlos Ruiz', 'RUC', 'B');
");

echo "Database initialized successfully.\n";

-- Stored Procedures for ADVISOR-ERP System in MySQL
-- Pattern: Every page/entity has one Stored Procedure handling actions (@p_accion)

DELIMITER $$

-- ---------------------------------------------------------------------
-- 1. SP: sp_usuarios (Mantenimiento de Usuarios, Login y 2FA)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_usuarios$$
CREATE PROCEDURE sp_usuarios(
    IN p_accion VARCHAR(50),
    IN p_id INT,
    IN p_username VARCHAR(50),
    IN p_password_hash VARCHAR(255),
    IN p_nombre_completo VARCHAR(120),
    IN p_email VARCHAR(100),
    IN p_dos_factores_activo TINYINT,
    IN p_dos_factores_secret VARCHAR(50),
    IN p_estado CHAR(1)
)
BEGIN
    IF p_accion = 'LOGIN' THEN
        SELECT id, username, password_hash, nombre_completo, email, dos_factores_activo, estado
        FROM usuarios
        WHERE username = p_username AND estado = 'A';

    ELSEIF p_accion = 'VERIFY_2FA' THEN
        SELECT id, username, nombre_completo, email
        FROM usuarios
        WHERE id = p_id AND dos_factores_secret = p_dos_factores_secret AND estado = 'A';

    ELSEIF p_accion = 'CONSULTAR' THEN
        IF p_id IS NOT NULL AND p_id > 0 THEN
            SELECT id, username, nombre_completo, email, dos_factores_activo, dos_factores_secret, estado, created_at
            FROM usuarios WHERE id = p_id;
        ELSE
            SELECT id, username, nombre_completo, email, dos_factores_activo, dos_factores_secret, estado, created_at
            FROM usuarios ORDER BY id DESC;
        END IF;

    ELSEIF p_accion = 'INSERTAR' THEN
        INSERT INTO usuarios (username, password_hash, nombre_completo, email, dos_factores_activo, dos_factores_secret, estado)
        VALUES (p_username, p_password_hash, p_nombre_completo, p_email, COALESCE(p_dos_factores_activo, 1), COALESCE(p_dos_factores_secret, '123456'), COALESCE(p_estado, 'A'));
        SELECT LAST_INSERT_ID() AS id;

    ELSEIF p_accion = 'EDITAR' THEN
        IF p_password_hash IS NOT NULL AND p_password_hash != '' THEN
            UPDATE usuarios
            SET username = p_username,
                password_hash = p_password_hash,
                nombre_completo = p_nombre_completo,
                email = p_email,
                dos_factores_activo = p_dos_factores_activo,
                dos_factores_secret = p_dos_factores_secret,
                estado = p_estado
            WHERE id = p_id;
        ELSE
            UPDATE usuarios
            SET username = p_username,
                nombre_completo = p_nombre_completo,
                email = p_email,
                dos_factores_activo = p_dos_factores_activo,
                dos_factores_secret = p_dos_factores_secret,
                estado = p_estado
            WHERE id = p_id;
        END IF;
        SELECT p_id AS id;

    ELSEIF p_accion = 'ELIMINAR' THEN
        DELETE FROM usuarios WHERE id = p_id;
        SELECT p_id AS id;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- 2. SP: sp_empresas (Mantenimiento de Empresas)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_empresas$$
CREATE PROCEDURE sp_empresas(
    IN p_accion VARCHAR(50),
    IN p_id INT,
    IN p_ruc VARCHAR(15),
    IN p_razon_social VARCHAR(200),
    IN p_direccion VARCHAR(250),
    IN p_telefono VARCHAR(20),
    IN p_estado CHAR(1),
    IN p_usuario_id INT
)
BEGIN
    IF p_accion = 'CONSULTAR' THEN
        IF p_id IS NOT NULL AND p_id > 0 THEN
            SELECT id, ruc, razon_social, direccion, telefono, estado, created_at
            FROM empresas WHERE id = p_id;
        ELSE
            SELECT id, ruc, razon_social, direccion, telefono, estado, created_at
            FROM empresas ORDER BY id DESC;
        END IF;

    ELSEIF p_accion = 'CONSULTAR_POR_USUARIO' THEN
        SELECT e.id, e.ruc, e.razon_social, e.direccion, e.telefono, e.estado
        FROM empresas e
        INNER JOIN usuario_empresas ue ON e.id = ue.empresa_id
        WHERE ue.usuario_id = p_usuario_id AND e.estado = 'A';

    ELSEIF p_accion = 'INSERTAR' THEN
        INSERT INTO empresas (ruc, razon_social, direccion, telefono, estado)
        VALUES (p_ruc, p_razon_social, p_direccion, p_telefono, COALESCE(p_estado, 'A'));
        SELECT LAST_INSERT_ID() AS id;

    ELSEIF p_accion = 'EDITAR' THEN
        UPDATE empresas
        SET ruc = p_ruc,
            razon_social = p_razon_social,
            direccion = p_direccion,
            telefono = p_telefono,
            estado = p_estado
        WHERE id = p_id;
        SELECT p_id AS id;

    ELSEIF p_accion = 'ELIMINAR' THEN
        DELETE FROM empresas WHERE id = p_id;
        SELECT p_id AS id;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- 3. SP: sp_empresa_periodos (Mantenimiento de Empresas Años y Meses)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_empresa_periodos$$
CREATE PROCEDURE sp_empresa_periodos(
    IN p_accion VARCHAR(50),
    IN p_id INT,
    IN p_empresa_id INT,
    IN p_anio INT,
    IN p_mes INT,
    IN p_estado CHAR(1)
)
BEGIN
    IF p_accion = 'CONSULTAR' THEN
        IF p_id IS NOT NULL AND p_id > 0 THEN
            SELECT ep.id, ep.empresa_id, e.razon_social AS empresa_nombre, ep.anio, ep.mes, ep.estado, ep.created_at
            FROM empresa_periodos ep
            INNER JOIN empresas e ON ep.empresa_id = e.id
            WHERE ep.id = p_id;
        ELSE
            SELECT ep.id, ep.empresa_id, e.razon_social AS empresa_nombre, ep.anio, ep.mes, ep.estado, ep.created_at
            FROM empresa_periodos ep
            INNER JOIN empresas e ON ep.empresa_id = e.id
            ORDER BY ep.anio DESC, ep.mes DESC, ep.empresa_id ASC;
        END IF;

    ELSEIF p_accion = 'CONSULTAR_ANIOS_POR_EMPRESA' THEN
        SELECT DISTINCT anio FROM empresa_periodos
        WHERE empresa_id = p_empresa_id AND estado = 'A'
        ORDER BY anio DESC;

    ELSEIF p_accion = 'CONSULTAR_MESES_POR_EMPRESA_ANIO' THEN
        SELECT mes, estado FROM empresa_periodos
        WHERE empresa_id = p_empresa_id AND anio = p_anio
        ORDER BY mes ASC;

    ELSEIF p_accion = 'INSERTAR' THEN
        INSERT INTO empresa_periodos (empresa_id, anio, mes, estado)
        VALUES (p_empresa_id, p_anio, p_mes, COALESCE(p_estado, 'A'));
        SELECT LAST_INSERT_ID() AS id;

    ELSEIF p_accion = 'EDITAR' THEN
        UPDATE empresa_periodos
        SET empresa_id = p_empresa_id,
            anio = p_anio,
            mes = p_mes,
            estado = p_estado
        WHERE id = p_id;
        SELECT p_id AS id;

    ELSEIF p_accion = 'ELIMINAR' THEN
        DELETE FROM empresa_periodos WHERE id = p_id;
        SELECT p_id AS id;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- 4. SP: sp_modulos (Mantenimiento de Módulos)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_modulos$$
CREATE PROCEDURE sp_modulos(
    IN p_accion VARCHAR(50),
    IN p_id INT,
    IN p_codigo VARCHAR(30),
    IN p_nombre VARCHAR(100),
    IN p_icono VARCHAR(50),
    IN p_orden INT,
    IN p_estado CHAR(1)
)
BEGIN
    IF p_accion = 'CONSULTAR' THEN
        IF p_id IS NOT NULL AND p_id > 0 THEN
            SELECT id, codigo, nombre, icono, orden, estado FROM modulos WHERE id = p_id;
        ELSE
            SELECT id, codigo, nombre, icono, orden, estado FROM modulos ORDER BY orden ASC;
        END IF;

    ELSEIF p_accion = 'INSERTAR' THEN
        INSERT INTO modulos (codigo, nombre, icono, orden, estado)
        VALUES (p_codigo, p_nombre, COALESCE(p_icono, 'fas fa-folder'), COALESCE(p_orden, 0), COALESCE(p_estado, 'A'));
        SELECT LAST_INSERT_ID() AS id;

    ELSEIF p_accion = 'EDITAR' THEN
        UPDATE modulos
        SET codigo = p_codigo,
            nombre = p_nombre,
            icono = p_icono,
            orden = p_orden,
            estado = p_estado
        WHERE id = p_id;
        SELECT p_id AS id;

    ELSEIF p_accion = 'ELIMINAR' THEN
        DELETE FROM modulos WHERE id = p_id;
        SELECT p_id AS id;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- 5. SP: sp_menus (Mantenimiento de Menús)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_menus$$
CREATE PROCEDURE sp_menus(
    IN p_accion VARCHAR(50),
    IN p_id INT,
    IN p_modulo_id INT,
    IN p_nombre VARCHAR(100),
    IN p_pagina_url VARCHAR(200),
    IN p_icono VARCHAR(50),
    IN p_orden INT,
    IN p_estado CHAR(1)
)
BEGIN
    IF p_accion = 'CONSULTAR' THEN
        IF p_id IS NOT NULL AND p_id > 0 THEN
            SELECT m.id, m.modulo_id, mod.nombre AS modulo_nombre, m.nombre, m.pagina_url, m.icono, m.orden, m.estado
            FROM menus m
            INNER JOIN modulos mod ON m.modulo_id = mod.id
            WHERE m.id = p_id;
        ELSE
            SELECT m.id, m.modulo_id, mod.nombre AS modulo_nombre, m.nombre, m.pagina_url, m.icono, m.orden, m.estado
            FROM menus m
            INNER JOIN modulos mod ON m.modulo_id = mod.id
            ORDER BY mod.orden ASC, m.orden ASC;
        END IF;

    ELSEIF p_accion = 'CONSULTAR_POR_MODULO' THEN
        SELECT m.id, m.modulo_id, m.nombre, m.pagina_url, m.icono, m.orden, m.estado
        FROM menus m
        WHERE m.modulo_id = p_modulo_id AND m.estado = 'A'
        ORDER BY m.orden ASC;

    ELSEIF p_accion = 'INSERTAR' THEN
        INSERT INTO menus (modulo_id, nombre, pagina_url, icono, orden, estado)
        VALUES (p_modulo_id, p_nombre, p_pagina_url, COALESCE(p_icono, 'fas fa-file-alt'), COALESCE(p_orden, 0), COALESCE(p_estado, 'A'));
        SELECT LAST_INSERT_ID() AS id;

    ELSEIF p_accion = 'EDITAR' THEN
        UPDATE menus
        SET modulo_id = p_modulo_id,
            nombre = p_nombre,
            pagina_url = p_pagina_url,
            icono = p_icono,
            orden = p_orden,
            estado = p_estado
        WHERE id = p_id;
        SELECT p_id AS id;

    ELSEIF p_accion = 'ELIMINAR' THEN
        DELETE FROM menus WHERE id = p_id;
        SELECT p_id AS id;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- 6. SP: sp_usuario_permisos (Mantenimiento de Usuarios y Menus de cada Modulo)
-- & Dynamic Sidebar Menu Tree Builder
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_usuario_permisos$$
CREATE PROCEDURE sp_usuario_permisos(
    IN p_accion VARCHAR(50),
    IN p_id INT,
    IN p_usuario_id INT,
    IN p_modulo_id INT,
    IN p_menu_id INT,
    IN p_acceso TINYINT
)
BEGIN
    IF p_accion = 'CONSULTAR_MENU_TREE' THEN
        -- Construye el árbol dinámico de menú para el usuario logueado
        SELECT
            mod.id AS modulo_id,
            mod.codigo AS modulo_codigo,
            mod.nombre AS modulo_nombre,
            mod.icono AS modulo_icono,
            m.id AS menu_id,
            m.nombre AS menu_nombre,
            m.pagina_url AS menu_pagina_url,
            m.icono AS menu_icono
        FROM usuario_permisos up
        INNER JOIN modulos mod ON up.modulo_id = mod.id
        INNER JOIN menus m ON up.menu_id = m.id
        WHERE up.usuario_id = p_usuario_id
          AND up.acceso = 1
          AND mod.estado = 'A'
          AND m.estado = 'A'
        ORDER BY mod.orden ASC, m.orden ASC;

    ELSEIF p_accion = 'CONSULTAR_PERMISOS_USUARIO' THEN
        SELECT up.id, up.usuario_id, up.modulo_id, up.menu_id, up.acceso,
               mod.nombre AS modulo_nombre, m.nombre AS menu_nombre
        FROM usuario_permisos up
        INNER JOIN modulos mod ON up.modulo_id = mod.id
        INNER JOIN menus m ON up.menu_id = m.id
        WHERE up.usuario_id = p_usuario_id;

    ELSEIF p_accion = 'ASIGNAR_PERMISO' THEN
        INSERT INTO usuario_permisos (usuario_id, modulo_id, menu_id, acceso)
        VALUES (p_usuario_id, p_modulo_id, p_menu_id, COALESCE(p_acceso, 1))
        ON DUPLICATE KEY UPDATE acceso = COALESCE(p_acceso, 1);
        SELECT LAST_INSERT_ID() AS id;

    ELSEIF p_accion = 'REVOCAR_TODOS_USUARIO' THEN
        DELETE FROM usuario_permisos WHERE usuario_id = p_usuario_id;
        SELECT p_usuario_id AS id;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- 7. SP: sp_usuario_empresas (Mantenimiento Usuario - Empresa)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_usuario_empresas$$
CREATE PROCEDURE sp_usuario_empresas(
    IN p_accion VARCHAR(50),
    IN p_usuario_id INT,
    IN p_empresa_id INT
)
BEGIN
    IF p_accion = 'CONSULTAR' THEN
        SELECT ue.id, ue.usuario_id, u.username, u.nombre_completo, ue.empresa_id, e.razon_social
        FROM usuario_empresas ue
        INNER JOIN usuarios u ON ue.usuario_id = u.id
        INNER JOIN empresas e ON ue.empresa_id = e.id
        WHERE (p_usuario_id IS NULL OR ue.usuario_id = p_usuario_id);

    ELSEIF p_accion = 'ASIGNAR' THEN
        INSERT INTO usuario_empresas (usuario_id, empresa_id)
        VALUES (p_usuario_id, p_empresa_id)
        ON DUPLICATE KEY UPDATE empresa_id = p_empresa_id;
        SELECT LAST_INSERT_ID() AS id;

    ELSEIF p_accion = 'ELIMINAR' THEN
        DELETE FROM usuario_empresas WHERE usuario_id = p_usuario_id AND empresa_id = p_empresa_id;
        SELECT p_usuario_id AS id;

    ELSEIF p_accion = 'ELIMINAR_TODAS_DE_USUARIO' THEN
        DELETE FROM usuario_empresas WHERE usuario_id = p_usuario_id;
        SELECT p_usuario_id AS id;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- 8. SP: sp_configuracion (Personalización Framework)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_configuracion$$
CREATE PROCEDURE sp_configuracion(
    IN p_accion VARCHAR(50),
    IN p_tema_color VARCHAR(50),
    IN p_nombre_sistema VARCHAR(100)
)
BEGIN
    IF p_accion = 'CONSULTAR' THEN
        SELECT id, tema_color, nombre_sistema, updated_at
        FROM configuracion_framework
        WHERE id = 1;

    ELSEIF p_accion = 'ACTUALIZAR' THEN
        UPDATE configuracion_framework
        SET tema_color = COALESCE(p_tema_color, tema_color),
            nombre_sistema = COALESCE(p_nombre_sistema, nombre_sistema)
        WHERE id = 1;
        SELECT 1 AS id;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- 9. SP: sp_clientes (Mantenimiento de Clientes por Módulo / Página)
-- Demonstrating custom actions (e.g., CONSULTAR CLIENTES DE BAJA)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_clientes$$
CREATE PROCEDURE sp_clientes(
    IN p_accion VARCHAR(50),
    IN p_id INT,
    IN p_empresa_id INT,
    IN p_codigo VARCHAR(20),
    IN p_tipo_entidad VARCHAR(20),
    IN p_razon_social VARCHAR(200),
    IN p_direccion VARCHAR(250),
    IN p_ruc_dni VARCHAR(20),
    IN p_representante VARCHAR(100),
    IN p_tipo_doc VARCHAR(10),
    IN p_estado CHAR(1)
)
BEGIN
    IF p_accion = 'CONSULTAR' THEN
        IF p_id IS NOT NULL AND p_id > 0 THEN
            SELECT c.*, e.razon_social AS empresa_nombre
            FROM clientes c
            LEFT JOIN empresas e ON c.empresa_id = e.id
            WHERE c.id = p_id;
        ELSE
            SELECT c.*, e.razon_social AS empresa_nombre
            FROM clientes c
            LEFT JOIN empresas e ON c.empresa_id = e.id
            WHERE (p_empresa_id IS NULL OR c.empresa_id = p_empresa_id) AND c.estado = 'A'
            ORDER BY c.id DESC;
        END IF;

    ELSEIF p_accion = 'CONSULTAR_CLIENTES_DE_BAJA' THEN
        -- Acción personalizada dentro del mismo SP para clientes inactivados/de baja
        SELECT c.*, e.razon_social AS empresa_nombre
        FROM clientes c
        LEFT JOIN empresas e ON c.empresa_id = e.id
        WHERE (p_empresa_id IS NULL OR c.empresa_id = p_empresa_id) AND c.estado = 'B'
        ORDER BY c.id DESC;

    ELSEIF p_accion = 'INSERTAR' THEN
        INSERT INTO clientes (empresa_id, codigo, tipo_entidad, razon_social, direccion, ruc_dni, representante, tipo_doc, estado)
        VALUES (p_empresa_id, p_codigo, COALESCE(p_tipo_entidad, 'CLIENTE'), p_razon_social, p_direccion, p_ruc_dni, p_representante, COALESCE(p_tipo_doc, 'RUC'), COALESCE(p_estado, 'A'));
        SELECT LAST_INSERT_ID() AS id;

    ELSEIF p_accion = 'EDITAR' THEN
        UPDATE clientes
        SET empresa_id = p_empresa_id,
            codigo = p_codigo,
            tipo_entidad = p_tipo_entidad,
            razon_social = p_razon_social,
            direccion = p_direccion,
            ruc_dni = p_ruc_dni,
            representante = p_representante,
            tipo_doc = p_tipo_doc,
            estado = p_estado
        WHERE id = p_id;
        SELECT p_id AS id;

    ELSEIF p_accion = 'ELIMINAR' THEN
        DELETE FROM clientes WHERE id = p_id;
        SELECT p_id AS id;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- 10. SP: spClientes (Mantenimiento de Entidad / Clientes según ejemplo)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS spClientes$$
CREATE PROCEDURE spClientes(
    IN p_Accion VARCHAR(20),
    IN p_Emp_cCodigo CHAR(3),
    IN p_Ent_cCodEntidad VARCHAR(20),
    IN p_Ten_cTipoEntidad CHAR(1),
    IN p_Ent_cPersona VARCHAR(120),
    IN p_Ent_cDireccion VARCHAR(120),
    IN p_Ent_nRuc VARCHAR(15),
    IN p_Ent_cRepresentante VARCHAR(80),
    IN p_Ent_cTipoDoc CHAR(3),
    IN p_Ent_cFlagPersona CHAR(1),
    IN p_Ent_cEstadoEntidad CHAR(1),
    IN p_Ent_cEstado CHAR(1),
    IN p_Ent_cDeleted CHAR(1),
    IN p_Ent_cUserCrea VARCHAR(20),
    IN p_Ent_cUserModifica VARCHAR(20),
    IN p_Ent_ValidaLC CHAR(1)
)
BEGIN
    IF p_Accion = 'INSERTAR' THEN
        INSERT INTO CNM_ENTIDAD (
            Emp_cCodigo, Ent_cCodEntidad, Ten_cTipoEntidad, Ent_cPersona,
            Ent_cDireccion, Ent_nRuc, Ent_cRepresentante, Ent_cTipoDoc,
            Ent_cFlagPersona, Ent_cEstadoEntidad, Ent_cEstado, Ent_cDeleted,
            Ent_cUserCrea, Ent_dFechaCrea, Ent_cEquipoUser, Ent_ValidaLC
        ) VALUES (
            p_Emp_cCodigo, p_Ent_cCodEntidad, p_Ten_cTipoEntidad, p_Ent_cPersona,
            p_Ent_cDireccion, p_Ent_nRuc, p_Ent_cRepresentante, p_Ent_cTipoDoc,
            p_Ent_cFlagPersona, '1', 'A', p_Ent_cDeleted,
            p_Ent_cUserCrea, NOW(), USER(), p_Ent_ValidaLC
        );

    ELSEIF p_Accion = 'VALIDALC' THEN
        SELECT COALESCE(CE.Ent_ValidaLC, '0') AS Ent_ValidaLC
        FROM CNM_ENTIDAD CE
        WHERE CE.Emp_cCodigo = p_Emp_cCodigo
          AND CE.Ten_cTipoEntidad = p_Ten_cTipoEntidad
          AND CE.Ent_cCodEntidad = p_Ent_cCodEntidad;

    ELSEIF p_Accion = 'EDITAR' THEN
        UPDATE CNM_ENTIDAD
        SET Ent_cPersona = p_Ent_cPersona,
            Ent_cDireccion = p_Ent_cDireccion,
            Ent_nRuc = p_Ent_nRuc,
            Ent_cRepresentante = p_Ent_cRepresentante,
            Ent_cTipoDoc = p_Ent_cTipoDoc,
            Ent_cFlagPersona = p_Ent_cFlagPersona,
            Ent_cEstadoEntidad = '1',
            Ent_cEstado = 'A',
            Ent_cDeleted = p_Ent_cDeleted,
            Ent_cUserModifica = p_Ent_cUserModifica,
            Ent_dFechaModifica = NOW(),
            Ent_cEquipoUser = USER(),
            Ent_ValidaLC = p_Ent_ValidaLC
        WHERE Emp_cCodigo = p_Emp_cCodigo
          AND Ten_cTipoEntidad = p_Ten_cTipoEntidad
          AND Ent_cCodEntidad = p_Ent_cCodEntidad;

    ELSEIF p_Accion = 'ELIMINAR' THEN
        DELETE FROM CNM_ENTIDAD
        WHERE Emp_cCodigo = p_Emp_cCodigo
          AND Ten_cTipoEntidad = p_Ten_cTipoEntidad
          AND Ent_cCodEntidad = p_Ent_cCodEntidad;

    ELSEIF p_Accion = 'BUSCARREGISTRO' THEN
        SELECT * FROM CNM_ENTIDAD
        WHERE Emp_cCodigo = p_Emp_cCodigo
          AND Ten_cTipoEntidad = p_Ten_cTipoEntidad
          AND Ent_cCodEntidad = p_Ent_cCodEntidad;

    ELSEIF p_Accion = 'BUSCARTODOS' THEN
        SELECT Ent_cCodEntidad, Ent_cPersona
        FROM CNM_ENTIDAD
        WHERE Emp_cCodigo = p_Emp_cCodigo
          AND Ten_cTipoEntidad = p_Ten_cTipoEntidad;
    END IF;
END$$

DELIMITER ;

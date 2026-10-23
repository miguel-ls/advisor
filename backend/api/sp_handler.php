<?php
// backend/api/sp_handler.php - Generic API Router for Stored Procedures with Bearer Token

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// Validate Bearer Token
$userClaims = AuthMiddleware::authenticate();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$request = array_merge($_GET, $_POST, $input);

$sp = $request['sp'] ?? '';
$accion = strtoupper(trim($request['accion'] ?? 'CONSULTAR'));

if (empty($sp)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "El parámetro 'sp' (Procedimiento Almacenado) es obligatorio"]);
    exit();
}

$db = Database::getConnection();
$driver = Database::getDriver();

// Mapping of allowed Stored Procedures and their parameters
$spMap = [
    'sp_usuarios' => [
        'params' => ['p_accion', 'p_id', 'p_username', 'p_password_hash', 'p_nombre_completo', 'p_email', 'p_dos_factores_activo', 'p_dos_factores_secret', 'p_estado'],
        'values' => [
            'p_accion' => $accion,
            'p_id' => !empty($request['id']) ? intval($request['id']) : null,
            'p_username' => $request['username'] ?? null,
            'p_password_hash' => !empty($request['password']) ? password_hash($request['password'], PASSWORD_BCRYPT) : null,
            'p_nombre_completo' => $request['nombre_completo'] ?? null,
            'p_email' => $request['email'] ?? null,
            'p_dos_factores_activo' => isset($request['dos_factores_activo']) ? intval($request['dos_factores_activo']) : 1,
            'p_dos_factores_secret' => $request['dos_factores_secret'] ?? '123456',
            'p_estado' => $request['estado'] ?? 'A'
        ]
    ],
    'sp_empresas' => [
        'params' => ['p_accion', 'p_id', 'p_ruc', 'p_razon_social', 'p_direccion', 'p_telefono', 'p_estado', 'p_usuario_id'],
        'values' => [
            'p_accion' => $accion,
            'p_id' => !empty($request['id']) ? intval($request['id']) : null,
            'p_ruc' => $request['ruc'] ?? null,
            'p_razon_social' => $request['razon_social'] ?? null,
            'p_direccion' => $request['direccion'] ?? null,
            'p_telefono' => $request['telefono'] ?? null,
            'p_estado' => $request['estado'] ?? 'A',
            'p_usuario_id' => !empty($request['usuario_id']) ? intval($request['usuario_id']) : $userClaims['user_id']
        ]
    ],
    'sp_empresa_periodos' => [
        'params' => ['p_accion', 'p_id', 'p_empresa_id', 'p_anio', 'p_mes', 'p_estado'],
        'values' => [
            'p_accion' => $accion,
            'p_id' => !empty($request['id']) ? intval($request['id']) : null,
            'p_empresa_id' => !empty($request['empresa_id']) ? intval($request['empresa_id']) : null,
            'p_anio' => !empty($request['anio']) ? intval($request['anio']) : null,
            'p_mes' => !empty($request['mes']) ? intval($request['mes']) : null,
            'p_estado' => $request['estado'] ?? 'A'
        ]
    ],
    'sp_modulos' => [
        'params' => ['p_accion', 'p_id', 'p_codigo', 'p_nombre', 'p_icono', 'p_orden', 'p_estado'],
        'values' => [
            'p_accion' => $accion,
            'p_id' => !empty($request['id']) ? intval($request['id']) : null,
            'p_codigo' => $request['codigo'] ?? null,
            'p_nombre' => $request['nombre'] ?? null,
            'p_icono' => $request['icono'] ?? 'fas fa-folder',
            'p_orden' => isset($request['orden']) ? intval($request['orden']) : 0,
            'p_estado' => $request['estado'] ?? 'A'
        ]
    ],
    'sp_menus' => [
        'params' => ['p_accion', 'p_id', 'p_modulo_id', 'p_nombre', 'p_pagina_url', 'p_icono', 'p_orden', 'p_estado'],
        'values' => [
            'p_accion' => $accion,
            'p_id' => !empty($request['id']) ? intval($request['id']) : null,
            'p_modulo_id' => !empty($request['modulo_id']) ? intval($request['modulo_id']) : null,
            'p_nombre' => $request['nombre'] ?? null,
            'p_pagina_url' => $request['pagina_url'] ?? null,
            'p_icono' => $request['icono'] ?? 'fas fa-file-alt',
            'p_orden' => isset($request['orden']) ? intval($request['orden']) : 0,
            'p_estado' => $request['estado'] ?? 'A'
        ]
    ],
    'sp_usuario_permisos' => [
        'params' => ['p_accion', 'p_id', 'p_usuario_id', 'p_modulo_id', 'p_menu_id', 'p_acceso'],
        'values' => [
            'p_accion' => $accion,
            'p_id' => !empty($request['id']) ? intval($request['id']) : null,
            'p_usuario_id' => !empty($request['usuario_id']) ? intval($request['usuario_id']) : $userClaims['user_id'],
            'p_modulo_id' => !empty($request['modulo_id']) ? intval($request['modulo_id']) : null,
            'p_menu_id' => !empty($request['menu_id']) ? intval($request['menu_id']) : null,
            'p_acceso' => isset($request['acceso']) ? intval($request['acceso']) : 1
        ]
    ],
    'sp_usuario_empresas' => [
        'params' => ['p_accion', 'p_usuario_id', 'p_empresa_id'],
        'values' => [
            'p_accion' => $accion,
            'p_usuario_id' => !empty($request['usuario_id']) ? intval($request['usuario_id']) : $userClaims['user_id'],
            'p_empresa_id' => !empty($request['empresa_id']) ? intval($request['empresa_id']) : null
        ]
    ],
    'sp_configuracion' => [
        'params' => ['p_accion', 'p_tema_color', 'p_nombre_sistema'],
        'values' => [
            'p_accion' => $accion,
            'p_tema_color' => $request['tema_color'] ?? null,
            'p_nombre_sistema' => $request['nombre_sistema'] ?? null
        ]
    ],
    'sp_clientes' => [
        'params' => ['p_accion', 'p_id', 'p_empresa_id', 'p_codigo', 'p_tipo_entidad', 'p_razon_social', 'p_direccion', 'p_ruc_dni', 'p_representante', 'p_tipo_doc', 'p_estado'],
        'values' => [
            'p_accion' => $accion,
            'p_id' => !empty($request['id']) ? intval($request['id']) : null,
            'p_empresa_id' => !empty($request['empresa_id']) ? intval($request['empresa_id']) : null,
            'p_codigo' => $request['codigo'] ?? null,
            'p_tipo_entidad' => $request['tipo_entidad'] ?? 'CLIENTE',
            'p_razon_social' => $request['razon_social'] ?? null,
            'p_direccion' => $request['direccion'] ?? null,
            'p_ruc_dni' => $request['ruc_dni'] ?? null,
            'p_representante' => $request['representante'] ?? null,
            'p_tipo_doc' => $request['tipo_doc'] ?? 'RUC',
            'p_estado' => $request['estado'] ?? 'A'
        ]
    ],
    'spClientes' => [
        'params' => [
            'p_Accion', 'p_Emp_cCodigo', 'p_Ent_cCodEntidad', 'p_Ten_cTipoEntidad',
            'p_Ent_cPersona', 'p_Ent_cDireccion', 'p_Ent_nRuc', 'p_Ent_cRepresentante',
            'p_Ent_cTipoDoc', 'p_Ent_cFlagPersona', 'p_Ent_cEstadoEntidad', 'p_Ent_cEstado',
            'p_Ent_cDeleted', 'p_Ent_cUserCrea', 'p_Ent_cUserModifica', 'p_Ent_ValidaLC'
        ],
        'values' => [
            'p_Accion' => $accion,
            'p_Emp_cCodigo' => $request['Emp_cCodigo'] ?? $request['emp_codigo'] ?? '001',
            'p_Ent_cCodEntidad' => $request['Ent_cCodEntidad'] ?? $request['cod_entidad'] ?? '',
            'p_Ten_cTipoEntidad' => $request['Ten_cTipoEntidad'] ?? $request['tipo_entidad'] ?? 'C',
            'p_Ent_cPersona' => $request['Ent_cPersona'] ?? $request['persona'] ?? '',
            'p_Ent_cDireccion' => $request['Ent_cDireccion'] ?? $request['direccion'] ?? '',
            'p_Ent_nRuc' => $request['Ent_nRuc'] ?? $request['ruc'] ?? '',
            'p_Ent_cRepresentante' => $request['Ent_cRepresentante'] ?? $request['representante'] ?? '',
            'p_Ent_cTipoDoc' => $request['Ent_cTipoDoc'] ?? $request['tipo_doc'] ?? 'RUC',
            'p_Ent_cFlagPersona' => $request['Ent_cFlagPersona'] ?? $request['flag_persona'] ?? 'J',
            'p_Ent_cEstadoEntidad' => $request['Ent_cEstadoEntidad'] ?? '1',
            'p_Ent_cEstado' => $request['Ent_cEstado'] ?? 'A',
            'p_Ent_cDeleted' => $request['Ent_cDeleted'] ?? '',
            'p_Ent_cUserCrea' => $request['Ent_cUserCrea'] ?? ($userClaims['username'] ?? 'admin'),
            'p_Ent_cUserModifica' => $request['Ent_cUserModifica'] ?? ($userClaims['username'] ?? 'admin'),
            'p_Ent_ValidaLC' => $request['Ent_ValidaLC'] ?? '0'
        ]
    ]
];

if (!array_key_exists($sp, $spMap)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Procedimiento almacenado no permitido o no registrado: " . $sp]);
    exit();
}

try {
    $config = $spMap[$sp];
    $paramNames = $config['params'];
    $paramValues = $config['values'];

    if ($driver === 'mysql') {
        $placeholders = implode(', ', array_map(function($p) { return ':' . $p; }, $paramNames));
        $sql = "CALL {$sp}({$placeholders})";
        $stmt = $db->prepare($sql);
        foreach ($paramNames as $pName) {
            $stmt->bindValue(':' . $pName, $paramValues[$pName]);
        }
        $stmt->execute();
        $results = [];
        do {
            $data = $stmt->fetchAll();
            if ($data) {
                $results = array_merge($results, $data);
            }
        } while ($stmt->nextRowset());
    } else {
        // Fallback SQLite query handler matching Stored Procedure logic for testing
        $results = runSqliteProcedureFallback($db, $sp, $paramValues);
    }

    echo json_encode([
        "status" => "success",
        "accion" => $accion,
        "sp" => $sp,
        "data" => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Error al ejecutar el procedimiento almacenado ({$sp}): " . $e->getMessage()
    ]);
}

function runSqliteProcedureFallback($db, $sp, $v) {
    $accion = $v['p_accion'];
    $results = [];

    if ($sp === 'sp_usuarios') {
        if ($accion === 'CONSULTAR') {
            if ($v['p_id']) {
                $stmt = $db->prepare("SELECT id, username, nombre_completo, email, dos_factores_activo, dos_factores_secret, estado, created_at FROM usuarios WHERE id = ?");
                $stmt->execute([$v['p_id']]);
            } else {
                $stmt = $db->prepare("SELECT id, username, nombre_completo, email, dos_factores_activo, dos_factores_secret, estado, created_at FROM usuarios ORDER BY id DESC");
                $stmt->execute();
            }
            $results = $stmt->fetchAll();
        } elseif ($accion === 'INSERTAR') {
            $pass = !empty($v['p_password_hash']) ? $v['p_password_hash'] : password_hash('123456', PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO usuarios (username, password_hash, nombre_completo, email, dos_factores_activo, dos_factores_secret, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$v['p_username'], $pass, $v['p_nombre_completo'], $v['p_email'], $v['p_dos_factores_activo'], $v['p_dos_factores_secret'], $v['p_estado']]);
            $results = [["id" => $db->lastInsertId()]];
        } elseif ($accion === 'EDITAR') {
            if ($v['p_password_hash']) {
                $stmt = $db->prepare("UPDATE usuarios SET username=?, password_hash=?, nombre_completo=?, email=?, dos_factores_activo=?, dos_factores_secret=?, estado=? WHERE id=?");
                $stmt->execute([$v['p_username'], $v['p_password_hash'], $v['p_nombre_completo'], $v['p_email'], $v['p_dos_factores_activo'], $v['p_dos_factores_secret'], $v['p_estado'], $v['p_id']]);
            } else {
                $stmt = $db->prepare("UPDATE usuarios SET username=?, nombre_completo=?, email=?, dos_factores_activo=?, dos_factores_secret=?, estado=? WHERE id=?");
                $stmt->execute([$v['p_username'], $v['p_nombre_completo'], $v['p_email'], $v['p_dos_factores_activo'], $v['p_dos_factores_secret'], $v['p_estado'], $v['p_id']]);
            }
            $results = [["id" => $v['p_id']]];
        } elseif ($accion === 'ELIMINAR') {
            $stmt = $db->prepare("DELETE FROM usuarios WHERE id=?");
            $stmt->execute([$v['p_id']]);
            $results = [["id" => $v['p_id']]];
        }
    } elseif ($sp === 'sp_empresas') {
        if ($accion === 'CONSULTAR') {
            if ($v['p_id']) {
                $stmt = $db->prepare("SELECT id, ruc, razon_social, direccion, telefono, estado, created_at FROM empresas WHERE id=?");
                $stmt->execute([$v['p_id']]);
            } else {
                $stmt = $db->prepare("SELECT id, ruc, razon_social, direccion, telefono, estado, created_at FROM empresas ORDER BY id DESC");
                $stmt->execute();
            }
            $results = $stmt->fetchAll();
        } elseif ($accion === 'CONSULTAR_POR_USUARIO') {
            $stmt = $db->prepare("SELECT e.id, e.ruc, e.razon_social, e.direccion, e.telefono, e.estado FROM empresas e INNER JOIN usuario_empresas ue ON e.id = ue.empresa_id WHERE ue.usuario_id = ? AND e.estado = 'A'");
            $stmt->execute([$v['p_usuario_id']]);
            $results = $stmt->fetchAll();
        } elseif ($accion === 'INSERTAR') {
            $stmt = $db->prepare("INSERT INTO empresas (ruc, razon_social, direccion, telefono, estado) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$v['p_ruc'], $v['p_razon_social'], $v['p_direccion'], $v['p_telefono'], $v['p_estado']]);
            $results = [["id" => $db->lastInsertId()]];
        } elseif ($accion === 'EDITAR') {
            $stmt = $db->prepare("UPDATE empresas SET ruc=?, razon_social=?, direccion=?, telefono=?, estado=? WHERE id=?");
            $stmt->execute([$v['p_ruc'], $v['p_razon_social'], $v['p_direccion'], $v['p_telefono'], $v['p_estado'], $v['p_id']]);
            $results = [["id" => $v['p_id']]];
        } elseif ($accion === 'ELIMINAR') {
            $stmt = $db->prepare("DELETE FROM empresas WHERE id=?");
            $stmt->execute([$v['p_id']]);
            $results = [["id" => $v['p_id']]];
        }
    } elseif ($sp === 'sp_empresa_periodos') {
        if ($accion === 'CONSULTAR') {
            $stmt = $db->prepare("SELECT ep.id, ep.empresa_id, e.razon_social AS empresa_nombre, ep.anio, ep.mes, ep.estado, ep.created_at FROM empresa_periodos ep INNER JOIN empresas e ON ep.empresa_id = e.id ORDER BY ep.anio DESC, ep.mes DESC");
            $stmt->execute();
            $results = $stmt->fetchAll();
        } elseif ($accion === 'CONSULTAR_ANIOS_POR_EMPRESA') {
            $stmt = $db->prepare("SELECT DISTINCT anio FROM empresa_periodos WHERE empresa_id = ? AND estado = 'A' ORDER BY anio DESC");
            $stmt->execute([$v['p_empresa_id']]);
            $results = $stmt->fetchAll();
        } elseif ($accion === 'CONSULTAR_MESES_POR_EMPRESA_ANIO') {
            $stmt = $db->prepare("SELECT mes, estado FROM empresa_periodos WHERE empresa_id = ? AND anio = ? ORDER BY mes ASC");
            $stmt->execute([$v['p_empresa_id'], $v['p_anio']]);
            $results = $stmt->fetchAll();
        } elseif ($accion === 'INSERTAR') {
            $stmt = $db->prepare("INSERT INTO empresa_periodos (empresa_id, anio, mes, estado) VALUES (?, ?, ?, ?)");
            $stmt->execute([$v['p_empresa_id'], $v['p_anio'], $v['p_mes'], $v['p_estado']]);
            $results = [["id" => $db->lastInsertId()]];
        } elseif ($accion === 'EDITAR') {
            $stmt = $db->prepare("UPDATE empresa_periodos SET empresa_id=?, anio=?, mes=?, estado=? WHERE id=?");
            $stmt->execute([$v['p_empresa_id'], $v['p_anio'], $v['p_mes'], $v['p_estado'], $v['p_id']]);
            $results = [["id" => $v['p_id']]];
        } elseif ($accion === 'ELIMINAR') {
            $stmt = $db->prepare("DELETE FROM empresa_periodos WHERE id=?");
            $stmt->execute([$v['p_id']]);
            $results = [["id" => $v['p_id']]];
        }
    } elseif ($sp === 'sp_modulos') {
        if ($accion === 'CONSULTAR') {
            $stmt = $db->prepare("SELECT id, codigo, nombre, icono, orden, estado FROM modulos ORDER BY orden ASC");
            $stmt->execute();
            $results = $stmt->fetchAll();
        } elseif ($accion === 'INSERTAR') {
            $stmt = $db->prepare("INSERT INTO modulos (codigo, nombre, icono, orden, estado) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$v['p_codigo'], $v['p_nombre'], $v['p_icono'], $v['p_orden'], $v['p_estado']]);
            $results = [["id" => $db->lastInsertId()]];
        } elseif ($accion === 'EDITAR') {
            $stmt = $db->prepare("UPDATE modulos SET codigo=?, nombre=?, icono=?, orden=?, estado=? WHERE id=?");
            $stmt->execute([$v['p_codigo'], $v['p_nombre'], $v['p_icono'], $v['p_orden'], $v['p_estado'], $v['p_id']]);
            $results = [["id" => $v['p_id']]];
        } elseif ($accion === 'ELIMINAR') {
            $stmt = $db->prepare("DELETE FROM modulos WHERE id=?");
            $stmt->execute([$v['p_id']]);
            $results = [["id" => $v['p_id']]];
        }
    } elseif ($sp === 'sp_menus') {
        if ($accion === 'CONSULTAR') {
            $stmt = $db->prepare("SELECT m.id, m.modulo_id, mod.nombre AS modulo_nombre, m.nombre, m.pagina_url, m.icono, m.orden, m.estado FROM menus m INNER JOIN modulos mod ON m.modulo_id = mod.id ORDER BY mod.orden ASC, m.orden ASC");
            $stmt->execute();
            $results = $stmt->fetchAll();
        } elseif ($accion === 'INSERTAR') {
            $stmt = $db->prepare("INSERT INTO menus (modulo_id, nombre, pagina_url, icono, orden, estado) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$v['p_modulo_id'], $v['p_nombre'], $v['p_pagina_url'], $v['p_icono'], $v['p_orden'], $v['p_estado']]);
            $results = [["id" => $db->lastInsertId()]];
        } elseif ($accion === 'EDITAR') {
            $stmt = $db->prepare("UPDATE menus SET modulo_id=?, nombre=?, pagina_url=?, icono=?, orden=?, estado=? WHERE id=?");
            $stmt->execute([$v['p_modulo_id'], $v['p_nombre'], $v['p_pagina_url'], $v['p_icono'], $v['p_orden'], $v['p_estado'], $v['p_id']]);
            $results = [["id" => $v['p_id']]];
        } elseif ($accion === 'ELIMINAR') {
            $stmt = $db->prepare("DELETE FROM menus WHERE id=?");
            $stmt->execute([$v['p_id']]);
            $results = [["id" => $v['p_id']]];
        }
    } elseif ($sp === 'sp_usuario_permisos') {
        if ($accion === 'CONSULTAR_MENU_TREE') {
            $stmt = $db->prepare("SELECT mod.id AS modulo_id, mod.codigo AS modulo_codigo, mod.nombre AS modulo_nombre, mod.icono AS modulo_icono, m.id AS menu_id, m.nombre AS menu_nombre, m.pagina_url AS menu_pagina_url, m.icono AS menu_icono FROM usuario_permisos up INNER JOIN modulos mod ON up.modulo_id = mod.id INNER JOIN menus m ON up.menu_id = m.id WHERE up.usuario_id = ? AND up.acceso = 1 AND mod.estado = 'A' AND m.estado = 'A' ORDER BY mod.orden ASC, m.orden ASC");
            $stmt->execute([$v['p_usuario_id']]);
            $results = $stmt->fetchAll();
        } elseif ($accion === 'CONSULTAR_PERMISOS_USUARIO') {
            $stmt = $db->prepare("SELECT up.id, up.usuario_id, up.modulo_id, up.menu_id, up.acceso, mod.nombre AS modulo_nombre, m.nombre AS menu_nombre FROM usuario_permisos up INNER JOIN modulos mod ON up.modulo_id = mod.id INNER JOIN menus m ON up.menu_id = m.id WHERE up.usuario_id = ?");
            $stmt->execute([$v['p_usuario_id']]);
            $results = $stmt->fetchAll();
        } elseif ($accion === 'ASIGNAR_PERMISO') {
            $stmtDel = $db->prepare("DELETE FROM usuario_permisos WHERE usuario_id=? AND modulo_id=? AND menu_id=?");
            $stmtDel->execute([$v['p_usuario_id'], $v['p_modulo_id'], $v['p_menu_id']]);
            $stmt = $db->prepare("INSERT INTO usuario_permisos (usuario_id, modulo_id, menu_id, acceso) VALUES (?, ?, ?, ?)");
            $stmt->execute([$v['p_usuario_id'], $v['p_modulo_id'], $v['p_menu_id'], $v['p_acceso']]);
            $results = [["id" => $db->lastInsertId()]];
        } elseif ($accion === 'REVOCAR_TODOS_USUARIO') {
            $stmt = $db->prepare("DELETE FROM usuario_permisos WHERE usuario_id = ?");
            $stmt->execute([$v['p_usuario_id']]);
            $results = [["id" => $v['p_usuario_id']]];
        }
    } elseif ($sp === 'sp_usuario_empresas') {
        if ($accion === 'CONSULTAR') {
            $stmt = $db->prepare("SELECT ue.id, ue.usuario_id, u.username, u.nombre_completo, ue.empresa_id, e.razon_social FROM usuario_empresas ue INNER JOIN usuarios u ON ue.usuario_id = u.id INNER JOIN empresas e ON ue.empresa_id = e.id WHERE (? IS NULL OR ue.usuario_id = ?)");
            $stmt->execute([$v['p_usuario_id'], $v['p_usuario_id']]);
            $results = $stmt->fetchAll();
        } elseif ($accion === 'ASIGNAR') {
            $stmtDel = $db->prepare("DELETE FROM usuario_empresas WHERE usuario_id=? AND empresa_id=?");
            $stmtDel->execute([$v['p_usuario_id'], $v['p_empresa_id']]);
            $stmt = $db->prepare("INSERT INTO usuario_empresas (usuario_id, empresa_id) VALUES (?, ?)");
            $stmt->execute([$v['p_usuario_id'], $v['p_empresa_id']]);
            $results = [["id" => $db->lastInsertId()]];
        } elseif ($accion === 'ELIMINAR_TODAS_DE_USUARIO') {
            $stmt = $db->prepare("DELETE FROM usuario_empresas WHERE usuario_id = ?");
            $stmt->execute([$v['p_usuario_id']]);
            $results = [["id" => $v['p_usuario_id']]];
        }
    } elseif ($sp === 'sp_configuracion') {
        if ($accion === 'CONSULTAR') {
            $stmt = $db->query("SELECT id, tema_color, nombre_sistema, updated_at FROM configuracion_framework WHERE id = 1");
            $results = $stmt->fetchAll();
        } elseif ($accion === 'ACTUALIZAR') {
            $stmt = $db->prepare("UPDATE configuracion_framework SET tema_color=?, nombre_sistema=? WHERE id=1");
            $stmt->execute([$v['p_tema_color'], $v['p_nombre_sistema']]);
            $results = [["id" => 1]];
        }
    } elseif ($sp === 'sp_clientes') {
        if ($accion === 'CONSULTAR') {
            if ($v['p_id']) {
                $stmt = $db->prepare("SELECT c.*, e.razon_social AS empresa_nombre FROM clientes c LEFT JOIN empresas e ON c.empresa_id = e.id WHERE c.id = ?");
                $stmt->execute([$v['p_id']]);
            } else {
                $stmt = $db->prepare("SELECT c.*, e.razon_social AS empresa_nombre FROM clientes c LEFT JOIN empresas e ON c.empresa_id = e.id WHERE (? IS NULL OR c.empresa_id = ?) AND c.estado = 'A' ORDER BY c.id DESC");
                $stmt->execute([$v['p_empresa_id'], $v['p_empresa_id']]);
            }
            $results = $stmt->fetchAll();
        } elseif ($accion === 'CONSULTAR_CLIENTES_DE_BAJA') {
            $stmt = $db->prepare("SELECT c.*, e.razon_social AS empresa_nombre FROM clientes c LEFT JOIN empresas e ON c.empresa_id = e.id WHERE (? IS NULL OR c.empresa_id = ?) AND c.estado = 'B' ORDER BY c.id DESC");
            $stmt->execute([$v['p_empresa_id'], $v['p_empresa_id']]);
            $results = $stmt->fetchAll();
        } elseif ($accion === 'INSERTAR') {
            $stmt = $db->prepare("INSERT INTO clientes (empresa_id, codigo, tipo_entidad, razon_social, direccion, ruc_dni, representante, tipo_doc, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$v['p_empresa_id'], $v['p_codigo'], $v['p_tipo_entidad'], $v['p_razon_social'], $v['p_direccion'], $v['p_ruc_dni'], $v['p_representante'], $v['p_tipo_doc'], $v['p_estado']]);
            $results = [["id" => $db->lastInsertId()]];
        } elseif ($accion === 'EDITAR') {
            $stmt = $db->prepare("UPDATE clientes SET empresa_id=?, codigo=?, tipo_entidad=?, razon_social=?, direccion=?, ruc_dni=?, representante=?, tipo_doc=?, estado=? WHERE id=?");
            $stmt->execute([$v['p_empresa_id'], $v['p_codigo'], $v['p_tipo_entidad'], $v['p_razon_social'], $v['p_direccion'], $v['p_ruc_dni'], $v['p_representante'], $v['p_tipo_doc'], $v['p_estado'], $v['p_id']]);
            $results = [["id" => $v['p_id']]];
        } elseif ($accion === 'ELIMINAR') {
            $stmt = $db->prepare("DELETE FROM clientes WHERE id=?");
            $stmt->execute([$v['p_id']]);
            $results = [["id" => $v['p_id']]];
        }
    } elseif ($sp === 'spClientes') {
        if ($accion === 'INSERTAR') {
            $stmt = $db->prepare("INSERT INTO CNM_ENTIDAD (Emp_cCodigo, Ent_cCodEntidad, Ten_cTipoEntidad, Ent_cPersona, Ent_cDireccion, Ent_nRuc, Ent_cRepresentante, Ent_cTipoDoc, Ent_cFlagPersona, Ent_cEstadoEntidad, Ent_cEstado, Ent_cDeleted, Ent_cUserCrea, Ent_ValidaLC) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '1', 'A', ?, ?, ?)");
            $stmt->execute([$v['p_Emp_cCodigo'], $v['p_Ent_cCodEntidad'], $v['p_Ten_cTipoEntidad'], $v['p_Ent_cPersona'], $v['p_Ent_cDireccion'], $v['p_Ent_nRuc'], $v['p_Ent_cRepresentante'], $v['p_Ent_cTipoDoc'], $v['p_Ent_cFlagPersona'], $v['p_Ent_cDeleted'], $v['p_Ent_cUserCrea'], $v['p_Ent_ValidaLC']]);
            $results = [["cod_entidad" => $v['p_Ent_cCodEntidad']]];
        } elseif ($accion === 'VALIDALC') {
            $stmt = $db->prepare("SELECT COALESCE(Ent_ValidaLC, '0') AS Ent_ValidaLC FROM CNM_ENTIDAD WHERE Emp_cCodigo=? AND Ten_cTipoEntidad=? AND Ent_cCodEntidad=?");
            $stmt->execute([$v['p_Emp_cCodigo'], $v['p_Ten_cTipoEntidad'], $v['p_Ent_cCodEntidad']]);
            $results = $stmt->fetchAll();
        } elseif ($accion === 'EDITAR') {
            $stmt = $db->prepare("UPDATE CNM_ENTIDAD SET Ent_cPersona=?, Ent_cDireccion=?, Ent_nRuc=?, Ent_cRepresentante=?, Ent_cTipoDoc=?, Ent_cFlagPersona=?, Ent_cEstadoEntidad='1', Ent_cEstado='A', Ent_cDeleted=?, Ent_cUserModifica=?, Ent_ValidaLC=? WHERE Emp_cCodigo=? AND Ten_cTipoEntidad=? AND Ent_cCodEntidad=?");
            $stmt->execute([$v['p_Ent_cPersona'], $v['p_Ent_cDireccion'], $v['p_Ent_nRuc'], $v['p_Ent_cRepresentante'], $v['p_Ent_cTipoDoc'], $v['p_Ent_cFlagPersona'], $v['p_Ent_cDeleted'], $v['p_Ent_cUserModifica'], $v['p_Ent_ValidaLC'], $v['p_Emp_cCodigo'], $v['p_Ten_cTipoEntidad'], $v['p_Ent_cCodEntidad']]);
            $results = [["cod_entidad" => $v['p_Ent_cCodEntidad']]];
        } elseif ($accion === 'ELIMINAR') {
            $stmt = $db->prepare("DELETE FROM CNM_ENTIDAD WHERE Emp_cCodigo=? AND Ten_cTipoEntidad=? AND Ent_cCodEntidad=?");
            $stmt->execute([$v['p_Emp_cCodigo'], $v['p_Ten_cTipoEntidad'], $v['p_Ent_cCodEntidad']]);
            $results = [["cod_entidad" => $v['p_Ent_cCodEntidad']]];
        } elseif ($accion === 'BUSCARREGISTRO') {
            $stmt = $db->prepare("SELECT * FROM CNM_ENTIDAD WHERE Emp_cCodigo=? AND Ten_cTipoEntidad=? AND Ent_cCodEntidad=?");
            $stmt->execute([$v['p_Emp_cCodigo'], $v['p_Ten_cTipoEntidad'], $v['p_Ent_cCodEntidad']]);
            $results = $stmt->fetchAll();
        } elseif ($accion === 'BUSCARTODOS') {
            $stmt = $db->prepare("SELECT Ent_cCodEntidad, Ent_cPersona FROM CNM_ENTIDAD WHERE Emp_cCodigo=? AND Ten_cTipoEntidad=?");
            $stmt->execute([$v['p_Emp_cCodigo'], $v['p_Ten_cTipoEntidad']]);
            $results = $stmt->fetchAll();
        }
    }

    return $results;
}

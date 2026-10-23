<?php
// backend/api/auth.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? $input['action'] ?? '';

$db = Database::getConnection();
$driver = Database::getDriver();

if ($action === 'login') {
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Usuario y contraseña son requeridos"]);
        exit();
    }

    try {
        if ($driver === 'mysql') {
            // Explicitly align both sides: existing databases may have been
            // imported with MariaDB's UCA 14 collation while the PDO connection
            // (and older XAMPP installs) use utf8mb4_unicode_ci.
            $stmt = $db->prepare("SELECT id, username, password_hash, nombre_completo, email, dos_factores_activo, estado
                FROM usuarios
                WHERE username COLLATE utf8mb4_unicode_ci = :username COLLATE utf8mb4_unicode_ci
                  AND estado = 'A'");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();
            $stmt->closeCursor();
        } else {
            $stmt = $db->prepare("SELECT id, username, password_hash, nombre_completo, email, dos_factores_activo, estado FROM usuarios WHERE username = :username AND estado = 'A'");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();
        }

        if (!$user) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Credenciales incorrectas o usuario inactivo"]);
            exit();
        }

        // Strictly verify password using password_verify
        if (!password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Credenciales incorrectas"]);
            exit();
        }

        // Check 2FA requirement
        if (!empty($user['dos_factores_activo'])) {
            $preToken = Auth::generateToken([
                'user_id' => $user['id'],
                'username' => $user['username'],
                'nombre_completo' => $user['nombre_completo'],
                'requires_2fa' => true
            ]);

            echo json_encode([
                "status" => "success",
                "requires_2fa" => true,
                "pre_token" => $preToken,
                "user_id" => $user['id'],
                "message" => "Por favor, ingrese su código de verificación de 2 factores (Código por defecto: 123456)"
            ]);
            exit();
        }

        $token = Auth::generateToken([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'nombre_completo' => $user['nombre_completo'],
            'requires_2fa' => false
        ]);

        echo json_encode([
            "status" => "success",
            "requires_2fa" => false,
            "token" => $token,
            "user" => [
                "id" => $user['id'],
                "username" => $user['username'],
                "nombre_completo" => $user['nombre_completo'],
                "email" => $user['email']
            ]
        ]);
        exit();

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error en servidor: " . $e->getMessage()]);
        exit();
    }
}

if ($action === 'verify_2fa') {
    $userId = $input['user_id'] ?? 0;
    $code = trim($input['code'] ?? '');

    if (empty($userId) || empty($code)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID de usuario y código 2FA requeridos"]);
        exit();
    }

    try {
        if ($driver === 'mysql') {
            $stmt = $db->prepare("SELECT id, username, nombre_completo, email
                FROM usuarios
                WHERE id = :id
                  AND dos_factores_secret COLLATE utf8mb4_unicode_ci = :secret COLLATE utf8mb4_unicode_ci
                  AND estado = 'A'");
            $stmt->execute([':id' => $userId, ':secret' => $code]);
            $user = $stmt->fetch();
            $stmt->closeCursor();
        } else {
            $stmt = $db->prepare("SELECT id, username, nombre_completo, email FROM usuarios WHERE id = :id AND dos_factores_secret = :code AND estado = 'A'");
            $stmt->execute([':id' => $userId, ':code' => $code]);
            $user = $stmt->fetch();
        }

        if (!$user) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Código 2FA incorrecto o expirado"]);
            exit();
        }

        $token = Auth::generateToken([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'nombre_completo' => $user['nombre_completo'],
            'requires_2fa' => false
        ]);

        echo json_encode([
            "status" => "success",
            "token" => $token,
            "user" => [
                "id" => $user['id'],
                "username" => $user['username'],
                "nombre_completo" => $user['nombre_completo'],
                "email" => $user['email']
            ]
        ]);
        exit();

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error en la verificación 2FA: " . $e->getMessage()]);
        exit();
    }
}

http_response_code(400);
echo json_encode(["status" => "error", "message" => "Acción no válida"]);

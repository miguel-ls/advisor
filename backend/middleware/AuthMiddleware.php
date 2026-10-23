<?php
// backend/middleware/AuthMiddleware.php

require_once __DIR__ . '/../config/Auth.php';

class AuthMiddleware {
    public static function authenticate() {
        $token = Auth::getBearerToken();
        if (!$token) {
            http_response_code(401);
            echo json_encode([
                "status" => "error",
                "message" => "Acceso no autorizado: Token Bearer no proporcionado"
            ]);
            exit();
        }

        $decoded = Auth::validateToken($token);
        if (!$decoded) {
            http_response_code(401);
            echo json_encode([
                "status" => "error",
                "message" => "Acceso no autorizado: Token Bearer inválido o expirado"
            ]);
            exit();
        }

        return $decoded; // Returns user claims: user_id, username, etc.
    }
}

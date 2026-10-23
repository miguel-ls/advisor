<?php
// backend/config/Database.php

class Database {
    private static $host = "127.0.0.1";
    private static $db_name = "advisor_db";
    private static $username = "root";
    private static $password = "";
    private static $port = "3306";
    private static $conn = null;
    private static $driver = 'mysql';

    private static $sqlite_file = __DIR__ . "/../../database/advisor_dev.sqlite";

    public static function getConnection() {
        if (self::$conn !== null) {
            return self::$conn;
        }

        try {
            $dsn = "mysql:host=" . self::$host . ";port=" . self::$port . ";dbname=" . self::$db_name . ";charset=utf8mb4";
            self::$conn = new PDO($dsn, self::$username, self::$password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            self::$conn->exec("SET collation_connection = utf8mb4_unicode_ci");
            self::$driver = 'mysql';
        } catch (PDOException $e) {
            // Fallback to SQLite setup for local testing/CLI
            if (!file_exists(self::$sqlite_file)) {
                require_once __DIR__ . '/../../setup_db.php';
            }
            $dsn = "sqlite:" . self::$sqlite_file;
            self::$conn = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            self::$driver = 'sqlite';
        }

        return self::$conn;
    }

    public static function getDriver() {
        return self::$driver;
    }
}

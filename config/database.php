<?php
/**
 * Dynamic Database Connection Configuration
 * Automatically toggles between Local and Cloud environments based on HTTP Host.
 */

function getDBConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // 1. Detect Host Domain / IP
    $hostHeader = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

    // Strip port numbers if running on custom ports like localhost:8080
    $domain = strtolower(explode(':', $hostHeader)[0]);

    // 2. Define Environment Configurations
    $isLocal = in_array($domain, ['localhost', '127.0.0.1', '::1']) || str_ends_with($domain, '.local') || str_ends_with($domain, '.test');

    if ($isLocal) {
        // --- LOCAL DEVELOPMENT ENVIRONMENT ---
        $dbHost = 'db';
        $dbPort = '3306';
        $dbName = 'dev_db';      // Your local database name
        $dbUser = 'dev_user';              // Your local MySQL username
        $dbPass = 'dev_password';                  // Your local MySQL password
    } else {
        // --- CLOUD PRODUCTION ENVIRONMENT (cPanel / Remote Host) ---
        $dbHost = 'localhost';
        $dbPort = '3306';
        $dbName = 'learnitc_pm_dashboard';
        $dbUser = 'learnitc_pm_user';
        $dbPass = 'yXQmf2ShlyEUHnqY'; // Replace with your actual cloud DB password
    }

    // 3. Establish PDO Connection
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Detailed error for local development; clean fallback for production
        if ($isLocal) {
            die("<div style='color:red; font-family:sans-serif; padding:1rem;'><strong>Local Database Connection Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
        } else {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database Connection Failed. Please contact system administrator.");
        }
    }
}
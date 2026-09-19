<?php
/**
 * Database connection configuration loaded from environment variables.
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

    // 2. Load the local .env file without requiring a third-party package.
    $envFile = dirname(__DIR__) . '/.env';
    if (is_readable($envFile)) {
        $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($envLines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));
            if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            if ($key !== '') {
                $_ENV[$key] = $value;
            }
        }
    }

    // 2. Define environment-specific variable names.
    $isLocal = in_array($domain, ['localhost', '127.0.0.1', '::1']) || str_ends_with($domain, '.local') || str_ends_with($domain, '.test');
    $environment = $isLocal ? 'LOCAL' : 'PRODUCTION';

    $dbHost = $_ENV["DB_{$environment}_HOST"] ?? '';
    $dbPort = $_ENV["DB_{$environment}_PORT"] ?? '3306';
    $dbName = $_ENV["DB_{$environment}_NAME"] ?? '';
    $dbUser = $_ENV["DB_{$environment}_USER"] ?? '';
    $dbPass = $_ENV["DB_{$environment}_PASS"] ?? '';

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
        error_log("Database configuration is incomplete for {$environment} environment.");
        die("Database configuration is incomplete. Set the required values in the server .env file.");
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
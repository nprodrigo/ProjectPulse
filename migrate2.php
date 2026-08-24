<?php
/**
 * ProjectPulse Database Migration & Auto-Update Script
 */

require_once __DIR__ . '/config/database.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db = getDBConnection();

if (!$db) {
    die("<div style='color:red; font-weight:bold; padding:2rem;'>Migration Failed: Could not connect to database. Check config/database.php</div>");
}

echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Database Migration</title>";
echo "<style>
        body { font-family: Arial, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; line-height: 1.6; }
        .card { background: #1e293b; border: 1px solid rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        .success { color: #34d399; font-weight: bold; }
        .info { color: #38bdf8; }
        .error { color: #fb7185; }
        .btn { display: inline-block; background: #6366f1; color: #fff; text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 6px; margin-top: 1rem; font-weight: bold; }
      </style></head><body><div class='card'>";

echo "<h2>ProjectPulse Database Auto-Migration</h2><hr style='border-color: rgba(255,255,255,0.1); margin-bottom: 1.5rem;'>";

try {
    // 1. Business Units Table & Seed Records
    $db->exec("CREATE TABLE IF NOT EXISTS `business_units` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(20) NOT NULL UNIQUE,
        `name` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p class='info'>✓ Business Units table verified.</p>";

    $db->exec("INSERT INTO `business_units` (`id`, `code`, `name`) VALUES
        (1, 'FIN', 'Finance & Operations'),
        (2, 'IT', 'IT & Infrastructure'),
        (3, 'CUST', 'Customer Success & Retail'),
        (4, 'COMP', 'Security & Compliance')
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);");
    echo "<p class='info'>✓ Business Units default records verified.</p>";

    // 2. Team Members Table
    $db->exec("CREATE TABLE IF NOT EXISTS `team_members` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `full_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(120) NOT NULL UNIQUE,
        `role_title` VARCHAR(100) DEFAULT 'Team Member',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p class='info'>✓ Team Members table verified.</p>";

    // 3. Projects Table Column Verification & Baseline Tracking
    $pCols = $db->query("SHOW COLUMNS FROM `projects`")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('bu_id', $pCols)) {
        $db->exec("ALTER TABLE `projects` ADD COLUMN `bu_id` INT NULL AFTER `category_id`;");
        $db->exec("ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_bu` FOREIGN KEY (`bu_id`) REFERENCES `business_units`(`id`) ON DELETE SET NULL;");
        echo "<p class='info'>✓ Column 'bu_id' added to projects table.</p>";
    }

    if (!in_array('manager_id', $pCols)) {
        $db->exec("ALTER TABLE `projects` ADD COLUMN `manager_id` INT NULL AFTER `bu_id`;");
        $db->exec("ALTER TABLE `projects` ADD CONSTRAINT `fk_projects_manager` FOREIGN KEY (`manager_id`) REFERENCES `team_members`(`id`) ON DELETE SET NULL;");
        echo "<p class='info'>✓ Column 'manager_id' added to projects table.</p>";
    }

    if (!in_array('original_target_date', $pCols)) {
        $db->exec("ALTER TABLE `projects` ADD COLUMN `original_start_date` DATE NULL AFTER `start_date`, ADD COLUMN `original_target_date` DATE NULL AFTER `target_completion_date`;");
        $db->exec("UPDATE `projects` SET `original_start_date` = `start_date`, `original_target_date` = `target_completion_date` WHERE `original_target_date` IS NULL;");
        echo "<p class='info'>✓ Baseline timeline fields (original_start_date, original_target_date) added to Projects table.</p>";
    }

    // 4. Project Teams Junction Table
    $db->exec("CREATE TABLE IF NOT EXISTS `project_teams` (
        `project_id` INT NOT NULL,
        `member_id` INT NOT NULL,
        PRIMARY KEY (`project_id`, `member_id`),
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`member_id`) REFERENCES `team_members`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p class='info'>✓ Project Teams junction table verified.</p>";

    // 5. Categorized Project Governance Teams (Strategic, Functional, Technical, PM)
    $db->exec("CREATE TABLE IF NOT EXISTS `project_team_roles` (
        `project_id` INT NOT NULL,
        `member_id` INT NOT NULL,
        `team_type` ENUM('Strategic', 'Functional', 'Technical', 'Project Management') NOT NULL DEFAULT 'Functional',
        PRIMARY KEY (`project_id`, `member_id`, `team_type`),
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`member_id`) REFERENCES `team_members`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p class='info'>✓ Project Team Roles governance table verified.</p>";

    // 6. Project Modules (WBS Level 1)
    $db->exec("CREATE TABLE IF NOT EXISTS `project_modules` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `module_name` VARCHAR(150) NOT NULL,
        `description` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p class='info'>✓ Project Modules (WBS) table verified.</p>";

    // 7. Tasks Table & Enhancements
    $db->exec("CREATE TABLE IF NOT EXISTS `tasks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `module_id` INT NULL,
        `start_date` DATE NULL,
        `assigned_to` INT NULL,
        `title` VARCHAR(200) NOT NULL,
        `description` TEXT NULL,
        `priority` ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
        `status` ENUM('To Do', 'In Progress', 'Under Review', 'Completed') DEFAULT 'To Do',
        `sort_order` INT NOT NULL DEFAULT 0,
        `due_date` DATE NULL,
        `original_due_date` DATE NULL,
        `original_days` DECIMAL(5,1) NOT NULL DEFAULT 0.5,
        `current_days` DECIMAL(5,1) NOT NULL DEFAULT 0.5,
        `effort_changes` DECIMAL(5,1) NOT NULL DEFAULT 0.0,
        `actual_days` DECIMAL(5,1) NOT NULL DEFAULT 0.0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`assigned_to`) REFERENCES `team_members`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`module_id`) REFERENCES `project_modules`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p class='info'>✓ Tasks table and variance/time fields verified.</p>";

    // 8. RACI Matrix Table
    $db->exec("CREATE TABLE IF NOT EXISTS `raci_matrix` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `entity_type` ENUM('Module', 'Task') NOT NULL,
        `entity_id` INT NOT NULL,
        `member_id` INT NOT NULL,
        `raci_role` ENUM('R', 'A', 'C', 'I') NOT NULL,
        FOREIGN KEY (`member_id`) REFERENCES `team_members`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p class='info'>✓ RACI Matrix table verified.</p>";

    // 9. Holidays Schema for Business Day Schedule Calculations
    $db->exec("CREATE TABLE IF NOT EXISTS `holidays` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `holiday_date` DATE NOT NULL UNIQUE,
        `title` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p class='info'>✓ Holidays calendar table verified.</p>";

    // 10. Daily Logs Table
    $db->exec("CREATE TABLE IF NOT EXISTS `daily_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `log_text` TEXT NOT NULL,
        `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p class='info'>✓ Daily Logs table verified.</p>";

    // 11. Users Table & Authentication Seeding
    $db->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `full_name` VARCHAR(100) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $cleanHash = password_hash('Password123!', PASSWORD_BCRYPT);
    
    $stmtU = $db->prepare("INSERT INTO `users` (`username`, `full_name`, `password`) VALUES
        ('admin', 'System Administrator', :pass1),
        ('niro', 'Niroshan', :pass2)
        ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `full_name` = VALUES(`full_name`);");
    
    $stmtU->execute([
        'pass1' => $cleanHash,
        'pass2' => $cleanHash
    ]);
    
    echo "<p class='info'>✓ User accounts (admin, niro) verified.</p>";

    echo "<hr style='border-color: rgba(255,255,255,0.1); margin: 1.5rem 0;'>";
    echo "<p class='success'>Database Schema & Governance Structures Successfully Updated!</p>";
    echo "<a href='index.php' class='btn'>Return to Dashboard &rarr;</a>";

} catch (PDOException $e) {
    echo "<p class='error'>Migration Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div></body></html>";
<?php
/**
 * Database Migration Script with Role Seeding
 */
require_once __DIR__ . '/config/database.php';

$db = getDBConnection();
if (!$db) die("Database Connection Failed.");

try {
    // 1. Ensure Role Column Exists
    $db->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'pm', 'viewer') NOT NULL DEFAULT 'admin';");

    // 2. Project Viewers Junction Table
    $db->exec("CREATE TABLE IF NOT EXISTS `project_viewers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `project_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user_project` (`user_id`, `project_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Seed Users for All 3 Roles
    $passHash = password_hash('Password123!', PASSWORD_BCRYPT);

    $stmtU = $db->prepare("INSERT INTO `users` (`username`, `full_name`, `password`, `role`) VALUES
        ('admin', 'System Administrator', :pass1, 'admin'),
        ('niro', 'Niroshan', :pass2, 'admin'),
        ('pm_user', 'Chamila Waduge', :pass3, 'pm'),
        ('viewer_user', 'External Auditor', :pass4, 'viewer')
        ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `role` = VALUES(`role`), `full_name` = VALUES(`full_name`);");

    $stmtU->execute([
        'pass1' => $passHash,
        'pass2' => $passHash,
        'pass3' => $passHash,
        'pass4' => $passHash
    ]);

    // 4. Map Viewer User to Project #1 for Testing
    $stmtV = $db->prepare("INSERT IGNORE INTO `project_viewers` (`user_id`, `project_id`) 
                           SELECT u.id, p.id FROM users u, projects p WHERE u.username = 'viewer_user' LIMIT 1;");
    $stmtV->execute();

    echo "<div style='font-family: sans-serif; padding: 2rem; background: #0f172a; color: #34d399;'>";
    echo "<h2>✓ Database Migration & Test Accounts Created Successfully!</h2>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin / Password123!</li>";
    echo "<li><strong>PM User:</strong> pm_user / Password123!</li>";
    echo "<li><strong>Viewer User:</strong> viewer_user / Password123!</li>";
    echo "</ul>";
    echo "<a href='login.php' style='color: #6366f1; font-weight: bold;'>Go to Login &rarr;</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "Migration Error: " . $e->getMessage();
}
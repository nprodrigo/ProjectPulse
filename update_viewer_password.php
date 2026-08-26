<?php
/**
 * Emergency Password & Account Reset Script
 */
require_once __DIR__ . '/config/database.php';

$db = getDBConnection();

if (!$db) {
    die("Database Connection Failed.");
}

try {
    // 1. Ensure team_members table has credentials columns
    $db->exec("ALTER TABLE `team_members` 
        ADD COLUMN `username` VARCHAR(50) NULL UNIQUE AFTER `email`,
        ADD COLUMN `password` VARCHAR(255) NULL AFTER `username`,
        ADD COLUMN `system_role` ENUM('admin', 'user') NOT NULL DEFAULT 'user' AFTER `password`;");
} catch (PDOException $e) {
    // Columns already exist, proceed silently
}

try {
    // 2. Hash default password
    $defaultPassword = 'Password123!';
    $passwordHash    = password_hash($defaultPassword, PASSWORD_BCRYPT);

    // 3. Upsert default accounts directly into team_members
    $accounts = [
        [
            'name'     => 'System Administrator',
            'email'    => 'admin@company.com',
            'role'     => 'IT Admin',
            'username' => 'admin',
            'sys_role' => 'admin'
        ],
        [
            'name'     => 'Chamila Waduge',
            'email'    => 'chamila@company.com',
            'role'     => 'Project Manager',
            'username' => 'chamila',
            'sys_role' => 'user'
        ],
        [
            'name'     => 'External Auditor',
            'email'    => 'auditor@company.com',
            'role'     => 'Client Stakeholder',
            'username' => 'auditor',
            'sys_role' => 'user'
        ]
    ];

    $stmtCheck = $db->prepare("SELECT id FROM team_members WHERE LOWER(username) = LOWER(:uname) OR LOWER(email) = LOWER(:email)");
    $stmtUpd   = $db->prepare("UPDATE team_members SET password = :pass, system_role = :srole, username = :uname WHERE id = :id");
    $stmtIns   = $db->prepare("INSERT INTO team_members (full_name, email, role_title, username, password, system_role) VALUES (:fname, :email, :rtitle, :uname, :pass, :srole)");

    foreach ($accounts as $acc) {
        $stmtCheck->execute(['uname' => $acc['username'], 'email' => $acc['email']]);
        $existingId = $stmtCheck->fetchColumn();

        if ($existingId) {
            $stmtUpd->execute([
                'pass'  => $passwordHash,
                'srole' => $acc['sys_role'],
                'uname' => $acc['username'],
                'id'    => $existingId
            ]);
        } else {
            $stmtIns->execute([
                'fname'  => $acc['name'],
                'email'  => $acc['email'],
                'rtitle' => $acc['role'],
                'uname'  => $acc['username'],
                'pass'   => $passwordHash,
                'srole'  => $acc['sys_role']
            ]);
        }
    }

    echo "<div style='font-family: sans-serif; padding: 2rem; background: #0f172a; color: #34d399; border-radius: 8px; margin: 2rem;'>";
    echo "<h2>✓ Password Reset Complete!</h2>";
    echo "<p style='color: #cbd5e1;'>All accounts have been updated in <code>team_members</code> with password: <strong>Password123!</strong></p>";
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; color: #fff; margin-bottom: 1rem;'>";
    echo "<tr><th>Username</th><th>Email</th><th>System Role</th><th>Default Password</th></tr>";
    echo "<tr><td>admin</td><td>admin@company.com</td><td>Admin</td><td>Password123!</td></tr>";
    echo "<tr><td>chamila</td><td>chamila@company.com</td><td>User</td><td>Password123!</td></tr>";
    echo "<tr><td>auditor</td><td>auditor@company.com</td><td>User</td><td>Password123!</td></tr>";
    echo "</table>";
    echo "<a href='login.php' style='color: #6366f1; font-weight: bold; font-size: 1.1rem;'>Click here to Login &rarr;</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='color: red; padding: 2rem;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
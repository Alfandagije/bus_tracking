<?php
/**
 * Aiven Database Migration: 4-Role System
 * Run this ONCE: php run_migration_roles.php
 * Or visit http://localhost/IOT_BUS_TRACKING_SYSTEM/run_migration_roles.php
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Aiven Database Migration: 4-Role System</h2>";

try {
    $db = getDb();
    echo "<p style='color:green;'>Connected to database successfully.</p>";

    $statements = [
        "CREATE TABLE IF NOT EXISTS routes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            route_name VARCHAR(100) NOT NULL,
            origin VARCHAR(100) NOT NULL,
            destination VARCHAR(100) NOT NULL,
            base_price DECIMAL(10,2) DEFAULT 500.00,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'buses' AND COLUMN_NAME = 'route_id');
         SET @sql = IF(@exists = 0, 'ALTER TABLE buses ADD COLUMN route_id INT DEFAULT NULL AFTER fare', 'SELECT 1');
         PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt",

        "SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'buses' AND CONSTRAINT_NAME = 'buses_ibfk_1');
         SET @sql = IF(@exists = 0, 'ALTER TABLE buses ADD FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL', 'SELECT 1');
         PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt",

        "SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'drivers' AND COLUMN_NAME = 'user_id');
         SET @sql = IF(@exists = 0, 'ALTER TABLE drivers ADD COLUMN user_id INT DEFAULT NULL AFTER phone', 'SELECT 1');
         PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt",

        "SET @exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'drivers' AND CONSTRAINT_NAME = 'drivers_ibfk_2');
         SET @sql = IF(@exists = 0, 'ALTER TABLE drivers ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1');
         PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt",

        "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'driver', 'passenger') DEFAULT 'passenger'",

        "UPDATE users SET role = 'passenger' WHERE role = 'user'",

        "INSERT IGNORE INTO routes (route_name, origin, destination, base_price, status) VALUES
         ('Kigali Express Route 1', 'Kigali City Center', 'Kimironko', 500.00, 'active'),
         ('Kigali Express Route 2', 'Kigali City Center', 'Nyabugogo', 500.00, 'active'),
         ('Kigali Express Route 3', 'Kigali City Center', 'Remera', 500.00, 'active')",

        "UPDATE buses SET route_id = 1 WHERE bus_code = 'BUS001' AND route_id IS NULL",
        "UPDATE buses SET route_id = 2 WHERE bus_code = 'BUS002' AND route_id IS NULL",
        "UPDATE buses SET route_id = 3 WHERE bus_code = 'BUS003' AND route_id IS NULL",
    ];

    $users = [
        ['admin@bus.com', 'admin123', 'System Admin', '+250788000000', 'admin'],
        ['manager@bus.com', 'manager123', 'System Manager', '+250788000001', 'manager'],
        ['driver@bus.com', 'driver123', 'Jean Driver', '+250788000002', 'driver'],
    ];

    echo "<h3>Step 1: Schema Migration</h3>";
    $success = 0;
    $failed = 0;
    foreach ($statements as $i => $sql) {
        try {
            $db->exec($sql);
            echo "<p style='color:green;'>OK: Statement " . ($i + 1) . "</p>";
            $success++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'Duplicate column') !== false || strpos($msg, 'Duplicate key') !== false || strpos($msg, 'already exists') !== false) {
                echo "<p style='color:orange;'>SKIP: Statement " . ($i + 1) . " (already exists)</p>";
                $success++;
            } else {
                echo "<p style='color:red;'>FAIL: Statement " . ($i + 1) . ": " . htmlspecialchars($msg) . "</p>";
                $failed++;
            }
        }
    }

    echo "<h3>Step 2: Seed Users</h3>";
    foreach ($users as $u) {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$u[0]]);
            $existing = $stmt->fetch();

            $hashed = password_hash($u[1], PASSWORD_DEFAULT);
            if ($existing) {
                $stmt = $db->prepare("UPDATE users SET password = ?, role = ?, full_name = ?, phone = ? WHERE email = ?");
                $stmt->execute([$hashed, $u[4], $u[2], $u[3], $u[0]]);
                echo "<p style='color:green;'>Updated: {$u[0]} ({$u[4]})</p>";
            } else {
                $stmt = $db->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$u[2], $u[0], $u[3], $hashed, $u[4]]);
                echo "<p style='color:green;'>Created: {$u[0]} ({$u[4]})</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color:red;'>User {$u[0]}: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    echo "<h3>Step 3: Link Driver User</h3>";
    try {
        $db->exec("UPDATE drivers SET user_id = (SELECT id FROM users WHERE email = 'driver@bus.com' LIMIT 1) WHERE user_id IS NULL LIMIT 1");
        echo "<p style='color:green;'>Driver user linked to drivers table.</p>";
    } catch (Exception $e) {
        echo "<p style='color:orange;'>Driver link: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "<h3>Step 4: Verify</h3>";
    $tables = ['users', 'routes', 'buses', 'seats', 'drivers', 'bookings', 'payments', 'sms_logs'];
    echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-family:sans-serif;'>";
    echo "<tr><th>Table</th><th>Rows</th><th>Status</th></tr>";
    foreach ($tables as $t) {
        try {
            $count = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            echo "<tr><td>$t</td><td>$count</td><td style='color:green;'>OK</td></tr>";
        } catch (Exception $e) {
            echo "<tr><td>$t</td><td>-</td><td style='color:red;'>ERROR</td></tr>";
        }
    }
    echo "</table>";

    echo "<h3>Step 5: Verify Roles</h3>";
    try {
        $roles = $db->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll();
        echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-family:sans-serif;'>";
        echo "<tr><th>Role</th><th>Count</th></tr>";
        foreach ($roles as $r) {
            echo "<tr><td>{$r['role']}</td><td>{$r['cnt']}</td></tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "<h3>Step 6: Verify Routes</h3>";
    try {
        $routes = $db->query("SELECT * FROM routes ORDER BY id")->fetchAll();
        echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-family:sans-serif;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Origin</th><th>Destination</th><th>Price</th></tr>";
        foreach ($routes as $r) {
            echo "<tr><td>{$r['id']}</td><td>{$r['route_name']}</td><td>{$r['origin']}</td><td>{$r['destination']}</td><td>RWF {$r['base_price']}</td></tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "<hr><h2>Migration Complete!</h2>";
    echo "<p>All 4 roles are now active: <strong>admin, manager, driver, passenger</strong></p>";
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse;font-family:sans-serif;'>";
    echo "<tr><th>Role</th><th>Email</th><th>Password</th></tr>";
    echo "<tr><td>Admin</td><td>admin@bus.com</td><td>admin123</td></tr>";
    echo "<tr><td>Manager</td><td>manager@bus.com</td><td>manager123</td></tr>";
    echo "<tr><td>Driver</td><td>driver@bus.com</td><td>driver123</td></tr>";
    echo "<tr><td>Passenger</td><td colspan='2'>Register at /auth/register.php</td></tr>";
    echo "</table>";
    echo "<p><strong>Delete this file after use!</strong></p>";

} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

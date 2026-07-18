<?php
/**
 * Migration runner - Admin only
 * Run once then DELETE this file for security
 */
require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized - Admin only');
}

$db = getDb();

$migrations = [
    "CREATE TABLE IF NOT EXISTS drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        license_number VARCHAR(50) UNIQUE NOT NULL,
        assigned_bus_id INT DEFAULT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) DEFAULT 'RWF',
        payment_method VARCHAR(50) DEFAULT 'MTN_MoMo',
        transaction_id VARCHAR(100) DEFAULT NULL,
        momo_ref VARCHAR(100) DEFAULT NULL,
        status ENUM('pending', 'successful', 'failed', 'reversed') DEFAULT 'pending',
        payer_phone VARCHAR(20) DEFAULT NULL,
        api_response TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",

    "ALTER TABLE bookings ADD COLUMN amount DECIMAL(10,2) DEFAULT 500.00 AFTER payment_ref",

    "ALTER TABLE buses ADD COLUMN fare DECIMAL(10,2) DEFAULT 500.00 AFTER total_seats",

    "ALTER TABLE buses ADD COLUMN driver_id INT DEFAULT NULL AFTER fare",
];

$results = [];
foreach ($migrations as $i => $sql) {
    try {
        $db->exec($sql);
        $results[] = ['step' => $i + 1, 'status' => 'OK', 'sql' => substr($sql, 0, 80) . '...'];
    } catch (PDOException $e) {
        $code = $e->getCode();
        // Column already exists (1060) or table already created is fine
        if ($code == '1060' || $code == '42S01') {
            $results[] = ['step' => $i + 1, 'status' => 'SKIP (already exists)', 'sql' => substr($sql, 0, 80) . '...'];
        } else {
            $results[] = ['step' => $i + 1, 'status' => 'ERROR', 'sql' => substr($sql, 0, 80) . '...', 'error' => $e->getMessage()];
        }
    }
}

// Try adding foreign keys separately (may fail if already exists)
$fks = [
    "ALTER TABLE buses ADD FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL",
    "ALTER TABLE payments ADD FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE",
    "ALTER TABLE payments ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE",
    "ALTER TABLE drivers ADD FOREIGN KEY (assigned_bus_id) REFERENCES buses(id) ON DELETE SET NULL",
];

foreach ($fks as $i => $sql) {
    try {
        $db->exec($sql);
        $results[] = ['step' => 'FK-' . ($i + 1), 'status' => 'OK', 'sql' => substr($sql, 0, 80) . '...'];
    } catch (PDOException $e) {
        $results[] = ['step' => 'FK-' . ($i + 1), 'status' => 'SKIP (already exists)', 'sql' => substr($sql, 0, 80) . '...'];
    }
}

// Verify tables exist
$verify = [];
$tables = ['drivers', 'payments'];
foreach ($tables as $t) {
    $stmt = $db->query("SHOW TABLES LIKE '{$t}'");
    $verify[$t] = $stmt->fetch() ? 'EXISTS' : 'MISSING';
}

$cols = [];
foreach ($tables as $t) {
    $stmt = $db->query("DESCRIBE {$t}");
    $cols[$t] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

?>
<!DOCTYPE html>
<html>
<head><title>Migration v2 Results</title></head>
<body style="font-family:monospace;padding:20px;max-width:900px;margin:0 auto;">
<h1>Migration v2 Results</h1>
<table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;">
<tr style="background:#1a73e8;color:#fff;"><th>Step</th><th>Status</th><th>SQL</th><th>Error</th></tr>
<?php foreach ($results as $r): ?>
<tr style="background:<?= $r['status'] === 'OK' ? '#dcfce7' : ($r['status'] === 'SKIP (already exists)' ? '#fef3c7' : '#fee2e2') ?>">
    <td><?= $r['step'] ?></td>
    <td><strong><?= htmlspecialchars($r['status']) ?></strong></td>
    <td style="font-size:0.8rem;"><?= htmlspecialchars($r['sql'] ?? '') ?></td>
    <td style="color:red;font-size:0.8rem;"><?= htmlspecialchars($r['error'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</table>

<h2>Verification</h2>
<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
<tr style="background:#1a73e8;color:#fff;"><th>Table</th><th>Status</th><th>Columns</th></tr>
<?php foreach ($tables as $t): ?>
<tr>
    <td><strong><?= $t ?></strong></td>
    <td style="background:<?= $verify[$t] === 'EXISTS' ? '#dcfce7' : '#fee2e2' ?>"><?= $verify[$t] ?></td>
    <td style="font-size:0.75rem;"><?= implode(', ', $cols[$t] ?? []) ?></td>
</tr>
<?php endforeach; ?>
</table>

<p style="margin-top:20px;color:#666;"><strong>IMPORTANT:</strong> Delete this file after migration: <code>api/run_migration.php</code></p>
<p><a href="/dashboard/index.php">Go to Admin Dashboard</a></p>
</body>
</html>

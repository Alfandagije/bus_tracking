<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

$password = $_GET['key'] ?? '';
if ($password !== 'setup2026') {
    echo "<h2>Unauthorized</h2><p>Add ?key=setup2026 to the URL</p>";
    exit;
}

try {
    $db = getDb();

    $schema = file_get_contents(__DIR__ . '/sql/setup_phpmyadmin.sql');

    $statements = explode(';', $schema);
    $success = 0;
    $errors = [];

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;
        if (preg_match('/^(SET\s|--)/i', $stmt)) {
            if (stripos($stmt, 'FOREIGN_KEY_CHECKS') !== false || stripos($stmt, '@') !== false || stripos($stmt, 'PREPARE') !== false || stripos($stmt, 'EXECUTE') !== false || stripos($stmt, 'DEALLOCATE') !== false) {
                try {
                    $db->exec($stmt);
                    $success++;
                } catch (PDOException $e) {
                    $errors[] = htmlspecialchars(substr($stmt, 0, 80)) . '... &rarr; ' . htmlspecialchars($e->getMessage());
                }
            } else {
                try { $db->exec($stmt); } catch (Exception $e) {}
                $success++;
            }
            continue;
        }
        try {
            $db->exec($stmt);
            $success++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'already exists') !== false || stripos($msg, 'Duplicate') !== false) {
                $success++;
            } else {
                $errors[] = htmlspecialchars(substr($stmt, 0, 100)) . '... &rarr; ' . htmlspecialchars($msg);
            }
        }
    }

    echo "<h2>Database Setup Complete</h2>";
    echo "<p>Statements executed: $success</p>";

    if (count($errors) > 0) {
        echo "<h3>Errors:</h3><pre>";
        foreach ($errors as $e) echo "$e\n\n";
        echo "</pre>";
    } else {
        echo "<p style='color:green;font-weight:bold;'>All tables created/updated successfully!</p>";
    }

    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables:</h3><ul>";
    foreach ($tables as $t) echo "<li>$t</li>";
    echo "</ul>";

    echo "<h3>Table Columns:</h3>";
    foreach ($tables as $t) {
        $cols = $db->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p><strong>$t:</strong> " . implode(', ', $cols) . "</p>";
    }

    echo "<p style='color:red;font-weight:bold;'>DELETE this file after use!</p>";

} catch (Exception $e) {
    echo "<h2>Connection Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

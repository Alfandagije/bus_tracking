<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
try { $db = getDb(); } catch (Exception $e) { $db = null; }
$logs = [];
if ($db) {
    try {
        $logs = $db->query("
            SELECT sl.*, b.bus_code, s.seat_number, u.full_name
            FROM sms_logs sl
            JOIN bookings bk ON sl.booking_id = bk.id
            JOIN buses b ON bk.bus_id = b.id
            JOIN seats s ON bk.seat_id = s.id
            JOIN users u ON bk.user_id = u.id
            ORDER BY sl.created_at DESC
        ")->fetchAll();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Logs - Admin - Smart Bus Tracking</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav>
    <a href="../index.php" class="nav-brand"><img src="../assets/icons/bus.svg" class="icon"> SmartBus Tracker</a>
    <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="nav-links" id="navLinks">
        <a href="../index.php">Live Tracking</a>
        <div class="nav-user">
            <span><img src="../assets/icons/user.svg" class="icon"> <?= htmlspecialchars($_SESSION['full_name']) ?> (Admin)</span>
            <a href="../auth/logout.php" class="btn btn-sm btn-primary">Logout</a>
        </div>
    </div>
</nav>
<div class="admin-layout">
    <button class="hamburger" id="sidebarToggle" aria-label="Toggle sidebar" style="display:none;">
        <span></span><span></span><span></span>
    </button>
    <div class="admin-sidebar" id="adminSidebar">
        <h3>Admin Panel</h3>
        <a href="index.php"><img src="../assets/icons/chart.svg" class="icon"> Dashboard</a>
        <a href="admin_buses.php"><img src="../assets/icons/bus.svg" class="icon"> Buses</a>
        <a href="admin_drivers.php"><img src="../assets/icons/user.svg" class="icon"> Drivers</a>
        <a href="admin_bookings.php"><img src="../assets/icons/ticket.svg" class="icon"> Bookings</a>
        <a href="admin_payments.php"><img src="../assets/icons/ticket.svg" class="icon"> Payments</a>
        <a href="admin_sms_logs.php" class="active"><img src="../assets/icons/mail.svg" class="icon"> SMS Logs</a>
        <a href="admin_passengers.php"><img src="../assets/icons/users.svg" class="icon"> Passengers</a>
        <a href="admin_reports.php"><img src="../assets/icons/chart.svg" class="icon"> Reports</a>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-content">
        <h2 style="margin-bottom:24px;"><img src="../assets/icons/mail.svg" class="icon"> SMS Logs</h2>
        <div class="card">
            <div class="table-container">
                <table>
                    <thead><tr>
                        <th>ID</th><th>Booking</th><th>Passenger</th><th>Phone</th><th>Bus</th><th>Message</th><th>Status</th><th>Created</th><th>Sent At</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($logs as $l): 
                            $sc = $l['status'] === 'sent' ? 'badge-success' : ($l['status'] === 'pending' ? 'badge-warning' : 'badge-danger');
                        ?>
                        <tr>
                            <td>#<?= sec($l['id']) ?></td>
                            <td>#<?= sec($l['booking_id']) ?></td>
                            <td><?= sec($l['full_name']) ?></td>
                            <td><?= sec($l['phone']) ?></td>
                            <td><?= sec($l['bus_code']) ?></td>
                            <td style="max-width:300px;font-size:0.8rem;white-space:normal;"><?= sec($l['message']) ?></td>
                            <td><span class="badge <?= sec($sc) ?>"><?= sec($l['status']) ?></span></td>
                            <td style="font-size:0.8rem;"><?= sec($l['created_at']) ?></td>
                            <td style="font-size:0.8rem;"><?= sec($l['sent_at'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('hamburger')?.addEventListener('click', function() {
    this.classList.toggle('active');
    document.getElementById('navLinks').classList.toggle('open');
});
document.addEventListener('click', function(e) {
    const nav = document.querySelector('nav');
    if (nav && !nav.contains(e.target) && !e.target.closest('.admin-sidebar')) {
        document.getElementById('hamburger')?.classList.remove('active');
        document.getElementById('navLinks')?.classList.remove('open');
    }
});
if (window.innerWidth < 992) {
    document.getElementById('sidebarToggle').style.display = 'flex';
}
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.getElementById('adminSidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
});
document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
    document.getElementById('adminSidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
});
</script>
</body>
</html>

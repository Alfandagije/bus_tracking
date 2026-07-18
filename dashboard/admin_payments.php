<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
try { $db = getDb(); } catch (Exception $e) { $db = null; }
$payments = [];
if ($db) {
    try {
        $payments = $db->query("
            SELECT p.*, b.bus_code, b.bus_name, s.seat_number, u.full_name, u.email, u.phone
            FROM payments p
            JOIN bookings bk ON p.booking_id = bk.id
            JOIN buses b ON bk.bus_id = b.id
            JOIN seats s ON bk.seat_id = s.id
            JOIN users u ON p.user_id = u.id
            ORDER BY p.created_at DESC
        ")->fetchAll();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Admin - Smart Bus Tracking</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav>
    <a href="../index.php" class="nav-brand"><?= icon('bus') ?> SmartBus Tracker</a>
    <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="nav-links" id="navLinks">
        <a href="../index.php">Live Tracking</a>
        <div class="nav-user">
            <span><?= icon('user') ?> <?= htmlspecialchars($_SESSION['full_name']) ?> (Admin)</span>
            <a href="../auth/logout.php" class="btn btn-sm btn-primary">Logout</a>
        </div>
    </div>
</nav>
<div class="admin-layout">
    <button class="hamburger" id="sidebarToggle" aria-label="Toggle sidebar" style="display:none;"><span></span><span></span><span></span></button>
    <div class="admin-sidebar" id="adminSidebar">
        <h3>Admin Panel</h3>
        <a href="index.php"><?= icon('chart') ?> Dashboard</a>
        <a href="admin_buses.php"><?= icon('bus') ?> Buses</a>
        <a href="admin_drivers.php"><?= icon('user') ?> Drivers</a>
        <a href="admin_bookings.php"><?= icon('ticket') ?> Bookings</a>
        <a href="admin_payments.php" class="active"><?= icon('ticket') ?> Payments</a>
        <a href="admin_sms_logs.php"><?= icon('mail') ?> SMS Logs</a>
        <a href="admin_passengers.php"><?= icon('users') ?> Passengers</a>
        <a href="admin_reports.php"><?= icon('chart') ?> Reports</a>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-content">
        <h2 style="margin-bottom:24px;"><?= icon('ticket') ?> Payment Management</h2>
        <div id="alertMessage" class="message" style="margin-bottom: 20px;"></div>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead><tr>
                        <th>ID</th><th>Booking</th><th>Passenger</th><th>Phone</th><th>Bus</th><th>Seat</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($payments as $p):
                            $sc = $p['status'] === 'successful' ? 'badge-success' : ($p['status'] === 'pending' ? 'badge-warning' : 'badge-danger');
                        ?>
                        <tr>
                            <td>#<?= sec($p['id']) ?></td>
                            <td>#<?= sec($p['booking_id']) ?></td>
                            <td><?= sec($p['full_name']) ?></td>
                            <td><?= sec($p['payer_phone'] ?? $p['phone']) ?></td>
                            <td><?= sec($p['bus_code']) ?></td>
                            <td><?= sec($p['seat_number']) ?></td>
                            <td><strong>RWF <?= number_format(sec($p['amount'])) ?></strong></td>
                            <td><?= sec($p['payment_method']) ?></td>
                            <td><span class="badge <?= sec($sc) ?>"><?= sec($p['status']) ?></span></td>
                            <td style="font-size:0.8rem;"><?= sec($p['created_at']) ?></td>
                            <td>
                                <?php if ($p['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-success" onclick="confirmPayment(<?= sec($p['id']) ?>)">Confirm</button>
                                    <button class="btn btn-sm btn-danger" onclick="failPayment(<?= sec($p['id']) ?>)">Fail</button>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
function showAlert(msg, type) {
    const el = document.getElementById('alertMessage');
    el.textContent = msg; el.className = 'message ' + type; el.style.display = 'block';
    window.scrollTo({top:0,behavior:'smooth'});
}
async function confirmPayment(id) {
    if (!confirm('Confirm this payment?')) return;
    const res = await fetch('../api/admin_payments.php?action=confirm', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({payment_id: id})
    });
    const r = await res.json();
    if (r.status === 'success') { showAlert(r.message, 'success'); setTimeout(()=>location.reload(), 1500); }
    else showAlert(r.message, 'error');
}
async function failPayment(id) {
    if (!confirm('Mark this payment as failed?')) return;
    const res = await fetch('../api/admin_payments.php?action=fail', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({payment_id: id})
    });
    const r = await res.json();
    if (r.status === 'success') { showAlert(r.message, 'success'); setTimeout(()=>location.reload(), 1500); }
    else showAlert(r.message, 'error');
}
document.getElementById('hamburger')?.addEventListener('click', function(){ this.classList.toggle('active'); document.getElementById('navLinks').classList.toggle('open'); });
document.addEventListener('click', function(e){ const nav=document.querySelector('nav'); if(nav&&!nav.contains(e.target)&&!e.target.closest('.admin-sidebar')){document.getElementById('hamburger')?.classList.remove('active');document.getElementById('navLinks')?.classList.remove('open');} });
if(window.innerWidth<992){document.getElementById('sidebarToggle').style.display='flex';}
document.getElementById('sidebarToggle')?.addEventListener('click', function(){ document.getElementById('adminSidebar').classList.toggle('open'); document.getElementById('sidebarOverlay').classList.toggle('open'); });
document.getElementById('sidebarOverlay')?.addEventListener('click', function(){ document.getElementById('adminSidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('open'); });
</script>
</body>
</html>

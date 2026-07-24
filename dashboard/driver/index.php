<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../../auth/login.php');
    exit;
}
require_once __DIR__ . '/../../config/database.php';
try { $db = getDb(); } catch (Exception $e) { $db = null; }

$driver = null;
$bookings = [];
$bus = null;
$seatCount = 0;

if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT d.*, b.bus_code, b.bus_name, b.current_lat, b.current_lng, b.status as bus_status
            FROM drivers d
            LEFT JOIN buses b ON d.assigned_bus_id = b.id
            WHERE d.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $driver = $stmt->fetch();

        if ($driver && $driver['assigned_bus_id']) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM seats WHERE bus_id = ?");
            $stmt->execute([$driver['assigned_bus_id']]);
            $seatCount = $stmt->fetchColumn();

            $stmt = $db->prepare("
                SELECT bk.id, bk.booking_date, bk.status, bk.payment_method, bk.amount,
                       s.seat_number, u.full_name, u.phone
                FROM bookings bk
                JOIN seats s ON bk.seat_id = s.id
                JOIN users u ON bk.user_id = u.id
                WHERE bk.bus_id = ? AND bk.booking_date = CURDATE() AND bk.status != 'cancelled'
                ORDER BY s.seat_number
            ");
            $stmt->execute([$driver['assigned_bus_id']]);
            $bookings = $stmt->fetchAll();

            $bus = $driver;
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bus - Driver - Smart Bus Tracking</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<nav>
    <a href="../../index.php" class="nav-brand"><?= icon('bus') ?> SmartBus Tracker</a>
    <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="nav-links" id="navLinks">
        <a href="../../index.php">Live Tracking</a>
        <div class="nav-user">
            <span><?= icon('user') ?> <?= htmlspecialchars($_SESSION['full_name']) ?> (Driver)</span>
            <a href="../../auth/logout.php" class="btn btn-sm btn-primary">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div style="max-width:900px;margin:0 auto;">
        <h2 style="margin-bottom:24px;"><?= icon('bus') ?> My Assigned Bus</h2>

        <?php if (!$driver || !$driver['assigned_bus_id']): ?>
            <div class="card">
                <div class="text-center" style="padding:60px 0;">
                    <h3 style="margin-bottom:8px;color:var(--gray-500);">No Bus Assigned</h3>
                    <p style="color:var(--gray-500);">Contact your manager to assign a bus.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="stats-grid" style="margin-bottom:24px;">
                <div class="stat-card">
                    <div class="stat-icon"><?= icon('bus', 'icon-lg') ?></div>
                    <div class="stat-value"><?= sec($bus['bus_code']) ?></div>
                    <div class="stat-label"><?= sec($bus['bus_name']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><?= icon('seat', 'icon-lg') ?></div>
                    <div class="stat-value"><?= count($bookings) ?>/<?= sec($seatCount) ?></div>
                    <div class="stat-label">Booked Seats</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><?= icon('ticket', 'icon-lg') ?></div>
                    <div class="stat-value"><?= count($bookings) ?></div>
                    <div class="stat-label">Today's Passengers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><?= icon('check', 'icon-lg') ?></div>
                    <div class="stat-value"><span class="badge badge-<?= $bus['bus_status'] === 'active' ? 'success' : 'danger' ?>"><?= sec($bus['bus_status']) ?></span></div>
                    <div class="stat-label">Bus Status</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><?= icon('users') ?> Today's Booked Passengers</h2>
                </div>
                <?php if (count($bookings) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead><tr>
                                <th>Seat</th><th>Passenger</th><th>Phone</th><th>Status</th><th>Payment</th><th>Amount</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td><strong><?= sec($b['seat_number']) ?></strong></td>
                                    <td><?= sec($b['full_name']) ?></td>
                                    <td><?= sec($b['phone']) ?></td>
                                    <td><span class="badge badge-<?= $b['status'] === 'paid' ? 'success' : 'warning' ?>"><?= sec($b['status']) ?></span></td>
                                    <td><?= sec($b['payment_method'] ?? '-') ?></td>
                                    <td>RWF <?= number_format(sec($b['amount'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center" style="padding:40px 0;color:var(--gray-500);">
                        <p>No passengers booked for today.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('hamburger')?.addEventListener('click',function(){this.classList.toggle('active');document.getElementById('navLinks').classList.toggle('open');});
document.addEventListener('click',function(e){const nav=document.querySelector('nav');if(nav&&!nav.contains(e.target)){document.getElementById('hamburger')?.classList.remove('active');document.getElementById('navLinks')?.classList.remove('open');}});
</script>
</body>
</html>

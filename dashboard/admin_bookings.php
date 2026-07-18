<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
try { $db = getDb(); } catch (Exception $e) { $db = null; }
$bookings = [];
if ($db) {
    try {
        $bookings = $db->query("
            SELECT bk.*, b.bus_code, b.bus_name, s.seat_number, u.full_name, u.email, u.phone
            FROM bookings bk
            JOIN buses b ON bk.bus_id = b.id
            JOIN seats s ON bk.seat_id = s.id
            JOIN users u ON bk.user_id = u.id
            ORDER BY bk.created_at DESC
        ")->fetchAll();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings - Admin - Smart Bus Tracking</title>
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
        <a href="admin_bookings.php" class="active"><img src="../assets/icons/ticket.svg" class="icon"> Bookings</a>
        <a href="admin_payments.php"><img src="../assets/icons/ticket.svg" class="icon"> Payments</a>
        <a href="admin_sms_logs.php"><img src="../assets/icons/mail.svg" class="icon"> SMS Logs</a>
        <a href="admin_passengers.php"><img src="../assets/icons/users.svg" class="icon"> Passengers</a>
        <a href="admin_reports.php"><img src="../assets/icons/chart.svg" class="icon"> Reports</a>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-content">
        <h2 style="margin-bottom:24px;"><img src="../assets/icons/ticket.svg" class="icon"> All Bookings</h2>
        
        <div id="alertMessage" class="message" style="margin-bottom: 20px;"></div>

        <div class="card">
            <!-- Bulk Actions Bar -->
            <div class="bulk-actions-bar" id="bulkActionsBar">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-weight:600;"><span id="selectedCount">0</span> bookings selected</span>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <select id="bulkStatusSelect" class="status-select">
                        <option value="">Change status to...</option>
                        <option value="paid">Paid (Confirm Payment)</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button class="btn btn-sm btn-primary" onclick="applyBulkAction()">Apply</button>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead><tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllCheckbox" style="cursor:pointer; transform: scale(1.1);"></th>
                        <th>ID</th><th>Passenger</th><th>Email</th><th>Phone</th><th>Bus</th><th>Seat</th><th>Date</th><th>Status</th><th>Payment</th><th>SMS</th><th>Booked</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($bookings as $b): 
                            $scClass = $b['status'] === 'paid' ? 'paid' : ($b['status'] === 'pending' ? 'pending' : 'cancelled');
                        ?>
                        <tr>
                            <td style="text-align: center;"><input type="checkbox" class="booking-checkbox" value="<?= sec($b['id']) ?>" style="cursor:pointer; transform: scale(1.1);"></td>
                            <td>#<?= sec($b['id']) ?></td>
                            <td><?= sec($b['full_name']) ?></td>
                            <td style="font-size:0.8rem;"><?= sec($b['email']) ?></td>
                            <td><?= sec($b['phone']) ?></td>
                            <td><?= sec($b['bus_code']) ?></td>
                            <td><?= sec($b['seat_number']) ?></td>
                            <td><?= sec($b['booking_date']) ?></td>
                            <td>
                                <select class="status-select <?= sec($scClass) ?>" data-id="<?= sec($b['id']) ?>" onchange="updateSingleStatus(<?= sec($b['id']) ?>, this.value, this)" style="font-size: 0.75rem; padding: 2px 6px;">
                                    <option value="pending" <?= $b['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="paid" <?= $b['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="cancelled" <?= $b['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:6px; flex-wrap: nowrap;">
                                    <span><?= sec($b['payment_method'] ?? '-') ?></span>
                                    <?php if ($b['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success" style="padding: 2px 6px; font-size: 0.65rem; border-radius: 4px;" onclick="confirmSinglePayment(<?= sec($b['id']) ?>)">Confirm</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= $b['sms_sent'] ? '<span class="badge badge-success">Sent</span>' : '<span class="badge badge-warning">Pending</span>' ?></td>
                            <td style="font-size:0.8rem;"><?= sec($b['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Select All checkbox logic
const selectAll = document.getElementById('selectAllCheckbox');
const checkboxes = document.getElementsByClassName('booking-checkbox');
const bulkBar = document.getElementById('bulkActionsBar');
const selectedCount = document.getElementById('selectedCount');

function updateBulkBar() {
    const checked = Array.from(checkboxes).filter(cb => cb.checked);
    selectedCount.textContent = checked.length;
    if (checked.length > 0) {
        bulkBar.style.display = 'flex';
    } else {
        bulkBar.style.display = 'none';
    }
}

if (selectAll) {
    selectAll.addEventListener('change', function() {
        for (let cb of checkboxes) {
            cb.checked = this.checked;
        }
        updateBulkBar();
    });
}

document.addEventListener('change', function(e) {
    if (e.target && e.target.classList.contains('booking-checkbox')) {
        updateBulkBar();
        // If one is unchecked, uncheck select all
        if (!e.target.checked) {
            selectAll.checked = false;
        } else {
            // Check if all are checked
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            selectAll.checked = allChecked;
        }
    }
});

function showAlert(message, type) {
    const alertBox = document.getElementById('alertMessage');
    alertBox.textContent = message;
    alertBox.className = 'message ' + type;
    alertBox.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function sendStatusUpdate(bookingIds, status, paymentMethod = '') {
    try {
        const response = await fetch('../api/admin_update_booking_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_ids: bookingIds,
                status: status,
                payment_method: paymentMethod
            })
        });
        const result = await response.json();
        if (result.status === 'success') {
            showAlert(result.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else if (result.status === 'warning') {
            let errorMsg = result.message + '\n' + result.errors.join('\n');
            showAlert(errorMsg, 'error');
            setTimeout(() => location.reload(), 3000);
        } else {
            showAlert(result.message || 'Update failed', 'error');
            setTimeout(() => location.reload(), 3000);
        }
    } catch (err) {
        showAlert('Network error: ' + err.message, 'error');
    }
}

function updateSingleStatus(bookingId, status, selectElement) {
    if (confirm(`Change status of Booking #${bookingId} to ${status}?`)) {
        sendStatusUpdate([bookingId], status);
    } else {
        // Reset select to previous status style class
        location.reload();
    }
}

function confirmSinglePayment(bookingId) {
    if (confirm(`Confirm payment for Booking #${bookingId}?`)) {
        sendStatusUpdate([bookingId], 'paid', 'Confirmed by Admin');
    }
}

function applyBulkAction() {
    const statusSelect = document.getElementById('bulkStatusSelect');
    const status = statusSelect.value;
    if (!status) {
        alert('Please select a target status.');
        return;
    }
    const checkedIds = Array.from(checkboxes)
        .filter(cb => cb.checked)
        .map(cb => cb.value);

    if (checkedIds.length === 0) {
        alert('No bookings selected.');
        return;
    }

    if (confirm(`Change status of ${checkedIds.length} selected bookings to ${status}?`)) {
        sendStatusUpdate(checkedIds, status);
    }
}

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

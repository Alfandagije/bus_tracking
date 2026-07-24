<?php session_start(); require_once __DIR__ . '/config/helpers.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Smart Bus Tracking</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav>
    <a href="index.php" class="nav-brand"><?= icon('bus') ?> SmartBus Tracker</a>
    <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="nav-links" id="navLinks">
        <a href="index.php">Live Tracking</a>
        <a href="booking.php">Book Seat</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="my_bookings.php" class="active">My Bookings</a>
            <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                <a href="dashboard/index.php">Dashboard</a>
            <?php endif; ?>
            <?php if ($_SESSION['role'] === 'driver'): ?>
                <a href="dashboard/driver/index.php">My Bus</a>
            <?php endif; ?>
            <div class="nav-user">
                <span><?= icon('user') ?> <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                <a href="auth/logout.php" class="btn btn-sm btn-primary">Logout</a>
            </div>
        <?php else: ?>
            <div class="nav-user">
                <a href="auth/login.php" class="btn btn-sm btn-primary">Login</a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2><?= icon('ticket') ?> My Bookings</h2>
        </div>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <p class="text-center" style="color:var(--gray-500);padding:40px 0;">
                Please <a href="auth/login.php">login</a> to view your bookings.
            </p>
        <?php else: ?>
            <div id="bookingsContainer">
                <div class="spinner"></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['user_id'])): ?>
<script>
function esc(str) { return String(str).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];}); }

async function loadBookings() {
    try {
        const res = await fetch('api/get_bookings.php');
        const data = await res.json();
        const container = document.getElementById('bookingsContainer');

        if (data.status === 'success' && data.data.length > 0) {
            let html = `<div class="table-container"><table>
                <thead><tr>
                    <th>ID</th><th>Bus</th><th>Seat</th><th>Date</th><th>Status</th><th>Payment</th><th>Booked On</th>
                </tr></thead><tbody>`;

            data.data.forEach(b => {
                const statusClass = b.status === 'paid' ? 'badge-success' : b.status === 'pending' ? 'badge-warning' : 'badge-danger';
                html += `<tr>
                    <td><strong>#${esc(b.id)}</strong></td>
                    <td>${esc(b.bus_code)} - ${esc(b.bus_name)}</td>
                    <td>${esc(b.seat_number)}</td>
                    <td>${esc(b.booking_date)}</td>
                    <td><span class="badge ${statusClass}">${esc(b.status)}</span></td>
                    <td>${esc(b.payment_method || '-')}</td>
                    <td>${esc(b.created_at)}</td>
                </tr>`;
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center" style="padding:60px 0;">
                    <div style="margin-bottom:12px;"><img src="assets/icons/ticket.svg" class="icon-xl"></div>
                    <h3 style="margin-bottom:8px;">No Bookings Yet</h3>
                    <p style="color:var(--gray-500);margin-bottom:20px;">Book your first seat now!</p>
                    <a href="booking.php" class="btn btn-primary">Book a Seat</a>
                </div>
            `;
        }
    } catch (err) {
        document.getElementById('bookingsContainer').innerHTML = `
            <div class="text-center" style="padding:40px;color:var(--gray-500);">
                Failed to load bookings. <a href="javascript:void(0)" onclick="loadBookings()">Retry</a>
            </div>
        `;
    }
}

loadBookings();
</script>
<?php endif; ?>
<script>
document.getElementById('hamburger')?.addEventListener('click', function() {
    this.classList.toggle('active');
    document.getElementById('navLinks').classList.toggle('open');
});
document.addEventListener('click', function(e) {
    const nav = document.querySelector('nav');
    if (!nav.contains(e.target)) {
        document.getElementById('hamburger')?.classList.remove('active');
        document.getElementById('navLinks')?.classList.remove('open');
    }
});
</script>
</body>
</html>

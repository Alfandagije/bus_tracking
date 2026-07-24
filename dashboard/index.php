<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDb();
} catch (Exception $e) { $db = null; }

if ($db) {
    try { $totalBuses = $db->query("SELECT COUNT(*) FROM buses")->fetchColumn(); } catch (Exception $e) { $totalBuses = 0; }
    try { $totalBookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn(); } catch (Exception $e) { $totalBookings = 0; }
    try { $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn(); } catch (Exception $e) { $totalUsers = 0; }
    try { $pendingSms = $db->query("SELECT COUNT(*) FROM sms_logs WHERE status='pending'")->fetchColumn(); } catch (Exception $e) { $pendingSms = 0; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart Bus Tracking</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&callback=initAdminMap&libraries=geometry" async defer></script>
    <style>
        .bus-marker-label { background: white; border-radius: 4px; padding: 2px 6px; font-size: 11px; font-weight: 600; white-space: nowrap; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
<nav>
    <a href="../index.php" class="nav-brand"><?= icon('bus') ?> SmartBus Tracker</a>
    <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="nav-links" id="navLinks">
        <a href="../index.php">Live Tracking</a>
        <a href="../booking.php">Book Seat</a>
        <a href="../my_bookings.php">My Bookings</a>
        <div class="nav-user">
            <span><?= icon('user') ?> <?= htmlspecialchars($_SESSION['full_name']) ?> (<?= ucfirst($_SESSION['role']) ?>)</span>
            <a href="../auth/logout.php" class="btn btn-sm btn-primary">Logout</a>
        </div>
    </div>
</nav>

<div class="admin-layout">
    <button class="hamburger" id="sidebarToggle" aria-label="Toggle sidebar" style="display:none;">
        <span></span><span></span><span></span>
    </button>
    <div class="admin-sidebar" id="adminSidebar">
        <h3><?= $_SESSION['role'] === 'admin' ? 'Admin' : 'Manager' ?> Panel</h3>
        <a href="index.php" class="active"><?= icon('chart') ?> Dashboard</a>
        <a href="admin_buses.php"><?= icon('bus') ?> Buses</a>
        <a href="admin_drivers.php"><?= icon('user') ?> Drivers</a>
        <a href="admin_routes.php"><?= icon('ticket') ?> Routes</a>
        <a href="admin_bookings.php"><?= icon('ticket') ?> Bookings</a>
        <a href="admin_payments.php"><?= icon('ticket') ?> Payments</a>
        <a href="admin_sms_logs.php"><?= icon('mail') ?> SMS Logs</a>
        <a href="admin_passengers.php"><?= icon('users') ?> Passengers</a>
        <a href="admin_reports.php"><?= icon('chart') ?> Reports</a>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-content">
        <h2 style="margin-bottom:24px;"><?= icon('chart') ?> Admin Dashboard</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><?= icon('bus', 'icon-lg') ?></div>
                <div class="stat-value"><?= sec($totalBuses) ?></div>
                <div class="stat-label">Total Buses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><?= icon('ticket', 'icon-lg') ?></div>
                <div class="stat-value"><?= sec($totalBookings) ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><?= icon('users', 'icon-lg') ?></div>
                <div class="stat-value"><?= sec($totalUsers) ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><?= icon('mail', 'icon-lg') ?></div>
                <div class="stat-value"><?= sec($pendingSms) ?></div>
                <div class="stat-label">Pending SMS</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Live Bus Tracking</h2>
            </div>
            <div id="adminMap" style="height:400px;border-radius:8px;"></div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Recent Bookings</h2>
                <a href="admin_bookings.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div id="recentBookings">
                <div class="spinner"></div>
            </div>
        </div>
    </div>
</div>

<script>
function esc(str) { return String(str).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];}); }

let map;
let markers = {};
let infoWindows = {};
let animationFrames = {};
let mapInitialized = false;
const busColors = ['#1a73e8', '#ea4335', '#34a853', '#fbbc04', '#9334e6'];

function getBusColor(busCode) {
    const idx = parseInt(busCode.replace('BUS', '')) % busColors.length;
    return busColors[idx] || '#1a73e8';
}

function initAdminMap() {
    map = new google.maps.Map(document.getElementById('adminMap'), {
        center: { lat: -1.9441, lng: 30.0619 },
        zoom: 13,
        streetViewControl: false,
        mapTypeControl: true,
        mapTypeControlOptions: { position: google.maps.ControlPosition.TOP_RIGHT, mapTypeIds: ['roadmap', 'satellite', 'hybrid', 'terrain'] },
        fullscreenControl: true,
        styles: [
            { featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] },
            { featureType: 'transit', elementType: 'labels', stylers: [{ visibility: 'off' }] }
        ]
    });
    updateMap();
}

function smoothMoveMarker(marker, targetLat, targetLng, key, duration = 2000) {
    if (animationFrames[key]) {
        cancelAnimationFrame(animationFrames[key]);
    }
    const startPos = marker.getPosition();
    const startLat = startPos.lat();
    const startLng = startPos.lng();
    const startTime = performance.now();

    function animate(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const ease = 1 - Math.pow(1 - progress, 3);
        const lat = startLat + (targetLat - startLat) * ease;
        const lng = startLng + (targetLng - startLng) * ease;
        marker.setPosition({ lat, lng });
        if (progress < 1) {
            animationFrames[key] = requestAnimationFrame(animate);
        } else {
            delete animationFrames[key];
        }
    }
    animationFrames[key] = requestAnimationFrame(animate);
}

async function updateMap() {
    try {
        const res = await fetch('../api/get_buses.php');
        const data = await res.json();
        if (data.status === 'success') {
            const bounds = new google.maps.LatLngBounds();
            let hasCoords = false;

            data.data.forEach(bus => {
                const lat = parseFloat(bus.current_lat);
                const lng = parseFloat(bus.current_lng);
                if (lat && lng) {
                    hasCoords = true;
                    const latLng = { lat, lng };
                    bounds.extend(latLng);

                    if (markers[bus.bus_code]) {
                        smoothMoveMarker(markers[bus.bus_code], lat, lng, bus.bus_code);
                        if (infoWindows[bus.bus_code]) {
                            infoWindows[bus.bus_code].setContent(`
                                <div style="padding:8px;min-width:180px;">
                                    <div style="font-size:18px;margin-bottom:6px;"><img src="../assets/icons/bus.svg" class="icon" style="vertical-align:middle;"> <b>${esc(bus.bus_code)}</b></div>
                                    <div style="font-size:13px;color:#5f6368;">${esc(bus.bus_name)}</div>
                                    <div style="font-size:12px;color:#9aa0a6;margin-top:4px;">
                                        Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}
                                    </div>
                                </div>
                            `);
                        }
                    } else {
                        const marker = new google.maps.Marker({
                            position: latLng,
                            map: map,
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 14,
                                fillColor: getBusColor(bus.bus_code),
                                fillOpacity: 1,
                                strokeColor: '#ffffff',
                                strokeWeight: 3
                            },
                            label: { text: bus.bus_code, fontSize: '11px', color: '#fff', fontWeight: 'bold' },
                            title: `${bus.bus_code} - ${bus.bus_name}`,
                            zIndex: 100
                        });

                        const infoWindow = new google.maps.InfoWindow({
                            content: `
                                <div style="padding:8px;min-width:180px;">
                                    <div style="font-size:18px;margin-bottom:6px;"><img src="../assets/icons/bus.svg" class="icon" style="vertical-align:middle;"> <b>${esc(bus.bus_code)}</b></div>
                                    <div style="font-size:13px;color:#5f6368;">${esc(bus.bus_name)}</div>
                                    <div style="font-size:12px;color:#9aa0a6;margin-top:4px;">
                                        Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}
                                    </div>
                                </div>
                            `
                        });

                        marker.addListener('click', () => {
                            if (infoWindows[bus.bus_code]) infoWindows[bus.bus_code].close();
                            infoWindow.open(map, marker);
                            infoWindows[bus.bus_code] = infoWindow;
                        });

                        markers[bus.bus_code] = marker;
                    }
                }
            });

            if (hasCoords && !mapInitialized) {
                map.fitBounds(bounds, 30);
                mapInitialized = true;
            }
        }
    } catch (err) { console.error(err); }
}

async function loadRecentBookings() {
    try {
        const res = await fetch('../api/get_bookings.php');
        const data = await res.json();
        const container = document.getElementById('recentBookings');
        if (data.status === 'success' && data.data.length > 0) {
            const recent = data.data.slice(0, 10);
            let html = `<div class="table-container"><table>
                <thead><tr><th>ID</th><th>Passenger</th><th>Bus</th><th>Seat</th><th>Status</th><th>Date</th></tr></thead><tbody>`;
            recent.forEach(b => {
                const sc = b.status === 'paid' ? 'badge-success' : b.status === 'pending' ? 'badge-warning' : 'badge-danger';
                html += `<tr>
                    <td>#${esc(b.id)}</td>
                    <td>${esc(b.full_name || '-')}</td>
                    <td>${esc(b.bus_code)}</td>
                    <td>${esc(b.seat_number)}</td>
                    <td><span class="badge ${sc}">${esc(b.status)}</span></td>
                    <td>${esc(b.booking_date)}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-center" style="color:var(--gray-500);padding:20px;">No bookings yet</p>';
        }
    } catch (err) {
        document.getElementById('recentBookings').innerHTML = '<p style="color:var(--gray-500);">Failed to load</p>';
    }
}

loadRecentBookings();
setInterval(updateMap, 5000);
</script>
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

<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Bus Tracking Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php require_once 'config/database.php'; ?>
    <script>
        const GOOGLE_MAPS_API_KEY = '<?= GOOGLE_MAPS_API_KEY ?>';
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&callback=initMap&libraries=geometry&loading=async" defer></script>
    <style>
        #map { height: 100%; width: 100%; }
        .bus-marker-label {
            background: white;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
<nav>
    <a href="index.php" class="nav-brand"><?= icon('bus') ?> SmartBus Tracker</a>
    <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="nav-links" id="navLinks">
        <a href="index.php" class="active">Live Tracking</a>
        <a href="booking.php">Book Seat</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="my_bookings.php">My Bookings</a>
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
    <div class="dashboard-grid">
        <div class="side-panel">
            <div class="card">
                <div class="card-header">
                    <h2><?= icon('bus') ?> Select Bus</h2>
                </div>
                <select id="busSelector" class="bus-selector">
                    <option value="">Loading buses...</option>
                </select>
            </div>

            <div class="card" id="busInfoCard" style="display:none;">
                <div class="bus-info-card">
                    <div class="bus-icon"><?= icon('bus', 'icon-xl') ?></div>
                    <div class="bus-details">
                        <h3 id="busName">-</h3>
                        <p id="busStatus">-</p>
                    </div>
                </div>
                <div class="flex-between mb-4">
                    <span>Last update:</span>
                    <span id="lastUpdate" style="font-size:0.8rem;color:var(--gray-500);">-</span>
                </div>
                <div style="margin-top:8px;padding:8px 12px;background:var(--gray-50,#f8f9fa);border-radius:8px;">
                    <div style="font-size:0.8rem;color:var(--gray-500);">Est. arrival to destination</div>
                    <div id="etaValue" style="font-size:1.3rem;font-weight:700;color:var(--primary);">--</div>
                    <div id="etaDistance" style="font-size:0.75rem;color:var(--gray-400);">--</div>
                </div>
            </div>

            <div class="card" id="seatCard" style="display:none;">
                <div class="card-header">
                    <h2><?= icon('seat') ?> Seat Status</h2>
                    <span id="seatCount" class="badge badge-info">0/0</span>
                </div>
                <div id="seatGrid" class="seat-grid"></div>
            </div>

            <div class="card" id="loginPrompt" style="display:none;">
                <p class="text-center" style="color:var(--gray-500);">
                    <a href="auth/login.php">Login</a> or <a href="auth/register.php">Register</a> to book a seat
                </p>
            </div>
        </div>

        <div class="map-container">
            <div id="map"></div>

        </div>
    </div>
</div>

<script>
function esc(str) { return String(str).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];}); }

function formatTime(t) {
    if (!t) return 'TBA';
    const parts = String(t).split(':');
    let h = parseInt(parts[0], 10);
    if (isNaN(h)) return 'TBA';
    const m = parts[1] || '00';
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return h + ':' + m + ' ' + ampm;
}

let directionsService;

function haversineDistance(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLng/2) * Math.sin(dLng/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

async function fetchETA(busCode) {
    const route = busRouteData[busCode];
    if (!route || !route.dest_lat || !route.dest_lng) return null;
    const marker = markers[busCode];
    if (!marker) return null;
    const pos = marker.getPosition();
    const lat = pos.lat(), lng = pos.lng();

    try {
        const result = await new Promise((resolve) => {
            directionsService.route({
                origin: new google.maps.LatLng(lat, lng),
                destination: new google.maps.LatLng(route.dest_lat, route.dest_lng),
                travelMode: google.maps.TravelMode.DRIVING
            }, (result, status) => resolve({ result, status }));
        });
        if (result.status === 'OK' && result.result.routes.length > 0) {
            const leg = result.result.routes[0].legs[0];
            return {
                minutes: Math.round(leg.duration.value / 60),
                distanceKm: (leg.distance.value / 1000).toFixed(1),
                destination: route.destination
            };
        }
        console.log('Directions API fallback to Haversine, status:', result.status);
    } catch (e) {
        console.log('Directions API error, fallback to Haversine:', e);
    }

    const distKm = haversineDistance(lat, lng, route.dest_lat, route.dest_lng);
    const roadKm = distKm * 1.4;
    const minutes = Math.round((roadKm / 25) * 60);
    return { minutes, distanceKm: roadKm.toFixed(1), destination: route.destination };
}

let isLoadingBuses = false;
let etaRequestId = 0;

async function showETA(busCode) {
    const myId = ++etaRequestId;
    const eta = await fetchETA(busCode);
    if (myId !== etaRequestId) return;
    if (!eta) return;

    let label;
    if (eta.minutes < 1) {
        label = 'Arriving now';
    } else if (eta.minutes < 60) {
        label = eta.minutes + ' min';
    } else {
        const h = Math.floor(eta.minutes / 60);
        const m = eta.minutes % 60;
        label = h + 'h ' + m + 'min';
    }
    document.getElementById('etaValue').textContent = label;
    document.getElementById('etaDistance').textContent = eta.distanceKm + ' km to ' + eta.destination;
}

let map;
let markers = {};
let infoWindows = {};
let currentBusCode = '';
let animationFrames = {};
let initialFitDone = false;
let userHasZoomed = false;
let busRouteData = {};

function initMap() {
    const kigali = { lat: -1.9441, lng: 30.0619 };
    map = new google.maps.Map(document.getElementById('map'), {
        center: kigali,
        zoom: 13,
        streetViewControl: true,
        streetViewControlOptions: { position: google.maps.ControlPosition.RIGHT_BOTTOM },
        mapTypeControl: true,
        mapTypeControlOptions: { position: google.maps.ControlPosition.TOP_RIGHT, mapTypeIds: ['roadmap', 'satellite', 'hybrid', 'terrain'] },
        fullscreenControl: true,
        rotateControl: true,
        tilt: 0,
        styles: [
            { featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] },
            { featureType: 'transit', elementType: 'labels', stylers: [{ visibility: 'off' }] }
        ]
    });

    map.addListener('zoom_changed', function() {
        userHasZoomed = true;
    });

    directionsService = new google.maps.DirectionsService();

    loadBuses();
    setInterval(async () => {
        await loadBuses();
        await refreshSelectedBus();
    }, 3000);
}

const busColors = ['#1a73e8', '#ea4335', '#34a853', '#fbbc04', '#9334e6', '#e67e22', '#2c3e50'];

function getBusColor(busCode) {
    const idx = parseInt(busCode.replace('BUS', '')) % busColors.length;
    return busColors[idx] || '#1a73e8';
}

function createBusMarkerIcon(busCode) {
    const color = getBusColor(busCode);
    return {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 14,
        fillColor: color,
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeWeight: 3,
        labelOrigin: new google.maps.Point(0, 4)
    };
}

function smoothMoveGoogleMarker(marker, targetLat, targetLng, busCode, duration = 2000) {
    if (!marker || !marker.getPosition) return;
    const startPos = marker.getPosition();
    if (!startPos) return;
    if (animationFrames[busCode]) {
        cancelAnimationFrame(animationFrames[busCode]);
    }

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
            animationFrames[busCode] = requestAnimationFrame(animate);
        } else {
            delete animationFrames[busCode];
        }
    }
    animationFrames[busCode] = requestAnimationFrame(animate);
}

async function loadBuses() {
    try {
        const res = await fetch('api/get_buses.php');
        const data = await res.json();
        const selector = document.getElementById('busSelector');
        const previousSelection = selector.value || currentBusCode;

        if (data.status === 'success' && data.data.length > 0) {
            isLoadingBuses = true;
            selector.innerHTML = '<option value="">Select a bus...</option>';
            data.data.forEach(bus => {
                const opt = document.createElement('option');
                opt.value = bus.bus_code;
                opt.textContent = `${bus.bus_code} - ${bus.bus_name}`;
                selector.appendChild(opt);
            });

            // Restore the previously selected bus after rebuilding
            if (previousSelection) {
                selector.value = previousSelection;
            }
            isLoadingBuses = false;

            const bounds = new google.maps.LatLngBounds();
            let hasValidCoords = false;

            data.data.forEach(bus => {
                const lat = parseFloat(bus.current_lat);
                const lng = parseFloat(bus.current_lng);
                if (lat && lng) {
                    hasValidCoords = true;
                    const latLng = { lat, lng };
                    bounds.extend(latLng);

                    // Store route data for ETA calculation
                    busRouteData[bus.bus_code] = {
                        route_name: bus.route_name || null,
                        origin: bus.origin || null,
                        destination: bus.destination || null,
                        dest_lat: bus.dest_lat ? parseFloat(bus.dest_lat) : null,
                        dest_lng: bus.dest_lng ? parseFloat(bus.dest_lng) : null
                    };

                    if (markers[bus.bus_code]) {
                        if (bus.bus_code !== currentBusCode) {
                            smoothMoveGoogleMarker(markers[bus.bus_code], lat, lng, bus.bus_code);
                        }
                    } else {
                        const marker = new google.maps.Marker({
                            position: latLng,
                            map: map,
                            icon: createBusMarkerIcon(bus.bus_code),
                            label: {
                                text: bus.bus_code,
                                fontSize: '11px',
                                color: '#fff',
                                fontWeight: 'bold'
                            },
                            title: `${bus.bus_code} - ${bus.bus_name}`,
                            zIndex: 100
                        });

                        const infoWindow = new google.maps.InfoWindow({
                            content: `
                                <div style="padding:8px;min-width:180px;">
                                    <div style="font-size:18px;margin-bottom:6px;"><img src="assets/icons/bus.svg" class="icon" style="vertical-align:middle;"> <b>${esc(bus.bus_code)}</b></div>
                                    <div style="font-size:13px;color:#5f6368;">${esc(bus.bus_name)}</div>
                                    ${bus.route_name ? '<div style="font-size:12px;color:#1a73e8;margin-top:2px;">📍 ' + esc(bus.route_name) + '</div>' : ''}
                                    <div style="font-size:12px;color:#1a73e8;margin-top:2px;">🕒 Departs ${esc(formatTime(bus.departure_time))} • RWF ${Number(bus.fare || 0).toLocaleString()}</div>
                                    <div style="font-size:12px;color:#9aa0a6;margin-top:4px;">
                                        Lat: ${lat.toFixed(6)}<br>
                                        Lng: ${lng.toFixed(6)}
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

            if (!initialFitDone && !currentBusCode && hasValidCoords) {
                map.fitBounds(bounds, 50);
                initialFitDone = true;
            }
        }
    } catch (err) {
        console.error('Failed to load buses:', err);
    }
}

async function loadSeats(busCode) {
    if (!busCode) {
        document.getElementById('seatCard').style.display = 'none';
        document.getElementById('busInfoCard').style.display = 'none';
        return;
    }

    try {
        const [busRes, seatsRes] = await Promise.all([
            fetch(`api/get_bus_location.php?bus_code=${busCode}`),
            fetch(`api/get_seats.php?bus_code=${busCode}`)
        ]);
        const busData = await busRes.json();
        const seatsData = await seatsRes.json();

        if (busData.status === 'success') {
            const bus = busData.data;
            document.getElementById('busInfoCard').style.display = 'block';
            document.getElementById('busName').textContent = `${bus.bus_code} - ${bus.bus_name}`;
            document.getElementById('busStatus').innerHTML = `<span class="badge badge-success">Active</span> <span class="badge badge-info">Departs ${esc(formatTime(bus.departure_time))}</span> <span class="badge badge-info">RWF ${Number(bus.fare || 0).toLocaleString()}</span>`;
            document.getElementById('lastUpdate').textContent = bus.last_update || 'Just now';

            if (parseFloat(bus.current_lat)) {
                map.panTo({ lat: parseFloat(bus.current_lat), lng: parseFloat(bus.current_lng) });
            }
        }

        if (seatsData.status === 'success') {
            const seats = seatsData.data;
            const grid = document.getElementById('seatGrid');
            const card = document.getElementById('seatCard');
            card.style.display = 'block';

            const total = seats.length;
            const available = seats.filter(s => s.status === 'available').length;
            document.getElementById('seatCount').textContent = `${available}/${total} Free`;

            grid.innerHTML = '';
            const colMap = { A1: 1, A2: 4, A3: 1, A4: 4 };
            const rowMap = { A1: 1, A2: 1, A3: 2, A4: 2 };
            const icons = { available: 'seat.svg', occupied: 'person.svg', booked: 'lock.svg' };
            seats.forEach(seat => {
                const div = document.createElement('div');
                div.className = `seat ${seat.status}`;
                div.style.gridColumn = colMap[seat.seat_number] || '1';
                div.style.gridRow = rowMap[seat.seat_number] || '1';
                const img = document.createElement('img');
                img.className = 'seat-icon';
                img.src = `assets/icons/${icons[seat.status] || 'seat.svg'}`;
                img.alt = seat.status;
                div.appendChild(img);
                const span = document.createElement('span');
                span.textContent = seat.seat_number;
                div.appendChild(span);
                div.dataset.seatNumber = seat.seat_number;
                div.dataset.status = seat.status;

                if (seat.status === 'available') {
                    div.addEventListener('click', () => {
                        if (<?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>) {
                            bookSeat(busCode, seat.seat_number);
                        } else {
                            alert('Please login to book a seat');
                            window.location.href = 'auth/login.php';
                        }
                    });
                }

                grid.appendChild(div);
            });

            document.getElementById('loginPrompt').style.display = <?= isset($_SESSION['user_id']) ? "'none'" : "'block'" ?>;
        }
    } catch (err) {
        console.error('Failed to load seats:', err);
    }
}

async function bookSeat(busCode, seatNumber) {
    if (!confirm(`Book seat ${seatNumber} on ${busCode}?`)) return;

    try {
        const res = await fetch('api/book_seat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bus_code: busCode, seat_number: seatNumber })
        });
        const result = await res.json();

        if (result.status === 'success') {
            alert(`✅ ${result.message}`);
            loadSeats(busCode);
        } else {
            alert(`❌ ${result.message}`);
        }
    } catch (err) {
        alert('Network error. Please try again.');
    }
}

document.getElementById('busSelector').addEventListener('change', function() {
    if (isLoadingBuses) return;
    // Reset previous selected bus marker to normal size
    for (const code in markers) {
        markers[code].setIcon(createBusMarkerIcon(code));
        markers[code].setZIndex(100);
    }
    currentBusCode = this.value;
    if (currentBusCode) {
        userHasZoomed = false;
        document.getElementById('etaValue').textContent = '--';
        document.getElementById('etaDistance').textContent = '--';
        document.getElementById('seatCard').style.display = 'block';
        loadSeats(currentBusCode);
        showETA(currentBusCode);
        // Immediately highlight and zoom to the selected bus
        if (markers[currentBusCode]) {
            const pos = markers[currentBusCode].getPosition();
            map.panTo(pos);
            map.setZoom(16);
            markers[currentBusCode].setIcon({
                path: google.maps.SymbolPath.CIRCLE,
                scale: 18,
                fillColor: getBusColor(currentBusCode),
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 4,
                labelOrigin: new google.maps.Point(0, 4)
            });
            markers[currentBusCode].setZIndex(999);
        }
    } else {
        document.getElementById('seatCard').style.display = 'none';
        document.getElementById('busInfoCard').style.display = 'none';
        // Zoom back out to show all buses
        const bounds = new google.maps.LatLngBounds();
        let hasValid = false;
        for (const code in markers) {
            const pos = markers[code].getPosition();
            if (pos) { bounds.extend(pos); hasValid = true; }
        }
        if (hasValid) map.fitBounds(bounds, 50);
    }
});


async function refreshSelectedBus() {
    if (!currentBusCode) return;

    try {
        const [busRes, seatsRes] = await Promise.all([
            fetch(`api/get_bus_location.php?bus_code=${currentBusCode}`),
            fetch(`api/get_seats.php?bus_code=${currentBusCode}`)
        ]);
        const busData = await busRes.json();
        const seatsData = await seatsRes.json();

        if (busData.status === 'success') {
            const bus = busData.data;
            const lat = parseFloat(bus.current_lat);
            const lng = parseFloat(bus.current_lng);

            document.getElementById('busName').textContent = `${bus.bus_code} - ${bus.bus_name}`;
            document.getElementById('busStatus').innerHTML = `<span class="badge badge-success">Active</span> <span class="badge badge-info">Departs ${esc(formatTime(bus.departure_time))}</span> <span class="badge badge-info">RWF ${Number(bus.fare || 0).toLocaleString()}</span>`;
            document.getElementById('lastUpdate').textContent = bus.last_update || 'Just now';

            if (lat && lng) {
                if (!userHasZoomed) map.setZoom(16);
                map.panTo({ lat, lng });
                if (markers[currentBusCode]) {
                    smoothMoveGoogleMarker(markers[currentBusCode], lat, lng, currentBusCode);
                    markers[currentBusCode].setIcon({
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 18,
                        fillColor: getBusColor(currentBusCode),
                        fillOpacity: 1,
                        strokeColor: '#ffffff',
                        strokeWeight: 4,
                        labelOrigin: new google.maps.Point(0, 4)
                    });
                    markers[currentBusCode].setZIndex(999);
                }
            }
            showETA(currentBusCode);
        }

        if (seatsData.status === 'success') {
            const seats = seatsData.data;
            const total = seats.length;
            const available = seats.filter(s => s.status === 'available').length;
            document.getElementById('seatCount').textContent = `${available}/${total} Free`;

            const grid = document.getElementById('seatGrid');
            if (grid.children.length !== seats.length || grid.dataset.busCode !== currentBusCode) {
                grid.dataset.busCode = currentBusCode;
                grid.innerHTML = '';
                const colMap = { A1: 1, A2: 4, A3: 1, A4: 4 };
                const rowMap = { A1: 1, A2: 1, A3: 2, A4: 2 };
                const icons = { available: 'seat.svg', occupied: 'person.svg', booked: 'lock.svg' };
                seats.forEach(seat => {
                    const div = document.createElement('div');
                    div.className = `seat ${seat.status}`;
                    div.style.gridColumn = colMap[seat.seat_number] || '1';
                    div.style.gridRow = rowMap[seat.seat_number] || '1';
                    const img = document.createElement('img');
                    img.className = 'seat-icon';
                    img.src = `assets/icons/${icons[seat.status] || 'seat.svg'}`;
                    img.alt = seat.status;
                    div.appendChild(img);
                    const span = document.createElement('span');
                    span.textContent = seat.seat_number;
                    div.appendChild(span);
                    div.dataset.seatNumber = seat.seat_number;
                    div.dataset.status = seat.status;

                    if (seat.status === 'available') {
                        div.addEventListener('click', () => {
                            if (<?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>) {
                                bookSeat(currentBusCode, seat.seat_number);
                            } else {
                                alert('Please login to book a seat');
                                window.location.href = 'auth/login.php';
                            }
                        });
                    }

                    grid.appendChild(div);
                });
            } else {
                const colMap = { A1: 1, A2: 4, A3: 1, A4: 4 };
                const rowMap = { A1: 1, A2: 1, A3: 2, A4: 2 };
                const icons = { available: 'seat.svg', occupied: 'person.svg', booked: 'lock.svg' };
                seats.forEach((seat, i) => {
                    const div = grid.children[i];
                    if (!div) return;
                    if (div.dataset.status !== seat.status) {
                        div.className = `seat ${seat.status}`;
                        div.querySelector('img').src = `assets/icons/${icons[seat.status] || 'seat.svg'}`;
                        div.querySelector('img').alt = seat.status;
                        div.dataset.status = seat.status;
                    }
                });
            }
        }
    } catch (err) {
        console.error('Refresh error:', err);
    }
}

</script>
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
<?php include 'partials/footer.php'; ?>
</body>
</html>

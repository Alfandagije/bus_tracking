<?php session_start(); require_once __DIR__ . '/config/helpers.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Seat - Smart Bus Tracking</title>
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
        <a href="booking.php" class="active">Book Seat</a>
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
    <div style="max-width:800px;margin:0 auto;">
        <div class="card">
            <div class="card-header">
                <h2><?= icon('ticket') ?> Book a Seat</h2>
            </div>

            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="message" style="display:block;background:#fff8e1;color:#f57f17;border:1px solid #ffe082;text-align:center;">
                    Please <a href="auth/login.php" style="font-weight:700;">login</a> or <a href="auth/register.php" style="font-weight:700;">register</a> to book a seat.
                </div>
            <?php else: ?>
                <div class="booking-steps">
                    <div class="step active" id="step1">1. Select Bus</div>
                    <div class="step" id="step2">2. Choose Seat</div>
                    <div class="step" id="step3">3. Pay & Confirm</div>
                </div>

                <div id="step1Content">
                    <div class="form-group">
                        <label>Select Bus</label>
                        <select id="bookBusSelector" class="bus-selector">
                            <option value="">Loading buses...</option>
                        </select>
                    </div>
                </div>

                <div id="step2Content" style="display:none;">
                    <div id="selectedBusInfo" class="bus-info-card"></div>
                    <p style="margin-bottom:12px;font-size:0.875rem;color:var(--gray-500);">
                        <?= icon('lightbulb') ?> Green seats are available. Click to select.
                    </p>
                    <div id="bookSeatGrid" class="seat-grid" style="margin-bottom:20px;"></div>
                    <div style="text-align:center;">
                        <p style="margin-bottom:8px;">Selected: <strong id="selectedSeatDisplay">-</strong></p>
                        <button id="confirmBookingBtn" class="btn btn-primary" disabled>Confirm Booking</button>
                    </div>
                </div>

                <div id="step3Content" style="display:none;">
                    <div id="bookingResult" class="text-center"></div>
                    <div id="paymentSection" style="display:none;margin-top:16px;">
                        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px;margin-bottom:16px;">
                            <h3 style="margin-bottom:8px;">Pay with MTN MoMo</h3>
                            <p style="color:var(--gray-500);font-size:0.875rem;margin-bottom:12px;">Enter your MTN mobile money number to complete payment.</p>
                            <div style="margin-bottom:12px;">
                                <label style="font-weight:600;font-size:0.875rem;display:block;margin-bottom:4px;">Phone Number</label>
                                <input type="tel" id="momoPhone" placeholder="+250788123456" value="<?= htmlspecialchars($_SESSION['phone'] ?? '') ?>" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:1rem;">
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button id="payMomoBtn" class="btn btn-primary" onclick="payWithMomo()" style="flex:1;">Pay with MTN MoMo</button>
                                <button class="btn" style="background:var(--gray-100);" onclick="skipPayment()">Pay Later</button>
                            </div>
                        </div>
                        <div id="paymentStatus" style="display:none;"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['user_id'])): ?>
<script>
function esc(str) { return String(str).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];}); }

let selectedSeat = null;
let selectedBusCode = '';

async function loadBuses() {
    try {
        const res = await fetch('api/get_buses.php');
        const data = await res.json();
        const selector = document.getElementById('bookBusSelector');
        if (data.status === 'success' && data.data.length > 0) {
            selector.innerHTML = '<option value="">Choose a bus route...</option>';
            data.data.forEach(bus => {
                const opt = document.createElement('option');
                opt.value = bus.bus_code;
                opt.textContent = `${bus.bus_code} - ${bus.bus_name} (${bus.total_seats} seats)`;
                selector.appendChild(opt);
            });
        }
    } catch (err) {
        console.error(err);
    }
}

async function loadSeatsForBooking(busCode) {
    try {
        const res = await fetch(`api/get_seats.php?bus_code=${busCode}`);
        const data = await res.json();
        if (data.status === 'success') {
            const grid = document.getElementById('bookSeatGrid');
            grid.innerHTML = '';

            const busRes = await fetch(`api/get_bus_location.php?bus_code=${busCode}`);
            const busData = await busRes.json();
            if (busData.status === 'success') {
                document.getElementById('selectedBusInfo').innerHTML = `
                    <div class="bus-icon"><img src="assets/icons/bus.svg" class="icon-xl"></div>
                    <div class="bus-details">
                        <h3>${esc(busData.data.bus_code)} - ${esc(busData.data.bus_name)}</h3>
                        <p><span class="badge badge-success">Active</span></p>
                    </div>
                `;
            }

            const colMap = { A1: 1, A2: 4, A3: 1, A4: 4 };
            const rowMap = { A1: 1, A2: 1, A3: 2, A4: 2 };
            const icons = { available: 'seat.svg', occupied: 'person.svg', booked: 'lock.svg' };
            data.data.forEach(seat => {
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

                if (seat.status === 'available') {
                    div.style.cursor = 'pointer';
                    div.addEventListener('click', function() {
                        document.querySelectorAll('#bookSeatGrid .seat.available').forEach(s => {
                            s.style.outline = 'none';
                            s.style.outlineOffset = '0';
                        });
                        this.style.outline = '3px solid var(--primary)';
                        this.style.outlineOffset = '2px';
                        selectedSeat = this.dataset.seatNumber;
                        document.getElementById('selectedSeatDisplay').textContent = selectedSeat;
                        document.getElementById('confirmBookingBtn').disabled = false;
                    });
                }

                grid.appendChild(div);
            });
        }
    } catch (err) {
        console.error(err);
    }
}

document.getElementById('bookBusSelector').addEventListener('change', function() {
    selectedBusCode = this.value;
    selectedSeat = null;
    document.getElementById('selectedSeatDisplay').textContent = '-';
    document.getElementById('confirmBookingBtn').disabled = true;

    if (selectedBusCode) {
        document.getElementById('step1').classList.remove('active');
        document.getElementById('step1').classList.add('completed');
        document.getElementById('step2').classList.add('active');
        document.getElementById('step1Content').style.display = 'none';
        document.getElementById('step2Content').style.display = 'block';
        loadSeatsForBooking(selectedBusCode);
    }
});

let lastBookingId = null;

async function payWithMomo() {
    const phone = document.getElementById('momoPhone').value.trim();
    if (!phone) { alert('Please enter your MTN MoMo number'); return; }
    
    const btn = document.getElementById('payMomoBtn');
    btn.disabled = true;
    btn.textContent = 'Processing...';
    
    try {
        const res = await fetch('api/momo_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ booking_id: lastBookingId, phone: phone })
        });
        const result = await res.json();
        
        const statusDiv = document.getElementById('paymentStatus');
        statusDiv.style.display = 'block';
        
        if (result.status === 'success') {
            statusDiv.innerHTML = '<div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;padding:12px;"><strong>Payment Initiated!</strong><br>' + esc(result.message) + '</div>';
            if (result.transaction_id) {
                checkPaymentStatus(lastBookingId);
            }
        } else {
            statusDiv.innerHTML = '<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:12px;"><strong>Payment Failed</strong><br>' + esc(result.message) + '</div>';
            btn.disabled = false;
            btn.textContent = 'Retry Payment';
        }
    } catch (err) {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Pay with MTN MoMo';
    }
}

function skipPayment() {
    document.getElementById('paymentSection').style.display = 'none';
    document.getElementById('bookingResult').innerHTML = `
        <div style="margin-bottom:16px;"><img src="assets/icons/check.svg" class="icon-xl"></div>
        <h3 style="margin-bottom:8px;">Booking Created!</h3>
        <p style="color:var(--gray-500);margin-bottom:12px;">Payment will be collected by the driver.</p>
        <div class="bus-info-card" style="justify-content:center;">
            <div><img src="assets/icons/bus.svg" class="icon" style="vertical-align:middle;"> ${esc(selectedBusCode)}</div>
            <div><img src="assets/icons/seat.svg" class="icon" style="vertical-align:middle;"> Seat ${esc(selectedSeat)}</div>
            <div><img src="assets/icons/ticket.svg" class="icon" style="vertical-align:middle;"> #${esc(lastBookingId)}</div>
        </div>
        <div style="margin-top:20px;">
            <a href="my_bookings.php" class="btn btn-primary">View My Bookings</a>
            <a href="booking.php" class="btn" style="background:var(--gray-100);margin-left:8px;">Book Another</a>
        </div>
    `;
}

async function checkPaymentStatus(bookingId) {
    let attempts = 0;
    const maxAttempts = 20;
    const interval = setInterval(async () => {
        attempts++;
        if (attempts > maxAttempts) { clearInterval(interval); return; }
        try {
            const res = await fetch(`api/momo_check_status.php?booking_id=${bookingId}`);
            const data = await res.json();
            if (data.status === 'success' && data.data) {
                if (data.data.status === 'successful') {
                    clearInterval(interval);
                    document.getElementById('paymentSection').style.display = 'none';
                    document.getElementById('bookingResult').innerHTML = `
                        <div style="margin-bottom:16px;"><img src="assets/icons/check.svg" class="icon-xl"></div>
                        <h3 style="margin-bottom:8px;">Payment Confirmed!</h3>
                        <p style="color:var(--gray-500);margin-bottom:12px;">Your payment of RWF ${data.data.amount} via MTN MoMo has been confirmed.</p>
                        <div class="bus-info-card" style="justify-content:center;">
                            <div><img src="assets/icons/bus.svg" class="icon" style="vertical-align:middle;"> ${esc(selectedBusCode)}</div>
                            <div><img src="assets/icons/seat.svg" class="icon" style="vertical-align:middle;"> Seat ${esc(selectedSeat)}</div>
                            <div><img src="assets/icons/ticket.svg" class="icon" style="vertical-align:middle;"> #${esc(bookingId)}</div>
                        </div>
                        <div style="margin-top:20px;">
                            <a href="my_bookings.php" class="btn btn-primary">View My Bookings</a>
                            <a href="booking.php" class="btn" style="background:var(--gray-100);margin-left:8px;">Book Another</a>
                        </div>
                    `;
                } else if (data.data.status === 'failed') {
                    clearInterval(interval);
                    document.getElementById('paymentStatus').innerHTML = '<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:12px;"><strong>Payment Failed</strong><br>The payment was not completed. Please try again.</div>';
                    document.getElementById('payMomoBtn').disabled = false;
                    document.getElementById('payMomoBtn').textContent = 'Retry Payment';
                }
            }
        } catch (e) {}
    }, 3000);
}

async function confirmBookingAndPay() {
    if (!selectedSeat || !selectedBusCode) return;
    if (!confirm(`Book ${selectedBusCode} - Seat ${selectedSeat}?`)) return;
    
    const btn = document.getElementById('confirmBookingBtn');
    btn.disabled = true;
    btn.textContent = 'Booking...';
    
    try {
        const res = await fetch('api/book_seat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bus_code: selectedBusCode, seat_number: selectedSeat })
        });
        const result = await res.json();
        
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step2').classList.add('completed');
        document.getElementById('step3').classList.add('active');
        document.getElementById('step2Content').style.display = 'none';
        document.getElementById('step3Content').style.display = 'block';
        
        const resultDiv = document.getElementById('bookingResult');
        if (result.status === 'success') {
            lastBookingId = result.booking_id;
            resultDiv.innerHTML = `
                <div style="margin-bottom:16px;"><img src="assets/icons/check.svg" class="icon-xl"></div>
                <h3 style="margin-bottom:8px;">Seat Reserved!</h3>
                <p style="color:var(--gray-500);margin-bottom:12px;">Complete payment to confirm your booking.</p>
                <div class="bus-info-card" style="justify-content:center;">
                    <div><img src="assets/icons/bus.svg" class="icon" style="vertical-align:middle;"> ${esc(selectedBusCode)}</div>
                    <div><img src="assets/icons/seat.svg" class="icon" style="vertical-align:middle;"> Seat ${esc(selectedSeat)}</div>
                    <div><img src="assets/icons/ticket.svg" class="icon" style="vertical-align:middle;"> #${esc(result.booking_id)}</div>
                </div>
            `;
            document.getElementById('paymentSection').style.display = 'block';
        } else {
            resultDiv.innerHTML = `
                <div style="margin-bottom:16px;"><img src="assets/icons/x.svg" class="icon-xl"></div>
                <h3 style="margin-bottom:8px;">Booking Failed</h3>
                <p style="color:var(--gray-500);">${esc(result.message)}</p>
                <div style="margin-top:20px;">
                    <button onclick="location.reload()" class="btn btn-primary">Try Again</button>
                </div>
            `;
        }
    } catch (err) {
        alert('Network error');
        const btn = document.getElementById('confirmBookingBtn');
        btn.disabled = false;
        btn.textContent = 'Confirm Booking';
    }
}

document.getElementById('confirmBookingBtn').addEventListener('click', confirmBookingAndPay);

loadBuses();
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

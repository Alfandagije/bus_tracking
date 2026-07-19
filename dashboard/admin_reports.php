<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
try { $db = getDb(); } catch (Exception $e) { $db = null; }
$buses = [];
if ($db) {
    try { $buses = $db->query("SELECT bus_code, bus_name FROM buses ORDER BY bus_code")->fetchAll(); } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin - Smart Bus Tracking</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .report-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
        .btn-view { background: #6c5ce7; color: #fff; }
        .btn-view:hover { background: #5a4bd1; transform: translateY(-1px); box-shadow: var(--shadow); }
        .btn-download { background: var(--primary); color: #fff; }
        .btn-download:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: var(--shadow); }
        .btn-email { background: #34a853; color: #fff; }
        .btn-email:hover { background: #2d9249; transform: translateY(-1px); box-shadow: var(--shadow); }
        .btn-preview { background: #ff9800; color: #fff; }
        .btn-preview:hover { background: #e68a00; transform: translateY(-1px); box-shadow: var(--shadow); }
        .stats-grid-report { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-top: 12px; }
        .stat-mini { background: var(--gray-50); border-radius: 10px; padding: 14px; text-align: center; border: 1px solid var(--gray-200); transition: all 0.2s; }
        .stat-mini:hover { border-color: var(--primary); box-shadow: var(--shadow); }
        .stat-mini .stat-num { font-size: 1.6rem; font-weight: 700; color: var(--primary); }
        .stat-mini .stat-label { font-size: 0.75rem; color: var(--gray-500); margin-top: 2px; }
        .pdf-viewer-container { margin-top: 20px; display: none; }
        .pdf-viewer-container.active { display: block; }
        .pdf-viewer-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .pdf-viewer-header h3 { font-size: 1rem; font-weight: 600; }
        .pdf-iframe { width: 100%; height: 70vh; min-height: 500px; border: 2px solid var(--gray-200); border-radius: var(--radius); }
        .date-range-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .loading-text { color: var(--gray-500); font-style: italic; font-size: 0.85rem; }
        @media (max-width: 600px) { .date-range-row { grid-template-columns: 1fr; } }
    </style>
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
        <a href="admin_payments.php"><?= icon('ticket') ?> Payments</a>
        <a href="admin_sms_logs.php"><?= icon('mail') ?> SMS Logs</a>
        <a href="admin_passengers.php"><?= icon('users') ?> Passengers</a>
        <a href="admin_reports.php" class="active"><?= icon('chart') ?> Reports</a>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-content">
        <h2 style="margin-bottom:24px;"><?= icon('chart') ?> Reports</h2>
        <div id="alertMessage" class="message" style="margin-bottom: 20px;"></div>

        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <h2>Generate Report</h2>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:16px;">
                <div class="form-group">
                    <label style="font-weight:600;margin-bottom:4px;display:block;">Report Type</label>
                    <select id="reportType" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                        <option value="bookings">Bookings Report</option>
                        <option value="payments">Payments Report</option>
                        <option value="drivers">Drivers Report</option>
                        <option value="summary">Summary Report</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-weight:600;margin-bottom:4px;display:block;">From Date</label>
                    <input type="date" id="dateFrom" value="<?= date('Y-m-01') ?>" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                </div>
                <div class="form-group">
                    <label style="font-weight:600;margin-bottom:4px;display:block;">To Date</label>
                    <input type="date" id="dateTo" value="<?= date('Y-m-d') ?>" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                </div>
                <div class="form-group">
                    <label style="font-weight:600;margin-bottom:4px;display:block;">Bus (Optional)</label>
                    <select id="reportBus" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                        <option value="">All Buses</option>
                        <?php foreach ($buses as $b): ?>
                            <option value="<?= sec($b['bus_code']) ?>"><?= sec($b['bus_code']) ?> - <?= sec($b['bus_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="report-actions">
                <button class="btn btn-view" onclick="viewPdf()" title="View PDF in browser">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    View PDF
                </button>
                <button class="btn btn-download" onclick="downloadPdf()" title="Download PDF file">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download PDF
                </button>
                <button class="btn btn-email" onclick="openEmailModal()" title="Email report as PDF attachment">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    Email Report
                </button>
                <button class="btn btn-preview" onclick="loadPreview()" title="Quick preview of report stats">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    Quick Preview
                </button>
            </div>
        </div>

        <div class="card" id="statsCard" style="margin-bottom:24px;">
            <div class="card-header">
                <h2>Report Statistics</h2>
            </div>
            <div id="statsContent">
                <p class="loading-text">Click "Quick Preview" to see statistics for the selected date range, or generate a PDF report directly.</p>
            </div>
        </div>

        <div class="pdf-viewer-container" id="pdfViewer">
            <div class="card">
                <div class="pdf-viewer-header">
                    <h2>PDF Report Viewer</h2>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-sm btn-download" onclick="downloadPdf()">Download</button>
                        <button class="btn btn-sm" style="background:var(--gray-100);" onclick="closePdfViewer()">Close</button>
                    </div>
                </div>
                <iframe id="pdfFrame" class="pdf-iframe" src=""></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="emailModal">
    <div class="modal">
        <h3>Email Report</h3>
        <p style="font-size:0.85rem;color:var(--gray-500);margin-bottom:12px;">The PDF report will be generated and sent as an email attachment.</p>
        <div class="form-group">
            <label style="font-weight:600;margin-bottom:4px;display:block;">Recipient Email</label>
            <input type="email" id="emailTo" placeholder="admin@bustracking.rw" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
        </div>
        <div class="modal-actions">
            <button class="btn" style="background:var(--gray-100);" onclick="closeEmailModal()">Cancel</button>
            <button class="btn btn-email" onclick="sendEmail()" id="emailSendBtn">Send Report</button>
        </div>
    </div>
</div>

<script>
function showAlert(msg, type) {
    const e = document.getElementById('alertMessage');
    e.textContent = msg;
    e.className = 'message ' + type;
    e.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => { e.style.display = 'none'; }, 8000);
}

function validateDates() {
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    if (!from || !to) { showAlert('Please select both From and To dates.', 'error'); return false; }
    if (from > to) { showAlert('From date cannot be after To date.', 'error'); return false; }
    return true;
}

function getParams() {
    return 'type=' + encodeURIComponent(document.getElementById('reportType').value) +
           '&from=' + encodeURIComponent(document.getElementById('dateFrom').value) +
           '&to=' + encodeURIComponent(document.getElementById('dateTo').value) +
           '&bus_code=' + encodeURIComponent(document.getElementById('reportBus').value);
}

function viewPdf() {
    if (!validateDates()) return;
    const url = '../api/generate_report.php?' + getParams() + '&mode=view';
    const viewer = document.getElementById('pdfViewer');
    const frame = document.getElementById('pdfFrame');
    frame.src = url;
    viewer.classList.add('active');
    viewer.scrollIntoView({ behavior: 'smooth' });
}

function closePdfViewer() {
    const viewer = document.getElementById('pdfViewer');
    const frame = document.getElementById('pdfFrame');
    frame.src = '';
    viewer.classList.remove('active');
}

function downloadPdf() {
    if (!validateDates()) return;
    window.location.href = '../api/generate_report.php?' + getParams();
}

function openEmailModal() {
    if (!validateDates()) return;
    document.getElementById('emailModal').classList.add('active');
}

function closeEmailModal() {
    document.getElementById('emailModal').classList.remove('active');
}

async function sendEmail() {
    const email = document.getElementById('emailTo').value;
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('Please enter a valid email address');
        return;
    }
    const btn = document.getElementById('emailSendBtn');
    btn.textContent = 'Sending...';
    btn.disabled = true;
    const body = {
        email: email,
        type: document.getElementById('reportType').value,
        from: document.getElementById('dateFrom').value,
        to: document.getElementById('dateTo').value,
        bus_code: document.getElementById('reportBus').value
    };
    try {
        const res = await fetch('../api/email_report.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const r = await res.json();
        closeEmailModal();
        if (r.status === 'success') showAlert(r.message, 'success');
        else showAlert(r.message, 'error');
    } catch (e) {
        closeEmailModal();
        showAlert('Failed to send email. Please try again.', 'error');
    }
    btn.textContent = 'Send Report';
    btn.disabled = false;
}

async function loadPreview() {
    if (!validateDates()) return;
    const content = document.getElementById('statsContent');
    content.innerHTML = '<p class="loading-text">Loading statistics...</p>';
    try {
        const res = await fetch('../api/report_preview.php?' + getParams());
        const data = await res.json();
        if (data.status === 'success') {
            const s = data.stats;
            content.innerHTML = `
                <div class="stats-grid-report">
                    <div class="stat-mini">
                        <div class="stat-num">${s.total_bookings}</div>
                        <div class="stat-label">Total Bookings</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-num">${s.paid_bookings}</div>
                        <div class="stat-label">Paid Bookings</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-num">${s.pending_bookings}</div>
                        <div class="stat-label">Pending Bookings</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-num">${s.successful_payments}</div>
                        <div class="stat-label">Successful Payments</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-num">RWF ${s.revenue.toLocaleString()}</div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>
                <p style="margin-top:12px;font-size:0.8rem;color:var(--gray-500);">Period: ${document.getElementById('dateFrom').value} to ${document.getElementById('dateTo').value}${document.getElementById('reportBus').value ? ' | Bus: ' + document.getElementById('reportBus').value : ''}</p>
            `;
        } else {
            content.innerHTML = '<p class="loading-text">Failed to load statistics.</p>';
        }
    } catch (e) {
        content.innerHTML = '<p class="loading-text">Error loading statistics. Please try again.</p>';
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

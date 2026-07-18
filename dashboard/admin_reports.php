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
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <button class="btn btn-primary" onclick="downloadPdf()">📥 Download PDF</button>
                <button class="btn" style="background:#34a853;color:#fff;" onclick="openEmailModal()">📧 Email Report</button>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Quick Stats</h2>
            </div>
            <div id="quickStats" style="padding:12px;color:var(--gray-500);">Click "Download PDF" to generate a report with full details.</div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="emailModal">
    <div class="modal">
        <h3>Email Report</h3>
        <div class="form-group">
            <label style="font-weight:600;margin-bottom:4px;display:block;">Recipient Email</label>
            <input type="email" id="emailTo" placeholder="admin@bustracking.rw" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
        </div>
        <div class="modal-actions">
            <button class="btn" style="background:var(--gray-100);" onclick="closeEmailModal()">Cancel</button>
            <button class="btn" style="background:#34a853;color:#fff;" onclick="sendEmail()">Send</button>
        </div>
    </div>
</div>

<script>
function showAlert(msg,type){const e=document.getElementById('alertMessage');e.textContent=msg;e.className='message '+type;e.style.display='block';window.scrollTo({top:0,behavior:'smooth'});}
function getParams(){return 'type='+document.getElementById('reportType').value+'&from='+document.getElementById('dateFrom').value+'&to='+document.getElementById('dateTo').value+'&bus_code='+document.getElementById('reportBus').value;}
function downloadPdf(){window.location.href='../api/generate_report.php?'+getParams();}
function openEmailModal(){document.getElementById('emailModal').classList.add('active');}
function closeEmailModal(){document.getElementById('emailModal').classList.remove('active');}
async function sendEmail(){
    const email=document.getElementById('emailTo').value;
    if(!email){alert('Enter email');return;}
    const body={email,type:document.getElementById('reportType').value,from:document.getElementById('dateFrom').value,to:document.getElementById('dateTo').value,bus_code:document.getElementById('reportBus').value};
    const res=await fetch('../api/email_report.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const r=await res.json();
    closeEmailModal();
    if(r.status==='success'){showAlert(r.message,'success');}
    else showAlert(r.message,'error');
}
document.getElementById('hamburger')?.addEventListener('click',function(){this.classList.toggle('active');document.getElementById('navLinks').classList.toggle('open');});
document.addEventListener('click',function(e){const nav=document.querySelector('nav');if(nav&&!nav.contains(e.target)&&!e.target.closest('.admin-sidebar')){document.getElementById('hamburger')?.classList.remove('active');document.getElementById('navLinks')?.classList.remove('open');}});
if(window.innerWidth<992){document.getElementById('sidebarToggle').style.display='flex';}
document.getElementById('sidebarToggle')?.addEventListener('click',function(){document.getElementById('adminSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');});
document.getElementById('sidebarOverlay')?.addEventListener('click',function(){document.getElementById('adminSidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');});
</script>
</body>
</html>

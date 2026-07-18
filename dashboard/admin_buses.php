<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
try { $db = getDb(); } catch (Exception $e) { $db = null; }
$buses = [];
$drivers = [];
if ($db) {
    try { $buses = $db->query("SELECT b.*, d.full_name as driver_name FROM buses b LEFT JOIN drivers d ON b.driver_id = d.id ORDER BY b.bus_code")->fetchAll(); } catch (Exception $e) {}
    try { $drivers = $db->query("SELECT id, full_name, license_number FROM drivers WHERE status = 'active' ORDER BY full_name")->fetchAll(); } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buses - Admin - Smart Bus Tracking</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;}
        .modal-overlay.active{display:flex;}
        .modal{background:#fff;border-radius:12px;padding:24px;width:90%;max-width:500px;max-height:90vh;overflow-y:auto;}
        .modal h3{margin-bottom:16px;}
        .modal .form-group{margin-bottom:12px;}
        .modal label{display:block;font-weight:600;margin-bottom:4px;font-size:0.875rem;}
        .modal input,.modal select{width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:0.9rem;}
        .modal-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:16px;}
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
        <a href="admin_buses.php" class="active"><?= icon('bus') ?> Buses</a>
        <a href="admin_drivers.php"><?= icon('user') ?> Drivers</a>
        <a href="admin_bookings.php"><?= icon('ticket') ?> Bookings</a>
        <a href="admin_payments.php"><?= icon('ticket') ?> Payments</a>
        <a href="admin_sms_logs.php"><?= icon('mail') ?> SMS Logs</a>
        <a href="admin_passengers.php"><?= icon('users') ?> Passengers</a>
        <a href="admin_reports.php"><?= icon('chart') ?> Reports</a>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-content">
        <h2 style="margin-bottom:24px;"><?= icon('bus') ?> Bus Management</h2>
        <div id="alertMessage" class="message" style="margin-bottom: 20px;"></div>

        <div style="margin-bottom:16px;">
            <button class="btn btn-primary" onclick="openModal()">+ Add Bus</button>
        </div>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead><tr>
                        <th>Code</th><th>Name</th><th>Seats</th><th>Fare</th><th>Driver</th><th>Status</th><th>Lat</th><th>Lng</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($buses as $bus): ?>
                        <tr id="bus-<?= sec($bus['id']) ?>">
                            <td><strong><?= sec($bus['bus_code']) ?></strong></td>
                            <td><?= sec($bus['bus_name']) ?></td>
                            <td><?= sec($bus['total_seats']) ?></td>
                            <td>RWF <?= number_format(sec($bus['fare'] ?? 500)) ?></td>
                            <td><?= $bus['driver_name'] ? sec($bus['driver_name']) : '<span style="color:#999;">None</span>' ?></td>
                            <td><span class="badge badge-<?= $bus['status'] === 'active' ? 'success' : ($bus['status'] === 'maintenance' ? 'badge-warning' : 'danger') ?>"><?= sec($bus['status']) ?></span></td>
                            <td style="font-family:monospace;font-size:0.75rem;"><?= sec($bus['current_lat']) ?></td>
                            <td style="font-family:monospace;font-size:0.75rem;"><?= sec($bus['current_lng']) ?></td>
                            <td>
                                <button class="btn btn-sm" style="background:var(--gray-100);" onclick='editBus(<?= json_encode($bus) ?>)'>Edit</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteBus(<?= sec($bus['id']) ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="busModal">
    <div class="modal">
        <h3 id="modalTitle">Add Bus</h3>
        <input type="hidden" id="busId">
        <div class="form-group">
            <label>Bus Code</label>
            <input type="text" id="busCode" placeholder="e.g. BUS004">
        </div>
        <div class="form-group">
            <label>Bus Name</label>
            <input type="text" id="busName" placeholder="e.g. Kigali Express Route 4">
        </div>
        <div class="form-group">
            <label>Total Seats</label>
            <input type="number" id="busSeats" value="30" min="1">
        </div>
        <div class="form-group">
            <label>Fare (RWF)</label>
            <input type="number" id="busFare" value="500" min="0">
        </div>
        <div class="form-group">
            <label>Driver</label>
            <select id="busDriver">
                <option value="">No Driver</option>
                <?php foreach ($drivers as $d): ?>
                    <option value="<?= sec($d['id']) ?>"><?= sec($d['full_name']) ?> (<?= sec($d['license_number']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select id="busStatus">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="maintenance">Maintenance</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn" style="background:var(--gray-100);" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveBus()">Save</button>
        </div>
    </div>
</div>

<script>
function showAlert(msg,type){const e=document.getElementById('alertMessage');e.textContent=msg;e.className='message '+type;e.style.display='block';window.scrollTo({top:0,behavior:'smooth'});}
function openModal(){document.getElementById('modalTitle').textContent='Add Bus';document.getElementById('busId').value='';document.getElementById('busCode').value='';document.getElementById('busCode').disabled=false;document.getElementById('busName').value='';document.getElementById('busSeats').value='30';document.getElementById('busFare').value='500';document.getElementById('busDriver').value='';document.getElementById('busStatus').value='active';document.getElementById('busModal').classList.add('active');}
function closeModal(){document.getElementById('busModal').classList.remove('active');}
function editBus(b){document.getElementById('modalTitle').textContent='Edit Bus';document.getElementById('busId').value=b.id;document.getElementById('busCode').value=b.bus_code;document.getElementById('busCode').disabled=true;document.getElementById('busName').value=b.bus_name;document.getElementById('busSeats').value=b.total_seats;document.getElementById('busFare').value=b.fare||500;document.getElementById('busDriver').value=b.driver_id||'';document.getElementById('busStatus').value=b.status;document.getElementById('busModal').classList.add('active');}
async function saveBus(){
    const id=document.getElementById('busId').value;
    const body={bus_code:document.getElementById('busCode').value,bus_name:document.getElementById('busName').value,total_seats:parseInt(document.getElementById('busSeats').value),fare:parseFloat(document.getElementById('busFare').value),driver_id:document.getElementById('busDriver').value,status:document.getElementById('busStatus').value};
    if(id) body.id=parseInt(id);
    const action=id?'update':'create';
    const res=await fetch('../api/admin_manage_buses.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const r=await res.json();
    if(r.status==='success'){showAlert(r.message,'success');setTimeout(()=>location.reload(),1500);}
    else showAlert(r.message,'error');
}
async function deleteBus(id){if(!confirm('Delete this bus? All associated seats will be removed.'))return;const res=await fetch('../api/admin_manage_buses.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});const r=await res.json();if(r.status==='success'){showAlert(r.message,'success');document.getElementById('bus-'+id).remove();}else showAlert(r.message,'error');}
document.getElementById('hamburger')?.addEventListener('click',function(){this.classList.toggle('active');document.getElementById('navLinks').classList.toggle('open');});
document.addEventListener('click',function(e){const nav=document.querySelector('nav');if(nav&&!nav.contains(e.target)&&!e.target.closest('.admin-sidebar')){document.getElementById('hamburger')?.classList.remove('active');document.getElementById('navLinks')?.classList.remove('open');}});
if(window.innerWidth<992){document.getElementById('sidebarToggle').style.display='flex';}
document.getElementById('sidebarToggle')?.addEventListener('click',function(){document.getElementById('adminSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');});
document.getElementById('sidebarOverlay')?.addEventListener('click',function(){document.getElementById('adminSidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');});
</script>
</body>
</html>

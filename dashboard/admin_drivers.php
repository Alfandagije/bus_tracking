<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
try { $db = getDb(); } catch (Exception $e) { $db = null; }
$drivers = [];
$buses = [];
if ($db) {
    try { $drivers = $db->query("SELECT d.*, b.bus_code, b.bus_name FROM drivers d LEFT JOIN buses b ON d.assigned_bus_id = b.id ORDER BY d.created_at DESC")->fetchAll(); } catch (Exception $e) {}
    try { $buses = $db->query("SELECT id, bus_code, bus_name FROM buses ORDER BY bus_code")->fetchAll(); } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drivers - Admin - Smart Bus Tracking</title>
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
        <a href="admin_buses.php"><?= icon('bus') ?> Buses</a>
        <a href="admin_drivers.php" class="active"><?= icon('user') ?> Drivers</a>
        <a href="admin_bookings.php"><?= icon('ticket') ?> Bookings</a>
        <a href="admin_payments.php"><?= icon('ticket') ?> Payments</a>
        <a href="admin_sms_logs.php"><?= icon('mail') ?> SMS Logs</a>
        <a href="admin_passengers.php"><?= icon('users') ?> Passengers</a>
        <a href="admin_reports.php"><?= icon('chart') ?> Reports</a>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-content">
        <h2 style="margin-bottom:24px;"><?= icon('user') ?> Driver Management</h2>
        <div id="alertMessage" class="message" style="margin-bottom: 20px;"></div>

        <div style="margin-bottom:16px;">
            <button class="btn btn-primary" onclick="openModal()">+ Add Driver</button>
        </div>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead><tr>
                        <th>ID</th><th>Name</th><th>Phone</th><th>License</th><th>Assigned Bus</th><th>Status</th><th>Actions</th>
                    </tr></thead>
                    <tbody id="driversTable">
                        <?php foreach ($drivers as $d): ?>
                        <tr id="driver-<?= sec($d['id']) ?>">
                            <td>#<?= sec($d['id']) ?></td>
                            <td><?= sec($d['full_name']) ?></td>
                            <td><?= sec($d['phone']) ?></td>
                            <td><?= sec($d['license_number']) ?></td>
                            <td><?= $d['bus_code'] ? sec($d['bus_code']) . ' - ' . sec($d['bus_name']) : '<span style="color:#999;">Unassigned</span>' ?></td>
                            <td><span class="badge badge-<?= $d['status'] === 'active' ? 'success' : 'danger' ?>"><?= sec($d['status']) ?></span></td>
                            <td>
                                <button class="btn btn-sm" style="background:var(--gray-100);" onclick='editDriver(<?= json_encode($d) ?>)'>Edit</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteDriver(<?= sec($d['id']) ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="driverModal">
    <div class="modal">
        <h3 id="modalTitle">Add Driver</h3>
        <input type="hidden" id="driverId">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" id="driverName" placeholder="e.g. Jean Mutabazi">
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="text" id="driverPhone" placeholder="e.g. +250788123456">
        </div>
        <div class="form-group">
            <label>License Number</label>
            <input type="text" id="driverLicense" placeholder="e.g. RW-2024-001">
        </div>
        <div class="form-group">
            <label>Assigned Bus</label>
            <select id="driverBus">
                <option value="">None (Unassigned)</option>
                <?php foreach ($buses as $b): ?>
                    <option value="<?= sec($b['id']) ?>"><?= sec($b['bus_code']) ?> - <?= sec($b['bus_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select id="driverStatus">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn" style="background:var(--gray-100);" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveDriver()">Save</button>
        </div>
    </div>
</div>

<script>
const buses = <?= json_encode($buses) ?>;
function showAlert(msg,type){const e=document.getElementById('alertMessage');e.textContent=msg;e.className='message '+type;e.style.display='block';window.scrollTo({top:0,behavior:'smooth'});}
function openModal(){document.getElementById('modalTitle').textContent='Add Driver';document.getElementById('driverId').value='';document.getElementById('driverName').value='';document.getElementById('driverPhone').value='';document.getElementById('driverLicense').value='';document.getElementById('driverBus').value='';document.getElementById('driverStatus').value='active';document.getElementById('driverModal').classList.add('active');}
function closeModal(){document.getElementById('driverModal').classList.remove('active');}
function editDriver(d){document.getElementById('modalTitle').textContent='Edit Driver';document.getElementById('driverId').value=d.id;document.getElementById('driverName').value=d.full_name;document.getElementById('driverPhone').value=d.phone;document.getElementById('driverLicense').value=d.license_number;document.getElementById('driverBus').value=d.assigned_bus_id||'';document.getElementById('driverStatus').value=d.status;document.getElementById('driverModal').classList.add('active');}
async function saveDriver(){
    const id=document.getElementById('driverId').value;
    const body={full_name:document.getElementById('driverName').value,phone:document.getElementById('driverPhone').value,license_number:document.getElementById('driverLicense').value,assigned_bus_id:document.getElementById('driverBus').value,status:document.getElementById('driverStatus').value};
    if(id) body.id=parseInt(id);
    const action=id?'update':'create';
    const res=await fetch('../api/admin_drivers.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const r=await res.json();
    if(r.status==='success'){showAlert(r.message,'success');setTimeout(()=>location.reload(),1500);}
    else showAlert(r.message,'error');
}
async function deleteDriver(id){if(!confirm('Delete this driver?'))return;const res=await fetch('../api/admin_drivers.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});const r=await res.json();if(r.status==='success'){showAlert(r.message,'success');document.getElementById('driver-'+id).remove();}else showAlert(r.message,'error');}
document.getElementById('hamburger')?.addEventListener('click',function(){this.classList.toggle('active');document.getElementById('navLinks').classList.toggle('open');});
document.addEventListener('click',function(e){const nav=document.querySelector('nav');if(nav&&!nav.contains(e.target)&&!e.target.closest('.admin-sidebar')){document.getElementById('hamburger')?.classList.remove('active');document.getElementById('navLinks')?.classList.remove('open');}});
if(window.innerWidth<992){document.getElementById('sidebarToggle').style.display='flex';}
document.getElementById('sidebarToggle')?.addEventListener('click',function(){document.getElementById('adminSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');});
document.getElementById('sidebarOverlay')?.addEventListener('click',function(){document.getElementById('adminSidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');});
</script>
</body>
</html>

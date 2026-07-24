<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
try { $db = getDb(); } catch (Exception $e) { $db = null; }
$routes = [];
if ($db) {
    try {
        $routes = $db->query("
            SELECT r.*, 
                (SELECT COUNT(*) FROM buses WHERE route_id = r.id) as bus_count
            FROM routes r
            ORDER BY r.route_name
        ")->fetchAll();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routes - Dashboard - Smart Bus Tracking</title>
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
            <span><?= icon('user') ?> <?= htmlspecialchars($_SESSION['full_name']) ?> (<?= ucfirst($_SESSION['role']) ?>)</span>
            <a href="../auth/logout.php" class="btn btn-sm btn-primary">Logout</a>
        </div>
    </div>
</nav>
<div class="admin-layout">
    <button class="hamburger" id="sidebarToggle" aria-label="Toggle sidebar" style="display:none;"><span></span><span></span><span></span></button>
    <div class="admin-sidebar" id="adminSidebar">
        <h3><?= $_SESSION['role'] === 'admin' ? 'Admin' : 'Manager' ?> Panel</h3>
        <a href="index.php"><?= icon('chart') ?> Dashboard</a>
        <a href="admin_buses.php"><?= icon('bus') ?> Buses</a>
        <a href="admin_drivers.php"><?= icon('user') ?> Drivers</a>
        <a href="admin_routes.php" class="active"><?= icon('ticket') ?> Routes</a>
        <a href="admin_bookings.php"><?= icon('ticket') ?> Bookings</a>
        <a href="admin_payments.php"><?= icon('ticket') ?> Payments</a>
        <a href="admin_sms_logs.php"><?= icon('mail') ?> SMS Logs</a>
        <a href="admin_passengers.php"><?= icon('users') ?> Passengers</a>
        <a href="admin_reports.php"><?= icon('chart') ?> Reports</a>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-content">
        <h2 style="margin-bottom:24px;"><?= icon('ticket') ?> Route Management</h2>
        <div id="alertMessage" class="message" style="margin-bottom: 20px;"></div>

        <div style="margin-bottom:16px;">
            <button class="btn btn-primary" onclick="openModal()">+ Add Route</button>
        </div>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead><tr>
                        <th>ID</th><th>Route Name</th><th>Origin</th><th>Destination</th><th>Base Price</th><th>Buses</th><th>Status</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($routes as $r): ?>
                        <tr id="route-<?= sec($r['id']) ?>">
                            <td>#<?= sec($r['id']) ?></td>
                            <td><strong><?= sec($r['route_name']) ?></strong></td>
                            <td><?= sec($r['origin']) ?></td>
                            <td><?= sec($r['destination']) ?></td>
                            <td>RWF <?= number_format(sec($r['base_price'])) ?></td>
                            <td><?= sec($r['bus_count']) ?></td>
                            <td><span class="badge badge-<?= $r['status'] === 'active' ? 'success' : 'danger' ?>"><?= sec($r['status']) ?></span></td>
                            <td>
                                <button class="btn btn-sm" style="background:var(--gray-100);" onclick='editRoute(<?= json_encode($r) ?>)'>Edit</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteRoute(<?= sec($r['id']) ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="routeModal">
    <div class="modal">
        <h3 id="modalTitle">Add Route</h3>
        <input type="hidden" id="routeId">
        <div class="form-group">
            <label>Route Name</label>
            <input type="text" id="routeName" placeholder="e.g. Kigali Express Route 4">
        </div>
        <div class="form-group">
            <label>Origin</label>
            <input type="text" id="routeOrigin" placeholder="e.g. Kigali City Center">
        </div>
        <div class="form-group">
            <label>Destination</label>
            <input type="text" id="routeDest" placeholder="e.g. Kimironko">
        </div>
        <div class="form-group">
            <label>Base Price (RWF)</label>
            <input type="number" id="routePrice" value="500" min="0">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select id="routeStatus">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn" style="background:var(--gray-100);" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveRoute()">Save</button>
        </div>
    </div>
</div>

<script>
function showAlert(msg,type){const e=document.getElementById('alertMessage');e.textContent=msg;e.className='message '+type;e.style.display='block';window.scrollTo({top:0,behavior:'smooth'});}
function openModal(){document.getElementById('modalTitle').textContent='Add Route';document.getElementById('routeId').value='';document.getElementById('routeName').value='';document.getElementById('routeOrigin').value='';document.getElementById('routeDest').value='';document.getElementById('routePrice').value='500';document.getElementById('routeStatus').value='active';document.getElementById('routeModal').classList.add('active');}
function closeModal(){document.getElementById('routeModal').classList.remove('active');}
function editRoute(r){document.getElementById('modalTitle').textContent='Edit Route';document.getElementById('routeId').value=r.id;document.getElementById('routeName').value=r.route_name;document.getElementById('routeOrigin').value=r.origin;document.getElementById('routeDest').value=r.destination;document.getElementById('routePrice').value=r.base_price||500;document.getElementById('routeStatus').value=r.status;document.getElementById('routeModal').classList.add('active');}
async function saveRoute(){
    const id=document.getElementById('routeId').value;
    const body={route_name:document.getElementById('routeName').value,origin:document.getElementById('routeOrigin').value,destination:document.getElementById('routeDest').value,base_price:parseFloat(document.getElementById('routePrice').value),status:document.getElementById('routeStatus').value};
    if(id) body.id=parseInt(id);
    const action=id?'update':'create';
    const res=await fetch('../api/admin_routes.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const r=await res.json();
    if(r.status==='success'){showAlert(r.message,'success');setTimeout(()=>location.reload(),1500);}
    else showAlert(r.message,'error');
}
async function deleteRoute(id){if(!confirm('Delete this route?'))return;const res=await fetch('../api/admin_routes.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});const r=await res.json();if(r.status==='success'){showAlert(r.message,'success');document.getElementById('route-'+id).remove();}else showAlert(r.message,'error');}
document.getElementById('hamburger')?.addEventListener('click',function(){this.classList.toggle('active');document.getElementById('navLinks').classList.toggle('open');});
document.addEventListener('click',function(e){const nav=document.querySelector('nav');if(nav&&!nav.contains(e.target)&&!e.target.closest('.admin-sidebar')){document.getElementById('hamburger')?.classList.remove('active');document.getElementById('navLinks')?.classList.remove('open');}});
if(window.innerWidth<992){document.getElementById('sidebarToggle').style.display='flex';}
document.getElementById('sidebarToggle')?.addEventListener('click',function(){document.getElementById('adminSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');});
document.getElementById('sidebarOverlay')?.addEventListener('click',function(){document.getElementById('adminSidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');});
</script>
</body>
</html>

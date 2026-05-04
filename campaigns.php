<?php
// ========================================================================
// 1. INITIALIZATION & SECURITY CHECK
// ========================================================================
session_start();
@include 'config.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

$toastMessage = "";
$toastType = "";

// ========================================================================
// 2. CAMPAIGN MANAGEMENT LOGIC (CREATE, UPDATE, DELETE)
// ========================================================================

// A. CREATE NEW CAMPAIGN LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_campaign'])) {
    if(isset($conn)){
        $campaign_name = mysqli_real_escape_string($conn, $_POST['campaign_name'] ?? '');
        $campaign_type = mysqli_real_escape_string($conn, $_POST['campaign_type'] ?? '');
        $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
        $target_audience = mysqli_real_escape_string($conn, $_POST['target_audience'] ?? '');
        $budget = mysqli_real_escape_string($conn, $_POST['budget'] ?? '0');
        $currency = mysqli_real_escape_string($conn, $_POST['currency'] ?? 'USD');
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date'] ?? '');
        $end_date = mysqli_real_escape_string($conn, $_POST['end_date'] ?? '');
        $assigned_to = mysqli_real_escape_string($conn, $_POST['assigned_to'] ?? 'Unassigned');
        $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Planning');
        
        $insert_sql = "INSERT INTO campaigns (campaign_name, campaign_type, description, target_audience, budget, currency, start_date, end_date, assigned_to, status) 
                       VALUES ('$campaign_name', '$campaign_type', '$description', '$target_audience', '$budget', '$currency', '$start_date', '$end_date', '$assigned_to', '$status')";
        
        try {
            if(mysqli_query($conn, $insert_sql)){
                $toastMessage = "Campaign created successfully!"; $toastType = "success";
            }
        } catch (mysqli_sql_exception $e) {
            $toastMessage = "Database Error: " . $e->getMessage(); $toastType = "error";
        }
    }
}

// B. UPDATE/EDIT EXISTING CAMPAIGN LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_campaign'])) {
    if(isset($conn)){
        $id = mysqli_real_escape_string($conn, $_POST['campaign_id'] ?? '');
        $campaign_name = mysqli_real_escape_string($conn, $_POST['campaign_name'] ?? '');
        $campaign_type = mysqli_real_escape_string($conn, $_POST['campaign_type'] ?? '');
        $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
        $target_audience = mysqli_real_escape_string($conn, $_POST['target_audience'] ?? '');
        $budget = mysqli_real_escape_string($conn, $_POST['budget'] ?? '0');
        $currency = mysqli_real_escape_string($conn, $_POST['currency'] ?? 'USD');
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date'] ?? '');
        $end_date = mysqli_real_escape_string($conn, $_POST['end_date'] ?? '');
        $assigned_to = mysqli_real_escape_string($conn, $_POST['assigned_to'] ?? 'Unassigned');
        $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Planning');

        $update_sql = "UPDATE campaigns SET campaign_name='$campaign_name', campaign_type='$campaign_type', description='$description', target_audience='$target_audience', budget='$budget', currency='$currency', start_date='$start_date', end_date='$end_date', assigned_to='$assigned_to', status='$status' WHERE id='$id'";
        
        try {
            if(mysqli_query($conn, $update_sql)){
                $toastMessage = "Campaign updated successfully!"; $toastType = "success";
            }
        } catch (mysqli_sql_exception $e) {
            $toastMessage = "Database Error: " . $e->getMessage(); $toastType = "error";
        }
    }
}

// C. DELETE CAMPAIGN LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_campaign'])) {
    if(isset($conn)){
        $del_id = mysqli_real_escape_string($conn, $_POST['delete_campaign_id'] ?? '');
        $delete_sql = "DELETE FROM campaigns WHERE id='$del_id'";
        try {
            if(mysqli_query($conn, $delete_sql)){
                $toastMessage = "Campaign deleted successfully!"; $toastType = "success";
            }
        } catch (mysqli_sql_exception $e) {
            $toastMessage = "Error deleting campaign!"; $toastType = "error";
        }
    }
}

// ========================================================================
// 3. FETCH DATA FOR UI (Users for Assignment, Campaigns)
// ========================================================================
$assigneeOptions = ""; 
if(isset($conn)){
    $user_query = mysqli_query($conn, "SELECT username, name FROM users ORDER BY name ASC");
    while($u = mysqli_fetch_assoc($user_query)){
        $assigneeOptions .= "<option value='{$u['username']}'>{$u['name']} ({$u['username']})</option>";
    }
}

$campaignTableRows = "";
if(isset($conn)){
    $campaigns_query = mysqli_query($conn, "SELECT * FROM campaigns ORDER BY start_date DESC, id DESC");
    if($campaigns_query && mysqli_num_rows($campaigns_query) > 0){
        while($row = mysqli_fetch_assoc($campaigns_query)){
            $campaignData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            
            // Status Badge Color
            $statusClass = "status-planning";
            if($row['status'] == 'Active') $statusClass = "status-active";
            if($row['status'] == 'Completed') $statusClass = "status-completed";
            if($row['status'] == 'On Hold') $statusClass = "status-onhold";
            
            // Campaign Type Badge Color
            $typeClass = "type-email";
            if($row['campaign_type'] == 'Social Media') $typeClass = "type-social";
            if($row['campaign_type'] == 'Content Marketing') $typeClass = "type-content";
            if($row['campaign_type'] == 'Paid Ads') $typeClass = "type-paid";
            if($row['campaign_type'] == 'Event') $typeClass = "type-event";

            $campaignTableRows .= "
                <tr class='campaign-row' data-status='{$row['status']}'>
                    <td style='font-weight: 700;'>#{$row['id']}</td>
                    <td style='text-align: left; font-weight: 600;'>{$row['campaign_name']}</td>
                    <td><span class='badge $typeClass'>{$row['campaign_type']}</span></td>
                    <td>{$row['assigned_to']}</td>
                    <td>{$row['currency']} " . number_format($row['budget'], 2) . "</td>
                    <td>" . date('M d, Y', strtotime($row['start_date'])) . "</td>
                    <td><span class='badge $statusClass'>{$row['status']}</span></td>
                    <td>
                        <div class='action-btns'>
                            <button class='btn-view' onclick='openViewModal({$campaignData})'><i class='fa-solid fa-eye'></i></button>
                            <button class='btn-edit' onclick='openEditModal({$campaignData})'><i class='fa-solid fa-pen'></i></button>
                            <form method='POST' id='delete-campaign-{$row['id']}' style='display:inline;'>
                                <input type='hidden' name='delete_campaign_id' value='{$row['id']}'>
                                <input type='hidden' name='delete_campaign' value='1'>
                                <button type='button' class='btn-delete' onclick='confirmDelete(\"delete-campaign-{$row['id']}\", \"campaign\")'><i class='fa-solid fa-trash'></i></button>
                            </form>
                        </div>
                    </td>
                </tr>";
        }
    } else {
        $campaignTableRows = "<tr><td colspan='8' style='padding: 20px; color: #6b7280;'>No campaigns found.</td></tr>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaigns - Systellio CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: #f3f4f6; display: flex; height: 100vh; overflow: hidden; transition: background-color 0.3s, color 0.3s; color: #111827; }

        /* Toast */
        #toastBox { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 9999; right: 30px; top: 30px; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 10px; transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55), visibility 0.4s; }
        #toastBox.show { visibility: visible; transform: translateX(0); }
        #toastBox.success { background-color: #10b981; }
        #toastBox.error { background-color: #ef4444; }

        /* Sidebar */
        .sidebar { width: 260px; background-color: #0b1524; color: #ffffff; display: flex; flex-direction: column; transition: margin-left 0.3s ease; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 1000; }
        .sidebar.collapsed { margin-left: -260px; }
        .sidebar-header { padding: 25px 20px; display: flex; flex-direction: column; gap: 5px; }
        .sidebar-logo { width: 120px; margin-bottom: 5px; }
        .brand-role { font-size: 10px; font-weight: 700; color: #60a5fa; letter-spacing: 1.5px; text-transform: uppercase; }
        
        .sidebar-menu { list-style: none; padding: 10px 0; flex-grow: 1; overflow-y: auto; }
        .sidebar-menu > li { padding: 14px 20px 14px 21px; display: flex; align-items: center; gap: 15px; cursor: pointer; transition: 0.3s; color: #94a3b8; border-left: 3px solid transparent; }
        .sidebar-menu > li:hover { background-color: #162235; color: #ffffff; }
        .sidebar-menu > li i { font-size: 15px; width: 20px; text-align: center; }
        .sidebar-menu > li a { color: inherit; text-decoration: none; font-size: 13px; font-weight: 500; width: 100%; }

        .sidebar-menu > li.active { background-color: #1e293b; color: #3b82f6; border-left: 3px solid #3b82f6; }
        .sidebar-menu > li.active i { color: #3b82f6; }
        
        .dropdown-item { padding: 0 !important; display: block !important; border-left: none !important; }
        .dropdown-title { padding: 14px 20px 14px 24px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; color: #94a3b8; transition: 0.3s; border-left: 3px solid transparent; }
        .dropdown-title:hover { background-color: #162235; color: #ffffff; }
        .dropdown-title-left { display: flex; align-items: center; gap: 15px; }
        .dropdown-title-left i { font-size: 15px; width: 20px; text-align: center; }
        .dropdown-title-left span { font-size: 13px; font-weight: 500; }
        .dropdown-icon { font-size: 10px !important; transition: transform 0.3s ease; }
        
        .submenu { list-style: none; display: none; background-color: #0b1524; padding-top: 5px; padding-bottom: 10px; }
        .submenu li { padding: 10px 20px 10px 59px !important; border-left: none !important; background-color: transparent !important; }
        .submenu li a { color: #64748b; text-decoration: none; font-size: 12px; transition: 0.3s; cursor: pointer; }
        .submenu li a:hover { color: #ffffff; }
        .dropdown-item.open .submenu { display: block; }
        .dropdown-item.open .dropdown-icon { transform: rotate(180deg); }

        /* Main Content */
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; transition: background-color 0.3s ease; background-color: #f3f4f6; }
        .top-navbar { background-color: #ffffff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.03); transition: 0.3s; }
        .toggle-btn { font-size: 20px; color: #4b5563; cursor: pointer; transition: color 0.3s; }
        .toggle-btn:hover { color: #111827; }
        
        .navbar-actions { display: flex; align-items: center; gap: 20px; }
        .nav-icon-btn { cursor: pointer; font-size: 20px; color: #6b7280; transition: 0.3s; position: relative; }
        .nav-icon-btn:hover { color: #3b82f6; }
        
        .user-profile { display: flex; align-items: center; gap: 10px; font-weight: 600; color: inherit; font-size: 14px; }
        .user-profile i { font-size: 24px; color: #3b82f6; }

        /* Campaign Section Styles */
        #campaignSection { padding: 30px; display: block; }
        .campaign-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; }
        .campaign-title h1 { font-size: 26px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; transition: 0.3s;}
        .campaign-title p { font-size: 11px; color: #6b7280; font-weight: 500; }
        
        .header-actions { display: flex; gap: 12px; }
        .btn-primary { background-color: #000000; color: #ffffff; padding: 10px 18px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background-color: #1f2937; transform: translateY(-1px); }

        .tab-container { display: flex; gap: 25px; border-bottom: 1px solid #d1d5db; margin-bottom: 25px; transition: 0.3s;}
        .tab-btn { padding: 10px 5px; font-size: 13px; font-weight: 600; color: #6b7280; cursor: pointer; position: relative; transition: 0.3s; }
        .tab-btn:hover { color: #111827; }
        .tab-btn.active { color: #3b82f6; }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -1px; left: 0; width: 100%; height: 2px; background-color: #3b82f6; }

        .table-wrapper { border-radius: 8px; overflow: hidden; border: 1px solid #d1d5db; transition: 0.3s; background: #ffffff;}
        .custom-table { width: 100%; border-collapse: collapse; text-align: center; font-size: 12px; }
        .custom-table th { background-color: #c4f042; padding: 14px 10px; font-weight: 700; color: #000000; border-bottom: 1px solid #d1d5db; transition: 0.3s;}
        .custom-table td { padding: 14px 10px; color: #374151; font-weight: 500; vertical-align: middle; border-right: 1px solid rgba(0,0,0,0.05); transition: 0.3s;}
        .custom-table td:last-child { border-right: none; }

        .custom-table tbody tr:nth-child(4n+1) { background-color: #e6fced; } 
        .custom-table tbody tr:nth-child(4n+2) { background-color: #fcedf6; } 
        .custom-table tbody tr:nth-child(4n+3) { background-color: #fceddb; } 
        .custom-table tbody tr:nth-child(4n+4) { background-color: #e6edff; } 

        .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .status-planning { background-color: #dbeafe; color: #3b82f6; }
        .status-active { background-color: #dcfce7; color: #10b981; }
        .status-completed { background-color: #fef3c7; color: #f59e0b; }
        .status-onhold { background-color: #fee2e2; color: #ef4444; }
        
        .type-email { background-color: #e0e7ff; color: #4f46e5; }
        .type-social { background-color: #fce7f3; color: #ec4899; }
        .type-content { background-color: #dbeafe; color: #0284c7; }
        .type-paid { background-color: #fef3c7; color: #ca8a04; }
        .type-event { background-color: #d1d5db; color: #374151; }

        .action-btns { display: flex; justify-content: center; gap: 6px; }
        .btn-view { background-color: #60a5fa; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}
        .btn-edit { background-color: #34d399; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}
        .btn-delete { background-color: #f87171; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}
        .btn-view:hover { background-color: #3b82f6; }
        .btn-edit:hover { background-color: #10b981; }
        .btn-delete:hover { background-color: #ef4444; }

        /* Modals */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; padding: 30px; border-radius: 10px; width: 100%; max-width: 700px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-height: 90vh; overflow-y: auto; transition: 0.3s;}
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { font-size: 20px; font-weight: 700; transition: 0.3s;}
        .close-btn { font-size: 20px; cursor: pointer; color: #6b7280; border: none; background: none; transition: 0.3s;}
        .close-btn:hover { color: #ef4444; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 15px; position: relative; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: #374151; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; outline: none; transition: 0.3s; font-family: 'Inter', sans-serif; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        .form-group textarea { resize: vertical; min-height: 80px; }
        .full-width { grid-column: span 2; }

        .submit-btn { width: 100%; background-color: #000000; color: #ffffff; padding: 12px; border-radius: 6px; font-size: 14px; font-weight: 700; border: none; cursor: pointer; transition: 0.3s; margin-top: 10px; }
        .submit-btn:hover { background-color: #1f2937; }

        .view-data-box { background: #f9fafb; padding: 10px 12px; border-radius: 6px; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 500; min-height: 40px; display: flex; align-items: center; }

        /* Dark Mode */
        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .main-content { background-color: #0f172a; }
        body.dark-mode .top-navbar { background-color: #1e293b; border-bottom: 1px solid #334155; box-shadow: none; }
        body.dark-mode .nav-icon-btn { color: #cbd5e1; }
        
        body.dark-mode .tab-container { border-color: #334155; }
        body.dark-mode .tab-btn { color: #94a3b8; }
        body.dark-mode .tab-btn:hover { color: #f8fafc; }
        
        body.dark-mode .table-wrapper { border-color: #334155; background: #1e293b; }
        body.dark-mode .custom-table th { background-color: #334155; color: #f8fafc; border-color: #475569; }
        body.dark-mode .custom-table td { color: #cbd5e1; border-color: #334155; }
        
        body.dark-mode .custom-table tbody tr:nth-child(even) { background-color: #1e293b; } 
        body.dark-mode .custom-table tbody tr:nth-child(odd) { background-color: #0f172a; } 

        body.dark-mode .modal-content { background-color: #1e293b; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        body.dark-mode .form-group label { color: #cbd5e1; }
        body.dark-mode .form-group input, body.dark-mode .form-group select, body.dark-mode .form-group textarea { background-color: #0f172a; color: #f8fafc; border-color: #334155; }
        body.dark-mode .view-data-box { background-color: #0f172a; color: #f8fafc; border-color: #334155; }
    </style>
</head>
<body>

    <div id="toastBox">
        <i id="toastIcon" class="fa-solid fa-circle-check"></i>
        <span id="toastMsg">Action Successful!</span>
    </div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Systellio Logo" class="sidebar-logo">
            <span class="brand-role">SUPER ADMIN</span>
        </div>

        <ul class="sidebar-menu">
            <li onclick="window.location.href='super_admin_dashboard.php'">
                <i class="fa-solid fa-table-cells-large"></i>
                <a href="super_admin_dashboard.php">Dashboard</a>
            </li>

            <li class="dropdown-item" id="userMenu">
                <div class="dropdown-title" onclick="toggleSubMenu('userMenu')">
                    <div class="dropdown-title-left"><i class="fa-solid fa-user-group"></i><span>User Management</span></div>
                    <i class="fa-solid fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="user_list.php">User List</a></li>
                    <li><a href="user_tasks.php">User Tasks</a></li>
                    <li><a href="user_activity.php">User Activity</a></li>
                </ul>
            </li>

            <li onclick="window.location.href='company_list.php'">
                <i class="fa-solid fa-building"></i>
                <a href="company_list.php">Company List</a>
            </li>

            <li onclick="window.location.href='client_list.php'">
                <i class="fa-solid fa-address-book"></i>
                <a href="client_list.php">Contact List</a>
            </li>

            <li onclick="window.location.href='deal_pipeline.php'">
                <i class="fa-solid fa-handshake"></i>
                <a href="deal_pipeline.php">Deal Pipeline</a>
            </li>

            <li class="active" onclick="window.location.href='campaigns.php'">
                <i class="fa-solid fa-bullhorn"></i>
                <a href="campaigns.php">Campaigns</a>
            </li>

            <li onclick="window.location.href='analytics_reports.php'">
                <i class="fa-solid fa-chart-line"></i>
                <a href="analytics_reports.php">Analytics</a>
            </li>

            <li onclick="window.location.href='settings.php'">
                <i class="fa-solid fa-gear"></i>
                <a href="settings.php">Settings</a>
            </li>

            <li onclick="window.location.href='logout.php'" style="margin-top: auto; color: #ef4444;">
                <i class="fa-solid fa-right-from-bracket"></i>
                <a href="logout.php">Logout</a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <div class="toggle-btn" id="outerToggle"><i class="fa-solid fa-bars-staggered"></i></div>
            <div class="navbar-actions">
                <div class="nav-icon-btn"><i class="fa-solid fa-moon" id="darkModeToggle"></i></div>
                <div class="nav-icon-btn"><i class="fa-solid fa-bell"></i></div>
                <div class="user-profile"><i class="fa-solid fa-circle-user"></i><span><?php echo $_SESSION['name']; ?></span></div>
            </div>
        </div>

        <div id="campaignSection">
            <div class="campaign-header">
                <div class="campaign-title">
                    <h1>Campaign Management</h1>
                    <p>Create, manage, and track your marketing campaigns.</p>
                </div>
                <div class="header-actions">
                    <button class="btn-primary" onclick="openModal('createCampaignModal')"><i class="fa-solid fa-plus"></i> Create Campaign</button>
                </div>
            </div>

            <div class="tab-container">
                <div class="tab-btn active" onclick="filterCampaigns('all', this)">All Campaigns</div>
                <div class="tab-btn" onclick="filterCampaigns('Planning', this)">Planning</div>
                <div class="tab-btn" onclick="filterCampaigns('Active', this)">Active</div>
                <div class="tab-btn" onclick="filterCampaigns('Completed', this)">Completed</div>
                <div class="tab-btn" onclick="filterCampaigns('On Hold', this)">On Hold</div>
            </div>

            <div class="table-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Campaign Name</th>
                            <th>Type</th>
                            <th>Assigned To</th>
                            <th>Budget</th>
                            <th>Start Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo $campaignTableRows; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Campaign Modal -->
    <div id="createCampaignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Campaign</h2>
                <button type="button" class="close-btn" onclick="closeModal('createCampaignModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="campaigns.php" method="POST">
                <div class="form-grid">
                    <div class="form-group full-width"><label>Campaign Name</label><input type="text" name="campaign_name" required placeholder="e.g. Summer Product Launch"></div>
                    
                    <div class="form-group">
                        <label>Campaign Type</label>
                        <select name="campaign_type" required>
                            <option value="" disabled selected>Select Type</option>
                            <option value="Email">Email</option>
                            <option value="Social Media">Social Media</option>
                            <option value="Content Marketing">Content Marketing</option>
                            <option value="Paid Ads">Paid Ads</option>
                            <option value="Event">Event</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="Planning">Planning</option>
                            <option value="Active">Active</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Budget</label>
                        <input type="number" name="budget" step="0.01" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>Currency</label>
                        <select name="currency">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="INR">INR</option>
                            <option value="BDT">BDT</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Assigned To</label>
                        <select name="assigned_to">
                            <option value="Unassigned">Unassigned</option>
                            <?php echo $assigneeOptions; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" required>
                    </div>

                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Target Audience</label>
                        <input type="text" name="target_audience" placeholder="e.g. Ages 18-35, Tech Enthusiasts">
                    </div>

                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Provide detailed information about the campaign..."></textarea>
                    </div>
                </div>
                <button type="submit" name="create_campaign" class="submit-btn">Create Campaign</button>
            </form>
        </div>
    </div>

    <!-- Edit Campaign Modal -->
    <div id="editCampaignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Campaign</h2>
                <button type="button" class="close-btn" onclick="closeModal('editCampaignModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="campaigns.php" method="POST">
                <input type="hidden" name="campaign_id" id="edit_campaign_id">
                <div class="form-grid">
                    <div class="form-group full-width"><label>Campaign Name</label><input type="text" name="campaign_name" id="edit_campaign_name" required></div>
                    
                    <div class="form-group">
                        <label>Campaign Type</label>
                        <select name="campaign_type" id="edit_campaign_type" required>
                            <option value="Email">Email</option>
                            <option value="Social Media">Social Media</option>
                            <option value="Content Marketing">Content Marketing</option>
                            <option value="Paid Ads">Paid Ads</option>
                            <option value="Event">Event</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status" required>
                            <option value="Planning">Planning</option>
                            <option value="Active">Active</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Budget</label>
                        <input type="number" name="budget" id="edit_budget" step="0.01">
                    </div>

                    <div class="form-group">
                        <label>Currency</label>
                        <select name="currency" id="edit_currency">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="INR">INR</option>
                            <option value="BDT">BDT</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Assigned To</label>
                        <select name="assigned_to" id="edit_assigned_to">
                            <option value="Unassigned">Unassigned</option>
                            <?php echo $assigneeOptions; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" id="edit_start_date" required>
                    </div>

                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" id="edit_end_date" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Target Audience</label>
                        <input type="text" name="target_audience" id="edit_target_audience">
                    </div>

                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" id="edit_description"></textarea>
                    </div>
                </div>
                <button type="submit" name="update_campaign" class="submit-btn" style="background-color: #22c55e;">Update Campaign</button>
            </form>
        </div>
    </div>

    <!-- View Campaign Modal -->
    <div id="viewCampaignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Campaign Details</h2>
                <button type="button" class="close-btn" onclick="closeModal('viewCampaignModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="form-grid">
                <div class="form-group"><label>Campaign Name</label><div class="view-data-box" id="view_campaign_name">-</div></div>
                <div class="form-group"><label>Campaign Type</label><div class="view-data-box" id="view_campaign_type">-</div></div>
                <div class="form-group"><label>Status</label><div class="view-data-box" id="view_status">-</div></div>
                <div class="form-group"><label>Assigned To</label><div class="view-data-box" id="view_assigned_to">-</div></div>
                <div class="form-group"><label>Budget</label><div class="view-data-box" id="view_budget">-</div></div>
                <div class="form-group"><label>Currency</label><div class="view-data-box" id="view_currency">-</div></div>
                <div class="form-group"><label>Start Date</label><div class="view-data-box" id="view_start_date">-</div></div>
                <div class="form-group"><label>End Date</label><div class="view-data-box" id="view_end_date">-</div></div>
                <div class="form-group full-width"><label>Target Audience</label><div class="view-data-box" id="view_target_audience">-</div></div>
                <div class="form-group full-width"><label>Description</label><div class="view-data-box" id="view_description" style="min-height: 80px; align-items: flex-start; padding-top: 10px;">-</div></div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="submit-btn" onclick="switchToEditMode()" style="background-color: #22c55e; margin-top: 0;"><i class="fa-solid fa-pen-to-square"></i> Edit Campaign</button>
                <button class="submit-btn" onclick="closeModal('viewCampaignModal')" style="background-color: #6b7280; margin-top: 0;">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Data Filtering Logic
        function filterCampaigns(status, btnElement) {
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');

            const rows = document.querySelectorAll('.campaign-row');
            rows.forEach(row => {
                if (status === 'all') { row.style.display = ''; } 
                else {
                    if (row.getAttribute('data-status') === status) { row.style.display = ''; } 
                    else { row.style.display = 'none'; }
                }
            });
        }

        // Modal Logic
        function openModal(id) { document.getElementById(id).style.display = "flex"; }
        function closeModal(id) { document.getElementById(id).style.display = "none"; }

        let currentCampaignData = null;

        function openViewModal(campaign) {
            currentCampaignData = campaign;
            document.getElementById('view_campaign_name').innerText = campaign.campaign_name || 'N/A';
            document.getElementById('view_campaign_type').innerText = campaign.campaign_type || 'N/A';
            document.getElementById('view_status').innerText = campaign.status || 'N/A';
            document.getElementById('view_assigned_to').innerText = campaign.assigned_to || 'N/A';
            document.getElementById('view_budget').innerText = (campaign.currency || 'USD') + ' ' + (parseFloat(campaign.budget).toFixed(2) || '0.00');
            document.getElementById('view_currency').innerText = campaign.currency || 'USD';
            document.getElementById('view_start_date').innerText = campaign.start_date ? new Date(campaign.start_date).toLocaleDateString() : 'N/A';
            document.getElementById('view_end_date').innerText = campaign.end_date ? new Date(campaign.end_date).toLocaleDateString() : 'N/A';
            document.getElementById('view_target_audience').innerText = campaign.target_audience || 'N/A';
            document.getElementById('view_description').innerText = campaign.description || 'No description provided.';
            openModal('viewCampaignModal');
        }

        function switchToEditMode() {
            closeModal('viewCampaignModal');
            if(currentCampaignData) openEditModal(currentCampaignData);
        }

        function openEditModal(campaign) {
            document.getElementById('edit_campaign_id').value = campaign.id;
            document.getElementById('edit_campaign_name').value = campaign.campaign_name || '';
            document.getElementById('edit_campaign_type').value = campaign.campaign_type || '';
            document.getElementById('edit_status').value = campaign.status || '';
            document.getElementById('edit_budget').value = campaign.budget || '';
            document.getElementById('edit_currency').value = campaign.currency || 'USD';
            document.getElementById('edit_assigned_to').value = campaign.assigned_to || 'Unassigned';
            document.getElementById('edit_start_date').value = campaign.start_date || '';
            document.getElementById('edit_end_date').value = campaign.end_date || '';
            document.getElementById('edit_target_audience').value = campaign.target_audience || '';
            document.getElementById('edit_description').value = campaign.description || '';
            openModal('editCampaignModal');
        }

        function confirmDelete(formId, type) {
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete this ${type}. This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(formId).submit();
                }
            });
        }

        function toggleSubMenu(menuId) { 
            const menu = document.getElementById(menuId); menu.classList.toggle('open'); 
        }

        document.getElementById('outerToggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('collapsed'));
        
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (localStorage.getItem('darkMode') === 'enabled') { document.body.classList.add('dark-mode'); darkModeToggle.classList.replace('fa-moon', 'fa-sun'); }
        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            if (document.body.classList.contains('dark-mode')) { localStorage.setItem('darkMode', 'enabled'); darkModeToggle.classList.replace('fa-moon', 'fa-sun'); } 
            else { localStorage.setItem('darkMode', 'disabled'); darkModeToggle.classList.replace('fa-sun', 'fa-moon'); }
        });

        // Show Toast if message exists
        <?php if($toastMessage): ?>
        const toastBox = document.getElementById('toastBox');
        const toastMsg = document.getElementById('toastMsg');
        const toastIcon = document.getElementById('toastIcon');
        
        toastMsg.innerText = "<?php echo $toastMessage; ?>";
        toastBox.className = "show <?php echo $toastType; ?>";
        toastIcon.className = "fa-solid <?php echo ($toastType == 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>";
        
        setTimeout(() => { toastBox.className = toastBox.className.replace("show", ""); }, 4000);
        <?php endif; ?>
    </script>
</body>
</html>

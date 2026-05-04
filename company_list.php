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
// CSV EXPORT LOGIC FOR COMPANIES
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['export_companies_csv'])) {
    if (isset($conn)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=companies_export_' . date('Y-m-d') . '.csv');
        $output = fopen("php://output", "w");
        fputcsv($output, array('ID', 'Company Name', 'Assigned Agent', 'Total Contacts', 'Email', 'Phone', 'Website'));
        
        $query = mysqli_query($conn, "SELECT * FROM companies ORDER BY id DESC");
        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                fputcsv($output, array(
                    $row['id'], 
                    $row['company_name'], 
                    $row['assigned_agent'], 
                    $row['total_contacts'],
                    $row['company_email'] ?? '',
                    $row['company_number'] ?? '',
                    $row['company_website'] ?? ''
                ));
            }
        }
        fclose($output);
        exit(); 
    }
}

// ========================================================================
// 2. COMPANY LOGIC (CREATE, BULK UPLOAD, DELETE)
// ========================================================================

// A. Create Single Company
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_company'])) {
    if(isset($conn)){
        $comp_name = mysqli_real_escape_string($conn, $_POST['company_name'] ?? '');
        $assigned_agent = mysqli_real_escape_string($conn, $_POST['assigned_agent'] ?? 'Unassigned');
        $comp_email = mysqli_real_escape_string($conn, $_POST['company_email'] ?? '');
        $country_code = mysqli_real_escape_string($conn, $_POST['company_country_code'] ?? '');
        $raw_number = mysqli_real_escape_string($conn, $_POST['company_number'] ?? '');
        $comp_number = $country_code . ' ' . $raw_number;
        $comp_website = mysqli_real_escape_string($conn, $_POST['company_website'] ?? '');
        $fb_url = mysqli_real_escape_string($conn, $_POST['fb_url'] ?? '');
        $linkedin_url = mysqli_real_escape_string($conn, $_POST['linkedin_url'] ?? '');
        $insta_url = mysqli_real_escape_string($conn, $_POST['insta_url'] ?? '');
        $twitter_url = mysqli_real_escape_string($conn, $_POST['twitter_url'] ?? '');
        
        $total_contacts = 0; 

        $insert_sql = "INSERT INTO companies (company_name, assigned_agent, total_contacts, company_email, company_number, company_website, fb_url, linkedin_url, insta_url, twitter_url) 
                       VALUES ('$comp_name', '$assigned_agent', '$total_contacts', '$comp_email', '$comp_number', '$comp_website', '$fb_url', '$linkedin_url', '$insta_url', '$twitter_url')";
        try {
            if(mysqli_query($conn, $insert_sql)){
                $toastMessage = "Company added successfully!";
                $toastType = "success";
            }
        } catch (mysqli_sql_exception $e) {
            $toastMessage = "Database Error! Ensure all columns exist in 'companies' table.";
            $toastType = "error";
        }
    }
}

// B. Bulk Upload Companies
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_upload_companies'])) {
    if(isset($conn) && isset($_FILES['company_csv']) && $_FILES['company_csv']['error'] == 0){
        $file = $_FILES['company_csv']['tmp_name'];
        $handle = fopen($file, "r");
        $row_count = 0;
        try {
            while(($data = fgetcsv($handle, 1000, ",")) !== FALSE){
                $row_count++;
                if($row_count == 1) continue;
                
                $c_name = mysqli_real_escape_string($conn, $data[0] ?? '');
                $c_agent = mysqli_real_escape_string($conn, $data[1] ?? 'Unassigned');
                $c_contacts = (int)($data[2] ?? 0);
                
                if(!empty($c_name)){
                    mysqli_query($conn, "INSERT INTO companies (company_name, assigned_agent, total_contacts) VALUES ('$c_name', '$c_agent', '$c_contacts')");
                }
            }
            fclose($handle);
            $toastMessage = "CSV uploaded! Added " . ($row_count - 1) . " companies.";
            $toastType = "success";
        } catch (mysqli_sql_exception $e) {
            $toastMessage = "Upload Failed! Check CSV format & DB Table.";
            $toastType = "error";
        }
    } else {
        $toastMessage = "Please select a valid CSV file.";
        $toastType = "error";
    }
}

// C. Delete Company
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_company'])) {
    if(isset($conn)){
        $del_id = mysqli_real_escape_string($conn, $_POST['delete_company_id'] ?? '');
        $delete_sql = "DELETE FROM companies WHERE id='$del_id'";
        if(mysqli_query($conn, $delete_sql)){
            $toastMessage = "Company deleted successfully!";
            $toastType = "success";
        } else {
            $toastMessage = "Error deleting company!";
            $toastType = "error";
        }
    }
}

// ========================================================================
// 3. FETCH DATA FOR UI
// ========================================================================
$assigneeOptions = ""; 
if(isset($conn)){
    try {
        $user_query = mysqli_query($conn, "SELECT id, name FROM users ORDER BY name ASC");
        if($user_query && mysqli_num_rows($user_query) > 0){
            while($uRow = mysqli_fetch_assoc($user_query)){
                $userIdStr = "038H" . $uRow['id'];
                $assigneeOptions .= "<option value='{$uRow['name']}'>{$uRow['name']} ({$userIdStr})</option>";
            }
        }
    } catch (mysqli_sql_exception $e) {}
}

$hasCompanies = false;
$companyTableRows = "";
$totalCompanies = "0";

if(isset($conn)){
    try {
        $query_string = "
            SELECT c.id, c.company_name, c.assigned_agent, 
                   (SELECT COUNT(*) FROM contacts WHERE company_id = c.id) AS total_dynamic_contacts 
            FROM companies c 
            ORDER BY c.id DESC
        ";
        $comp_query = mysqli_query($conn, $query_string);
        if($comp_query && mysqli_num_rows($comp_query) > 0){
            $hasCompanies = true;
            $totalCompanies = mysqli_num_rows($comp_query);
            while($row = mysqli_fetch_assoc($comp_query)){
                $c_name = htmlspecialchars($row['company_name']);
                $c_agent = htmlspecialchars($row['assigned_agent']);
                $c_contacts = htmlspecialchars($row['total_dynamic_contacts']); 
                $c_id = $row['id'];
                $companyTableRows .= "<tr>
                    <td><input type='checkbox' class='tbl-checkbox'></td>
                    <td><b>{$c_name}</b></td>
                    <td><div style='display:flex; justify-content:center; align-items:center; gap:8px;'><i class='fa-solid fa-user' style='color:#9ca3af;'></i> {$c_agent}</div></td>
                    <td><span class='comp-contacts-pill'>{$c_contacts} Contacts</span></td>
                    <td>
                        <div class='action-btns'>
                            <button class='btn-view' title='View' onclick=\"showToast('View Feature Coming Soon','success')\"><i class='fa-regular fa-eye'></i></button>
                            <form method='POST' id='delete-comp-{$c_id}' style='display:inline;'>
                                <input type='hidden' name='delete_company_id' value='{$c_id}'>
                                <input type='hidden' name='delete_company' value='1'>
                                <button type='button' class='btn-delete' onclick='confirmDelete(\"delete-comp-{$c_id}\", \"company\")' title='Delete'><i class='fa-solid fa-trash'></i></button>
                            </form>
                        </div>
                    </td>
                </tr>";
            }
        }
    } catch(mysqli_sql_exception $e) {}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Company & Organization - Systellio CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* আপনার সম্পূর্ণ গ্লোবাল CSS কোড এখানে থাকবে */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: #f3f4f6; display: flex; height: 100vh; overflow: hidden; transition: background-color 0.3s, color 0.3s; color: #111827; }
        
        #toastBox { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 9999; right: 30px; top: 30px; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 10px; transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55), visibility 0.4s; }
        #toastBox.show { visibility: visible; transform: translateX(0); }
        #toastBox.success { background-color: #10b981; }
        #toastBox.error { background-color: #ef4444; }

        .sidebar { width: 260px; background-color: #0b1524; color: #ffffff; display: flex; flex-direction: column; transition: margin-left 0.3s ease; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 1000; }
        .sidebar.collapsed { margin-left: -260px; }
        .sidebar-header { padding: 25px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .sidebar-logo { width: 100px; height: auto; margin-bottom: 10px; }
        .brand-role { font-size: 10px; font-weight: 700; color: #60a5fa; letter-spacing: 1.5px; text-transform: uppercase; }
        .sidebar-menu { list-style: none; padding: 10px 0; flex-grow: 1; overflow-y: auto; }
        .sidebar-menu > li { padding: 14px 20px 14px 21px; display: flex; align-items: center; gap: 15px; cursor: pointer; transition: 0.3s; color: #94a3b8; border-left: 3px solid transparent; }
        .sidebar-menu > li:hover { background-color: #162235; color: #ffffff; }
        .sidebar-menu > li i { font-size: 15px; width: 20px; text-align: center; }
        .sidebar-menu > li a { color: inherit; text-decoration: none; font-size: 13px; font-weight: 500; width: 100%; }
        .sidebar-menu > li.active { background-color: #1e293b; color: #3b82f6; border-left: 3px solid #3b82f6; }
        .sidebar-menu > li.active i { color: #3b82f6; }
        .dropdown-title.active-main { color: #3b82f6; }
        .submenu li.active-sub a { color: #3b82f6; font-weight: 600; }
        .submenu li.active-sub { position: relative; }
        .submenu li.active-sub::before { content: ""; position: absolute; left: 35px; top: 50%; transform: translateY(-50%); width: 6px; height: 6px; background-color: #3b82f6; border-radius: 50%; }

        .dropdown-item { padding: 0 !important; display: block !important; border-left: none !important; }
        .dropdown-title { padding: 14px 20px 14px 24px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; color: #94a3b8; transition: 0.3s; border-left: 3px solid transparent; }
        .dropdown-title:hover { background-color: #162235; color: #ffffff; }
        .dropdown-title-left { display: flex; align-items: center; gap: 15px; }
        .dropdown-icon { font-size: 10px !important; transition: transform 0.3s ease; }
        .submenu { list-style: none; display: none; background-color: #0b1524; padding-top: 5px; padding-bottom: 10px; }
        .submenu li { padding: 10px 20px 10px 59px !important; border-left: none !important; background-color: transparent !important; }
        .submenu li a { color: #64748b; text-decoration: none; font-size: 12px; transition: 0.3s; cursor: pointer; }
        .submenu li a:hover { color: #ffffff; }
        .dropdown-item.open .submenu { display: block; }
        .dropdown-item.open .dropdown-icon { transform: rotate(180deg); }

        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; transition: background-color 0.3s ease; background-color: #f3f4f6; }
        .top-navbar { background-color: #ffffff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.03); transition: 0.3s; }
        .toggle-btn { font-size: 20px; color: #4b5563; cursor: pointer; transition: color 0.3s; }
        .navbar-actions { display: flex; align-items: center; gap: 20px; }
        .nav-icon-btn { cursor: pointer; font-size: 20px; color: #6b7280; transition: 0.3s; position: relative; }
        .nav-icon-btn:hover { color: #3b82f6; }
        .notification-badge { position: absolute; top: -4px; right: -4px; background-color: #ef4444; color: white; font-size: 9px; font-weight: bold; padding: 2px 5px; border-radius: 50%; border: 2px solid #ffffff; }
        .user-profile { display: flex; align-items: center; gap: 10px; font-weight: 600; color: inherit; font-size: 14px; }

        .company-container { padding: 30px; display: block; }
        .comp-header-title h1 { font-size: 26px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; transition: 0.3s; color: #111827;}
        .comp-header-title p { font-size: 13px; color: #6b7280; font-weight: 500; }
        .header-buttons { display: flex; gap: 10px; }
        
        .btn-export { background-color: #10b981; color: #ffffff; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s;}
        .btn-upload { background-color: #1e293b; color: #ffffff; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s;}
        .btn-add-client { background-color: #3b82f6; color: #ffffff; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s;}

        .comp-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;}
        .comp-search { position: relative; width: 300px; }
        .comp-search i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 14px;}
        .comp-search input { width: 100%; padding: 10px 15px 10px 38px; border: 1px solid #d1d5db; border-radius: 20px; font-size: 13px; font-family: 'Inter', sans-serif; outline: none; transition: 0.3s; color: #374151;}
        .comp-total { font-size: 13px; font-weight: 600; color: #4b5563; background: #ffffff; border: 1px solid #d1d5db; padding: 8px 15px; border-radius: 20px;}

        .table-wrapper { border-radius: 8px; overflow: hidden; border: 1px solid #d1d5db; transition: 0.3s; background: #ffffff;}
        .custom-table { width: 100%; border-collapse: collapse; text-align: center; font-size: 12px; }
        .custom-table th { background-color: #c4f042; padding: 14px 10px; font-weight: 700; color: #000000; border-bottom: 1px solid #d1d5db; transition: 0.3s;}
        .custom-table td { padding: 14px 10px; color: #374151; font-weight: 500; vertical-align: middle; border-right: 1px solid rgba(0,0,0,0.05); transition: 0.3s;}
        .custom-table tbody tr:nth-child(4n+1) { background-color: #e6fced; } 
        .custom-table tbody tr:nth-child(4n+2) { background-color: #fcedf6; } 
        .custom-table tbody tr:nth-child(4n+3) { background-color: #fceddb; } 
        .custom-table tbody tr:nth-child(4n+4) { background-color: #e6edff; } 

        .comp-contacts-pill { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 20px; display: inline-block;}
        .tbl-checkbox { width: 16px; height: 16px; border: 1px solid #d1d5db; border-radius: 4px; cursor: pointer; accent-color: #3b82f6;}
        .action-btns { display: flex; justify-content: center; gap: 6px; }
        .btn-view { background-color: #60a5fa; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}
        .btn-delete { background-color: #f87171; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}

        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; padding: 30px; border-radius: 10px; width: 100%; max-width: 600px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-height: 90vh; overflow-y: auto;}
        .small-modal { max-width: 450px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-btn { font-size: 20px; cursor: pointer; color: #6b7280; border: none; background: none; }
        
        .comp-input { width: 100%; border: none; border-bottom: 2px solid #e5e7eb; padding: 10px 0; font-size: 14px; outline: none; transition: 0.3s; background: transparent;}
        .comp-input:focus { border-bottom-color: #3b82f6; }
        .comp-select { width: 100%; border: none; border-bottom: 2px solid #e5e7eb; padding: 10px 0; font-size: 14px; outline: none; transition: 0.3s; background: transparent; cursor: pointer;}
        .comp-input-label { font-size: 12px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; display: block;}
        .comp-info-header { display: flex; align-items: center; gap: 10px; color: #3b82f6; font-weight: 700; font-size: 15px; margin-top: 15px; margin-bottom: 5px;}
        .comp-info-header::after { content: ''; flex-grow: 1; height: 1px; background: #bfdbfe; }
        .comp-info-subtext { font-size: 11px; color: #6b7280; margin-bottom: 20px;}
        .comp-save-btn { background-color: #3b82f6; color: white; border: none; padding: 12px 0; border-radius: 8px; width: 100%; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 15px;}
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .form-group { margin-bottom: 15px; }
        .full-width { grid-column: span 2; }

        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .main-content { background-color: #0f172a; }
        body.dark-mode .top-navbar { background-color: #1e293b; border-bottom: 1px solid #334155; box-shadow: none; }
        body.dark-mode .nav-icon-btn { color: #cbd5e1; }
        body.dark-mode .comp-header-title h1 { color: #f8fafc; }
        body.dark-mode .table-wrapper { border-color: #334155; background: #1e293b; }
        body.dark-mode .custom-table th { background-color: #334155; color: #f8fafc; border-color: #475569; }
        body.dark-mode .custom-table td { color: #cbd5e1; border-color: #334155; }
        body.dark-mode .custom-table tbody tr:nth-child(even) { background-color: #1e293b; } 
        body.dark-mode .custom-table tbody tr:nth-child(odd) { background-color: #0f172a; } 
        body.dark-mode .custom-table tbody tr:hover { background-color: #334155; }
        body.dark-mode .comp-search input { background-color: #0f172a; color: #f8fafc; border-color: #334155; }
        body.dark-mode .comp-total { background-color: #0f172a; color: #cbd5e1; border-color: #334155; }
        body.dark-mode .modal-content { background-color: #1e293b; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        body.dark-mode .comp-input { border-bottom-color: #475569; color: #f8fafc; }
        body.dark-mode .comp-select { border-bottom-color: #475569; color: #f8fafc; }
        body.dark-mode .comp-select option { background-color: #1e293b; color: #f8fafc; }
        body.dark-mode .comp-info-header { color: #60a5fa; }
        body.dark-mode .comp-info-header::after { background: #334155; }
        .swal2-container { z-index: 9999 !important; }
        body.dark-mode .swal2-popup { background-color: #1e293b; color: #f8fafc; border: 1px solid #334155; }
        body.dark-mode .swal2-title, body.dark-mode .swal2-html-container { color: #f8fafc; }
    </style>
</head>
<body>

    <div id="toastBox"><i id="toastIcon" class="fa-solid fa-circle-check"></i><span id="toastMsg">Action Successful!</span></div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Systellio Logo" class="sidebar-logo">
            <span class="brand-role">SUPER ADMIN</span>
        </div>

        <ul class="sidebar-menu">
            <li onclick="window.location.href='super_admin_dashboard.php'"><i class="fa-solid fa-table-cells-large"></i><a href="super_admin_dashboard.php">Dashboard</a></li>

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

            <li class="dropdown-item open" id="leadsMenu">
                <div class="dropdown-title active-main" onclick="toggleSubMenu('leadsMenu')">
                    <div class="dropdown-title-left"><i class="fa-solid fa-briefcase"></i><span>Leads & Accounts</span></div>
                    <i class="fa-solid fa-chevron-down dropdown-icon" style="transform: rotate(180deg);"></i>
                </div>
                <ul class="submenu" style="display: block;">
                    <li class="active-sub"><a href="company_list.php">Company & Organization</a></li>
                    <li><a href="client_list.php">Accounts & Clients</a></li>
                </ul>
            </li>
            
            <li class="dropdown-item" id="dealsMenu">
                <div class="dropdown-title" onclick="toggleSubMenu('dealsMenu')">
                    <div class="dropdown-title-left"><i class="fa-solid fa-bullhorn"></i><span>Deals & Campaign</span></div>
                    <i class="fa-solid fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="deal_pipeline.php">Deal Pipeline</a></li>
                    <li><a href="analytics_reports.php">Campaigns</a></li>
                </ul>
            </li>

            <li onclick="window.location.href='#'"><i class="fa-solid fa-clipboard-list"></i><a href="#">Task Management</a></li>
            <li onclick="window.location.href='analytics_reports.php'"><i class="fa-solid fa-chart-column"></i><a href="analytics_reports.php">Analytics & Reports</a></li>
            <li onclick="window.location.href='settings.php'"><i class="fa-solid fa-gear"></i><a href="settings.php">Settings</a></li>
            
            <li style="margin-top: 20px; border-top: 1px solid #1e293b; padding-top: 20px;" onclick="window.location.href='logout.php'"><i class="fa-solid fa-right-from-bracket" style="color: #ef4444;"></i><a href="#">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <div><i class="fa-solid fa-bars toggle-btn" id="outerToggle"></i></div>
            <div class="navbar-actions">
                <i class="fa-solid fa-moon nav-icon-btn" id="darkModeToggle"></i>
                <div class="nav-icon-btn" title="Notifications" onclick="showToast('No new notifications', 'success')"><i class="fa-regular fa-bell"></i><span class="notification-badge">3</span></div>
                <div class="user-profile"><i class="fa-solid fa-circle-user" style="color: #3b82f6;"></i><span><?php echo $_SESSION['name']; ?></span></div>
            </div>
        </div>

        <div class="company-container">
            <div class="user-list-header" style="margin-bottom: 20px;">
                <div class="comp-header-title">
                    <h1>Company Database</h1>
                    <p>Welcome back, <?php echo $_SESSION['name']; ?></p>
                </div>
                <div class="header-buttons">
                    <form method="POST" style="display:inline-block;">
                        <button type="submit" name="export_companies_csv" class="btn-export" style="height: 100%;"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
                    </form>
                    <button class="btn-upload" onclick="openModal('bulkUploadCompanyModal')"><i class="fa-solid fa-cloud-arrow-up"></i> Bulk Upload</button>
                    <button class="btn-add-client" onclick="openModal('addCompanyModal')"><i class="fa-solid fa-plus"></i> Add Company</button>
                </div>
            </div>

            <div class="comp-toolbar">
                <div class="comp-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search company...">
                </div>
                <div class="comp-total">Total Companies: <?php echo (isset($hasCompanies) && $hasCompanies) ? $totalCompanies : "4"; ?></div>
            </div>

            <div class="table-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="tbl-checkbox" title="Select All"></th>
                            <th>Company Name</th>
                            <th>Assigned Agent</th>
                            <th>Total Contacts</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(isset($hasCompanies) && $hasCompanies): ?>
                            <?php echo $companyTableRows; ?>
                        <?php else: ?>
                            <tr>
                                <td><input type="checkbox" class="tbl-checkbox"></td>
                                <td><b>Blue Point Accounting</b></td>
                                <td><div style="display:flex; justify-content:center; align-items:center; gap:8px;"><i class="fa-solid fa-user" style="color:#9ca3af;"></i> <?php echo explode(' ', trim($_SESSION['name']))[0]; ?></div></td>
                                <td><span class="comp-contacts-pill">9 Contacts</span></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-view" onclick="showToast('View Feature Coming Soon','success')"><i class="fa-regular fa-eye"></i></button>
                                        <button class="btn-delete" onclick="showToast('Dummy Delete','error')"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="addCompanyModal" class="modal">
        <div class="modal-content comp-modal-content">
            <div class="modal-header">
                <h2>Add New Company</h2>
                <button type="button" class="close-btn" onclick="closeModal('addCompanyModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="company_list.php" method="POST">
                <div class="form-group full-width" style="margin-bottom: 20px;">
                    <label class="comp-input-label">Company Name <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="company_name" class="comp-input" required>
                </div>
                
                <div class="comp-info-header"><i class="fa-solid fa-building"></i> Company Info</div>
                <p class="comp-info-subtext">If selecting an existing company, these fields will update the company record.</p>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="comp-input-label">Company Email</label>
                        <input type="email" name="company_email" class="comp-input" placeholder="info@company.com">
                    </div>
                    <div class="form-group">
                        <label class="comp-input-label">Company Number</label>
                        <div style="display: flex; gap: 10px;">
                            <select name="company_country_code" class="comp-select" style="max-width: 100px;">
                                <option value="+880">🇧🇩 +880</option>
                                <option value="+1">🇺🇸 +1</option>
                                <option value="+44">🇬🇧 +44</option>
                            </select>
                            <input type="text" name="company_number" class="comp-input" placeholder="234 567 8900" style="flex: 1;">
                        </div>
                    </div>
                </div>

                <div class="form-group full-width" style="margin-bottom: 20px;">
                    <label class="comp-input-label">Company Website</label>
                    <input type="url" name="company_website" class="comp-input" placeholder="https://...">
                </div>
                
                <div class="form-group full-width" style="margin-bottom: 25px;">
                    <label class="comp-input-label">Assigned Agent</label>
                    <select name="assigned_agent" class="comp-select">
                        <option value="Unassigned" selected>Select Agent...</option>
                        <?php echo $assigneeOptions; ?>
                    </select>
                </div>

                <div class="form-grid-4" style="margin-bottom: 25px;">
                    <div class="form-group"><label class="comp-input-label"><i class="fa-brands fa-facebook" style="color: #1877F2;"></i> Facebook</label><input type="url" name="fb_url" class="comp-input" placeholder="URL"></div>
                    <div class="form-group"><label class="comp-input-label"><i class="fa-brands fa-linkedin" style="color: #0A66C2;"></i> LinkedIn</label><input type="url" name="linkedin_url" class="comp-input" placeholder="URL"></div>
                    <div class="form-group"><label class="comp-input-label"><i class="fa-brands fa-instagram" style="color: #E4405F;"></i> Instagram</label><input type="url" name="insta_url" class="comp-input" placeholder="URL"></div>
                    <div class="form-group"><label class="comp-input-label"><i class="fa-brands fa-x-twitter" style="color: #000000;"></i> Twitter</label><input type="url" name="twitter_url" class="comp-input" placeholder="URL"></div>
                </div>
                
                <button type="submit" name="create_company" class="comp-save-btn">Save Company</button>
            </form>
        </div>
    </div>

    <div id="bulkUploadCompanyModal" class="modal">
        <div class="modal-content small-modal">
            <div class="modal-header">
                <h2>Bulk Upload Companies (CSV)</h2>
                <button type="button" class="close-btn" onclick="closeModal('bulkUploadCompanyModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="company_list.php" method="POST" enctype="multipart/form-data">
                <p style="font-size: 12px; color: #6b7280; margin-bottom: 15px;">Columns: <b>Company Name, Assigned Agent, Total Contacts</b></p>
                <div class="form-group" style="margin-bottom: 20px;"><input type="file" name="company_csv" accept=".csv" required></div>
                <button type="submit" name="bulk_upload_companies" class="submit-btn" style="background-color: #10b981;">Upload CSV Data</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = "flex"; }
        function closeModal(id) { document.getElementById(id).style.display = "none"; }
        window.onclick = function(event) { if (event.target.classList.contains('modal')) event.target.style.display = "none"; }

        function showToast(message, type) {
            const toast = document.getElementById("toastBox");
            document.getElementById("toastMsg").innerText = message;
            toast.className = "show " + type;
            document.getElementById("toastIcon").className = (type === 'success') ? "fa-solid fa-circle-check" : "fa-solid fa-circle-xmark";
            setTimeout(() => toast.className = toast.className.replace("show", ""), 3000);
        }

        window.onload = function() {
            <?php if($toastMessage != ""): ?> showToast("<?php echo $toastMessage; ?>", "<?php echo $toastType; ?>"); <?php endif; ?>
        };

        function toggleSubMenu(menuId) { 
            const menu = document.getElementById(menuId); menu.classList.toggle('open'); 
            const icon = menu.querySelector('.dropdown-icon');
            if(menu.classList.contains('open')) { icon.style.transform = 'rotate(180deg)'; } 
            else { icon.style.transform = 'rotate(0deg)'; }
        }

        document.getElementById('outerToggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('collapsed'));
        
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (localStorage.getItem('darkMode') === 'enabled') { document.body.classList.add('dark-mode'); darkModeToggle.classList.replace('fa-moon', 'fa-sun'); }
        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            if (document.body.classList.contains('dark-mode')) { localStorage.setItem('darkMode', 'enabled'); darkModeToggle.classList.replace('fa-moon', 'fa-sun'); } 
            else { localStorage.setItem('darkMode', 'disabled'); darkModeToggle.classList.replace('fa-sun', 'fa-moon'); }
        });

        function confirmDelete(formId, typeName) {
            const isDark = document.body.classList.contains('dark-mode');
            Swal.fire({
                title: 'Are you sure?', text: "You won't be able to revert this!", icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#ef4444', cancelButtonColor: '#6b7280', confirmButtonText: 'Yes, delete it!',
                background: isDark ? '#1e293b' : '#fff', color: isDark ? '#f8fafc' : '#111827'
            }).then((result) => { if (result.isConfirmed) { document.getElementById(formId).submit(); } });
        }
    </script>
</body>
</html>
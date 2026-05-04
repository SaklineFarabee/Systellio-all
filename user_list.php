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
// 2. USER MANAGEMENT LOGIC (CREATE, UPDATE, DELETE)
// ========================================================================

// A. CREATE NEW USER LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    if(isset($conn)){
        $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $username = mysqli_real_escape_string($conn, $_POST['username'] ?? ''); 
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $role = mysqli_real_escape_string($conn, $_POST['role'] ?? '');
        $designation = mysqli_real_escape_string($conn, $_POST['designation'] ?? '');
        $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $country_code = mysqli_real_escape_string($conn, $_POST['country_code'] ?? '');
        $full_phone = trim($country_code . ' ' . $phone);
        
        $raw_password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if($raw_password !== $confirm_password) {
            $toastMessage = "Passwords do not match!"; $toastType = "error";
        } else {
            $password = password_hash($raw_password, PASSWORD_DEFAULT); 
            $status = 'active'; 
            
            // Debugging: Check if columns exist in the database
            // Based on crm_system (1).sql, the 'users' table has: id, name, username, email, password, role, designation, status, created_at
            // It DOES NOT have a 'phone' column. This is likely the bug.
            
            $insert_sql = "INSERT INTO users (name, username, email, password, role, designation, status) VALUES ('$name', '$username', '$email', '$password', '$role', '$designation', '$status')";
            
            try {
                if(mysqli_query($conn, $insert_sql)){
                    $toastMessage = "User created successfully!"; $toastType = "success";
                }
            } catch (mysqli_sql_exception $e) {
                $toastMessage = "Database Error: " . $e->getMessage(); $toastType = "error";
            }
        }
    }
}

// B. UPDATE/EDIT EXISTING USER LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    if(isset($conn)){
        $id = mysqli_real_escape_string($conn, $_POST['user_id'] ?? '');
        $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $role = mysqli_real_escape_string($conn, $_POST['role'] ?? '');
        $designation = mysqli_real_escape_string($conn, $_POST['designation'] ?? '');
        $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'active'); 

        $raw_password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if(!empty($raw_password) && $raw_password !== $confirm_password) {
            $toastMessage = "Passwords do not match! User not updated."; $toastType = "error";
        } else {
            $update_sql = "UPDATE users SET name='$name', username='$username', email='$email', role='$role', designation='$designation', status='$status'";
            if (!empty($raw_password)) {
                $new_password = password_hash($raw_password, PASSWORD_DEFAULT);
                $update_sql .= ", password='$new_password'";
            }
            $update_sql .= " WHERE id='$id'";
            try {
                if(mysqli_query($conn, $update_sql)){
                    $toastMessage = "User updated successfully!"; $toastType = "success";
                }
            } catch (mysqli_sql_exception $e) {
                $toastMessage = "Database Error: " . $e->getMessage(); $toastType = "error";
            }
        }
    }
}

// C. DELETE USER LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    if(isset($conn)){
        $del_id = mysqli_real_escape_string($conn, $_POST['delete_user_id'] ?? '');
        $delete_sql = "DELETE FROM users WHERE id='$del_id'";
        try {
            if(mysqli_query($conn, $delete_sql)){
                $toastMessage = "User deleted successfully!"; $toastType = "success";
            }
        } catch (mysqli_sql_exception $e) {
            $toastMessage = "Error deleting user!"; $toastType = "error";
        }
    }
}

// ========================================================================
// 3. DESIGNATION MANAGEMENT LOGIC
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_designation']) && isset($conn)) {
        $designation_title = mysqli_real_escape_string($conn, $_POST['designation_title'] ?? '');
        $insert_desig = "INSERT INTO designations (title) VALUES ('$designation_title')";
        try {
            if(mysqli_query($conn, $insert_desig)){
                $toastMessage = "Designation added successfully!"; $toastType = "success";
            }
        } catch (mysqli_sql_exception $e) {
            $toastMessage = "Error adding designation!"; $toastType = "error";
        }
    }
    
    if (isset($_POST['update_designation']) && isset($conn)) {
        $desig_id = mysqli_real_escape_string($conn, $_POST['desig_id'] ?? '');
        $designation_title = mysqli_real_escape_string($conn, $_POST['designation_title'] ?? '');
        $update_desig = "UPDATE designations SET title='$designation_title' WHERE id='$desig_id'";
        if(mysqli_query($conn, $update_desig)){
            $toastMessage = "Designation updated successfully!"; $toastType = "success";
        } else {
            $toastMessage = "Error updating designation!"; $toastType = "error";
        }
    }

    if (isset($_POST['delete_designation']) && isset($conn)) {
        $desig_id = mysqli_real_escape_string($conn, $_POST['desig_id'] ?? '');
        $delete_desig = "DELETE FROM designations WHERE id='$desig_id'";
        if(mysqli_query($conn, $delete_desig)){
            $toastMessage = "Designation deleted successfully!"; $toastType = "success";
        } else {
            $toastMessage = "Error deleting designation!"; $toastType = "error";
        }
    }
}

// ========================================================================
// 4. FETCH DATA FOR UI (Designations, Users)
// ========================================================================
$designationsList = ""; 
$designationTableRows = ""; 

if(isset($conn)){
    try {
        $desig_query = mysqli_query($conn, "SELECT * FROM designations ORDER BY title ASC");
        if($desig_query && mysqli_num_rows($desig_query) > 0){
            while($row = mysqli_fetch_assoc($desig_query)){
                $designationsList .= "<option value='{$row['title']}'>{$row['title']}</option>";
                $desigData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                $designationTableRows .= "
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: left; font-weight: 500;' class='desig-text'>{$row['title']}</td>
                        <td style='padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: right;'>
                            <button type='button' style='background:none; border:none; color:#3b82f6; cursor:pointer; margin-right:15px; font-size:14px;' onclick='openEditDesignationModal({$desigData})'><i class='fa-solid fa-pen-to-square'></i></button>
                            <form method='POST' id='delete-desig-{$row['id']}' style='display:inline;'>
                                <input type='hidden' name='desig_id' value='{$row['id']}'>
                                <input type='hidden' name='delete_designation' value='1'>
                                <button type='button' onclick='confirmDelete(\"delete-desig-{$row['id']}\", \"designation\")' style='background:none; border:none; color:#ef4444; cursor:pointer; font-size:14px;'><i class='fa-solid fa-trash'></i></button>
                            </form>
                        </td>
                    </tr>";
            }
        } else {
            $designationsList .= "<option value='COO'>COO</option><option value='CTO'>CTO</option><option value='Manager'>Manager</option>";
            $designationTableRows .= "<tr><td colspan='2' style='padding: 10px; text-align:center; color:#6b7280;'>No designations found.</td></tr>";
        }
    } catch (mysqli_sql_exception $e) {
        $designationsList .= "<option value='COO'>COO</option><option value='CTO'>CTO</option><option value='Manager'>Manager</option>";
        $designationTableRows .= "<tr><td colspan='2' style='padding: 10px; text-align:center; color:#ef4444;'>Table 'designations' not found.</td></tr>";
    }
}

$userTableRows = "";
if(isset($conn)){
    $users_query = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
    if($users_query && mysqli_num_rows($users_query) > 0){
        while($row = mysqli_fetch_assoc($users_query)){
            $userData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            $statusClass = ($row['status'] == 'active') ? 'status-active' : 'status-inactive';
            $userTableRows .= "
                <tr class='user-row' data-status='{$row['status']}'>
                    <td>#{$row['id']}</td>
                    <td style='text-align: left; font-weight: 600;'>{$row['name']}</td>
                    <td>{$row['username']}</td>
                    <td>{$row['email']}</td>
                    <td>{$row['role']}</td>
                    <td><span class='badge $statusClass'>{$row['status']}</span></td>
                    <td>
                        <div class='action-btns'>
                            <button class='btn-view' onclick='openViewModal({$userData})'><i class='fa-solid fa-eye'></i></button>
                            <button class='btn-edit' onclick='openEditModal({$userData})'><i class='fa-solid fa-pen'></i></button>
                            <form method='POST' id='delete-user-{$row['id']}' style='display:inline;'>
                                <input type='hidden' name='delete_user_id' value='{$row['id']}'>
                                <input type='hidden' name='delete_user' value='1'>
                                <button type='button' class='btn-delete' onclick='confirmDelete(\"delete-user-{$row['id']}\", \"user\")'><i class='fa-solid fa-trash'></i></button>
                            </form>
                        </div>
                    </td>
                </tr>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User List - Systellio CRM</title>
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

        /* User Section Styles */
        #userSection { padding: 30px; display: block; }
        .user-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; }
        .user-title h1 { font-size: 26px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; transition: 0.3s;}
        .user-title p { font-size: 11px; color: #6b7280; font-weight: 500; }
        
        .header-actions { display: flex; gap: 12px; }
        .btn-primary { background-color: #000000; color: #ffffff; padding: 10px 18px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background-color: #1f2937; transform: translateY(-1px); }
        .btn-secondary { background-color: #ffffff; color: #111827; padding: 10px 18px; border-radius: 6px; font-size: 12px; font-weight: 600; border: 1px solid #d1d5db; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .btn-secondary:hover { background-color: #f9fafb; }

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
        .status-active { background-color: #dcfce7; color: #10b981; }
        .status-inactive { background-color: #fee2e2; color: #ef4444; }

        .action-btns { display: flex; justify-content: center; gap: 6px; }
        .btn-view { background-color: #60a5fa; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}
        .btn-edit { background-color: #34d399; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}
        .btn-delete { background-color: #f87171; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}
        .btn-view:hover { background-color: #3b82f6; }
        .btn-edit:hover { background-color: #10b981; }
        .btn-delete:hover { background-color: #ef4444; }

        /* Modals */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; padding: 30px; border-radius: 10px; width: 100%; max-width: 600px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-height: 90vh; overflow-y: auto; transition: 0.3s;}
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { font-size: 20px; font-weight: 700; transition: 0.3s;}
        .close-btn { font-size: 20px; cursor: pointer; color: #6b7280; border: none; background: none; transition: 0.3s;}
        .close-btn:hover { color: #ef4444; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 15px; position: relative; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: #374151; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; outline: none; transition: 0.3s; }
        .form-group input:focus, .form-group select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        .password-toggle { position: absolute; right: 12px; top: 35px; cursor: pointer; color: #6b7280; font-size: 14px; }
        .password-error { color: #ef4444; font-size: 10px; font-weight: 600; margin-top: 4px; display: none; }

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
        body.dark-mode .form-group input, body.dark-mode .form-group select { background-color: #0f172a; color: #f8fafc; border-color: #334155; }
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

            <li class="dropdown-item open" id="userMenu">
                <div class="dropdown-title" onclick="toggleSubMenu('userMenu')">
                    <div class="dropdown-title-left"><i class="fa-solid fa-user-group"></i><span>User Management</span></div>
                    <i class="fa-solid fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li class="active-sub"><a href="user_list.php">User List</a></li>
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

        <div id="userSection">
            <div class="user-header">
                <div class="user-title">
                    <h1>User Management</h1>
                    <p>Manage your team members, roles, and account permissions.</p>
                </div>
                <div class="header-actions">
                    <button class="btn-secondary" onclick="openModal('createDesignationModal')"><i class="fa-solid fa-id-badge"></i> Designations</button>
                    <button class="btn-primary" onclick="openModal('createUserModal')"><i class="fa-solid fa-plus"></i> Add New User</button>
                </div>
            </div>

            <div class="tab-container">
                <div class="tab-btn active" onclick="filterUsers('all', this)">All Users</div>
                <div class="tab-btn" onclick="filterUsers('active', this)">Active</div>
                <div class="tab-btn" onclick="filterUsers('inactive', this)">In-Active</div>
            </div>

            <div class="table-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>User ID</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo $userTableRows; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="createDesignationModal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h2>Manage Designations</h2>
                <button type="button" class="close-btn" onclick="closeModal('createDesignationModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="user_list.php" method="POST">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>New Designation Title</label>
                    <input type="text" name="designation_title" required placeholder="e.g. Senior Developer">
                </div>
                <button type="submit" name="create_designation" class="submit-btn">Add Designation</button>
            </form>
            <div style="margin-top: 25px;">
                <h3 style="font-size: 14px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Existing Designations</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <?php echo $designationTableRows; ?>
                </table>
            </div>
        </div>
    </div>

    <div id="editDesignationModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2>Edit Designation</h2>
                <button type="button" class="close-btn" onclick="closeModal('editDesignationModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="user_list.php" method="POST">
                <input type="hidden" name="desig_id" id="edit_desig_id">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Designation Title</label>
                    <input type="text" name="designation_title" id="edit_desig_title" required>
                </div>
                <button type="submit" name="update_designation" class="submit-btn" style="background-color: #22c55e;">Update Designation</button>
            </form>
        </div>
    </div>

    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New User</h2>
                <button type="button" class="close-btn" onclick="closeModal('createUserModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="user_list.php" method="POST">
                <div class="form-grid">
                    <div class="form-group"><label>Full Name</label><input type="text" name="name" required placeholder="e.g. MD. Farabee"></div>
                    <div class="form-group"><label>User ID</label><input type="text" name="username" required placeholder="e.g. sakline01"></div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <div style="display: flex; gap: 10px;">
                            <select name="country_code" style="padding: 5px; max-width: 120px;">
                                <option value="+880">🇧🇩 +880</option>
                                <option value="+1">🇺🇸 +1</option>
                                <option value="+44">🇬🇧 +44</option>
                                <option value="+91">🇮🇳 +91</option>
                            </select>
                            <input type="text" name="phone" placeholder="1812345678" style="flex: 1; padding: 5px;">
                        </div>
                    </div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" required placeholder="e.g. example@gmail.com"></div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" id="create_pass" required placeholder="********" onkeyup="checkPasswordMatch('create_pass', 'create_confirm_pass', 'create_error_msg', 'create_submit_btn')">
                        <i class="fa-solid fa-eye password-toggle" onclick="togglePassword('create_pass', this)"></i>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" id="create_confirm_pass" required placeholder="********" onkeyup="checkPasswordMatch('create_pass', 'create_confirm_pass', 'create_error_msg', 'create_submit_btn')">
                        <i class="fa-solid fa-eye password-toggle" onclick="togglePassword('create_confirm_pass', this)"></i>
                        <span id="create_error_msg" class="password-error">Passwords do not match!</span>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required>
                            <option value="" disabled selected>Select Role</option>
                            <option value="super_admin">Super Admin</option>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="agent">Agent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <select name="designation">
                            <option value="" disabled selected>Select Designation</option>
                            <?php echo $designationsList; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="create_user" id="create_submit_btn" class="submit-btn" style="margin-top: 20px;">Save User</button>
            </form>
        </div>
    </div>

    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User Details</h2>
                <button type="button" class="close-btn" onclick="closeModal('editUserModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="user_list.php" method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-grid">
                    <div class="form-group"><label>Full Name</label><input type="text" name="name" id="edit_name" required></div>
                    <div class="form-group"><label>User ID</label><input type="text" name="username" id="edit_username" required></div>
                    <div class="form-group"><label>Phone Number</label><input type="text" name="phone" id="edit_phone"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" required></div>
                    
                    <div class="form-group">
                        <label>Password <span style="font-weight: 400; font-size: 10px; opacity:0.7;">(Leave blank to keep same)</span></label>
                        <input type="password" name="password" id="edit_password" placeholder="********" onkeyup="checkPasswordMatch('edit_password', 'edit_confirm_password', 'edit_error_msg', 'edit_submit_btn')">
                        <i class="fa-solid fa-eye password-toggle" onclick="togglePassword('edit_password', this)"></i>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" id="edit_confirm_password" placeholder="********" onkeyup="checkPasswordMatch('edit_password', 'edit_confirm_password', 'edit_error_msg', 'edit_submit_btn')">
                        <i class="fa-solid fa-eye password-toggle" onclick="togglePassword('edit_confirm_password', this)"></i>
                        <span id="edit_error_msg" class="password-error">Passwords do not match!</span>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" id="edit_role" required>
                            <option value="super_admin">Super Admin</option>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="agent">Agent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Account Status</label>
                        <select name="status" id="edit_status" required>
                            <option value="active">Active</option>
                            <option value="inactive">In-Active</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <select name="designation" id="edit_designation">
                            <?php echo $designationsList; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="update_user" id="edit_submit_btn" class="submit-btn" style="background-color: #22c55e; margin-top: 20px;">Update User</button>
            </form>
        </div>
    </div>

    <div id="viewUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>User Profile View</h2>
                <button type="button" class="close-btn" onclick="closeModal('viewUserModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="form-grid">
                <div class="form-group"><label>Full Name</label><div class="view-data-box" id="view_name">-</div></div>
                <div class="form-group"><label>User ID</label><div class="view-data-box" id="view_username">-</div></div>
                <div class="form-group"><label>Phone Number</label><div class="view-data-box" id="view_phone">-</div></div>
                <div class="form-group"><label>Email ID</label><div class="view-data-box" id="view_email">-</div></div>
                <div class="form-group"><label>Password</label><div class="view-data-box" style="color: #6b7280; font-family: monospace;">******** (Encrypted)</div></div>
                <div class="form-group"><label>Role</label><div class="view-data-box" id="view_role">-</div></div>
                <div class="form-group"><label>Account Status</label><div class="view-data-box" id="view_status" style="font-weight: 700;">-</div></div>
                <div class="form-group"><label>Designation</label><div class="view-data-box" id="view_designation">-</div></div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="submit-btn" onclick="switchToEditMode()" style="background-color: #22c55e; margin-top: 0;"><i class="fa-solid fa-pen-to-square"></i> Edit User</button>
                <button class="submit-btn" onclick="closeModal('viewUserModal')" style="background-color: #6b7280; margin-top: 0;">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Data Filtering Logic
        function filterUsers(status, btnElement) {
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');

            const rows = document.querySelectorAll('.user-row');
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

        function openEditDesignationModal(desig) {
            closeModal('createDesignationModal');
            document.getElementById('edit_desig_id').value = desig.id;
            document.getElementById('edit_desig_title').value = desig.title;
            openModal('editDesignationModal');
        }

        let currentUserData = null; 

        function openViewModal(user) {
            currentUserData = user; 
            document.getElementById('view_name').innerText = user.name || 'N/A';
            document.getElementById('view_username').innerText = user.username || 'N/A';
            document.getElementById('view_email').innerText = user.email || 'N/A';
            document.getElementById('view_phone').innerText = user.phone || 'N/A';
            document.getElementById('view_role').innerText = user.role ? user.role.toUpperCase() : 'N/A';
            document.getElementById('view_designation').innerText = user.designation || 'N/A';
            
            const statusText = (user.status == 'active') ? 'Active' : 'In-Active';
            document.getElementById('view_status').innerText = statusText;
            document.getElementById('view_status').style.color = (user.status == 'active' || user.status == 'Active') ? '#10b981' : '#ef4444';
            openModal('viewUserModal');
        }

        function switchToEditMode() {
            closeModal('viewUserModal');
            if(currentUserData) openEditModal(currentUserData);
        }

        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_name').value = user.name || '';
            document.getElementById('edit_username').value = user.username || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role').value = user.role || '';
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_designation').value = user.designation || 'COO';
            
            const statusVal = user.status ? user.status.toLowerCase() : 'active';
            document.getElementById('edit_status').value = statusVal;
            openModal('editUserModal');
        }

        function togglePassword(id, icon) {
            const input = document.getElementById(id);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function checkPasswordMatch(passId, confirmId, errorId, submitBtnId) {
            const pass = document.getElementById(passId).value;
            const confirm = document.getElementById(confirmId).value;
            const errorMsg = document.getElementById(errorId);
            const submitBtn = document.getElementById(submitBtnId);

            if (confirm === "") {
                errorMsg.style.display = "none";
                submitBtn.disabled = false;
                submitBtn.style.opacity = "1";
            } else if (pass !== confirm) {
                errorMsg.style.display = "block";
                submitBtn.disabled = true;
                submitBtn.style.opacity = "0.5";
            } else {
                errorMsg.style.display = "none";
                submitBtn.disabled = false;
                submitBtn.style.opacity = "1";
            }
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

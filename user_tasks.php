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
// 2. TASK MANAGEMENT LOGIC (CREATE, UPDATE, DELETE)
// ========================================================================

// A. CREATE NEW TASK LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_task'])) {
    if(isset($conn)){
        $title = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
        $description = mysqli_real_escape_string($conn, $_POST['description'] ?? ''); 
        $assigned_to = mysqli_real_escape_string($conn, $_POST['assigned_to'] ?? 'Unassigned');
        $priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'Medium');
        $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'To-Do');
        $due_date = mysqli_real_escape_string($conn, $_POST['due_date'] ?? '');
        
        $insert_sql = "INSERT INTO tasks (title, description, assigned_to, priority, status, due_date) VALUES ('$title', '$description', '$assigned_to', '$priority', '$status', '$due_date')";
        try {
            if(mysqli_query($conn, $insert_sql)){
                $toastMessage = "Task created successfully!"; $toastType = "success";
            }
        } catch (mysqli_sql_exception $e) {
            $toastMessage = "Database Error! Could not create task."; $toastType = "error";
        }
    }
}

// B. UPDATE/EDIT EXISTING TASK LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_task'])) {
    if(isset($conn)){
        $id = mysqli_real_escape_string($conn, $_POST['task_id'] ?? '');
        $title = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
        $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
        $assigned_to = mysqli_real_escape_string($conn, $_POST['assigned_to'] ?? 'Unassigned');
        $priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'Medium');
        $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'To-Do');
        $due_date = mysqli_real_escape_string($conn, $_POST['due_date'] ?? '');

        $update_sql = "UPDATE tasks SET title='$title', description='$description', assigned_to='$assigned_to', priority='$priority', status='$status', due_date='$due_date' WHERE id='$id'";
        try {
            if(mysqli_query($conn, $update_sql)){
                $toastMessage = "Task updated successfully!"; $toastType = "success";
            }
        } catch (mysqli_sql_exception $e) {
            $toastMessage = "Database Error! Could not update task."; $toastType = "error";
        }
    }
}

// C. DELETE TASK LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_task'])) {
    if(isset($conn)){
        $del_id = mysqli_real_escape_string($conn, $_POST['delete_task_id'] ?? '');
        $delete_sql = "DELETE FROM tasks WHERE id='$del_id'";
        try {
            if(mysqli_query($conn, $delete_sql)){
                $toastMessage = "Task deleted successfully!"; $toastType = "success";
            }
        } catch (mysqli_sql_exception $e) {
            $toastMessage = "Error deleting task!"; $toastType = "error";
        }
    }
}

// ========================================================================
// 3. FETCH DATA FOR UI (Users for Assignment, Tasks)
// ========================================================================
$assigneeOptions = ""; 
if(isset($conn)){
    $user_query = mysqli_query($conn, "SELECT username, name FROM users ORDER BY name ASC");
    while($u = mysqli_fetch_assoc($user_query)){
        $assigneeOptions .= "<option value='{$u['username']}'>{$u['name']} ({$u['username']})</option>";
    }
}

$taskTableRows = "";
if(isset($conn)){
    $tasks_query = mysqli_query($conn, "SELECT * FROM tasks ORDER BY due_date ASC, id DESC");
    if($tasks_query && mysqli_num_rows($tasks_query) > 0){
        while($row = mysqli_fetch_assoc($tasks_query)){
            $taskData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            
            // Priority Badge Color
            $priorityClass = "priority-medium";
            if($row['priority'] == 'High') $priorityClass = "priority-high";
            if($row['priority'] == 'Low') $priorityClass = "priority-low";
            
            // Status Badge Color
            $statusClass = "status-todo";
            if($row['status'] == 'In-Progress') $statusClass = "status-progress";
            if($row['status'] == 'Completed') $statusClass = "status-completed";

            $taskTableRows .= "
                <tr class='task-row' data-status='{$row['status']}'>
                    <td style='font-weight: 700;'>#{$row['id']}</td>
                    <td style='text-align: left; font-weight: 600;'>{$row['title']}</td>
                    <td>{$row['assigned_to']}</td>
                    <td><span class='badge $priorityClass'>{$row['priority']}</span></td>
                    <td><span class='badge $statusClass'>{$row['status']}</span></td>
                    <td>" . ($row['due_date'] ? date('M d, Y', strtotime($row['due_date'])) : 'N/A') . "</td>
                    <td>
                        <div class='action-btns'>
                            <button class='btn-view' onclick='openViewModal({$taskData})'><i class='fa-solid fa-eye'></i></button>
                            <button class='btn-edit' onclick='openEditModal({$taskData})'><i class='fa-solid fa-pen'></i></button>
                            <form method='POST' id='delete-task-{$row['id']}' style='display:inline;'>
                                <input type='hidden' name='delete_task_id' value='{$row['id']}'>
                                <input type='hidden' name='delete_task' value='1'>
                                <button type='button' class='btn-delete' onclick='confirmDelete(\"delete-task-{$row['id']}\", \"task\")'><i class='fa-solid fa-trash'></i></button>
                            </form>
                        </div>
                    </td>
                </tr>";
        }
    } else {
        $taskTableRows = "<tr><td colspan='7' style='padding: 20px; color: #6b7280;'>No tasks found.</td></tr>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Tasks - Systellio CRM</title>
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
        .dropdown-title.active-main { color: #3b82f6; }
        .dropdown-title.active-main i { color: #3b82f6; }
        .submenu li.active-sub a { color: #3b82f6; font-weight: 600; }
        .submenu li.active-sub { position: relative; }
        .submenu li.active-sub::before { content: ""; position: absolute; left: 35px; top: 50%; transform: translateY(-50%); width: 6px; height: 6px; background-color: #3b82f6; border-radius: 50%; }

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

        /* Task Section Styles */
        #taskSection { padding: 30px; display: block; }
        .task-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; }
        .task-title h1 { font-size: 26px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; transition: 0.3s;}
        .task-title p { font-size: 11px; color: #6b7280; font-weight: 500; }
        
        .header-buttons { display: flex; gap: 10px; }
        .create-btn { background-color: #000000; color: #ffffff; padding: 10px 18px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: 0.3s;}
        .create-btn:hover { background-color: #1f2937; }

        .tabs-wrapper { margin-bottom: 20px; width: max-content; }
        .tab-top-line { height: 3px; width: 100%; background: linear-gradient(to right, #3b82f6 33%, #f59e0b 33%, #f59e0b 66%, #10b981 66%); border-radius: 3px 3px 0 0; }
        .tabs-container { display: flex; background: #ffffff; padding: 5px; border-radius: 0 0 6px 6px; gap: 5px; transition: 0.3s; border: 1px solid #e5e7eb; border-top: none;}
        .tab-btn { padding: 8px 18px; font-size: 12px; font-weight: 700; border: none; background: transparent; cursor: pointer; border-radius: 4px; color: #6b7280; display: flex; align-items: center; gap: 6px; transition: 0.3s;}
        .tab-btn.active { background: #f3f4f6; color: #111827; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

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
        .priority-high { background-color: #fee2e2; color: #ef4444; }
        .priority-medium { background-color: #fef3c7; color: #f59e0b; }
        .priority-low { background-color: #dcfce7; color: #10b981; }
        
        .status-todo { background-color: #e5e7eb; color: #374151; }
        .status-progress { background-color: #dbeafe; color: #3b82f6; }
        .status-completed { background-color: #d1fae5; color: #059669; }

        .action-btns { display: flex; justify-content: center; gap: 6px; }
        .btn-view { background-color: #60a5fa; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}
        .btn-view:hover { background-color: #3b82f6; }
        .btn-edit { background-color: #4ade80; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}
        .btn-edit:hover { background-color: #22c55e; }
        .btn-delete { background-color: #f87171; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}
        .btn-delete:hover { background-color: #ef4444; }

        /* Modals */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; padding: 30px; border-radius: 10px; width: 100%; max-width: 650px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-height: 90vh; overflow-y: auto; transition: 0.3s;}
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { font-size: 20px; font-weight: 700; transition: 0.3s;}
        .close-btn { font-size: 20px; cursor: pointer; color: #6b7280; border: none; background: none; transition: 0.3s;}
        .close-btn:hover { color: #ef4444; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 15px; position: relative; } 
        .full-width { grid-column: span 2; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; transition: 0.3s;}
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; outline: none; font-family: 'Inter', sans-serif; background-color: #f9fafb; transition: 0.3s;}
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #3b82f6; background-color: #fff; }
        
        .submit-btn { background-color: #000000; color: #ffffff; padding: 12px; border: none; border-radius: 6px; width: 100%; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .submit-btn:hover { background-color: #1f2937; }

        .view-data-box { background: #f9fafb; padding: 10px 12px; border-radius: 6px; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 500; word-break: break-all; min-height: 40px; display: flex; align-items: center; transition: 0.3s;}

        /* Dark Mode */
        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .main-content { background-color: #0f172a; }
        body.dark-mode .top-navbar { background-color: #1e293b; border-bottom: 1px solid #334155; box-shadow: none; }
        body.dark-mode .nav-icon-btn { color: #cbd5e1; }
        
        body.dark-mode .tabs-container { background: #1e293b; border-color: #334155; }
        body.dark-mode .tab-btn { color: #94a3b8; }
        body.dark-mode .tab-btn.active { background: #0f172a; color: #f8fafc; }

        body.dark-mode .table-wrapper { border-color: #334155; background: #1e293b; }
        body.dark-mode .custom-table th { background-color: #334155; color: #f8fafc; border-color: #475569; }
        body.dark-mode .custom-table td { color: #cbd5e1; border-color: #334155; }
        
        body.dark-mode .custom-table tbody tr:nth-child(even) { background-color: #1e293b; } 
        body.dark-mode .custom-table tbody tr:nth-child(odd) { background-color: #0f172a; } 

        body.dark-mode .modal-content { background-color: #1e293b; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        body.dark-mode .form-group label { color: #cbd5e1; }
        body.dark-mode .form-group input, body.dark-mode .form-group select, body.dark-mode .form-group textarea { background-color: #0f172a; color: #f8fafc; border-color: #334155; }
        body.dark-mode .view-data-box { background-color: #0f172a; color: #f8fafc; border-color: #334155; }
        body.dark-mode .create-btn, body.dark-mode .submit-btn { background-color: #3b82f6; }
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
                <div class="dropdown-title active-main" onclick="toggleSubMenu('userMenu')">
                    <div class="dropdown-title-left"><i class="fa-solid fa-user-group"></i><span>User Management</span></div>
                    <i class="fa-solid fa-chevron-down dropdown-icon" style="transform: rotate(180deg);"></i>
                </div>
                <ul class="submenu" style="display: block;">
                    <li><a href="user_list.php">User List</a></li>
                    <li class="active-sub"><a href="user_tasks.php">User Tasks</a></li>
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

        <div id="taskSection">
            <div class="task-header">
                <div class="task-title">
                    <h1>User Tasks Management</h1>
                    <p>Organize, assign, and track daily operational tasks.</p>
                </div>
                <div class="header-buttons">
                    <button class="create-btn" onclick="openModal('createTaskModal')"><i class="fa-solid fa-plus"></i> Create New Task</button>
                </div>
            </div>

            <div class="tabs-wrapper">
                <div class="tab-top-line"></div>
                <div class="tabs-container">
                    <button class="tab-btn active" onclick="filterTasks('all', this)"><i class="fa-solid fa-list-check"></i> All Tasks</button>
                    <button class="tab-btn" onclick="filterTasks('To-Do', this)"><i class="fa-solid fa-clock"></i> To-Do</button>
                    <button class="tab-btn" onclick="filterTasks('In-Progress', this)"><i class="fa-solid fa-spinner"></i> In-Progress</button>
                    <button class="tab-btn" onclick="filterTasks('Completed', this)"><i class="fa-solid fa-circle-check"></i> Completed</button>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Task Title</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo $taskTableRows; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Task Modal -->
    <div id="createTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Task</h2>
                <button type="button" class="close-btn" onclick="closeModal('createTaskModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="user_tasks.php" method="POST">
                <div class="form-grid">
                    <div class="form-group full-width"><label>Task Title</label><input type="text" name="title" required placeholder="e.g. Follow up with Acme Corp"></div>
                    <div class="form-group full-width"><label>Description</label><textarea name="description" rows="3" placeholder="Detailed task description..."></textarea></div>
                    <div class="form-group">
                        <label>Assigned To</label>
                        <select name="assigned_to" required>
                            <option value="Unassigned">Unassigned</option>
                            <?php echo $assigneeOptions; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="To-Do" selected>To-Do</option>
                            <option value="In-Progress">In-Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Due Date</label><input type="date" name="due_date" required></div>
                </div>
                <button type="submit" name="create_task" class="submit-btn">Save Task</button>
            </form>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div id="editTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Task Details</h2>
                <button type="button" class="close-btn" onclick="closeModal('editTaskModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="user_tasks.php" method="POST">
                <input type="hidden" name="task_id" id="edit_task_id">
                <div class="form-grid">
                    <div class="form-group full-width"><label>Task Title</label><input type="text" name="title" id="edit_title" required></div>
                    <div class="form-group full-width"><label>Description</label><textarea name="description" id="edit_description" rows="3"></textarea></div>
                    <div class="form-group">
                        <label>Assigned To</label>
                        <select name="assigned_to" id="edit_assigned_to" required>
                            <option value="Unassigned">Unassigned</option>
                            <?php echo $assigneeOptions; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" id="edit_priority">
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status">
                            <option value="To-Do">To-Do</option>
                            <option value="In-Progress">In-Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Due Date</label><input type="date" name="due_date" id="edit_due_date" required></div>
                </div>
                <button type="submit" name="update_task" class="submit-btn" style="background-color: #22c55e;">Update Task</button>
            </form>
        </div>
    </div>

    <!-- View Task Modal -->
    <div id="viewTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Task Details View</h2>
                <button type="button" class="close-btn" onclick="closeModal('viewTaskModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="form-grid">
                <div class="form-group full-width"><label>Task Title</label><div class="view-data-box" id="view_title">-</div></div>
                <div class="form-group full-width"><label>Description</label><div class="view-data-box" id="view_description" style="min-height: 80px; align-items: flex-start; padding-top: 10px;">-</div></div>
                <div class="form-group"><label>Assigned To</label><div class="view-data-box" id="view_assigned_to">-</div></div>
                <div class="form-group"><label>Priority</label><div class="view-data-box" id="view_priority">-</div></div>
                <div class="form-group"><label>Status</label><div class="view-data-box" id="view_status">-</div></div>
                <div class="form-group"><label>Due Date</label><div class="view-data-box" id="view_due_date">-</div></div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="submit-btn" onclick="switchToEditMode()" style="background-color: #22c55e; margin-top: 0;"><i class="fa-solid fa-pen-to-square"></i> Edit Task</button>
                <button class="submit-btn" onclick="closeModal('viewTaskModal')" style="background-color: #6b7280; margin-top: 0;">Close</button>
            </div>
        </div>
    </div>

    <script>
        function filterTasks(status, btnElement) {
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');

            const rows = document.querySelectorAll('.task-row');
            rows.forEach(row => {
                if (status === 'all') { row.style.display = ''; } 
                else {
                    if (row.getAttribute('data-status') === status) { row.style.display = ''; } 
                    else { row.style.display = 'none'; }
                }
            });
        }

        function openModal(id) { document.getElementById(id).style.display = "flex"; }
        function closeModal(id) { document.getElementById(id).style.display = "none"; }

        let currentTaskData = null; 

        function openViewModal(task) {
            currentTaskData = task; 
            document.getElementById('view_title').innerText = task.title || 'N/A';
            document.getElementById('view_description').innerText = task.description || 'No description provided.';
            document.getElementById('view_assigned_to').innerText = task.assigned_to || 'Unassigned';
            document.getElementById('view_priority').innerText = task.priority || 'Medium';
            document.getElementById('view_status').innerText = task.status || 'To-Do';
            document.getElementById('view_due_date').innerText = task.due_date || 'N/A';
            openModal('viewTaskModal');
        }

        function switchToEditMode() {
            closeModal('viewTaskModal');
            if(currentTaskData) openEditModal(currentTaskData);
        }

        function openEditModal(task) {
            document.getElementById('edit_task_id').value = task.id;
            document.getElementById('edit_title').value = task.title || '';
            document.getElementById('edit_description').value = task.description || '';
            document.getElementById('edit_assigned_to').value = task.assigned_to || 'Unassigned';
            document.getElementById('edit_priority').value = task.priority || 'Medium';
            document.getElementById('edit_status').value = task.status || 'To-Do';
            document.getElementById('edit_due_date').value = task.due_date || '';
            openModal('editTaskModal');
        }

        function confirmDelete(formId, typeName) {
            Swal.fire({ title: 'Are you sure?', text: "You won't be able to revert this!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, delete it!' })
            .then((result) => { if (result.isConfirmed) { document.getElementById(formId).submit(); } });
        }

        function toggleSubMenu(menuId) { 
            const menu = document.getElementById(menuId); menu.classList.toggle('open'); 
        }

        window.onload = function() {
            <?php if($toastMessage != ""): ?>
                const toast = document.getElementById("toastBox");
                document.getElementById("toastMsg").innerText = "<?php echo $toastMessage; ?>";
                toast.className = "show <?php echo $toastType; ?>";
                setTimeout(() => toast.className = toast.className.replace("show", ""), 3000);
            <?php endif; ?>
        };

        document.getElementById('outerToggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('collapsed'));
        
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (localStorage.getItem('darkMode') === 'enabled') { document.body.classList.add('dark-mode'); darkModeToggle.classList.replace('fa-moon', 'fa-sun'); }
        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            if (document.body.classList.contains('dark-mode')) { localStorage.setItem('darkMode', 'enabled'); darkModeToggle.classList.replace('fa-moon', 'fa-sun'); } 
            else { localStorage.setItem('darkMode', 'disabled'); darkModeToggle.classList.replace('fa-sun', 'fa-moon'); }
        });
    </script>
</body>
</html>

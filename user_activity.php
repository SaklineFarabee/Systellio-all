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
// 2. ACTIVITY LOG HELPER FUNCTIONS
// ========================================================================

/**
 * Log user activity to audit trail
 * This function records all significant actions performed by users
 */
function logActivity($action, $description, $entity_type, $entity_id, $old_value = null, $new_value = null) {
    global $conn;
    
    if (!isset($conn)) return false;
    
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['name'];
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $action = mysqli_real_escape_string($conn, $action);
    $description = mysqli_real_escape_string($conn, $description);
    $entity_type = mysqli_real_escape_string($conn, $entity_type);
    $entity_id = mysqli_real_escape_string($conn, $entity_id);
    $old_value = mysqli_real_escape_string($conn, $old_value ?? '');
    $new_value = mysqli_real_escape_string($conn, $new_value ?? '');
    
    $log_sql = "INSERT INTO activity_logs (user_id, username, action, description, entity_type, entity_id, old_value, new_value, ip_address, timestamp) 
                VALUES ('$user_id', '$username', '$action', '$description', '$entity_type', '$entity_id', '$old_value', '$new_value', '$ip_address', '$timestamp')";
    
    return mysqli_query($conn, $log_sql);
}

// ========================================================================
// 3. FETCH ACTIVITY LOG DATA
// ========================================================================

$activityTableRows = "";
$totalActivities = 0;
$filterType = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

if(isset($conn)){
    // Build query based on filter
    $whereClause = "WHERE 1=1";
    
    if($filterType !== 'all') {
        $filterType = mysqli_real_escape_string($conn, $filterType);
        $whereClause .= " AND action = '$filterType'";
    }
    
    if(!empty($searchTerm)) {
        $whereClause .= " AND (description LIKE '%$searchTerm%' OR username LIKE '%$searchTerm%' OR entity_type LIKE '%$searchTerm%')";
    }
    
    // Get total count
    $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM activity_logs $whereClause");
    if($count_query) {
        $count_row = mysqli_fetch_assoc($count_query);
        $totalActivities = $count_row['total'];
    }
    
    // Fetch activity logs with pagination
    $limit = 50;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;
    
    $activity_query = mysqli_query($conn, "SELECT * FROM activity_logs $whereClause ORDER BY timestamp DESC LIMIT $limit OFFSET $offset");
    
    if($activity_query && mysqli_num_rows($activity_query) > 0){
        while($row = mysqli_fetch_assoc($activity_query)){
            // Action Badge Color
            $actionClass = "action-view";
            if($row['action'] == 'CREATE') $actionClass = "action-create";
            if($row['action'] == 'UPDATE') $actionClass = "action-update";
            if($row['action'] == 'DELETE') $actionClass = "action-delete";
            if($row['action'] == 'LOGIN') $actionClass = "action-login";
            if($row['action'] == 'LOGOUT') $actionClass = "action-logout";
            
            // Entity Type Badge Color
            $entityClass = "entity-default";
            if($row['entity_type'] == 'User') $entityClass = "entity-user";
            if($row['entity_type'] == 'Task') $entityClass = "entity-task";
            if($row['entity_type'] == 'Company') $entityClass = "entity-company";
            if($row['entity_type'] == 'Deal') $entityClass = "entity-deal";
            if($row['entity_type'] == 'Contact') $entityClass = "entity-contact";
            
            $activityData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            
            $activityTableRows .= "
                <tr class='activity-row'>
                    <td style='text-align: left; font-weight: 600;'>{$row['username']}</td>
                    <td><span class='badge $actionClass'>{$row['action']}</span></td>
                    <td><span class='badge $entityClass'>{$row['entity_type']}</span></td>
                    <td style='text-align: left;'>{$row['description']}</td>
                    <td>" . date('M d, Y H:i', strtotime($row['timestamp'])) . "</td>
                    <td>{$row['ip_address']}</td>
                    <td>
                        <div class='action-btns'>
                            <button class='btn-view' onclick='openActivityDetailModal({$activityData})'><i class='fa-solid fa-eye'></i></button>
                        </div>
                    </td>
                </tr>";
        }
    } else {
        $activityTableRows = "<tr><td colspan='7' style='padding: 20px; color: #6b7280;'>No activities found.</td></tr>";
    }
}

// Calculate pagination
$totalPages = ceil($totalActivities / $limit);
$currentPage = $page;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity - Systellio CRM</title>
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

        /* Activity Section Styles */
        #activitySection { padding: 30px; display: block; }
        .activity-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; }
        .activity-title h1 { font-size: 26px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; transition: 0.3s;}
        .activity-title p { font-size: 11px; color: #6b7280; font-weight: 500; }
        
        .filter-section { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-input { padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; outline: none; background-color: #ffffff; transition: 0.3s; }
        .filter-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .filter-select { padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; outline: none; background-color: #ffffff; cursor: pointer; transition: 0.3s; }
        .filter-select:focus { border-color: #3b82f6; }
        .filter-btn { background-color: #000000; color: #ffffff; padding: 10px 18px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; transition: 0.3s; }
        .filter-btn:hover { background-color: #1f2937; }

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
        .action-create { background-color: #dcfce7; color: #10b981; }
        .action-update { background-color: #fef3c7; color: #f59e0b; }
        .action-delete { background-color: #fee2e2; color: #ef4444; }
        .action-login { background-color: #dbeafe; color: #3b82f6; }
        .action-logout { background-color: #e5e7eb; color: #374151; }
        .action-view { background-color: #f3e8ff; color: #7c3aed; }
        
        .entity-user { background-color: #dbeafe; color: #3b82f6; }
        .entity-task { background-color: #fef3c7; color: #f59e0b; }
        .entity-company { background-color: #dcfce7; color: #10b981; }
        .entity-deal { background-color: #e9d5ff; color: #a855f7; }
        .entity-contact { background-color: #fee2e2; color: #ef4444; }
        .entity-default { background-color: #e5e7eb; color: #374151; }

        .action-btns { display: flex; justify-content: center; gap: 6px; }
        .btn-view { background-color: #60a5fa; color: white; padding: 6px 10px; border-radius: 4px; font-size: 11px; border: none; cursor: pointer; transition: 0.3s;}
        .btn-view:hover { background-color: #3b82f6; }

        /* Pagination */
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px; text-decoration: none; color: #374151; transition: 0.3s; background-color: #ffffff; }
        .pagination a:hover { background-color: #f3f4f6; border-color: #3b82f6; color: #3b82f6; }
        .pagination .active { background-color: #3b82f6; color: #ffffff; border-color: #3b82f6; }

        /* Modals */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; padding: 30px; border-radius: 10px; width: 100%; max-width: 700px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-height: 90vh; overflow-y: auto; transition: 0.3s;}
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { font-size: 20px; font-weight: 700; transition: 0.3s;}
        .close-btn { font-size: 20px; cursor: pointer; color: #6b7280; border: none; background: none; transition: 0.3s;}
        .close-btn:hover { color: #ef4444; }

        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .detail-group { margin-bottom: 15px; } 
        .full-width { grid-column: span 2; }
        .detail-label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-value { background: #f9fafb; padding: 10px 12px; border-radius: 6px; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 500; word-break: break-all; min-height: 40px; display: flex; align-items: center; }

        /* Dark Mode */
        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .main-content { background-color: #0f172a; }
        body.dark-mode .top-navbar { background-color: #1e293b; border-bottom: 1px solid #334155; box-shadow: none; }
        body.dark-mode .nav-icon-btn { color: #cbd5e1; }
        
        body.dark-mode .filter-input, body.dark-mode .filter-select { background-color: #0f172a; color: #f8fafc; border-color: #334155; }
        body.dark-mode .filter-input:focus, body.dark-mode .filter-select:focus { border-color: #3b82f6; }

        body.dark-mode .table-wrapper { border-color: #334155; background: #1e293b; }
        body.dark-mode .custom-table th { background-color: #334155; color: #f8fafc; border-color: #475569; }
        body.dark-mode .custom-table td { color: #cbd5e1; border-color: #334155; }
        
        body.dark-mode .custom-table tbody tr:nth-child(even) { background-color: #1e293b; } 
        body.dark-mode .custom-table tbody tr:nth-child(odd) { background-color: #0f172a; } 

        body.dark-mode .modal-content { background-color: #1e293b; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        body.dark-mode .detail-label { color: #94a3b8; }
        body.dark-mode .detail-value { background-color: #0f172a; color: #f8fafc; border-color: #334155; }
        body.dark-mode .filter-btn { background-color: #3b82f6; }
        body.dark-mode .pagination a, body.dark-mode .pagination span { background-color: #1e293b; color: #cbd5e1; border-color: #334155; }
        body.dark-mode .pagination a:hover { background-color: #334155; }
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
                    <li><a href="user_tasks.php">User Tasks</a></li>
                    <li class="active-sub"><a href="user_activity.php">User Activity</a></li>
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

        <div id="activitySection">
            <div class="activity-header">
                <div class="activity-title">
                    <h1>User Activity Log</h1>
                    <p>Comprehensive audit trail of all system activities and user actions.</p>
                </div>
            </div>

            <div class="filter-section">
                <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; width: 100%;">
                    <input type="text" name="search" class="filter-input" placeholder="Search by user, description, or entity..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="flex: 1; min-width: 200px;">
                    
                    <select name="filter" class="filter-select">
                        <option value="all" <?php echo ($filterType === 'all') ? 'selected' : ''; ?>>All Actions</option>
                        <option value="CREATE" <?php echo ($filterType === 'CREATE') ? 'selected' : ''; ?>>Create</option>
                        <option value="UPDATE" <?php echo ($filterType === 'UPDATE') ? 'selected' : ''; ?>>Update</option>
                        <option value="DELETE" <?php echo ($filterType === 'DELETE') ? 'selected' : ''; ?>>Delete</option>
                        <option value="LOGIN" <?php echo ($filterType === 'LOGIN') ? 'selected' : ''; ?>>Login</option>
                        <option value="LOGOUT" <?php echo ($filterType === 'LOGOUT') ? 'selected' : ''; ?>>Logout</option>
                        <option value="VIEW" <?php echo ($filterType === 'VIEW') ? 'selected' : ''; ?>>View</option>
                    </select>
                    
                    <button type="submit" class="filter-btn"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                    <a href="user_activity.php" class="filter-btn" style="text-decoration: none; background-color: #6b7280;"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </form>
            </div>

            <div class="table-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity Type</th>
                            <th>Description</th>
                            <th>Timestamp</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo $activityTableRows; ?>
                    </tbody>
                </table>
            </div>

            <?php if($totalPages > 1): ?>
            <div class="pagination">
                <?php
                // Previous button
                if($currentPage > 1) {
                    echo "<a href='user_activity.php?page=1&filter=$filterType&search=$searchTerm'><i class='fa-solid fa-chevron-left'></i></a>";
                    echo "<a href='user_activity.php?page=" . ($currentPage - 1) . "&filter=$filterType&search=$searchTerm'>Previous</a>";
                }
                
                // Page numbers
                for($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
                    if($i == $currentPage) {
                        echo "<span class='active'>$i</span>";
                    } else {
                        echo "<a href='user_activity.php?page=$i&filter=$filterType&search=$searchTerm'>$i</a>";
                    }
                }
                
                // Next button
                if($currentPage < $totalPages) {
                    echo "<a href='user_activity.php?page=" . ($currentPage + 1) . "&filter=$filterType&search=$searchTerm'>Next</a>";
                    echo "<a href='user_activity.php?page=$totalPages&filter=$filterType&search=$searchTerm'><i class='fa-solid fa-chevron-right'></i></a>";
                }
                ?>
            </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 15px; color: #6b7280; font-size: 12px;">
                Showing <?php echo min($limit, $totalActivities - (($currentPage - 1) * $limit)); ?> of <?php echo $totalActivities; ?> activities
            </div>
        </div>
    </div>

    <!-- Activity Detail Modal -->
    <div id="activityDetailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Activity Details</h2>
                <button type="button" class="close-btn" onclick="closeModal('activityDetailModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="detail-grid">
                <div class="detail-group">
                    <label class="detail-label">User</label>
                    <div class="detail-value" id="detail_username">-</div>
                </div>
                <div class="detail-group">
                    <label class="detail-label">Action</label>
                    <div class="detail-value" id="detail_action">-</div>
                </div>
                <div class="detail-group">
                    <label class="detail-label">Entity Type</label>
                    <div class="detail-value" id="detail_entity_type">-</div>
                </div>
                <div class="detail-group">
                    <label class="detail-label">Entity ID</label>
                    <div class="detail-value" id="detail_entity_id">-</div>
                </div>
                <div class="detail-group full-width">
                    <label class="detail-label">Description</label>
                    <div class="detail-value" id="detail_description" style="min-height: 60px; align-items: flex-start; padding-top: 10px;">-</div>
                </div>
                <div class="detail-group">
                    <label class="detail-label">Timestamp</label>
                    <div class="detail-value" id="detail_timestamp">-</div>
                </div>
                <div class="detail-group">
                    <label class="detail-label">IP Address</label>
                    <div class="detail-value" id="detail_ip_address">-</div>
                </div>
                <div class="detail-group full-width">
                    <label class="detail-label">Old Value</label>
                    <div class="detail-value" id="detail_old_value" style="min-height: 60px; align-items: flex-start; padding-top: 10px; font-family: monospace; font-size: 11px;">-</div>
                </div>
                <div class="detail-group full-width">
                    <label class="detail-label">New Value</label>
                    <div class="detail-value" id="detail_new_value" style="min-height: 60px; align-items: flex-start; padding-top: 10px; font-family: monospace; font-size: 11px;">-</div>
                </div>
            </div>
            <button class="filter-btn" onclick="closeModal('activityDetailModal')" style="margin-top: 20px; background-color: #6b7280;">Close</button>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = "flex"; }
        function closeModal(id) { document.getElementById(id).style.display = "none"; }

        function openActivityDetailModal(activity) {
            document.getElementById('detail_username').innerText = activity.username || 'N/A';
            document.getElementById('detail_action').innerText = activity.action || 'N/A';
            document.getElementById('detail_entity_type').innerText = activity.entity_type || 'N/A';
            document.getElementById('detail_entity_id').innerText = activity.entity_id || 'N/A';
            document.getElementById('detail_description').innerText = activity.description || 'No description provided.';
            document.getElementById('detail_timestamp').innerText = activity.timestamp || 'N/A';
            document.getElementById('detail_ip_address').innerText = activity.ip_address || 'N/A';
            document.getElementById('detail_old_value').innerText = activity.old_value || 'No previous value';
            document.getElementById('detail_new_value').innerText = activity.new_value || 'No new value';
            openModal('activityDetailModal');
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
    </script>
</body>
</html>

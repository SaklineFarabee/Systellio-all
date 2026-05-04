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
// 2. FETCH ANALYTICS DATA
// ========================================================================
// Default Fallback Values
$totalRevenue = 0;
$activeDealsCount = 0;
$wonDealsCount = 0;
$lostDealsCount = 0;
$totalTasksCount = 0;
$completedTasksCount = 0;
$totalCompaniesCount = 0;
$totalUsersCount = 0;

$recentDeals = [];
$taskStatusData = ['todo' => 0, 'progress' => 0, 'done' => 0, 'overdue' => 0];

if(isset($conn)){
    try {
        // Deal Analytics
        $deal_query = mysqli_query($conn, "SELECT deal_value, stage, deal_name, created_at FROM deals ORDER BY id DESC");
        if($deal_query){
            while($row = mysqli_fetch_assoc($deal_query)){
                $val = (float)$row['deal_value'];
                $stage = strtolower($row['stage']);
                
                if($stage == 'won') {
                    $totalRevenue += $val;
                    $wonDealsCount++;
                } elseif($stage == 'lost') {
                    $lostDealsCount++;
                } else {
                    $activeDealsCount++; // Lead, Proposal, Negotiation
                }
                
                // Collect recent 5 deals for table
                if(count($recentDeals) < 5) {
                    $recentDeals[] = $row;
                }
            }
        }

        // Task Analytics
        $task_query = mysqli_query($conn, "SELECT status FROM tasks");
        if($task_query){
            while($row = mysqli_fetch_assoc($task_query)){
                $totalTasksCount++;
                $t_stat = strtolower($row['status']);
                if(strpos($t_stat, 'done') !== false) {
                    $completedTasksCount++;
                    $taskStatusData['done']++;
                } elseif(strpos($t_stat, 'progress') !== false) {
                    $taskStatusData['progress']++;
                } elseif(strpos($t_stat, 'overdue') !== false) {
                    $taskStatusData['overdue']++;
                } else {
                    $taskStatusData['todo']++;
                }
            }
        }

        // Company & User Counts
        $comp_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM companies");
        if($comp_query) $totalCompaniesCount = mysqli_fetch_assoc($comp_query)['count'] ?? 0;

        $user_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status='active'");
        if($user_query) $totalUsersCount = mysqli_fetch_assoc($user_query)['count'] ?? 0;

    } catch(mysqli_sql_exception $e) {
        // Use fallbacks if tables don't exist yet
    }
}

// Calculate percentages for UI
$winRate = ($wonDealsCount + $lostDealsCount > 0) ? round(($wonDealsCount / ($wonDealsCount + $lostDealsCount)) * 100) : 0;
$taskCompletionRate = ($totalTasksCount > 0) ? round(($completedTasksCount / $totalTasksCount) * 100) : 0;

// Dummy data if DB is empty
if($totalRevenue == 0 && $totalTasksCount == 0 && empty($recentDeals)) {
    $totalRevenue = 45200;
    $activeDealsCount = 12;
    $winRate = 68;
    $taskCompletionRate = 85;
    $totalCompaniesCount = 24;
    $totalUsersCount = 8;
    
    $taskStatusData = ['todo' => 15, 'progress' => 8, 'done' => 45, 'overdue' => 3];
    
    $recentDeals = [
        ['deal_name' => 'Enterprise CRM Upgrade', 'deal_value' => '12000', 'stage' => 'Won', 'created_at' => date('Y-m-d', strtotime('-2 days'))],
        ['deal_name' => 'Website Redesign Phase 1', 'deal_value' => '4500', 'stage' => 'Negotiation', 'created_at' => date('Y-m-d', strtotime('-4 days'))],
        ['deal_name' => 'SEO Optimization Q3', 'deal_value' => '2100', 'stage' => 'Proposal', 'created_at' => date('Y-m-d', strtotime('-5 days'))],
        ['deal_name' => 'Cloud Migration', 'deal_value' => '8500', 'stage' => 'Lead', 'created_at' => date('Y-m-d', strtotime('-1 week'))]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - Systellio CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ========================================================================
           GLOBAL STYLES & RESET (Same as Main Dashboard)
        ======================================================================== */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: #f3f4f6; display: flex; height: 100vh; overflow: hidden; transition: background-color 0.3s, color 0.3s; color: #111827; }

        /* ========================================================================
           SIDEBAR STYLES
        ======================================================================== */
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

        /* ========================================================================
           MAIN CONTENT & TOP NAVBAR STYLES
        ======================================================================== */
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; transition: background-color 0.3s ease; background-color: #f3f4f6; }
        .top-navbar { background-color: #ffffff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.03); transition: 0.3s; }
        .toggle-btn { font-size: 20px; color: #4b5563; cursor: pointer; transition: color 0.3s; }
        .toggle-btn:hover { color: #111827; }
        
        .navbar-actions { display: flex; align-items: center; gap: 20px; }
        .nav-icon-btn { cursor: pointer; font-size: 20px; color: #6b7280; transition: 0.3s; position: relative; }
        .nav-icon-btn:hover { color: #3b82f6; }
        
        .notification-badge { position: absolute; top: -4px; right: -4px; background-color: #ef4444; color: white; font-size: 9px; font-weight: bold; padding: 2px 5px; border-radius: 50%; border: 2px solid #ffffff; }
        body.dark-mode .notification-badge { border-color: #1e293b; }

        .user-profile { display: flex; align-items: center; gap: 10px; font-weight: 600; color: inherit; font-size: 14px; }
        .user-profile i { font-size: 24px; color: #3b82f6; }

        /* ========================================================================
           ANALYTICS SPECIFIC STYLES
        ======================================================================== */
        .analytics-container { padding: 30px; display: block; }
        
        .analytics-header-title h1 { font-size: 26px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; transition: 0.3s; color: #111827;}
        .analytics-header-title p { font-size: 13px; color: #6b7280; font-weight: 500; margin-bottom: 25px;}
        
        .btn-export { background-color: #10b981; color: #ffffff; padding: 10px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s;}
        .btn-export:hover { background-color: #059669; }

        /* Top Key Metrics Cards */
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .metric-card { background: #ffffff; padding: 24px; border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: flex; flex-direction: column; position: relative; overflow: hidden; transition: 0.3s;}
        .metric-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .metric-card.revenue::before { background: #10b981; }
        .metric-card.winrate::before { background: #3b82f6; }
        .metric-card.tasks::before { background: #f59e0b; }
        .metric-card.entities::before { background: #8b5cf6; }
        
        .metric-title { font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin-bottom: 10px; display: flex; justify-content: space-between;}
        .metric-value { font-size: 32px; font-weight: 800; color: #111827; margin-bottom: 5px; transition: 0.3s;}
        .metric-sub { font-size: 12px; font-weight: 600; }
        .metric-sub.positive { color: #10b981; }
        .metric-sub.neutral { color: #6b7280; }

        /* Dashboard Grid Layout for Charts & Tables */
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;}
        
        .dash-panel { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); transition: 0.3s;}
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f3f4f6;}
        .panel-title { font-size: 16px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px; transition: 0.3s;}
        
        /* Table Styles for Deals */
        .simple-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;}
        .simple-table th { padding: 12px 10px; color: #6b7280; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 2px solid #f3f4f6;}
        .simple-table td { padding: 15px 10px; color: #374151; font-weight: 500; border-bottom: 1px solid #f3f4f6; transition: 0.3s;}
        
        .pill { padding: 5px 12px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; letter-spacing: 0.5px;}
        .pill.high { background: #fee2e2; color: #ef4444; } 
        .pill.medium { background: #fef3c7; color: #f59e0b; } 
        .pill.low { background: #d1fae5; color: #10b981; } 
        .pill.todo { background: #e5e7eb; color: #4b5563; } 
        .pill.progress { background: #dbeafe; color: #2563eb; }

        /* Custom Progress Bars for Tasks */
        .progress-item { margin-bottom: 18px; }
        .progress-label { display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #4b5563; margin-bottom: 6px; transition: 0.3s;}
        .progress-track { width: 100%; height: 8px; background-color: #f3f4f6; border-radius: 10px; overflow: hidden;}
        .progress-fill { height: 100%; border-radius: 10px; }
        .fill-done { background-color: #10b981; }
        .fill-prog { background-color: #3b82f6; }
        .fill-todo { background-color: #9ca3af; }
        .fill-over { background-color: #ef4444; }

        /* ========================================================================
           DARK MODE STYLES 
        ======================================================================== */
        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .main-content { background-color: #0f172a; }
        body.dark-mode .top-navbar { background-color: #1e293b; border-bottom: 1px solid #334155; box-shadow: none; }
        body.dark-mode .nav-icon-btn { color: #cbd5e1; }
        body.dark-mode .nav-icon-btn:hover { color: #f8fafc; }
        
        body.dark-mode .analytics-header-title h1 { color: #f8fafc; }
        body.dark-mode .metric-card, body.dark-mode .dash-panel { background-color: #1e293b; border-color: #334155; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
        body.dark-mode .metric-value, body.dark-mode .panel-title { color: #f8fafc; }
        body.dark-mode .panel-header { border-bottom-color: #334155; }
        
        body.dark-mode .simple-table th { border-bottom-color: #334155; color: #94a3b8;}
        body.dark-mode .simple-table td { border-bottom-color: #334155; color: #cbd5e1;}
        
        body.dark-mode .progress-label { color: #cbd5e1; }
        body.dark-mode .progress-track { background-color: #334155; }
        
        body.dark-mode .pill.todo { background-color: #334155; color: #cbd5e1; }
    </style>
</head>
<body>

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
                    <li onclick="window.location.href='super_admin_dashboard.php'"><a href="super_admin_dashboard.php">User List</a></li>
                </ul>
            </li>

            <li class="dropdown-item" id="leadsMenu">
                <div class="dropdown-title" onclick="toggleSubMenu('leadsMenu')">
                    <div class="dropdown-title-left"><i class="fa-solid fa-briefcase"></i><span>Leads & Accounts</span></div>
                    <i class="fa-solid fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li onclick="window.location.href='super_admin_dashboard.php'"><a href="super_admin_dashboard.php">Company & Organization</a></li>
                </ul>
            </li>
            
            <li class="dropdown-item" id="dealsMenu">
                <div class="dropdown-title" onclick="toggleSubMenu('dealsMenu')">
                    <div class="dropdown-title-left"><i class="fa-solid fa-bullhorn"></i><span>Deals & Campaign</span></div>
                    <i class="fa-solid fa-chevron-down dropdown-icon"></i>
                </div>
                <ul class="submenu">
                    <li onclick="window.location.href='deal_pipeline.php'"><a href="deal_pipeline.php">Deal Pipeline</a></li>
                </ul>
            </li>

            <li onclick="window.location.href='super_admin_dashboard.php'"><i class="fa-solid fa-clipboard-list"></i><a href="super_admin_dashboard.php">Task Management</a></li>
            
            <li class="active"><i class="fa-solid fa-chart-column"></i><a href="analytics_reports.php">Analytics & Reports</a></li>
            
            <li><i class="fa-solid fa-gear"></i><a href="settings.php">Settings</a></li>
            
            <li style="margin-top: 20px; border-top: 1px solid #1e293b; padding-top: 20px;" onclick="window.location.href='logout.php'"><i class="fa-solid fa-right-from-bracket" style="color: #ef4444;"></i><a href="#" style="color: #ef4444;">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <div><i class="fa-solid fa-bars toggle-btn" id="outerToggle"></i></div>
            
            <div class="navbar-actions">
                <i class="fa-solid fa-moon nav-icon-btn" id="darkModeToggle" title="Toggle Dark Mode"></i>
                <div class="nav-icon-btn" title="Notifications"><i class="fa-regular fa-bell"></i><span class="notification-badge">3</span></div>
                <div class="user-profile"><i class="fa-solid fa-circle-user" style="color: #3b82f6;"></i><span><?php echo $_SESSION['name']; ?></span></div>
            </div>
        </div>

        <div class="analytics-container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="analytics-header-title">
                    <h1>Analytics Overview</h1>
                    <p>Performance metrics and business intelligence at a glance.</p>
                </div>
                <button class="btn-export" onclick="alert('Exporting Report PDF...')"><i class="fa-solid fa-file-pdf"></i> Export Full Report</button>
            </div>

            <div class="metrics-grid">
                <div class="metric-card revenue">
                    <div class="metric-title">Total Won Revenue <i class="fa-solid fa-sack-dollar" style="color:#10b981;"></i></div>
                    <div class="metric-value">$<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="metric-sub positive"><i class="fa-solid fa-arrow-trend-up"></i> +8.5% from last month</div>
                </div>
                <div class="metric-card winrate">
                    <div class="metric-title">Deal Win Rate <i class="fa-solid fa-trophy" style="color:#3b82f6;"></i></div>
                    <div class="metric-value"><?php echo $winRate; ?>%</div>
                    <div class="metric-sub neutral">Based on <?php echo ($wonDealsCount + $lostDealsCount); ?> closed deals</div>
                </div>
                <div class="metric-card tasks">
                    <div class="metric-title">Task Completion <i class="fa-solid fa-check-double" style="color:#f59e0b;"></i></div>
                    <div class="metric-value"><?php echo $taskCompletionRate; ?>%</div>
                    <div class="metric-sub neutral"><?php echo $completedTasksCount; ?> out of <?php echo $totalTasksCount; ?> tasks done</div>
                </div>
                <div class="metric-card entities">
                    <div class="metric-title">Total Active Entities <i class="fa-solid fa-database" style="color:#8b5cf6;"></i></div>
                    <div class="metric-value"><?php echo ($totalCompaniesCount + $totalUsersCount); ?></div>
                    <div class="metric-sub neutral"><?php echo $totalCompaniesCount; ?> Companies | <?php echo $totalUsersCount; ?> Users</div>
                </div>
            </div>

            <div class="dashboard-grid">
                
                <div class="dash-panel">
                    <div class="panel-header">
                        <div class="panel-title"><i class="fa-solid fa-handshake" style="color: #3b82f6;"></i> Recent Deal Activities</div>
                        <a href="deal_pipeline.php" style="font-size: 12px; color: #3b82f6; text-decoration: none; font-weight: 600;">View Pipeline &rarr;</a>
                    </div>
                    
                    <table class="simple-table">
                        <thead>
                            <tr>
                                <th>Deal Name</th>
                                <th>Date</th>
                                <th>Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recentDeals as $deal): ?>
                                <?php
                                    $dStage = htmlspecialchars($deal['stage']);
                                    $sClass = 'todo';
                                    if($dStage == 'Won') $sClass = 'low';
                                    if($dStage == 'Lost') $sClass = 'emergency';
                                    if($dStage == 'Proposal') $sClass = 'progress';
                                    if($dStage == 'Negotiation') $sClass = 'medium';
                                ?>
                                <tr>
                                    <td style="font-weight: 700;"><?php echo htmlspecialchars($deal['deal_name']); ?></td>
                                    <td style="color: #6b7280; font-size: 12px;"><?php echo date('M d, Y', strtotime($deal['created_at'])); ?></td>
                                    <td style="font-weight: 600;">$<?php echo number_format((float)$deal['deal_value'], 2); ?></td>
                                    <td><span class="pill <?php echo $sClass; ?>"><?php echo $dStage; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="dash-panel">
                    <div class="panel-header">
                        <div class="panel-title"><i class="fa-solid fa-list-check" style="color: #f59e0b;"></i> Global Task Status</div>
                    </div>
                    
                    <div style="margin-top: 10px;">
                        <?php
                            $tTotal = array_sum($taskStatusData) > 0 ? array_sum($taskStatusData) : 1; // Prevent division by zero
                            $pDone = round(($taskStatusData['done'] / $tTotal) * 100);
                            $pProg = round(($taskStatusData['progress'] / $tTotal) * 100);
                            $pTodo = round(($taskStatusData['todo'] / $tTotal) * 100);
                            $pOver = round(($taskStatusData['overdue'] / $tTotal) * 100);
                        ?>
                        
                        <div class="progress-item">
                            <div class="progress-label"><span>Completed</span> <span><?php echo $taskStatusData['done']; ?> (<?php echo $pDone; ?>%)</span></div>
                            <div class="progress-track"><div class="progress-fill fill-done" style="width: <?php echo $pDone; ?>%;"></div></div>
                        </div>
                        
                        <div class="progress-item">
                            <div class="progress-label"><span>In Progress</span> <span><?php echo $taskStatusData['progress']; ?> (<?php echo $pProg; ?>%)</span></div>
                            <div class="progress-track"><div class="progress-fill fill-prog" style="width: <?php echo $pProg; ?>%;"></div></div>
                        </div>
                        
                        <div class="progress-item">
                            <div class="progress-label"><span>To-Do</span> <span><?php echo $taskStatusData['todo']; ?> (<?php echo $pTodo; ?>%)</span></div>
                            <div class="progress-track"><div class="progress-fill fill-todo" style="width: <?php echo $pTodo; ?>%;"></div></div>
                        </div>
                        
                        <div class="progress-item">
                            <div class="progress-label"><span>Overdue</span> <span><?php echo $taskStatusData['overdue']; ?> (<?php echo $pOver; ?>%)</span></div>
                            <div class="progress-track"><div class="progress-fill fill-over" style="width: <?php echo $pOver; ?>%;"></div></div>
                        </div>
                    </div>
                </div>
                
            </div>
            
        </div>
    </div>

    <script>
        // --- DARK MODE LOGIC ---
        const darkModeToggle = document.getElementById('darkModeToggle');
        const body = document.body;

        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
            darkModeToggle.classList.replace('fa-moon', 'fa-sun');
        }

        darkModeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            if (body.classList.contains('dark-mode')) {
                localStorage.setItem('darkMode', 'enabled');
                darkModeToggle.classList.replace('fa-moon', 'fa-sun');
            } else {
                localStorage.setItem('darkMode', 'disabled');
                darkModeToggle.classList.replace('fa-sun', 'fa-moon');
            }
        });

        // --- SIDEBAR TOGGLE ---
        document.getElementById('outerToggle').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        // --- SUBMENU TOGGLE ---
        function toggleSubMenu(menuId) { 
            const menu = document.getElementById(menuId);
            menu.classList.toggle('open'); 
            const icon = menu.querySelector('.dropdown-icon');
            if(menu.classList.contains('open')) {
                icon.style.transform = 'rotate(180deg)';
            } else {
                icon.style.transform = 'rotate(0deg)';
            }
        }
    </script>
</body>
</html>
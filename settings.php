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
// 2. SETTINGS LOGIC (UPDATE PROFILE, PASSWORD, PREFERENCES)
// ========================================================================

// A. Update Personal Profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    if(isset($conn)){
        $uid = $_SESSION['user_id'];
        $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        
        $update_sql = "UPDATE users SET name='$name', email='$email', phone='$phone' WHERE id='$uid'";
        try {
            if(mysqli_query($conn, $update_sql)){
                $_SESSION['name'] = $name; // Update session name so navbar reflects change immediately
                $toastMessage = "Profile updated successfully!";
                $toastType = "success";
            }
        } catch (mysqli_sql_exception $e) {
            $toastMessage = "Database Error! Could not update profile.";
            $toastType = "error";
        }
    }
}

// B. Update Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    if(isset($conn)){
        $uid = $_SESSION['user_id'];
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (!empty($new_password) && $new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass_sql = "UPDATE users SET password='$hashed_password' WHERE id='$uid'";
            try {
                if(mysqli_query($conn, $update_pass_sql)){
                    $toastMessage = "Password changed successfully!";
                    $toastType = "success";
                }
            } catch (mysqli_sql_exception $e) {
                $toastMessage = "Database Error! Could not change password.";
                $toastType = "error";
            }
        } else {
            $toastMessage = "Passwords do not match!";
            $toastType = "error";
        }
    }
}

// C. Update System Preferences (Dummy Logic for UI)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_preferences'])) {
    $toastMessage = "System preferences saved successfully!";
    $toastType = "success";
}

// ========================================================================
// 3. FETCH CURRENT USER DATA
// ========================================================================
$current_name = $_SESSION['name'] ?? '';
$current_email = '';
$current_phone = '';
$current_designation = 'Super Admin';

if(isset($conn)){
    $uid = $_SESSION['user_id'];
    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id='$uid'");
    if($user_query && mysqli_num_rows($user_query) > 0){
        $user_data = mysqli_fetch_assoc($user_query);
        $current_name = $user_data['name'] ?? $_SESSION['name'];
        $current_email = $user_data['email'] ?? '';
        $current_phone = $user_data['phone'] ?? '';
        $current_designation = $user_data['designation'] ?? 'Super Admin';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Systellio CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ========================================================================
           GLOBAL STYLES & RESET 
        ======================================================================== */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: #f3f4f6; display: flex; height: 100vh; overflow: hidden; transition: background-color 0.3s, color 0.3s; color: #111827; }

        /* ========================================================================
           TOAST NOTIFICATION STYLES
        ======================================================================== */
        #toastBox { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 9999; right: 30px; top: 30px; font-size: 14px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 10px; transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55), visibility 0.4s; }
        #toastBox.show { visibility: visible; transform: translateX(0); }
        #toastBox.success { background-color: #10b981; }
        #toastBox.error { background-color: #ef4444; }

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
           SETTINGS SPECIFIC STYLES
        ======================================================================== */
        .settings-container { padding: 30px; display: block; }
        
        .settings-header-title h1 { font-size: 26px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; transition: 0.3s; color: #111827;}
        .settings-header-title p { font-size: 13px; color: #6b7280; font-weight: 500; margin-bottom: 25px;}

        /* Settings Grid */
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; align-items: start;}
        
        /* Settings Card Panel */
        .settings-panel { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); transition: 0.3s;}
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f3f4f6;}
        .panel-title { font-size: 16px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px; transition: 0.3s;}
        
        /* Form Inputs for Settings */
        .form-group { margin-bottom: 20px; position: relative;}
        .form-group label { display: block; font-size: 12px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; transition: 0.3s;}
        .form-group input, .form-group select { width: 100%; border: none; border-bottom: 2px solid #e5e7eb; padding: 10px 0; font-size: 14px; outline: none; transition: 0.3s; background: transparent; color: #374151;}
        .form-group input:focus, .form-group select:focus { border-bottom-color: #3b82f6; }
        
        /* Save Button */
        .settings-save-btn { background-color: #3b82f6; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px;}
        .settings-save-btn:hover { background-color: #2563eb; }

        /* Toggles */
        .toggle-switch { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6; transition: 0.3s;}
        .toggle-switch:last-child { border-bottom: none; }
        .toggle-label { font-size: 14px; font-weight: 600; color: #374151; transition: 0.3s;}
        .toggle-desc { font-size: 11px; color: #9ca3af; margin-top: 4px; font-weight: 500;}
        
        /* Checkbox styling to act like a toggle switch */
        .switch { position: relative; display: inline-block; width: 40px; height: 20px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 20px; }
        .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #10b981; }
        input:focus + .slider { box-shadow: 0 0 1px #10b981; }
        input:checked + .slider:before { transform: translateX(20px); }

        /* ========================================================================
           DARK MODE STYLES 
        ======================================================================== */
        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .main-content { background-color: #0f172a; }
        body.dark-mode .top-navbar { background-color: #1e293b; border-bottom: 1px solid #334155; box-shadow: none; }
        body.dark-mode .nav-icon-btn { color: #cbd5e1; }
        body.dark-mode .nav-icon-btn:hover { color: #f8fafc; }
        
        body.dark-mode .settings-header-title h1 { color: #f8fafc; }
        body.dark-mode .settings-panel { background-color: #1e293b; border-color: #334155; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
        body.dark-mode .panel-title { color: #f8fafc; }
        body.dark-mode .panel-header { border-bottom-color: #334155; }
        
        body.dark-mode .form-group label { color: #94a3b8; }
        body.dark-mode .form-group input, body.dark-mode .form-group select { color: #f8fafc; border-bottom-color: #475569; }
        body.dark-mode .form-group input:focus, body.dark-mode .form-group select:focus { border-bottom-color: #3b82f6; }
        body.dark-mode .form-group select option { background-color: #1e293b; color: #f8fafc; }

        body.dark-mode .toggle-switch { border-bottom-color: #334155; }
        body.dark-mode .toggle-label { color: #cbd5e1; }
        body.dark-mode .slider { background-color: #475569; }
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
            <li onclick="window.location.href='analytics_reports.php'"><i class="fa-solid fa-chart-column"></i><a href="analytics_reports.php">Analytics & Reports</a></li>
            
            <li class="active"><i class="fa-solid fa-gear"></i><a href="#">Settings</a></li>
            
            <li style="margin-top: 20px; border-top: 1px solid #1e293b; padding-top: 20px;" onclick="window.location.href='logout.php'"><i class="fa-solid fa-right-from-bracket" style="color: #ef4444;"></i><a href="#" style="color: #ef4444;">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <div><i class="fa-solid fa-bars toggle-btn" id="outerToggle"></i></div>
            
            <div class="navbar-actions">
                <i class="fa-solid fa-moon nav-icon-btn" id="darkModeToggle" title="Toggle Dark Mode"></i>
                <div class="nav-icon-btn" title="Notifications" onclick="showToast('No new notifications', 'success')"><i class="fa-regular fa-bell"></i><span class="notification-badge">3</span></div>
                <div class="user-profile"><i class="fa-solid fa-circle-user" style="color: #3b82f6;"></i><span><?php echo $_SESSION['name']; ?></span></div>
            </div>
        </div>

        <div class="settings-container">
            <div class="settings-header-title">
                <h1>System Settings</h1>
                <p>Manage your account, preferences, and system configurations.</p>
            </div>

            <div class="settings-grid">
                
                <div class="settings-panel">
                    <div class="panel-header">
                        <div class="panel-title"><i class="fa-solid fa-user-shield" style="color: #3b82f6;"></i> Personal Profile</div>
                    </div>
                    <form action="settings.php" method="POST">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($current_name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($current_email); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($current_phone); ?>">
                        </div>
                        <div class="form-group">
                            <label>Current Role & Designation</label>
                            <input type="text" value="Super Admin - <?php echo htmlspecialchars($current_designation); ?>" disabled style="opacity: 0.6; cursor: not-allowed;">
                        </div>
                        <button type="submit" name="update_profile" class="settings-save-btn"><i class="fa-solid fa-floppy-disk"></i> Update Profile</button>
                    </form>
                </div>

                <div class="settings-panel">
                    <div class="panel-header">
                        <div class="panel-title"><i class="fa-solid fa-lock" style="color: #ef4444;"></i> Security & Password</div>
                    </div>
                    <form action="settings.php" method="POST" id="passwordForm">
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" id="new_pass" required placeholder="Enter new password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" id="conf_pass" required placeholder="Confirm new password" onkeyup="checkSettingsPassword()">
                            <p id="pass_err" style="color: #ef4444; font-size: 10px; font-weight: 600; margin-top: 5px; display: none;">Passwords do not match!</p>
                        </div>
                        <button type="submit" name="change_password" id="btn_pass" class="settings-save-btn" style="background-color: #ef4444;"><i class="fa-solid fa-key"></i> Change Password</button>
                    </form>
                </div>

                <div class="settings-panel">
                    <div class="panel-header">
                        <div class="panel-title"><i class="fa-solid fa-sliders" style="color: #f59e0b;"></i> System Preferences</div>
                    </div>
                    <form action="settings.php" method="POST">
                        <div class="form-group">
                            <label>Timezone</label>
                            <select name="timezone">
                                <option value="UTC">UTC (Universal Time Coordinated)</option>
                                <option value="Asia/Dhaka" selected>Asia/Dhaka (+06:00)</option>
                                <option value="America/New_York">America/New_York (EST)</option>
                                <option value="Europe/London">Europe/London (GMT)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Default Currency</label>
                            <select name="currency">
                                <option value="USD" selected>USD ($)</option>
                                <option value="BDT">BDT (৳)</option>
                                <option value="EUR">EUR (€)</option>
                                <option value="GBP">GBP (£)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date Format</label>
                            <select name="date_format">
                                <option value="Y-m-d">YYYY-MM-DD (e.g. 2024-12-31)</option>
                                <option value="d/m/Y" selected>DD/MM/YYYY (e.g. 31/12/2024)</option>
                                <option value="M d, Y">Mon DD, YYYY (e.g. Dec 31, 2024)</option>
                            </select>
                        </div>
                        <button type="submit" name="save_preferences" class="settings-save-btn" style="background-color: #f59e0b;"><i class="fa-solid fa-check-double"></i> Save Preferences</button>
                    </form>
                </div>

                <div class="settings-panel">
                    <div class="panel-header">
                        <div class="panel-title"><i class="fa-solid fa-bell" style="color: #8b5cf6;"></i> Notifications & Alerts</div>
                    </div>
                    
                    <div class="toggle-switch">
                        <div>
                            <div class="toggle-label">Email Notifications</div>
                            <div class="toggle-desc">Receive daily summary emails and alerts.</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked onclick="showToast('Preference Updated', 'success')">
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="toggle-switch">
                        <div>
                            <div class="toggle-label">Browser Push Notifications</div>
                            <div class="toggle-desc">Get instant alerts for new tasks and deals.</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" onclick="showToast('Preference Updated', 'success')">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="toggle-switch">
                        <div>
                            <div class="toggle-label">Task Due Reminders</div>
                            <div class="toggle-desc">Notify 24 hours before a task is due.</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked onclick="showToast('Preference Updated', 'success')">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <script>
        // --- TOAST NOTIFICATION LOGIC ---
        window.onload = function() {
            <?php if($toastMessage != ""): ?>
                showToast("<?php echo $toastMessage; ?>", "<?php echo $toastType; ?>");
            <?php endif; ?>
        };

        function showToast(message, type) {
            const toast = document.getElementById("toastBox");
            const toastMsg = document.getElementById("toastMsg");
            const toastIcon = document.getElementById("toastIcon");

            toastMsg.innerText = message;
            toast.className = "show " + type;
            toastIcon.className = (type === 'success') ? "fa-solid fa-circle-check" : "fa-solid fa-circle-xmark";

            setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 3000);
        }

        // --- PASSWORD MATCH LOGIC ---
        function checkSettingsPassword() {
            const p1 = document.getElementById('new_pass').value;
            const p2 = document.getElementById('conf_pass').value;
            const err = document.getElementById('pass_err');
            const btn = document.getElementById('btn_pass');
            
            if(p2 !== "") {
                if(p1 !== p2) {
                    err.style.display = 'block';
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                } else {
                    err.style.display = 'none';
                    btn.disabled = false;
                    btn.style.opacity = '1';
                }
            } else {
                err.style.display = 'none';
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        }

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
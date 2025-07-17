<?php


session_start();
include 'db_connect.php';
// Initialize variables
$error = '';
$username_email = '';

// Check if the user is logged in
if (!isset($_SESSION['admin_id'])) {
    // Redirect to login page if not logged in
    header("Location: sign-in.php");
    exit;
}

// Get admin information from the database
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT admin_id, username, fullname, email, profile_image, role FROM admins WHERE admin_id = ? AND is_active = TRUE");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Admin not found or not active, destroy session and redirect to login
    session_destroy();
    header("Location: sign-in.php");
    exit;
}

// Get admin details
$admin = $result->fetch_assoc();

// Set default role if not present
if (!isset($admin['role'])) {
    $admin['role'] = 'Administrator';
}

// Update last login time
$update_stmt = $conn->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE admin_id = ?");
$update_stmt->bind_param("i", $admin_id);
$update_stmt->execute();
$update_stmt->close();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: sign-in.php");
    exit;
}

// Function to get profile image URL
function getProfileImageUrl($profileImage) {
    if (!empty($profileImage) && file_exists("uploads/profiles/" . $profileImage)) {
        return "uploads/profiles/" . $profileImage;
    } else {
        return "assets/images/default-avatar.png"; // Default image path
    }
}

// Get profile image URL
$profileImageUrl = getProfileImageUrl($admin['profile_image']);

// Close the statement
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechHub - Admin Dashboard</title>    
    <link rel="icon" href="images/hf.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0a0e1a;
            --secondary: #1a2332;
            --accent: #00d4ff;
            --accent-glow: rgba(0, 212, 255, 0.3);
            --light: #ffffff;
            --dark: #0a0e1a;
            --grey: #8892b0;
            --light-grey: #ccd6f6;
            --sidebar-width: 280px;
            --danger: #ff6b6b;
            --success: #64ffda;
            --warning: #ffd700;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0a0e1a 0%, #1a2332 100%);
            display: flex;
            min-height: 100vh;
            color: var(--light-grey);
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #0f1419 0%, #1a2332 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            border-right: 2px solid var(--accent);
            box-shadow: 4px 0 20px rgba(0, 212, 255, 0.15);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #1a2332;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 10px;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0, 212, 255, 0.2);
            background: rgba(0, 212, 255, 0.05);
            backdrop-filter: blur(10px);
        }
        
        .sidebar-logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--light);
            text-decoration: none;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo i {
            font-size: 28px;
            background: linear-gradient(45deg, var(--accent), #0099cc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sidebar-logo span {
            color: var(--accent);
            text-shadow: 0 0 10px var(--accent-glow);
        }
        
        .sidebar-close {
            color: var(--accent);
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            display: none;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .sidebar-close:hover {
            background: rgba(0, 212, 255, 0.1);
            transform: rotate(90deg);
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-title {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--accent);
            padding: 15px 20px 10px;
            margin-top: 15px;
            font-weight: 600;
            letter-spacing: 1.5px;
            border-bottom: 1px solid rgba(0, 212, 255, 0.1);
            margin-bottom: 10px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: var(--light-grey);
            text-decoration: none;
            padding: 15px 20px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            margin: 5px 15px;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }

        .sidebar-menu a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 212, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .sidebar-menu a:hover::before {
            left: 100%;
        }
        
        .sidebar-menu a:hover {
            background: rgba(0, 212, 255, 0.1);
            color: var(--light);
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.2);
            border-left: 4px solid var(--accent);
        }
        
        .sidebar-menu a.active {
            background: linear-gradient(90deg, rgba(0, 212, 255, 0.2), rgba(0, 212, 255, 0.1));
            border-left: 4px solid var(--accent);
            color: var(--light);
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
        }
        
        .sidebar-menu a i {
            margin-right: 12px;
            font-size: 18px;
            width: 20px;
            text-align: center;
            color: var(--accent);
        }

        .notification-badge {
            background: linear-gradient(45deg, #ff6b6b, #ff8e53);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: auto;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #0a0e1a 0%, #1a2332 100%);
        }
        
        /* Top Navigation */
        .top-navbar {
            background: linear-gradient(90deg, rgba(15, 20, 25, 0.95), rgba(26, 35, 50, 0.95));
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 212, 255, 0.2);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 212, 255, 0.1);
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--accent);
            font-size: 20px;
            cursor: pointer;
            display: none;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .toggle-sidebar:hover {
            background: rgba(0, 212, 255, 0.1);
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .navbar-title {
            font-weight: 600;
            color: var(--light);
            font-size: 20px;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }
        
        .welcome-text {
            font-size: 14px;
            color: var(--grey);
            padding: 10px 15px;
            background: rgba(0, 212, 255, 0.05);
            border-radius: 20px;
            border: 1px solid rgba(0, 212, 255, 0.2);
        }
        
        .welcome-text strong {
            color: var(--accent);
            font-weight: 600;
        }
        
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .navbar-actions .nav-link {
            color: var(--grey);
            font-size: 18px;
            position: relative;
            padding: 10px;
            border-radius: 50%;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .navbar-actions .nav-link:hover {
            color: var(--accent);
            background: rgba(0, 212, 255, 0.1);
            transform: scale(1.1);
        }
        
        .notification-count {
            position: absolute;
            top: 5px;
            right: 5px;
            background: linear-gradient(45deg, #ff6b6b, #ff8e53);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-3px); }
            60% { transform: translateY(-2px); }
        }
        
        /* Admin Profile Styles */
        .admin-profile {
            display: flex;
            align-items: center;
            position: relative;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 212, 255, 0.2);
            background: rgba(0, 212, 255, 0.05);
        }

        .admin-profile:hover {
            background: rgba(0, 212, 255, 0.1);
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.2);
        }

        .admin-avatar-container {
            position: relative;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            overflow: hidden;
            border: 2px solid var(--accent);
            margin-right: 12px;
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.4);
        }

        .admin-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .admin-info {
            display: flex;
            flex-direction: column;
        }

        .admin-name {
            font-weight: 600;
            font-size: 14px;
            display: block;
            line-height: 1.2;
            color: var(--light);
        }

        .admin-role {
            font-size: 12px;
            color: var(--accent);
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .admin-dropdown {
            position: relative;
        }

        .admin-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 55px;
            background: linear-gradient(180deg, #0f1419 0%, #1a2332 100%);
            min-width: 280px;
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.2);
            z-index: 1000;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(0, 212, 255, 0.3);
            backdrop-filter: blur(20px);
        }

        .admin-dropdown-header {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(0, 153, 204, 0.1));
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(0, 212, 255, 0.2);
        }

        .admin-dropdown-avatar-container {
            border-radius: 50%;
            width: 60px;
            height: 60px;
            overflow: hidden;
            border: 3px solid var(--accent);
            margin-right: 15px;
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.4);
        }

        .admin-dropdown-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .admin-dropdown-info {
            display: flex;
            flex-direction: column;
        }

        .admin-dropdown-name {
            font-weight: 600;
            font-size: 16px;
            color: var(--light);
        }

        .admin-dropdown-role {
            font-size: 12px;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .admin-dropdown-user {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(0, 212, 255, 0.1);
        }

        .admin-dropdown-user-name {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 16px;
            color: var(--light);
        }

        .admin-dropdown-user-email {
            color: var(--grey);
            font-size: 14px;
        }

        .admin-dropdown-content a {
            color: var(--light-grey);
            padding: 15px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 14px;
            border-bottom: 1px solid rgba(0, 212, 255, 0.1);
            transition: all 0.3s ease;
        }

        .admin-dropdown-content a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            color: var(--accent);
        }

        .admin-dropdown-content a.logout {
            color: var(--danger);
        }

        .admin-dropdown-content a.logout i {
            color: var(--danger);
        }

        .admin-dropdown-content a:hover {
            background: rgba(0, 212, 255, 0.1);
            color: var(--light);
            padding-left: 25px;
        }

        .admin-dropdown.show .admin-dropdown-content {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Dashboard Container */
        .dashboard-container {
            padding: 30px;
        }

        .dashboard-welcome {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(0, 153, 204, 0.05));
            padding: 30px;
            border-radius: 15px;
            border: 1px solid rgba(0, 212, 255, 0.2);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .welcome-title {
            font-size: 28px;
            font-weight: bold;
            color: var(--light);
            margin-bottom: 10px;
        }

        .welcome-subtitle {
            color: var(--accent);
            font-size: 16px;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .welcome-text {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                width: var(--sidebar-width);
                transform: translateX(0);
            }
            
            .sidebar-close {
                display: block;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .toggle-sidebar {
                display: block;
            }
            
            .navbar-title {
                display: none;
            }

            .admin-dropdown-content {
                right: -20px;
                min-width: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo">
                <i class="fas fa-microchip"></i>
                Tech<span>Hub</span>
            </a>
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-title">Main</div>
            <a href="dashboard.php" class="active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="orders_admin.php">
                <i class="fas fa-shopping-cart"></i> Orders
                <span class="notification-badge">5</span>
            </a>
            <a href="payment-history.php">
                <i class="fas fa-credit-card"></i> Payment History
            </a>
            
            <div class="menu-title">Inventory</div>
            <a href="products.php">
                <i class="fas fa-cube"></i> Products & Categories
            </a>
            <a href="stock.php">
                <i class="fas fa-warehouse"></i> Stock Management
            </a>
            
            <div class="menu-title">Users</div>
            <a href="users.php">
                <i class="fas fa-users"></i> User Management
            </a>
            
            <div class="menu-title">Reports & Settings</div>
            <a href="reports.php">
                <i class="fas fa-chart-line"></i> Reports & Analytics
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="top-navbar">
            <div class="nav-left">
                <button class="toggle-sidebar" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <span class="navbar-title">Dashboard</span>
                <div class="welcome-text">Welcome, <strong>Admin User</strong>!</div>
            </div>
            
            <div class="navbar-actions">
                <a href="messages.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span class="notification-count">5</span>
                </a>
                
                <div class="admin-dropdown" id="adminDropdown">
                    <div class="admin-profile">
                    <div class="admin-avatar-container">
                            <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Admin" class="admin-avatar">
                        </div>
                        <div class="admin-info">
                            <span class="admin-name">Admin User</span>
                            <span class="admin-role">Administrator</span>
                        </div>
                    </div>
                    
                    <div class="admin-dropdown-content" id="adminDropdownContent">
                        <div class="admin-dropdown-header">
                            <div class="admin-dropdown-avatar-container">
                                <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Admin" class="admin-dropdown-avatar">
                            </div>
                            <div class="admin-dropdown-info">
                                <span class="admin-dropdown-name"><?php echo htmlspecialchars($admin['fullname']); ?></span>
                                <span class="admin-dropdown-role"><?php echo htmlspecialchars($admin['role']); ?></span>
                            </div>
                        </div>
                        <div class="admin-dropdown-user">
                            <h4 class="admin-dropdown-user-name"><?php echo htmlspecialchars($admin['fullname']); ?></h4>
                            <p class="admin-dropdown-user-email"><?php echo htmlspecialchars($admin['email']); ?></p>
                        </div>
                        <a href="profileAdmin.php"><i class="fas fa-user"></i> Profile Settings</a>
                       
                        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Dashboard Content -->
        <div class="dashboard-container">
            <div class="dashboard-welcome">
                <div class="welcome-title">Welcome to TechHub Command Center</div>
                <div class="welcome-subtitle">Manage your tech operations from this central dashboard</div>
            </div>
            
            <!-- Your existing dashboard content goes here -->
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Admin dropdown toggle
        const adminDropdown = document.getElementById('adminDropdown');
        const adminDropdownContent = document.getElementById('adminDropdownContent');
        
        adminDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            adminDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!adminDropdown.contains(e.target)) {
                adminDropdown.classList.remove('show');
            }
        });
        
        // Sidebar toggle for responsive design
        const toggleSidebar = document.getElementById('toggleSidebar');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebar = document.querySelector('.sidebar');
        
        toggleSidebar.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        sidebarClose.addEventListener('click', function() {
            sidebar.classList.remove('active');
        });

        // Add smooth scrolling to sidebar links
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', function(e) {
                // Remove active class from all links
                document.querySelectorAll('.sidebar-menu a').forEach(l => l.classList.remove('active'));
                // Add active class to clicked link
                this.classList.add('active');
            });
        });
    });
    </script>
</body>
</html>
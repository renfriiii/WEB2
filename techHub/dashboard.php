<?php
// Start the session at the very beginning

session_start();
include 'db_connect.php';
// Initialize variables
$error = '';
$username_email = '';

// Check if the user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: sign-in.php");
    exit;
}
// Initialize variables
$error = '';
$success = '';
$conversations = []; // Initialize as empty array
$unreadCount = 0; // Initialize as 0

// Get admin information from the database
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT admin_id, username, fullname, email, profile_image, role FROM admins WHERE admin_id = ? AND is_active = TRUE");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: sign-in.php");
    exit;
}

$admin = $result->fetch_assoc();
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
        return "assets/images/default-avatar.png";
    }
}

$profileImageUrl = getProfileImageUrl($admin['profile_image']);

// Function to parse XML data (simulate reading from files)
function parseTransactionData($file = 'transaction.xml') {
    $xml = simplexml_load_file($file) or die('Failed to load transaction.xml');
    $transactions = [];

    foreach ($xml->transaction as $item) {
        $transactions[] = [
            'transaction_id' => (string)$item->transaction_id,
            'user_id' => (int)$item->user_id,
            'transaction_date' => (string)$item->transaction_date,
            'status' => (string)$item->status,
            'payment_method' => (string)$item->payment_method,
            'subtotal' => (float)$item->subtotal,
            'shipping_fee' => (float)$item->shipping_fee,
            'total_amount' => (float)$item->total_amount,
            'product_name' => (string)$item->items->item->product_name,
            'quantity' => (int)$item->items->item->quantity,
            'city' => (string)$item->shipping_info->city
        ];
    }

    return $transactions;
}

function parseProductData($file = 'product.xml') {
    $xml = simplexml_load_file($file) or die('Failed to load product.xml');
    $products = [];

    foreach ($xml->products->product as $item) {
        $products[] = [
            'id' => (int)$item->id,
            'name' => (string)$item->name,
            'category' => (string)$item->category,
            'price' => (float)$item->price,
            'stock' => (int)$item->stock,
            'rating' => (float)$item->rating,
            'review_count' => (int)$item->review_count,
            'featured' => filter_var($item->featured, FILTER_VALIDATE_BOOLEAN),
            'on_sale' => filter_var($item->on_sale, FILTER_VALIDATE_BOOLEAN)
        ];
    }

    return $products;
}

// Parse XML data
$transactions = parseTransactionData();
$products = parseProductData();

// Dashboard Stats
$totalRevenue = array_sum(array_column($transactions, 'total_amount'));
$totalOrders = count($transactions);
$totalProducts = count($products);



// User count (sample query — adjust as needed)
$user_count_query = "SELECT COUNT(*) as total_users FROM users WHERE is_active = 1";
$user_result = $conn->query($user_count_query);
$totalUsers = $user_result ? $user_result->fetch_assoc()['total_users'] : 0;

// Chart Data
$statusCounts = array_count_values(array_column($transactions, 'status'));
$paymentMethods = array_count_values(array_column($transactions, 'payment_method'));
$cityCounts = array_count_values(array_column($transactions, 'city'));

// Optional: Close statement if one was used earlier
// $stmt->close(); ← Only if using prepared statements

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechHub - Admin Dashboard</title>    <link rel="icon" href="images/hf.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
        
        /* Dashboard Container */
        .dashboard-container {
            padding: 30px;
        }

        /* Dashboard Styles */
        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .dashboard-subtitle {
            color: var(--grey);
            font-size: 16px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--secondary);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }

        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }

        .stat-card.revenue .stat-icon { background: linear-gradient(135deg, var(--success), #20c997); }
        .stat-card.orders .stat-icon { background: linear-gradient(135deg, var(--secondary), #0056b3); }
        .stat-card.products .stat-icon { background: linear-gradient(135deg, var(--warning), #e0a800); }
        .stat-card.users .stat-icon { background: linear-gradient(135deg, var(--info), #138496); }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--grey);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-change {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .stat-change.positive {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .stat-change.negative {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .chart-subtitle {
            font-size: 12px;
            color: var(--grey);
            margin-top: 4px;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .quick-actions-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .action-btn:hover {
            background: var(--secondary);
            transform: translateY(-1px);
        }

        .action-btn i {
            margin-right: 10px;
            font-size: 16px;
        }

        /* Recent Activity */
        .recent-activity {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .activity-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 16px;
            color: white;
        }

        .activity-icon.order { background: var(--secondary); }
        .activity-icon.payment { background: var(--success); }
        .activity-icon.user { background: var(--info); }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 14px;
            color: var(--dark);
            margin-bottom: 2px;
        }

        .activity-time {
            font-size: 12px;
            color: var(--grey);
        }

        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: var(--dark);
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .tooltip .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: var(--dark) transparent transparent transparent;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .welcome-text {
                display: none;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .charts-grid {
                grid-template-columns: 1fr;
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

            .dashboard-container {
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
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
                <!-- <a href="messages.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span class="notification-count">5</span>
                </a> -->
                
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
            

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card revenue tooltip">
                    <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                    <div class="stat-value">₱<?php echo number_format($totalRevenue); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-change positive">+12.5%</div>
                    <span class="tooltiptext">Total revenue from all completed transactions</span>
                </div>

                <div class="stat-card orders tooltip">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-value"><?php echo $totalOrders; ?></div>
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-change positive">+8.2%</div>
                    <span class="tooltiptext">Total number of orders placed</span>
                </div>

                <div class="stat-card products tooltip">
                    <div class="stat-icon"><i class="fas fa-tshirt"></i></div>
                    <div class="stat-value"><?php echo $totalProducts; ?></div>
                    <div class="stat-label">Products</div>
                    <div class="stat-change positive">+2</div>
                    <span class="tooltiptext">Total active products in inventory</span>
                </div>

                <div class="stat-card users tooltip">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo $totalUsers; ?></div>
                    <div class="stat-label">Active Users</div>
                    <div class="stat-change positive">+15.3%</div>
                    <span class="tooltiptext">Total registered and active users</span>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="charts-grid">
                <!-- Order Status Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <h3 class="chart-title">Order Status Distribution</h3>
                            <p class="chart-subtitle">Current status of all orders</p>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="orderStatusChart"></canvas>
                    </div>
                </div>

                <!-- Payment Methods Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <h3 class="chart-title">Payment Methods</h3>
                            <p class="chart-subtitle">Popular payment options</p>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="paymentMethodsChart"></canvas>
                    </div>
                </div>

                <!-- Sales by Location Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <h3 class="chart-title">Sales by Location</h3>
                            <p class="chart-subtitle">Orders distribution by city</p>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesLocationChart"></canvas>
                    </div>
                </div>

                <!-- Revenue Trend Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <h3 class="chart-title">Revenue Trend</h3>
                            <p class="chart-subtitle">Daily revenue for the past week</p>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3 class="quick-actions-title">Quick Actions</h3>
                <div class="actions-grid">
                    <a href="products.php" class="action-btn tooltip">
                        <i class="fas fa-plus"></i>
                        Add New Product
                        <span class="tooltiptext">Add a new product to your inventory</span>
                    </a>
                    <a href="orders_admin.php" class="action-btn tooltip">
                        <i class="fas fa-eye"></i>
                        View All Orders
                        <span class="tooltiptext">Manage and track all customer orders</span>
                    </a>
                    <a href="stock.php" class="action-btn tooltip">
                        <i class="fas fa-boxes"></i>
                        Manage Inventory
                        <span class="tooltiptext">Update stock levels and inventory</span>
                    </a>
                    <a href="reports.php" class="action-btn tooltip">
                        <i class="fas fa-chart-bar"></i>
                        Generate Report
                        <span class="tooltiptext">Create detailed business reports</span>
                    </a>
                    <a href="users.php" class="action-btn tooltip">
                        <i class="fas fa-user-plus"></i>
                        Manage Users
                        <span class="tooltiptext">View and manage customer accounts</span>
                    </a>
                    <a href="settings.php" class="action-btn tooltip">
                        <i class="fas fa-cog"></i>
                        System Settings
                        <span class="tooltiptext">Configure system preferences</span>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <h3 class="activity-title">Recent Activity</h3>
                
                <?php foreach($transactions as $transaction): ?>
                <div class="activity-item">
                    <div class="activity-icon <?php echo $transaction['status'] === 'delivered' ? 'payment' : 'order'; ?>">
                        <i class="fas <?php echo $transaction['status'] === 'delivered' ? 'fa-check' : 'fa-truck'; ?>"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">
                            Order #<?php echo substr($transaction['transaction_id'], -6); ?> 
                            (<?php echo $transaction['product_name']; ?>) - 
                            <strong>₱<?php echo number_format($transaction['total_amount']); ?></strong>
                        </div>
                        <div class="activity-time">
                            <?php echo date('M j, Y g:i A', strtotime($transaction['transaction_date'])); ?> • 
                            <?php echo ucfirst($transaction['status']); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="activity-item">
                    <div class="activity-icon user">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">New user registered</div>
                        <div class="activity-time">2 hours ago</div>
                    </div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon order">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">New product added to inventory</div>
                        <div class="activity-time">5 hours ago</div>
                    </div>
                </div>
            </div>
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

        // Chart colors matching the theme
        const colors = {
            primary: '#111111',
            secondary: '#0071c5',
            success: '#28a745',
            warning: '#ffc107',
            info: '#17a2b8',
            danger: '#dc3545'
        };

        // Order Status Chart
        const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
        const orderStatusData = <?php echo json_encode($statusCounts); ?>;
        
        new Chart(orderStatusCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(orderStatusData).map(status => status.charAt(0).toUpperCase() + status.slice(1)),
                datasets: [{
                    data: Object.values(orderStatusData),
                    backgroundColor: [colors.success, colors.warning, colors.info, colors.secondary],
                    borderWidth: 3,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Payment Methods Chart
        const paymentMethodsCtx = document.getElementById('paymentMethodsChart').getContext('2d');
        const paymentMethodsData = <?php echo json_encode($paymentMethods); ?>;
        
        new Chart(paymentMethodsCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(paymentMethodsData).map(method => method.toUpperCase()),
                datasets: [{
                    label: 'Orders',
                    data: Object.values(paymentMethodsData),
                    backgroundColor: [colors.secondary, colors.success],
                    borderColor: [colors.secondary, colors.success],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Sales by Location Chart
        const salesLocationCtx = document.getElementById('salesLocationChart').getContext('2d');
        const cityData = <?php echo json_encode($cityCounts); ?>;
        
        new Chart(salesLocationCtx, {
            type: 'pie',
            data: {
                labels: Object.keys(cityData).map(city => city.charAt(0).toUpperCase() + city.slice(1)),
                datasets: [{
                    data: Object.values(cityData),
                    backgroundColor: [colors.info, colors.warning, colors.danger, colors.success],
                    borderWidth: 3,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // Revenue Trend Chart
        const revenueTrendCtx = document.getElementById('revenueTrendChart').getContext('2d');
        
        // Sample data for the past week (you would get this from your database)
        const revenueData = [800, 1200, 900, 1500, 1100, 1800, 2200];
        const labels = [];
        
        // Generate last 7 days
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            labels.push(date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }));
        }
        
        new Chart(revenueTrendCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: revenueData,
                    borderColor: colors.secondary,
                    backgroundColor: colors.secondary + '20',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: colors.secondary,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        // Animate stat cards on page load
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            }, index * 100);
        });

        // Add click handlers for stat cards (for drill-down functionality)
        document.querySelector('.stat-card.revenue').addEventListener('click', function() {
            window.location.href = 'payment-history.php';
        });

        document.querySelector('.stat-card.orders').addEventListener('click', function() {
            window.location.href = 'orders_admin.php';
        });

        document.querySelector('.stat-card.products').addEventListener('click', function() {
            window.location.href = 'products.php';
        });

        document.querySelector('.stat-card.users').addEventListener('click', function() {
            window.location.href = 'users.php';
        });
    });
    </script>
</body>
</html>
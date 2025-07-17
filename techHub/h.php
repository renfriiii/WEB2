<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include your environment-aware database connection
include 'db_connect.php';

// Initialize variables
$error = '';
$username_email = '';

// Check if user is logged in
$loggedIn = isset($_SESSION['user_id']);
$user = null;

// If user is logged in, fetch their information
if ($loggedIn) {
    // Prepare and execute query to get user details
    $stmt = $conn->prepare("SELECT id, fullname, username, email, profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    // If user exists, store their details
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    }

    // Close statement and connection
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
           :root {
            /* Main Color Palette */
            --primary: #0a0e1a;
            --secondary: #00d4ff;
            --accent: #0099cc;
            --light: #ccd6f6;
            --dark: #0a0e1a;
            --grey: #8892b0;
            --light-grey: #1a2332;
            --border-color: rgba(0, 212, 255, 0.2);
            --backdrop-blur: rgba(10, 14, 26, 0.95);
            --price-color: #00d4ff;
            --sale-color: #00d4ff;
            
            /* Background Gradients */
            --bg-gradient: linear-gradient(135deg, #0a0e1a 0%, #1a2332 100%);
            --card-bg: rgba(26, 35, 50, 0.6);
            --hover-bg: rgba(0, 212, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: var(--bg-gradient);
            color: var(--light);
            min-height: 100vh;
        }

        .top-bar {
            background: rgba(0, 212, 255, 0.1);
            backdrop-filter: blur(10px);
            color: var(--light);
            padding: 8px 0;
            text-align: center;
            font-size: 12px;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
        }

        .top-bar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .top-bar a {
            color: var(--light);
            text-decoration: none;
            margin-left: 15px;
            transition: color 0.3s ease;
        }

        .top-bar a:hover {
            color: var(--secondary);
        }

        .header {
            background: var(--backdrop-blur);
            backdrop-filter: blur(20px);
            box-shadow: 0 4px 20px rgba(0, 212, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--border-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--light);
            text-decoration: none;
            letter-spacing: -0.5px;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        .logo span {
            color: var(--secondary);
            text-shadow: 0 0 20px var(--secondary);
        }

        .search-bar {
            flex-grow: 1;
            max-width: 500px;
            margin: 0 30px;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 25px;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            color: var(--light);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-bar input::placeholder {
            color: var(--grey);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
        }

        .search-bar button {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--grey);
            transition: color 0.3s ease;
        }

        .search-bar button:hover {
            color: var(--secondary);
        }

        .nav-icons {
            display: flex;
            align-items: center;
        }

        .nav-icons a {
            margin-left: 20px;
            font-size: 18px;
            color: var(--light);
            text-decoration: none;
            position: relative;
            transition: all 0.3s ease;
        }

        .nav-icons a:hover {
            color: var(--secondary);
            text-shadow: 0 0 10px var(--secondary);
        }

        .cart-count {
            position: absolute;
            top: -6px;
            right: -6px;
            background: linear-gradient(45deg, var(--secondary), var(--accent));
            color: var(--dark);
            border-radius: 50%;
            width: 16px;
            height: 16px;
            font-size: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }

        /* Enhanced Account Dropdown Styling */
        .account-dropdown {
            position: relative;
            display: inline-block;
        }

        .account-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: var(--backdrop-blur);
            backdrop-filter: blur(20px);
            min-width: 280px;
            box-shadow: 0 8px 32px rgba(0, 212, 255, 0.2);
            z-index: 1;
            border-radius: 12px;
            margin-top: 10px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .account-dropdown-content:before {
            content: '';
            position: absolute;
            top: -8px;
            right: 10px;
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-bottom: 8px solid var(--backdrop-blur);
        }

        /* Enhanced User Profile Header */
        .user-profile-header {
            display: flex;
            align-items: center;
            padding: 16px;
            background: linear-gradient(135deg, var(--card-bg), rgba(0, 212, 255, 0.1));
            border-bottom: 1px solid var(--border-color);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--secondary);
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
            margin-right: 15px;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .mini-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-info {
            flex: 1;
        }

        .user-info h4 {
            margin: 0;
            font-size: 16px;
            color: var(--light);
            font-weight: 600;
        }

        .user-info .username {
            display: block;
            font-size: 12px;
            color: var(--grey);
            margin-top: 2px;
        }

        .account-links {
            padding: 8px 0;
        }

        .account-links a {
            color: var(--light);
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 14px;
            font-weight: 400;
            margin: 0;
            transition: all 0.3s ease;
        }

        .account-links a i {
            margin-right: 10px;
            color: var(--secondary);
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        .account-links a:hover {
            background: var(--hover-bg);
            color: var(--secondary);
        }

        .account-dropdown.active .account-dropdown-content {
            display: block;
        }

        /* Main Navigation Styles */
        .main-nav {
            display: flex;
            justify-content: center;
            background: rgba(26, 35, 50, 0.3);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .main-nav a {
            padding: 15px 20px;
            text-decoration: none;
            color: var(--light);
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            position: relative;
        }

        .main-nav a:hover,
        .main-nav a.active {
            color: var(--secondary);
            text-shadow: 0 0 10px var(--secondary);
        }

        /* Hover underline effect */
        .main-nav a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--secondary), var(--accent));
            left: 50%;
            bottom: 10px;
            transition: all 0.3s ease;
            transform: translateX(-50%);
            box-shadow: 0 0 10px var(--secondary);
        }

        .main-nav a:hover:after,
        .main-nav a.active:after {
            width: 60%;
        }

        /* Mobile menu button */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--light);
            font-size: 22px;
            cursor: pointer;
        }

        /* Sign out button styling */
        .sign-out-btn {
            border-top: 1px solid var(--border-color);
            margin-top: 5px;
        }

        .sign-out-btn a {
            color: #ff6b6b !important;
        }

        .sign-out-btn a:hover {
            background: rgba(255, 107, 107, 0.1);
        }

    </style>
</head>
<body>
      <!-- Top Bar -->
      <div class="top-bar">
        <div class="container">
            <div>FREE SHIPPING ON ORDERS OVER ₱4,000!</div>
            <div>
                <a href="#">Help</a>
                <a href="#">Order Tracker</a>
                <?php if (!$loggedIn): ?>
                    <a href="sign-in.php">Sign In</a>
                    <a href="register.php">Register</a>
                <?php else: ?>
                    <a href="#">Welcome, <?php echo $user['username']; ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="navbar">
                <a href="usershop.php" class="logo">Tech<span>Hub</span></a>

                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search products...">
                    <button onclick="searchProducts()"><i class="fas fa-search"></i></button>
                </div>
                
                <div class="nav-icons">
                    <?php if ($loggedIn): ?>
                        <!-- Enhanced Account dropdown for logged-in users -->
                        <div class="account-dropdown" id="accountDropdown">
                            <a href="#" id="accountBtn">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img src="uploads/profiles/<?php echo $user['profile_image']; ?>" alt="Profile" class="mini-avatar">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                            </a>
                            <div class="account-dropdown-content" id="accountDropdownContent">
                                <div class="user-profile-header">
                                    <div class="user-avatar">
                                        <img src="<?php echo !empty($user['profile_image']) ? 'uploads/profiles/' . $user['profile_image'] : 'assets/images/default-avatar.png'; ?>" alt="Profile">
                                    </div>
                                    <div class="user-info">
                                        <h4><?php echo $user['fullname']; ?></h4>
                                        <span class="username">@<?php echo $user['username']; ?></span>
                                    </div>
                                </div>
                                <div class="account-links">
                                    <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                                    <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
                                  
                                    <div class="sign-out-btn">
                                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Login link for non-logged-in users -->
                        <a href="sign-in.php"><i class="fas fa-user-circle"></i></a>
                    <?php endif; ?>

                    <!-- Updated Cart Button -->
                   
                    <a href="cart.php" id="cartBtn" class="active">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count" id="cartCount">0</span>
                    </a>
                </div>

                <button class="menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- Main Navigation - Categories will be dynamically loaded here -->
        <nav class="main-nav" id="mainNav">
            <a href="usershop.php" class="active">HOME</a>
            <!-- Categories from product.xml will be inserted here by JavaScript -->
        </nav>
      

   

</body>
</html>
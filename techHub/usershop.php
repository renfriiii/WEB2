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
    <title>TechHub - Premium Tech Store</title>
    <link rel="icon" href="images/th.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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

        /* Results count */
        .results-count {
            text-align: center;
            margin: 5px 0;
            font-size: 14px;
            color: var(--grey);
        }

        /* Product Container Styling */
        #product-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Loading Status */
        #loading-status {
            text-align: center;
            font-size: 18px;
            color: var(--grey);
            padding: 40px 0;
        }

        .product-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.4s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 212, 255, 0.2);
            border-color: var(--secondary);
        }

        .product-card:hover::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(0, 153, 204, 0.1));
            border-radius: 16px;
            z-index: 1;
        }

        .product-image {
            position: relative;
            height: 240px;
            overflow: hidden;
            z-index: 2;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.08);
        }

        /* Sale Tag Styling */
        .sale-tag {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(45deg, var(--secondary), var(--accent));
            color: var(--dark);
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 0.8rem;
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.4);
            z-index: 3;
        }

        /* Product Info Section */
        .product-info {
            padding: 22px;
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            z-index: 2;
            position: relative;
        }

        .product-info h3 {
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--light);
            line-height: 1.3;
            text-align: center;
        }

        .product-category {
            font-size: 13px;
            color: var(--grey);
            margin: 0 0 12px 0;
            text-transform: uppercase;
            font-weight: 500;
            text-align: center;
        }

        .product-price {
            font-size: 18px;
            font-weight: 700;
            color: var(--price-color);
            margin: 0 0 8px 0;
            text-align: center;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        .product-stock {
            font-size: 12px;
            color: var(--grey);
            margin: 0;
            text-align: center;
        }

        /* Add to Cart Button */
        .add-to-cart-btn {
            display: block;
            background: linear-gradient(45deg, var(--secondary), var(--accent));
            color: var(--dark);
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 12px;
            width: 100%;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
        }

        .add-to-cart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 212, 255, 0.4);
        }

        /* Star Rating */
        .product-rating {
            display: flex;
            justify-content: center;
            margin-bottom: 10px;
            color: var(--secondary);
        }

        .product-rating span {
            font-size: 12px;
            color: var(--grey);
            margin-left: 5px;
        }

        /* When no products found */
        .no-products {
            grid-column: span 4;
            text-align: center;
            font-size: 18px;
            color: var(--grey);
            padding: 40px 0;
        }

        /* Section styling */
        .product-section {
            padding: 60px 0;
            background: linear-gradient(135deg, rgba(26, 35, 50, 0.5), rgba(10, 14, 26, 0.8));
            margin-top: 40px;
            border-top: 1px solid var(--border-color);
        }

        .product-section h2 {
            text-align: center;
            font-size: 28px;
            margin-bottom: 30px;
            color: var(--light);
            position: relative;
        }

        .product-section h2:after {
            content: "";
            display: block;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--secondary), var(--accent));
            margin: 15px auto 0;
            box-shadow: 0 0 10px var(--secondary);
        }

        /* Slideshow Styles */
        .slideshow-container {
            position: relative;
            max-width: 1280px;
            margin: 30px auto;
            overflow: hidden;
            border-radius: 16px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-color);
        }

        .slideshow-slide {
            display: none;
            width: 100%;
            height: 500px;
            background-size: cover;
            background-position: center;
            animation-name: fade;
            animation-duration: 1.5s;
            position: relative;
        }

        .slideshow-slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(0, 153, 204, 0.1));
        }

        .slideshow-slide.active {
            display: block;
        }

        .slideshow-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--backdrop-blur);
            backdrop-filter: blur(20px);
            color: var(--light);
            padding: 20px;
            border-top: 1px solid var(--border-color);
        }

        .slideshow-content h2 {
            font-size: 28px;
            margin-bottom: 10px;
            color: var(--light);
        }

        .slideshow-content p {
            font-size: 16px;
            margin-bottom: 15px;
            color: var(--grey);
        }

        .slideshow-btn {
            display: inline-block;
            background: linear-gradient(45deg, var(--secondary), var(--accent));
            color: var(--dark);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
        }

        .slideshow-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 212, 255, 0.4);
        }

        .slideshow-prev,
        .slideshow-next {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: var(--backdrop-blur);
            backdrop-filter: blur(20px);
            color: var(--light);
            font-size: 24px;
            padding: 15px;
            cursor: pointer;
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .slideshow-prev {
            left: 20px;
        }

        .slideshow-next {
            right: 20px;
        }

        .slideshow-prev:hover,
        .slideshow-next:hover {
            background: var(--hover-bg);
            color: var(--secondary);
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
        }

        .slideshow-dots {
            text-align: center;
            position: absolute;
            bottom: 80px;
            width: 100%;
        }

        .slideshow-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            margin: 0 5px;
            background-color: rgba(204, 214, 246, 0.5);
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .slideshow-dot.active {
            background: var(--secondary);
            box-shadow: 0 0 10px var(--secondary);
        }

        @keyframes fade {
            from { opacity: 0.4 }
            to { opacity: 1 }
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }

        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .pagination li {
            margin: 0 5px;
        }

        .pagination a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            color: var(--light);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .pagination a:hover {
            background: var(--hover-bg);
            color: var(--secondary);
        }

        .pagination a.active {
            background: linear-gradient(45deg, var(--secondary), var(--accent));
            color: var(--dark);
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
        }

        .pagination .prev-page,
        .pagination .next-page {
            width: auto;
            padding: 0 15px;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination .disabled:hover {
            background: var(--card-bg);
            color: var(--light);
        }

/* Footer Styles */
.footer {
    background-color: var(--primary);
    color: var(--light);
    padding: 50px 0 20px;
    margin-top: 60px;
}

.footer-columns {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 40px;
    margin-bottom: 40px;
}

.footer-column h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    color: var(--light);
}

.footer-column ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-column ul li {
    margin-bottom: 10px;
}

.footer-column ul li a {
    color: #cccccc;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s ease;
}

.footer-column ul li a:hover {
    color: var(--secondary);
}

.social-links {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
}

.social-links a {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    color: var(--light);
    text-decoration: none;
    transition: all 0.3s ease;
}

.social-links a:hover {
    background-color: var(--secondary);
    transform: translateY(-2px);
}

.newsletter h4 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
    color: var(--light);
}

.newsletter form {
    display: flex;
    gap: 10px;
}

.newsletter input {
    flex: 1;
    padding: 12px 15px;
    border: none;
    border-radius: 4px;
    background-color: rgba(255, 255, 255, 0.1);
    color: var(--light);
    font-size: 14px;
}

.newsletter input::placeholder {
    color: #cccccc;
}

.newsletter input:focus {
    outline: none;
    background-color: rgba(255, 255, 255, 0.2);
}

.newsletter button {
    padding: 12px 20px;
    background-color: var(--secondary);
    color: var(--light);
    border: none;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.newsletter button:hover {
    background-color: #005da6;
}

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.footer-bottom p {
    color: #cccccc;
    font-size: 14px;
    margin: 0;
}

.footer-links {
    display: flex;
    gap: 20px;
}

.footer-links a {
    color: #cccccc;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s ease;
}

.footer-links a:hover {
    color: var(--secondary);
}

.payment-methods {
    display: flex;
    gap: 15px;
    align-items: center;
}

.payment-methods i {
    font-size: 24px;
    color: #cccccc;
    transition: color 0.3s ease;
}

.payment-methods i:hover {
    color: var(--secondary);
}

/* Modal Styles */
.product-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.product-modal-content {
    background-color: #fff;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 6px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    position: relative;
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-close {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 24px;
    background: none;
    border: none;
    cursor: pointer;
    width: 30px;
    height: 30px;
    line-height: 30px;
    text-align: center;
    z-index: 10;
    color: #555;
    transition: color 0.2s;
}

.modal-close:hover {
    color: #000;
}

.modal-product-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.modal-product-image {
    padding: 20px;
}

.modal-product-image img {
    width: 100%;
    height: auto;
    object-fit: cover;
    border-radius: 4px;
}

.modal-product-details {
    padding: 30px 30px 30px 0;
}

.modal-product-title {
    font-size: 24px;
    color: #333;
    margin-bottom: 10px;
    font-weight: bold;
}

.modal-product-price {
    font-size: 24px;
    color: #e73c17;
    font-weight: bold;
    margin-bottom: 15px;
}

.modal-product-rating {
    margin-bottom: 15px;
}

.modal-product-rating .stars {
    color: #ffaa00;
    font-size: 18px;
}

.modal-product-rating .rating-text {
    color: #777;
    font-size: 14px;
}

.modal-product-description {
    margin-bottom: 20px;
    color: #555;
    line-height: 1.6;
}

.modal-product-size,
.modal-product-color {
    margin-bottom: 20px;
}

.modal-product-size label,
.modal-product-color label,
.modal-product-quantity label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
    color: #333;
}

.size-options {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.size-btn {
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #ddd;
    background-color: white;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
    border-radius: 4px;
}

.size-btn:hover {
    border-color: #999;
}

.size-btn.active {
    border-color: #333;
    background-color: #333;
    color: white;
}

.color-options {
    display: flex;
    gap: 10px;
}

.color-btn {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1px solid #ddd;
    cursor: pointer;
    transition: all 0.2s;
}

.color-btn:hover {
    transform: scale(1.1);
}

.color-btn.active {
    box-shadow: 0 0 0 2px #fff, 0 0 0 4px #333;
}

.modal-product-quantity {
    margin-bottom: 15px;
}

.quantity-control {
    display: flex;
    align-items: center;
    max-width: 120px;
}

.quantity-btn {
    width: 36px;
    height: 36px;
    border: 1px solid #ddd;
    background-color: #f5f5f5;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s;
}

.quantity-btn:hover {
    background-color: #e5e5e5;
}

.quantity-input {
    width: 50px;
    height: 36px;
    border: 1px solid #ddd;
    border-left: none;
    border-right: none;
    text-align: center;
    font-size: 16px;
}

.stock-info {
    color: #666;
    margin-bottom: 20px;
    font-size: 14px;
}

.modal-add-to-cart {
    width: 100%;
    padding: 12px;
    background-color: #2196F3;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.2s;
    max-width: 300px;
}

.modal-add-to-cart:hover {
    background-color: #0b7dda;
}

.add-to-cart-confirmation {
    position: fixed;
    top: 20px;
    right: 20px;
    background-color: #4CAF50;
    color: white;
    padding: 15px 20px;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    z-index: 1100;
    transition: opacity 0.5s;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateX(100px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.confirmation-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.confirmation-content i {
    font-size: 24px;
    color: white;
}

.confirmation-content p {
    margin: 0;
}

.fade-out {
    opacity: 0;
}

body.modal-open {
    overflow: hidden;
}

/* Responsive Design */
@media (max-width: 1200px) {
    #product-container {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 900px) {
    #product-container {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    #product-container {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 992px) {
    .search-bar {
        max-width: 300px;
    }

    .footer-columns {
        grid-template-columns: repeat(2, 1fr);
        gap: 30px;
    }
}

@media (max-width: 768px) {
    .top-bar .container {
        flex-direction: column;
        gap: 5px;
    }

    .search-bar {
        max-width: none;
        margin: 10px 0;
    }

    .navbar {
        flex-wrap: wrap;
    }

    .menu-toggle {
        display: block;
        order: 1;
    }

    .logo {
        order: 2;
        margin: 0 auto;
    }

    .nav-icons {
        order: 3;
    }

    .search-bar {
        order: 4;
        width: 100%;
        margin-top: 10px;
    }

    .main-nav {
        display: none;
        flex-direction: column;
        align-items: center;
    }

    .main-nav.active {
        display: flex;
    }

    .account-dropdown-content {
        position: fixed;
        top: 60px;
        right: 15px;
        width: calc(100% - 30px);
        max-width: 300px;
    }

    .slideshow-container {
        height: 300px;
    }

    .slideshow-slide {
        height: 300px;
    }

    .slideshow-content h2 {
        font-size: 22px;
    }

    .slideshow-content p {
        font-size: 14px;
    }

    .modal-product-grid {
        grid-template-columns: 1fr;
    }

    .modal-product-details {
        padding: 20px;
    }

    .modal-product-content {
        width: 95%;
    }

    .footer-columns {
        grid-template-columns: 1fr;
        gap: 30px;
    }

    .footer-bottom {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }

    .newsletter form {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .footer {
        padding: 30px 0 15px;
    }

    .footer-columns {
        gap: 25px;
    }

    .social-links {
        justify-content: center;
    }

    .payment-methods {
        justify-content: center;
    }
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

                    <!-- Updated Cart Button 
                    <a href="messagesUser.php"><i class="fas fa-envelope"></i></a>-->
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
            <a href="#" class="active">HOME</a>
            <!-- Categories from product.xml will be inserted here by JavaScript -->
        </nav>
    </header>

    <div class="slideshow-container">
        <div class="slideshow-slide active" style="background-image: url('images/slide2.jpg');">
            <div class="slideshow-content">
                <h2>Latest Smartphones 2025</h2>
                <p>Discover the newest flagship phones with cutting-edge technology</p>
                <a href="#" class="slideshow-btn">Shop Now</a>
            </div>
        </div>

        <div class="slideshow-slide" style="background-image: url('images/slide1.jpg');">
            <div class="slideshow-content">
                <h2>50% Off Gaming Gear</h2>
                <p>Limited time offer on premium gaming laptops, keyboards, and accessories</p>
                <a href="#" class="slideshow-btn">View Deals</a>
            </div>
        </div>

        <div class="slideshow-slide" style="background-image: url('images/slide3.jpg');">
            <div class="slideshow-content">
                <h2>Smart Home Collection</h2>
                <p>Transform your home with intelligent automation and IoT devices</p>
                <a href="#" class="slideshow-btn">Explore</a>
            </div>
        </div>
</div>

        <button class="slideshow-prev" onclick="changeSlide(-1)"><i class="fas fa-chevron-left"></i></button>
        <button class="slideshow-next" onclick="changeSlide(1)"><i class="fas fa-chevron-right"></i></button>

        <div class="slideshow-dots">
            <span class="slideshow-dot active" onclick="currentSlide(0)"></span>
            <span class="slideshow-dot" onclick="currentSlide(1)"></span>
            <span class="slideshow-dot" onclick="currentSlide(2)"></span>
        </div>
    </div>



    <!-- Product Section -->
    <div class="product-section" id="products">
        <div class="container">
            <h2>All Products</h2>
            <div id="results-count" class="results-count"></div>
            <div id="loading-status"></div>
            <div id="product-container"></div>

            <!-- Pagination -->
            <div class="pagination-container">
                <ul class="pagination" id="pagination">
                    <!-- Pagination will be generated by JavaScript -->
                </ul>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-columns">
                <div class="footer-column">
                    <h3>Customer Support</h3>
                    <ul>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Tech Support</a></li>
                        <li><a href="#">Warranty & Returns</a></li>
                        <li><a href="#">Product Manuals</a></li>
                        <li><a href="#">Live Chat</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>About TechHub</h3>
                    <ul>
                        <li><a href="#">Our Story</a></li>
                        <li><a href="#">Innovation Labs</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Press & Media</a></li>
                        <li><a href="#">Partner Program</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Connect With Us</h3>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                    <div class="newsletter">
                        <h4>Tech News & Updates</h4>
                        <form>
                            <input type="email" placeholder="Enter your email">
                            <button type="submit">Subscribe</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 TechHub. All Rights Reserved.</p>
                <div class="footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
                <div class="payment-methods">
                    <i class="fab fa-cc-visa"></i>
                    <i class="fab fa-cc-mastercard"></i>
                    <i class="fab fa-cc-paypal"></i>
                    <i class="fab fa-cc-amex"></i>
                    <i class="fab fa-cc-apple-pay"></i>
                </div>
            </div>
        </div>
    </footer>
    <script>
        // Global variables
let productData;
let products = [];
let loadingCompleted = false;
let currentPage = 1;
let productsPerPage = 6;
let totalPages = 1;
let filteredProducts = [];
let currentCategory = "all";
let searchQuery = "";

// Slideshow functionality
let slideIndex = 0;
let slideshowInterval;

// Initialize when the page loads
document.addEventListener("DOMContentLoaded", function () {
    // Load cart count first
    updateCartCountFromXML();

    // Initialize slideshow
    startSlideshow();

    // Initialize account dropdown
    initializeAccountDropdown();

    // Initialize mobile menu
    initializeMobileMenu();

    // Initialize search functionality
    initializeSearch();

    // Check if product.xml exists and load products
    checkAndLoadProducts();

    // Update cart count every 30 seconds
    setInterval(updateCartCountFromXML, 30000);
});

// Account dropdown functionality
function initializeAccountDropdown() {
    const accountBtn = document.getElementById('accountBtn');
    const accountDropdown = document.getElementById('accountDropdown');

    if (accountBtn) {
        accountBtn.addEventListener('click', function (e) {
            e.preventDefault();
            accountDropdown.classList.toggle('active');
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (accountDropdown && !accountDropdown.contains(e.target) && e.target !== accountBtn) {
            accountDropdown.classList.remove('active');
        }
    });
}

// Mobile menu functionality
function initializeMobileMenu() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mainNav = document.getElementById('mainNav');

    if (mobileMenuToggle && mainNav) {
        mobileMenuToggle.addEventListener('click', function () {
            mainNav.classList.toggle('active');
        });
    }
}

// Search functionality
function initializeSearch() {
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
        searchInput.addEventListener("input", function () {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                searchProducts();
            }, 300);
        });

        searchInput.addEventListener("keyup", function (event) {
            if (event.key === "Enter") {
                searchProducts();
            }
        });
    }
}

// Check for product.xml and load products
function checkAndLoadProducts() {
    fetch('product.xml', { method: 'HEAD' })
        .then(response => {
            if (response.ok) {
                console.log("product.xml file exists, loading products...");
                loadProductData();
            } else {
                console.error("product.xml file not found");
                document.getElementById("loading-status").textContent = "Product data file (product.xml) not found.";
            }
        })
        .catch(error => {
            console.error("Error checking for product.xml:", error);
            document.getElementById("loading-status").textContent = "Error checking for product data file.";
        });
}

// Slideshow functions
function startSlideshow() {
    slideshowInterval = setInterval(() => {
        changeSlide(1);
    }, 5000);
}

function changeSlide(n) {
    showSlide(slideIndex += n);
}

function currentSlide(n) {
    showSlide(slideIndex = n);
}

function showSlide(n) {
    const slides = document.getElementsByClassName("slideshow-slide");
    const dots = document.getElementsByClassName("slideshow-dot");

    if (n >= slides.length) { slideIndex = 0 }
    if (n < 0) { slideIndex = slides.length - 1 }

    for (let i = 0; i < slides.length; i++) {
        slides[i].classList.remove("active");
    }

    for (let i = 0; i < dots.length; i++) {
        dots[i].classList.remove("active");
    }

    slides[slideIndex].classList.add("active");
    dots[slideIndex].classList.add("active");

    clearInterval(slideshowInterval);
    startSlideshow();
}

// Function to load the XML data
function loadProductData() {
    document.getElementById("loading-status").textContent = "Loading products...";
    console.log("Attempting to load product data from product.xml");

    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (this.readyState == 4) {
            if (this.status == 200) {
                console.log("XML data loaded successfully");
                productData = this.responseXML;
                if (!productData) {
                    console.error("Failed to parse XML data");
                    document.getElementById("loading-status").textContent = "Error parsing product data. Please check the XML format.";
                    return;
                }
                parseProductData();
                setupFilters();
                applyFiltersAndSearch();
                loadingCompleted = true;
                document.getElementById("loading-status").textContent = "";
            } else {
                console.error("Failed to load XML data. Status:", this.status);
                document.getElementById("loading-status").textContent = "Failed to load products. Please check if product.xml exists.";
            }
        }
    };

    xhr.onerror = function () {
        console.error("Network error occurred while trying to fetch product.xml");
        document.getElementById("loading-status").textContent = "Network error. Please check your connection.";
    };

    xhr.open("GET", "product.xml", true);
    xhr.send();
}

// Parse XML into a more usable format
function parseProductData() {
    if (!productData) {
        console.error("No product data available to parse");
        return;
    }

    try {
        const productElements = productData.getElementsByTagName("product");
        console.log(`Found ${productElements.length} products in XML`);

        if (productElements.length === 0) {
            document.getElementById("loading-status").textContent = "No products found in the XML file.";
        }

        products = [];

        for (let i = 0; i < productElements.length; i++) {
            const product = productElements[i];
            const productObj = {
                id: getElementTextContent(product, "id"),
                name: getElementTextContent(product, "name"),
                category: getElementTextContent(product, "category"),
                price: getElementTextContent(product, "price"),
                description: getElementTextContent(product, "description"),
                image: getElementTextContent(product, "image"),
                stock: getElementTextContent(product, "stock"),
                rating: getElementTextContent(product, "rating") || "0",
                featured: getElementTextContent(product, "featured") === "true",
                on_sale: getElementTextContent(product, "on_sale") === "true"
            };

            const sizeElements = product.getElementsByTagName("size");
            productObj.sizes = [];
            for (let j = 0; j < sizeElements.length; j++) {
                productObj.sizes.push(sizeElements[j].textContent);
            }

            const colorElements = product.getElementsByTagName("color");
            productObj.colors = [];
            for (let j = 0; j < colorElements.length; j++) {
                productObj.colors.push(colorElements[j].textContent);
            }

            products.push(productObj);
        }

        console.log("Products loaded:", products.length);
    } catch (error) {
        console.error("Error parsing product data:", error);
        document.getElementById("loading-status").textContent = "Error processing product data.";
    }
}

// Helper function to get text content of an element
function getElementTextContent(parent, tagName) {
    const elements = parent.getElementsByTagName(tagName);
    if (elements.length > 0) {
        return elements[0].textContent;
    }
    return "";
}

// Function to set up the category filters - THIS IS THE KEY FUNCTION FOR DYNAMIC NAV
function setupFilters() {
    try {
        // Extract unique categories from products
        const categories = ["all"];
        products.forEach(product => {
            if (product.category && !categories.includes(product.category)) {
                categories.push(product.category);
            }
        });

        console.log("Available categories:", categories);

        // Get the main navigation element
        const mainNav = document.getElementById("mainNav");
        if (!mainNav) {
            console.error("Main navigation element not found");
            return;
        }

        // Clear existing navigation
        mainNav.innerHTML = "";

        // Add HOME link first
        const homeLink = document.createElement("a");
        homeLink.href = "#products";
        homeLink.textContent = "HOME";
        homeLink.classList.add("active");
        homeLink.addEventListener("click", function (e) {
            e.preventDefault();
            document.querySelectorAll("#mainNav a").forEach(link => {
                link.classList.remove("active");
            });
            this.classList.add("active");
            currentCategory = "all";
            currentPage = 1;
            applyFiltersAndSearch();
            // Smooth scroll to products section
            document.getElementById('products').scrollIntoView({ 
                behavior: 'smooth' 
            });
        });
        mainNav.appendChild(homeLink);

        // Add category links dynamically from XML
        categories.forEach(category => {
            if (category === "all") return; // Skip "all" as we already added HOME

            const categoryLink = document.createElement("a");
            categoryLink.href = "#products";
            categoryLink.textContent = category.toUpperCase();
            categoryLink.dataset.category = category;

            categoryLink.addEventListener("click", function (e) {
                e.preventDefault();

                // Remove active class from all nav links
                document.querySelectorAll("#mainNav a").forEach(link => {
                    link.classList.remove("active");
                });

                // Add active class to clicked link
                this.classList.add("active");

                // Set current category and reset page
                currentCategory = category;
                currentPage = 1;
                
                // Apply filters and display products
                applyFiltersAndSearch();
                
                // Smooth scroll to products section
                document.getElementById('products').scrollIntoView({ 
                    behavior: 'smooth' 
                });
            });

            mainNav.appendChild(categoryLink);
        });
    } catch (error) {
        console.error("Error setting up filters:", error);
    }
}

// Function to search products
function searchProducts() {
    const searchInput = document.getElementById("searchInput");
    searchQuery = searchInput.value.trim().toLowerCase();
    currentPage = 1;
    applyFiltersAndSearch();
    
    // Scroll to products section when searching
    document.getElementById('products').scrollIntoView({ 
        behavior: 'smooth' 
    });
}

// Function to apply filters and search
function applyFiltersAndSearch() {
    try {
        // Start with all products
        filteredProducts = [...products];

        // Apply category filter if not "all"
        if (currentCategory !== "all") {
            filteredProducts = filteredProducts.filter(product =>
                product.category && product.category.toLowerCase() === currentCategory.toLowerCase()
            );
        }

        // Apply search filter if there's a search query
        if (searchQuery) {
            filteredProducts = filteredProducts.filter(product =>
                (product.name && product.name.toLowerCase().includes(searchQuery)) ||
                (product.description && product.description.toLowerCase().includes(searchQuery)) ||
                (product.category && product.category.toLowerCase().includes(searchQuery))
            );
        }

        // Calculate total pages
        totalPages = Math.ceil(filteredProducts.length / productsPerPage);
        if (totalPages === 0) totalPages = 1;

        // Update section label
        updateSectionLabel();

        // Update results count
        updateResultsCount();

        // Display filtered products for current page
        displayFilteredProducts();

        // Update pagination
        updatePagination();
    } catch (error) {
        console.error("Error applying filters:", error);
        document.getElementById("loading-status").textContent = "Error filtering products.";
    }
}

// Function to update section label based on current category
function updateSectionLabel() {
    const sectionTitle = document.querySelector('.product-section h2');
    if (sectionTitle) {
        if (currentCategory === "all") {
            sectionTitle.textContent = "All Products";
        } else {
            sectionTitle.textContent = currentCategory.charAt(0).toUpperCase() + currentCategory.slice(1) + " Products";
        }
    }
}

// Function to update results count
function updateResultsCount() {
    const resultsCountElem = document.getElementById("results-count");
    if (resultsCountElem) {
        resultsCountElem.textContent = `${filteredProducts.length} products found`;
    }
}

// Function to display filtered products
function displayFilteredProducts() {
    const productContainer = document.getElementById("product-container");
    if (!productContainer) {
        console.error("Product container element not found");
        return;
    }

    productContainer.innerHTML = "";

    if (filteredProducts.length === 0) {
        const noProducts = document.createElement("div");
        noProducts.className = "no-products";
        noProducts.textContent = "No products found for your search. Try different keywords or filters.";
        productContainer.appendChild(noProducts);
        return;
    }

    const startIndex = (currentPage - 1) * productsPerPage;
    const endIndex = Math.min(startIndex + productsPerPage, filteredProducts.length);
    const currentPageProducts = filteredProducts.slice(startIndex, endIndex);

    currentPageProducts.forEach(product => {
        const productCard = createProductCard(product);
        productContainer.appendChild(productCard);
    });
}

// Function to create product card
function createProductCard(product) {
    const card = document.createElement("div");
    card.className = "product-card";
    card.dataset.productId = product.id;

    const imgContainer = document.createElement("div");
    imgContainer.className = "product-image";

    const img = document.createElement("img");
    img.src = product.image || "placeholder.jpg";
    img.alt = product.name;
    imgContainer.appendChild(img);

    if (product.on_sale) {
        const saleTag = document.createElement("span");
        saleTag.className = "sale-tag";
        saleTag.textContent = "SALE";
        imgContainer.appendChild(saleTag);
    }

    const info = document.createElement("div");
    info.className = "product-info";

    const name = document.createElement("h3");
    name.textContent = product.name;

    const category = document.createElement("p");
    category.className = "product-category";
    category.textContent = product.category;

    const ratingDiv = document.createElement("div");
    ratingDiv.className = "product-rating";

    const rating = parseFloat(product.rating);
    for (let i = 1; i <= 5; i++) {
        const star = document.createElement("i");
        if (i <= Math.floor(rating)) {
            star.className = "fas fa-star";
        } else if (i - 0.5 <= rating) {
            star.className = "fas fa-star-half-alt";
        } else {
            star.className = "far fa-star";
        }
        ratingDiv.appendChild(star);
    }

    const ratingText = document.createElement("span");
    ratingText.textContent = `(${product.rating})`;
    ratingDiv.appendChild(ratingText);

    const price = document.createElement("p");
    price.className = "product-price";
    price.textContent = `₱${parseFloat(product.price).toFixed(2)}`;

    const stock = document.createElement("p");
    stock.className = "product-stock";
    stock.textContent = `Stock: ${product.stock}`;

    const addToCartBtn = document.createElement("button");
    addToCartBtn.className = "add-to-cart-btn";
    addToCartBtn.textContent = "Add to Cart";
    addToCartBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        addToCart(product.id);
    });

    card.appendChild(imgContainer);
    info.appendChild(name);
    info.appendChild(price);
    info.appendChild(category);
    info.appendChild(ratingDiv);
    info.appendChild(stock);
    info.appendChild(addToCartBtn);
    card.appendChild(info);

    card.addEventListener("click", () => showProductDetails(product.id));

    return card;
}

// Function to update pagination
function updatePagination() {
    const paginationContainer = document.getElementById("pagination");
    if (!paginationContainer) {
        console.error("Pagination container not found");
        return;
    }

    paginationContainer.innerHTML = "";

    if (totalPages <= 1) {
        return;
    }

    // Previous page button
    const prevLi = document.createElement("li");
    const prevLink = document.createElement("a");
    prevLink.href = "#";
    prevLink.className = "prev-page";
    prevLink.innerHTML = '<i class="fas fa-chevron-left"></i> Prev';

    if (currentPage === 1) {
        prevLink.classList.add("disabled");
    } else {
        prevLink.addEventListener("click", function (e) {
            e.preventDefault();
            if (currentPage > 1) {
                currentPage--;
                applyFiltersAndSearch();
            }
        });
    }

    prevLi.appendChild(prevLink);
    paginationContainer.appendChild(prevLi);

    // Page numbers
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        const pageLi = document.createElement("li");
        const pageLink = document.createElement("a");
        pageLink.href = "#";
        pageLink.textContent = i;

        if (i === currentPage) {
            pageLink.classList.add("active");
        }

        pageLink.addEventListener("click", function (e) {
            e.preventDefault();
            currentPage = i;
            applyFiltersAndSearch();
        });

        pageLi.appendChild(pageLink);
        paginationContainer.appendChild(pageLi);
    }

    // Next page button
    const nextLi = document.createElement("li");
    const nextLink = document.createElement("a");
    nextLink.href = "#";
    nextLink.className = "next-page";
    nextLink.innerHTML = 'Next <i class="fas fa-chevron-right"></i>';

    if (currentPage === totalPages) {
        nextLink.classList.add("disabled");
    } else {
        nextLink.addEventListener("click", function (e) {
            e.preventDefault();
            if (currentPage < totalPages) {
                currentPage++;
                applyFiltersAndSearch();
            }
        });
    }

    nextLi.appendChild(nextLink);
    paginationContainer.appendChild(nextLi);
}

// Function to show product details modal
function showProductDetails(productId) {
    const product = products.find(p => p.id === productId);
    if (!product) {
        console.error(`Product with ID ${productId} not found`);
        return;
    }

    const modalOverlay = document.createElement('div');
    modalOverlay.className = 'product-modal-overlay';
    modalOverlay.id = 'product-modal';

    const modalContent = document.createElement('div');
    modalContent.className = 'product-modal-content';

    const closeButton = document.createElement('button');
    closeButton.className = 'modal-close';
    closeButton.innerHTML = '&times;';
    closeButton.addEventListener('click', closeProductModal);

    const productGrid = document.createElement('div');
    productGrid.className = 'modal-product-grid';

    const imageSection = document.createElement('div');
    imageSection.className = 'modal-product-image';

    const productImage = document.createElement('img');
    productImage.src = product.image || 'placeholder.jpg';
    productImage.alt = product.name;
    imageSection.appendChild(productImage);

    const detailsSection = document.createElement('div');
    detailsSection.className = 'modal-product-details';

    const title = document.createElement('h2');
    title.className = 'modal-product-title';
    title.textContent = product.name;

    const price = document.createElement('p');
    price.className = 'modal-product-price';
    price.textContent = `₱${parseFloat(product.price).toFixed(2)}`;

    const rating = document.createElement('div');
    rating.className = 'modal-product-rating';

    const starsDiv = document.createElement('div');
    starsDiv.className = 'stars';

    const ratingValue = parseFloat(product.rating);
    for (let i = 1; i <= 5; i++) {
        const star = document.createElement('i');
        if (i <= Math.floor(ratingValue)) {
            star.className = 'fas fa-star';
        } else if (i - 0.5 <= ratingValue) {
            star.className = 'fas fa-star-half-alt';
        } else {
            star.className = 'far fa-star';
        }
        starsDiv.appendChild(star);
    }

    const ratingText = document.createElement('span');
    ratingText.className = 'rating-text';
    ratingText.textContent = ` (${product.rating}) ratings`;
    starsDiv.appendChild(ratingText);
    rating.appendChild(starsDiv);

    const description = document.createElement('div');
    description.className = 'modal-product-description';
    description.textContent = product.description;

    const sizesSection = document.createElement('div');
    sizesSection.className = 'modal-product-size';

    if (product.sizes && product.sizes.length > 0) {
        const sizeLabel = document.createElement('label');
        sizeLabel.textContent = 'Size:';
        sizesSection.appendChild(sizeLabel);

        const sizeOptions = document.createElement('div');
        sizeOptions.className = 'size-options';

        product.sizes.forEach((size, index) => {
            const sizeBtn = document.createElement('button');
            sizeBtn.className = 'size-btn';
            if (index === 0) sizeBtn.classList.add('active');
            sizeBtn.textContent = size;
            sizeBtn.addEventListener('click', function () {
                document.querySelectorAll('.size-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
            });
            sizeOptions.appendChild(sizeBtn);
        });

        sizesSection.appendChild(sizeOptions);
    }

    const colorsSection = document.createElement('div');
    colorsSection.className = 'modal-product-color';

    if (product.colors && product.colors.length > 0) {
        const colorLabel = document.createElement('label');
        colorLabel.textContent = 'Color:';
        colorsSection.appendChild(colorLabel);

        const colorOptions = document.createElement('div');
        colorOptions.className = 'color-options';

        product.colors.forEach((color, index) => {
            const colorBtn = document.createElement('div');
            colorBtn.className = 'color-btn';
            colorBtn.style.backgroundColor = color;
            if (index === 0) colorBtn.classList.add('active');
            colorBtn.addEventListener('click', function () {
                document.querySelectorAll('.color-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
            });
            colorOptions.appendChild(colorBtn);
        });

        colorsSection.appendChild(colorOptions);
    }

    const quantitySection = document.createElement('div');
    quantitySection.className = 'modal-product-quantity';

    const quantityLabel = document.createElement('label');
    quantityLabel.textContent = 'Quantity:';

    const quantityControl = document.createElement('div');
    quantityControl.className = 'quantity-control';

    const decreaseBtn = document.createElement('button');
    decreaseBtn.className = 'quantity-btn';
    decreaseBtn.textContent = '-';
    decreaseBtn.addEventListener('click', function () {
        const input = this.nextElementSibling;
        let value = parseInt(input.value);
        if (value > 1) {
            input.value = value - 1;
        }
    });

    const quantityInput = document.createElement('input');
    quantityInput.type = 'text';
    quantityInput.className = 'quantity-input';
    quantityInput.value = '1';
    quantityInput.min = '1';
    quantityInput.addEventListener('change', function () {
        if (this.value < 1 || isNaN(this.value)) {
            this.value = 1;
        }
    });

    const increaseBtn = document.createElement('button');
    increaseBtn.className = 'quantity-btn';
    increaseBtn.textContent = '+';
    increaseBtn.addEventListener('click', function () {
        const input = this.previousElementSibling;
        let value = parseInt(input.value);
        const stock = parseInt(product.stock);
        if (value < stock) {
            input.value = value + 1;
        } else {
            alert(`Sorry, only ${stock} items in stock.`);
        }
    });

    quantityControl.appendChild(decreaseBtn);
    quantityControl.appendChild(quantityInput);
    quantityControl.appendChild(increaseBtn);

    quantitySection.appendChild(quantityLabel);
    quantitySection.appendChild(quantityControl);

    const stockInfo = document.createElement('p');
    stockInfo.className = 'stock-info';
    stockInfo.textContent = `In Stock: ${product.stock} items`;

    const addToCartBtn = document.createElement('button');
    addToCartBtn.className = 'modal-add-to-cart';
    addToCartBtn.textContent = 'Add to Cart';
    addToCartBtn.addEventListener('click', function () {
        const selectedSize = document.querySelector('.size-btn.active')?.textContent || '';
        const selectedColor = document.querySelector('.color-btn.active')?.style.backgroundColor || '';
        const quantity = parseInt(quantityInput.value);

        addToCartFromModal(product.id, selectedSize, selectedColor, quantity);
        showAddToCartConfirmation(product.name);
    });

    detailsSection.appendChild(title);
    detailsSection.appendChild(price);
    detailsSection.appendChild(rating);
    detailsSection.appendChild(description);
    detailsSection.appendChild(sizesSection);
    detailsSection.appendChild(colorsSection);
    detailsSection.appendChild(quantitySection);
    detailsSection.appendChild(stockInfo);
    detailsSection.appendChild(addToCartBtn);

    productGrid.appendChild(imageSection);
    productGrid.appendChild(detailsSection);

    modalContent.appendChild(closeButton);
    modalContent.appendChild(productGrid);
    modalOverlay.appendChild(modalContent);

    document.body.appendChild(modalOverlay);
    document.body.classList.add('modal-open');
    modalOverlay.style.display = 'flex';

    modalOverlay.addEventListener('click', function (e) {
        if (e.target === modalOverlay) {
            closeProductModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeProductModal();
        }
    });
}

// Function to close product modal
function closeProductModal() {
    const modalOverlay = document.getElementById('product-modal');
    if (modalOverlay) {
        modalOverlay.style.display = 'none';
        document.body.classList.remove('modal-open');
        modalOverlay.remove();
    }
}

// Function to add to cart from modal
function addToCartFromModal(productId, size, color, quantity) {
    console.log(`Adding to cart: Product ID ${productId}, Size: ${size}, Color: ${color}, Quantity: ${quantity}`);

    // Immediately increment cart count for better UX
    const cartCount = document.getElementById("cartCount");
    if (cartCount) {
        const currentCount = parseInt(cartCount.textContent) || 0;
        cartCount.textContent = currentCount + quantity;
    }

    sendItemToCartXML({
        productId: productId,
        productName: products.find(p => p.id === productId).name,
        image: products.find(p => p.id === productId).image,
        price: parseFloat(products.find(p => p.id === productId).price),
        size: size,
        color: color,
        quantity: quantity
    });
}

// Function to add product to cart
function addToCart(productId) {
    const product = products.find(p => p.id === productId);
    if (!product) {
        console.error(`Product with ID ${productId} not found`);
        return;
    }

    const cartItem = {
        productId: productId,
        productName: product.name,
        image: product.image,
        price: parseFloat(product.price),
        size: product.sizes && product.sizes.length > 0 ? product.sizes[0] : '',
        color: product.colors && product.colors.length > 0 ? product.colors[0] : '',
        quantity: 1
    };

    // Immediately increment cart count for better UX
    const cartCount = document.getElementById("cartCount");
    if (cartCount) {
        const currentCount = parseInt(cartCount.textContent) || 0;
        cartCount.textContent = currentCount + 1;
    }

    sendItemToCartXML(cartItem);
    showAddToCartConfirmation(product.name);
}

// Function to send item to cart XML
function sendItemToCartXML(item) {
    fetch('add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            productId: item.productId,
            productName: item.productName,
            image: item.image,
            price: item.price,
            size: item.size,
            color: item.color,
            quantity: item.quantity
        })
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(data => {
            console.log('Server says:', data);
            updateCartCountFromXML();
        })
        .catch(error => {
            console.error('Error saving to XML:', error);
            updateCartCountFromXML();
        });
}

// Function to update cart count from cart.xml
function updateCartCountFromXML() {
    fetch('get_cart_count.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch cart count');
            }
            return response.json();
        })
        .then(data => {
            const cartCount = document.getElementById("cartCount");
            if (cartCount) {
                if (data.count !== undefined) {
                    cartCount.textContent = data.count;
                    console.log('Cart count updated to:', data.count);
                } else {
                    console.error('Cart count data is undefined');
                    cartCount.textContent = '0';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching cart count:', error);
            const cartCount = document.getElementById("cartCount");
            if (cartCount) {
                cartCount.textContent = '0';
            }
        });
}

// Function to show add to cart confirmation
function showAddToCartConfirmation(productName) {
    const confirmation = document.createElement('div');
    confirmation.className = 'add-to-cart-confirmation';

    const confirmationContent = document.createElement('div');
    confirmationContent.className = 'confirmation-content';

    const icon = document.createElement('i');
    icon.className = 'fas fa-check-circle';

    const message = document.createElement('p');
    message.textContent = `${productName} added to cart successfully!`;

    confirmationContent.appendChild(icon);
    confirmationContent.appendChild(message);
    confirmation.appendChild(confirmationContent);

    document.body.appendChild(confirmation);

    setTimeout(() => {
        confirmation.classList.add('fade-out');
        setTimeout(() => {
            confirmation.remove();
        }, 500);
    }, 3000);
}
    </script>
</body>
</html>
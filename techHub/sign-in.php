<?php
// Start session
session_start();
include 'db_connect.php';

// Initialize variables
$error = '';
$username_email = '';

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_email = trim($_POST['username_email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']) ? true : false;

    // Validate inputs
    if (empty($username_email) || empty($password)) {
        $error = "Both username/email and password are required";
    } else {
        // Check if it's a user first
        $sql = "SELECT id, username, email, password, fullname, is_active FROM users 
                WHERE (username = ? OR email = ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username_email, $username_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            // User found
            $user = $result->fetch_assoc();

            if ($user['is_active'] == 0) {
                $error = "Your account is not active. Please verify your email.";
            } else {
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['fullname'] = $user['fullname'];
                    $_SESSION['user_type'] = 'user';

                    // Set cookies if remember me is checked
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        $sql = "UPDATE users SET remember_token = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $token, $user['id']);
                        $stmt->execute();

                        setcookie("techHub_token", $token, time() + (86400 * 30), "/");
                        setcookie("techHub_user_id", $user['id'], time() + (86400 * 30), "/");
                    }

                    // Update last login
                    $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();

                    header("Location: usershop.php");
                    exit();
                } else {
                    $error = "Invalid password";
                }
            }
        } else {
            // No user found, check admin
            $sql = "SELECT admin_id, username, email, password, fullname, role, is_active FROM admins 
                    WHERE (username = ? OR email = ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $username_email, $username_email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $admin = $result->fetch_assoc();

                if ($admin['is_active'] == 0) {
                    $error = "This admin account is inactive. Please contact super admin.";
                } else {
                    // ✅ MD5 check for admin passwords
                    if (md5($password) === $admin['password']) {
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_fullname'] = $admin['fullname'];
                        $_SESSION['admin_role'] = $admin['role'];
                        $_SESSION['user_type'] = 'admin';

                        if ($remember_me) {
                            $token = bin2hex(random_bytes(32));
                            $sql = "UPDATE admins SET remember_token = ? WHERE admin_id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("si", $token, $admin['admin_id']);
                            $stmt->execute();

                            setcookie("techHub_admin_token", $token, time() + (86400 * 30), "/");
                            setcookie("techHub_admin_id", $admin['admin_id'], time() + (86400 * 30), "/");
                        }

                        $sql = "UPDATE admins SET last_login = NOW() WHERE admin_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $admin['admin_id']);
                        $stmt->execute();

                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Invalid password";
                    }
                }
            } else {
                $error = "No account found with this username or email";
            }
        }
    }
}

// Check for remembered login
function checkRememberedLogin($conn) {
    if (isset($_COOKIE['techHub_token']) && isset($_COOKIE['techHub_user_id'])) {
        $token = $_COOKIE['techHub_token'];
        $user_id = $_COOKIE['techHub_user_id'];

        $sql = "SELECT id, username, fullname FROM users 
                WHERE id = ? AND remember_token = ? AND is_active = TRUE";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['user_type'] = 'user';

            $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();

            header("Location: usershop.php");
            exit();
        }
    }

    if (isset($_COOKIE['techHub_admin_token']) && isset($_COOKIE['techHub_admin_id'])) {
        $token = $_COOKIE['techHub_admin_token'];
        $admin_id = $_COOKIE['techHub_admin_id'];

        $sql = "SELECT admin_id, username, fullname, role FROM admins 
                WHERE admin_id = ? AND remember_token = ? AND is_active = TRUE";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $admin_id, $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();

            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_fullname'] = $admin['fullname'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['user_type'] = 'admin';

            $sql = "UPDATE admins SET last_login = NOW() WHERE admin_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $admin['admin_id']);
            $stmt->execute();

            header("Location: dashboard.php");
            exit();
        }
    }
}

// Already logged in?
if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
    if ($_SESSION['user_type'] == 'user') {
        header("Location: usershop.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
} else {
    checkRememberedLogin($conn);
}
?>

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - TechHub</title>
    <link rel="icon" href="images/techhub-icon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #0a0e1a 0%, #1a2332 100%);
            color: #ccd6f6;
            line-height: 1.6;
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* Header */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(10, 14, 26, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(0, 212, 255, 0.2);
        }

        nav {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #00d4ff;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
        }

        .glow {
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from {
                text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
            }
            to {
                text-shadow: 0 0 20px rgba(0, 212, 255, 0.8), 0 0 30px rgba(0, 212, 255, 0.6);
            }
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            color: #ccd6f6;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-links a:hover {
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background: linear-gradient(90deg, #00d4ff, #0099cc);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .sign-in-btn, .sign-up-btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .sign-in-btn {
            background: transparent;
            color: #ccd6f6;
            border: 2px solid #00d4ff;
        }

        .sign-in-btn:hover {
            background: #00d4ff;
            color: #0a0e1a;
            box-shadow: 0 5px 15px rgba(0, 212, 255, 0.3);
        }

        .sign-up-btn {
            background: transparent;
            color: #ccd6f6;
            border: 2px solid #00d4ff;
        }

        .sign-up-btn:hover {
            background: #00d4ff;
            color: #0a0e1a;
            box-shadow: 0 5px 15px rgba(0, 212, 255, 0.3);
        }

        /* Main Content */
        .main-content {
            padding-top: 120px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .signin-container {
            background: rgba(26, 35, 50, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            margin: 0 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .page-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: #00d4ff;
            margin-bottom: 30px;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #ccd6f6;
            font-size: 15px;
        }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            background: rgba(10, 14, 26, 0.6);
            border: 2px solid rgba(0, 212, 255, 0.3);
            border-radius: 10px;
            font-size: 15px;
            color: #ccd6f6;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.2);
            background: rgba(10, 14, 26, 0.8);
        }

        .form-control::placeholder {
            color: rgba(204, 214, 246, 0.5);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #00d4ff;
        }

        .checkbox-group label {
            margin-bottom: 0;
            font-size: 14px;
            color: #ccd6f6;
        }

        .forgot-password {
            text-align: right;
            margin-bottom: 25px;
        }

        .forgot-password a {
            color: #00d4ff;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .forgot-password a:hover {
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }

        .btn-signin {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #00d4ff, #0099cc);
            color: #0a0e1a;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.4);
        }

        .social-signin {
            margin-top: 30px;
            text-align: center;
        }

        .social-signin p {
            position: relative;
            margin-bottom: 20px;
            color: rgba(204, 214, 246, 0.7);
            font-size: 14px;
        }

        .social-signin p:before,
        .social-signin p:after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0, 212, 255, 0.3), transparent);
        }

        .social-signin p:before {
            left: 0;
        }

        .social-signin p:after {
            right: 0;
        }

        .social-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(0, 212, 255, 0.1);
            border: 2px solid rgba(0, 212, 255, 0.3);
            color: #00d4ff;
            font-size: 18px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .social-btn:hover {
            background: rgba(0, 212, 255, 0.2);
            border-color: #00d4ff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 212, 255, 0.3);
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            font-size: 15px;
            color: #ccd6f6;
        }

        .register-link a {
            color: #00d4ff;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .register-link a:hover {
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }

        .error-message {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            nav {
                padding: 0 1rem;
                flex-wrap: wrap;
            }

            .logo {
                font-size: 1.5rem;
            }

            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 1rem;
                margin-top: 1rem;
            }

            .nav-links.active {
                display: flex;
            }

            .auth-buttons {
                gap: 0.5rem;
            }

            .sign-in-btn, .sign-up-btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }

            .signin-container {
                padding: 30px 20px;
                margin: 0 10px;
            }

            .page-title {
                font-size: 2rem;
            }

            .main-content {
                padding-top: 100px;
            }

            .social-buttons {
                gap: 10px;
            }

            .social-btn {
                width: 45px;
                height: 45px;
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .signin-container {
                padding: 25px 15px;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .form-control {
                padding: 12px 15px;
            }

            .btn-signin {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <nav>
            <div class="logo glow">TechHub</div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <!-- <li><a href="#products">Products</a></li> -->
                <li><a href="index.php#about">About</a></li>
                <li><a href="index.php#contact">Contact</a></li>
            </ul>
            <div class="auth-buttons">
            <a href="sign-in.php" class="sign-in-btn">Sign In</a>
                <a href="sign-up.php" class="sign-up-btn">Sign Up</a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="signin-container">
            <h1 class="page-title">Sign In</h1>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form id="signinForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="username_email">Username or Email Address</label>
                    <input type="text" id="username_email" name="username_email" class="form-control" 
                           placeholder="Enter your username or email" required
                           value="<?php echo htmlspecialchars($username_email); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me">Remember me</label>
                </div>
                
                <div class="forgot-password">
                    <a href="forgot-password.php">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn-signin">SIGN IN</button>
                
                <div class="social-signin">
                    <p>Or sign in with</p>
                    <div class="social-buttons">
                        <a href="#" class="social-btn"><i class="fab fa-google"></i></a>
                        <a href="#" class="social-btn"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-btn"><i class="fab fa-apple"></i></a>
                    </div>
                </div>
                
                <div class="register-link">
                    Don't have an account? <a href="sign-up.php">Create one now</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mobile menu toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add mobile menu toggle button if needed
            const nav = document.querySelector('nav');
            const navLinks = document.querySelector('.nav-links');
            
            // Create mobile menu button
            const mobileMenuBtn = document.createElement('button');
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            mobileMenuBtn.className = 'mobile-menu-btn';
            mobileMenuBtn.style.cssText = `
                display: none;
                background: none;
                border: none;
                color: #00d4ff;
                font-size: 1.2rem;
                cursor: pointer;
                padding: 0.5rem;
            `;
            
            // Add mobile styles
            const style = document.createElement('style');
            style.textContent = `
                @media (max-width: 768px) {
                    .mobile-menu-btn {
                        display: block !important;
                    }
                    .nav-links {
                        position: absolute;
                        top: 100%;
                        left: 0;
                        width: 100%;
                        background: rgba(10, 14, 26, 0.95);
                        backdrop-filter: blur(10px);
                        border-top: 1px solid rgba(0, 212, 255, 0.2);
                        padding: 1rem 0;
                    }
                }
            `;
            document.head.appendChild(style);
            
            nav.insertBefore(mobileMenuBtn, nav.children[2]);
            
            mobileMenuBtn.addEventListener('click', function() {
                navLinks.classList.toggle('active');
            });
        });
    </script>
</body>
</html>
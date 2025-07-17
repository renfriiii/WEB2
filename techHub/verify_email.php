<?php
session_start();

// Include your environment-aware database connection
include 'db_connect.php';

// Initialize variables
$error = '';
$username_email = '';

// Check if user has registration email in session
if (!isset($_SESSION['registration_email']) || !isset($_SESSION['otp_purpose'])) {
    header("Location: sign.php");
    exit();
}

$email = $_SESSION['registration_email'];
$otp_purpose = $_SESSION['otp_purpose'];
$verification_message = "";
$is_verified = false;





// Process OTP verification
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and combine OTP digits
    $otp_digits = array();
    for ($i = 1; $i <= 6; $i++) {
        if (isset($_POST["otp_digit_$i"])) {
            $otp_digits[] = $_POST["otp_digit_$i"];
        } else {
            $verification_message = "Please enter all OTP digits.";
            break;
        }
    }

    if (count($otp_digits) == 6) {
        $entered_otp = implode("", $otp_digits);

        // Validate OTP in database
        $stmt = $conn->prepare("SELECT id, otp_expires_at FROM users WHERE email = ? AND otp_code = ? AND otp_purpose = ? AND otp_is_used = 0");
        $stmt->bind_param("sss", $email, $entered_otp, $otp_purpose);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Check if OTP has expired
            $current_time = date('Y-m-d H:i:s');
            $otp_expires_at = $user['otp_expires_at'];

            if ($current_time > $otp_expires_at) {
                $verification_message = "The verification code has expired. Please request a new one.";
            } else {
                // Update user as verified and mark OTP as used
                $update_stmt = $conn->prepare("UPDATE users SET is_active = 1, otp_is_used = 1 WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);

                if ($update_stmt->execute()) {
                    $is_verified = true;
                    $verification_message = "Email verified successfully! You can now login to your account.";

                    // Clear verification session variables
                    unset($_SESSION['registration_email']);
                    unset($_SESSION['otp_purpose']);
                } else {
                    $verification_message = "Error updating verification status. Please try again.";
                }

                $update_stmt->close();
            }
        } else {
            $verification_message = "Invalid verification code. Please try again.";
        }

        $stmt->close();
    }
}

// Handle resend OTP
if (isset($_POST['resend_otp'])) {
    // Generate new OTP
    $new_otp = sprintf("%06d", rand(0, 999999));
    $current_time = date('Y-m-d H:i:s');
    $otp_expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Update OTP in database
    $update_stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_created_at = ?, otp_expires_at = ?, otp_is_used = 0 WHERE email = ? AND otp_purpose = ?");
    $otp_is_used = 0;
    $update_stmt->bind_param("sssss", $new_otp, $current_time, $otp_expires_at, $email, $otp_purpose);

    if ($update_stmt->execute()) {
        // Get user's name for the email
        $name_stmt = $conn->prepare("SELECT fullname FROM users WHERE email = ?");
        $name_stmt->bind_param("s", $email);
        $name_stmt->execute();
        $name_result = $name_stmt->get_result();
        $user_data = $name_result->fetch_assoc();
        $fullname = $user_data['fullname'];
        $name_stmt->close();

        // Send new OTP
        if (sendVerificationEmail($email, $new_otp, $fullname)) {
            $verification_message = "A new verification code has been sent to your email.";
        } else {
            $verification_message = "Error sending verification email. Please try again.";
        }
    } else {
        $verification_message = "Error generating new verification code. Please try again.";
    }

    $update_stmt->close();
}

mysqli_close($conn);

// Function to send verification email
function sendVerificationEmail($email, $otp_code, $fullname)
{
    require 'C:\xampp\htdocs\PHPMailer\PHPMailer\src\Exception.php';
    require 'C:\xampp\htdocs\PHPMailer\PHPMailer\src\PHPMailer.php';
    require 'C:\xampp\htdocs\PHPMailer\PHPMailer\src\SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aliprimebank@gmail.com';
        $mail->Password = 'kodk kzue xsae fdsg';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('no-reply@TechHub.com', 'TechHub');
        $mail->addAddress($email);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your TechHub Account';
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <table style='width: 100%; padding: 20px; background-color: #f4f4f4;'>
                    <tr>
                        <td style='text-align: center;'>
                            <h2 style='color: #0071c5;'>Welcome to TechHub, {$fullname}!</h2>
                            <p>Thank you for registering with us. To complete your registration, please use the verification code below:</p>
                            <h1 style='color: #0071c5; font-size: 36px; letter-spacing: 4px;'>{$otp_code}</h1>
                            <p>This code will expire in <strong>15 minutes</strong>.</p>
                            <p>You must verify your email to activate your account and enjoy full access to TechHub's features.</p>
                            <p>If you did not register for a TechHub account, you can safely ignore this email.</p>
                            <br>
                            <p style='font-size: 14px;'>Best regards,<br>TechHub Team</p>
                            <hr style='border: 0; border-top: 1px solid #ddd; margin: 20px 0;'>
                            <p style='font-size: 12px; color: #888;'>This is an automated message. Please do not reply directly to this email.</p>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
        ";
        $mail->AltBody = "Hello {$fullname}, Your verification code for TechHub registration is: {$otp_code}. This code will expire in 15 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - TechHub</title>
    <link rel="icon" href="images/hf.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        /* :root {
            --primary: #111111;
            --secondary: #0071c5;
            --accent: #e5e5e5;
            --light: #ffffff;
            --dark: #111111;
            --grey: #767676;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        } */
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

        /* Account dropdown styling */
        .account-dropdown {
            position: relative;
            display: inline-block;
        }

        .account-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            z-index: 1;
            border-radius: 4px;
            margin-top: 10px;
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
            border-bottom: 8px solid white;
        }

        .account-dropdown-content a {
            color: var(--dark);
            padding: 12px 20px;
            text-decoration: none;
            display: block;
            font-size: 14px;
            font-weight: 400;
            margin: 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .account-dropdown-content a:last-child {
            border-bottom: none;
        }

        .account-dropdown-content a:hover {
            background-color: #f8f9fa;
            color: var(--secondary);
        }

        .account-dropdown-content h3 {
            padding: 12px 20px;
            margin: 0;
            font-size: 14px;
            color: var(--grey);
            background-color: #f8f9fa;
            border-bottom: 1px solid #f0f0f0;
            border-radius: 4px 4px 0 0;
            font-weight: 500;
        }

        .account-dropdown.active .account-dropdown-content {
            display: block;
        }

        /* Navigation Styles */
        .main-nav {
            display: flex;
            justify-content: center;
            background-color: var(--light);
            border-bottom: 1px solid #f0f0f0;
        }

        .main-nav a {
            padding: 15px 20px;
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            position: relative;
        }

        .main-nav a:hover,
        .main-nav a.active {
            color: var(--secondary);
        }

        .main-nav a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            background: var(--secondary);
            left: 50%;
            bottom: 10px;
            transition: all 0.2s ease;
            transform: translateX(-50%);
        }

        .main-nav a:hover:after,
        .main-nav a.active:after {
            width: 60%;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--primary);
            font-size: 22px;
            cursor: pointer;
        }

        /* Email Verification Specific Styles */
        .page-title {
            text-align: center;
            padding: 0 0 40px;
            font-size: 2rem;
            font-weight: 700;
            color: #00d4ff;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
            margin-bottom: 10px;
}

        .verification-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 40px;
            background: rgba(10, 14, 26, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 20px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(0, 212, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .verification-icon {
            font-size: 4rem;
            color: #00d4ff;
            margin-bottom: 30px;
            text-shadow: 0 0 30px rgba(0, 212, 255, 0.6);
            display: block;
            text-align: center;
        }

        .verification-message {
            font-size: 1.1rem;
            color: #ccd6f6;
            margin-bottom: 35px;
            line-height: 1.7;
            text-align: center;
        }
        
        .verification-container h2 {
            text-align: center;
            margin: 20px 0;
        }

        .email-display {
            display: inline-block;
            font-weight: 500;
            color: var(--secondary);
        }

        .otp-form {
            margin-bottom: 25px;
            position: relative;
        }

        /* OTP Verification Styles */
        .otp-inputs {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 30px 0;
        }

        .otp-input {
            width: 55px;
            height: 65px;
            font-size: 1.8rem;
            text-align: center;
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            background: rgba(10, 14, 26, 0.95);
            backdrop-filter: blur(10px);
            color: #ccd6f6;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .otp-input:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 
                0 0 0 3px rgba(0, 212, 255, 0.2),
                0 0 20px rgba(0, 212, 255, 0.5);
            background: rgba(10, 14, 26, 0.95);
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        .verify-btn {
            display: block;
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #0a0e1a;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .verify-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .verify-btn:hover::before {
            left: 100%;
        }

        .verify-btn:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 10px 25px rgba(0, 212, 255, 0.4),
                0 0 30px rgba(0, 212, 255, 0.3);
        }

        .verify-btn:active {
            transform: translateY(0);
        }

        /* Resend Section */
        .resend-section {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .resend-text {
            font-size: 0.95rem;
            color: #8892b0;
        }

        .resend-btn {
            background: none;
            border: none;
            color: #00d4ff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            text-decoration: underline;
        }

        .resend-btn:hover {
            color: #0099cc;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }

        .success-message {
            color: #28a745;
            font-weight: 500;
            margin: 20px 0;
            padding: 10px;
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: 5px;
        }

        .error-message {
            color: #dc3545;
            font-weight: 500;
            margin: 20px 0;
            padding: 10px;
            background-color: rgba(220, 53, 69, 0.1);
            border-radius: 5px;
        }

        .login-link {
            display: block;
            margin-top: 15px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #0a0e1a;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
        }

        .login-link:hover {
            background-color: #333;
        }

        /* Media queries */
        @media (max-width: 768px) {
            .top-bar .container {
                flex-direction: column;
                gap: 5px;
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

            .main-nav {
                display: none;
                flex-direction: column;
                align-items: center;
            }

            .main-nav.active {
                display: flex;
            }

            .verification-container {
                padding: 20px;
                margin: 0 15px 40px;
            }

            .otp-inputs {
                gap: 5px;
            }

            .otp-input {
                width: 40px;
                height: 45px;
                font-size: 20px;
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
                <a href="#">Become a Member</a>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header>
        <nav>
            <div class="logo glow">TechHub</div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="#products">Products</a></li>
                <li><a href="index.php#about">About</a></li>
                <li><a href="index.php#contact">Contact</a></li>
            </ul>
            <div class="auth-buttons">
                <a href="sign-in.php" class="sign-in-btn">Sign In</a>
                <a href="sign-up.php" class="sign-up-btn">Sign Up</a>
            </div>
        </nav>
    </header>

    <!-- Navigation -->
    <nav class="main-nav" id="mainNav">
        <a href="index.php">HOME</a>
        <a href="shop.html">SHOP</a>
        <a href="men.html">MEN</a>
        <a href="women.html">WOMEN</a>
        <a href="foot.html">FOOTWEAR</a>
        <a href="acces.html">ACCESSORIES</a>
        <a href="#about">ABOUT</a>
        <a href="#contact">CONTACT</a>
    </nav>

    <!-- Email Verification Section -->
    <section>
        <h1 class="page-title">Email Verification</h1>

        <div class="verification-container">
            <?php if ($is_verified): ?>
                <!-- Success State -->
                <div class="verification-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Email Verified Successfully!</h2>
                <p class="verification-message">
                    Your email has been verified and your account is now active.
                    You can now sign in to your TechHub account.
                </p>
                <a href="sign-in.php" class="login-link">Sign In Now</a>
            <?php else: ?>
                <!-- Verification Form State -->
                <div class="verification-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <p class="verification-message">
                    We've sent a verification code to <span class="email-display"><?php echo htmlspecialchars($email); ?></span>.
                    Please enter the 6-digit code below to verify your email address.
                </p>

                <?php if (!empty($verification_message)): ?>
                    <div class="<?php echo $is_verified ? 'success-message' : 'error-message'; ?>">
                        <?php echo $verification_message; ?>
                    </div>
                <?php endif; ?>

                <form class="otp-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="otp-inputs">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <input type="text" class="otp-input" name="otp_digit_<?php echo $i; ?>" maxlength="1" required
                                pattern="[0-9]" inputmode="numeric" autocomplete="off"
                                data-index="<?php echo $i; ?>">
                        <?php endfor; ?>
                    </div>

                    <button type="submit" class="verify-btn">Verify Email</button>
                </form>

                <div class="resend-section">
                    <span class="resend-text">Didn't receive the code?</span>
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="resend_otp" class="resend-btn">Resend Code</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Account dropdown functionality
            const accountBtn = document.getElementById('accountBtn');
            const accountDropdown = document.getElementById('accountDropdown');

            accountBtn.addEventListener('click', function(e) {
                e.preventDefault();
                accountDropdown.classList.toggle('active');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!accountDropdown.contains(e.target) && !accountBtn.contains(e.target)) {
                    accountDropdown.classList.remove('active');
                }
            });

            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mainNav = document.getElementById('mainNav');

            mobileMenuToggle.addEventListener('click', function() {
                mainNav.classList.toggle('active');
            });

            // OTP input handling
            const otpInputs = document.querySelectorAll('.otp-input');

            otpInputs.forEach(function(input) {
                // Auto-focus next input field
                input.addEventListener('input', function() {
                    if (this.value.length === 1) {
                        const nextIndex = parseInt(this.dataset.index) + 1;
                        if (nextIndex <= 6) {
                            document.querySelector(`.otp-input[data-index="${nextIndex}"]`).focus();
                        }
                    }
                });

                // Allow backspace to focus previous input
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && this.value.length === 0) {
                        const prevIndex = parseInt(this.dataset.index) - 1;
                        if (prevIndex >= 1) {
                            const prevInput = document.querySelector(`.otp-input[data-index="${prevIndex}"]`);
                            prevInput.focus();
                            prevInput.value = '';
                        }
                    }
                });

                // Only allow numbers
                input.addEventListener('keypress', function(e) {
                    if (!/^\d$/.test(e.key)) {
                        e.preventDefault();
                    }
                });

                // Handle paste event
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const paste = e.clipboardData.getData('text');

                    // If it's a 6-digit number
                    if (/^\d{6}$/.test(paste)) {
                        // Distribute across inputs
                        otpInputs.forEach((input, index) => {
                            input.value = paste[index];
                        });
                    }
                });
            });

            // Auto-focus first input on page load
            if (otpInputs.length > 0) {
                otpInputs[0].focus();
            }
        });
    </script>

    <script src="js/cart.js"></script>

</body>

</html>
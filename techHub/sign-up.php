<?php
session_start();


include 'db_connect.php';
// Initialize variables
$error = '';
$username_email = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CAPTCHA
    if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
        echo "<script>alert('Please complete the CAPTCHA verification.'); history.back();</script>";
        exit();
    }

    $recaptcha_secret = "6LdnIIIrAAAAABgFYGVLlLLD-3Wa0c4zrTpjGFjN";
    $recaptcha_response = $_POST['g-recaptcha-response'];
    $recaptcha_url = "https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}";
    $recaptcha_data = json_decode(file_get_contents($recaptcha_url));

    if (!$recaptcha_data->success) {
        echo "<script>alert('CAPTCHA verification failed. Please try again.'); history.back();</script>";
        exit();
    }

    // Sanitize input
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);

    // Handle profile image upload
    $profile_image = NULL;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            echo "<script>alert('Only JPG, JPEG, and PNG files are allowed.'); history.back();</script>";
            exit();
        }

        if ($_FILES['profile_image']['size'] > $max_size) {
            echo "<script>alert('File size must be less than 2MB.'); history.back();</script>";
            exit();
        }

        $upload_dir = 'uploads/profiles/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = uniqid() . '_' . basename($_FILES['profile_image']['name']);
        $target_file = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            $profile_image = $filename;
        } else {
            echo "<script>alert('Error uploading file.'); history.back();</script>";
            exit();
        }
    } else {
        // Make profile image required
        echo "<script>alert('Please upload a profile picture.'); history.back();</script>";
        exit();
    }

    // Password validation
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match.'); history.back();</script>";
        exit();
    }

    // Password strength validation
    if (
        strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        echo "<script>alert('Password must be at least 8 characters and include uppercase, lowercase, number, and special character.'); history.back();</script>";
        exit();
    }

    // Check if terms checkbox was checked
    if (!isset($_POST['terms']) || $_POST['terms'] != 'on') {
        echo "<script>alert('You must agree to the Terms of Service and Privacy Policy.'); history.back();</script>";
        exit();
    }

    // Hash password
    $password_hashed = password_hash($password, PASSWORD_DEFAULT);

    // Generate OTP for email verification
    $otp_code = sprintf("%06d", rand(0, 999999)); // Generate 6-digit OTP
    $otp_purpose = 'EMAIL_VERIFICATION';
    $current_time = date('Y-m-d H:i:s');
    $otp_expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $is_active = false; // Account not active until email verified

    // Check if username or email already exists
    $check_user_stmt = $conn->prepare("SELECT 1 FROM users WHERE username = ? OR email = ?");
    $check_user_stmt->bind_param("ss", $username, $email);
    $check_user_stmt->execute();
    $check_user_stmt->store_result();

    if ($check_user_stmt->num_rows > 0) {
        echo "<script>alert('Username or Email is already in use. Please choose another.'); history.back();</script>";
    } else {
        // Insert user into database
        $insert_user_stmt = $conn->prepare("INSERT INTO users 
            (fullname, email, username, password, address, phone, profile_image, is_active, 
            otp_code, otp_purpose, otp_created_at, otp_expires_at, otp_is_used) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $otp_is_used = false;
        $is_active_int = $is_active ? 1 : 0;
        $otp_is_used_int = $otp_is_used ? 1 : 0;

        $insert_user_stmt->bind_param(
            "sssssssissssi",
            $fullname,
            $email,
            $username,
            $password_hashed,
            $address,
            $phone,
            $profile_image,
            $is_active_int,
            $otp_code,
            $otp_purpose,
            $current_time,
            $otp_expires_at,
            $otp_is_used_int
        );

        if ($insert_user_stmt->execute()) {
            // Store OTP and email in session for verification page
            $_SESSION['registration_email'] = $email;
            $_SESSION['otp_purpose'] = $otp_purpose;

            // Send OTP email
            if (sendVerificationEmail($email, $otp_code, $fullname)) {
                header("Location: verify_email.php");
                exit();
            } else {
                $_SESSION['email_error'] = "Account created but there was an issue sending the verification email. Please contact support.";
                header("Location:sign-in.php");
                exit();
            }
        } else {
            echo "<script>alert('Error: " . $insert_user_stmt->error . "'); history.back();</script>";
        }

        $insert_user_stmt->close();
    }

    $check_user_stmt->close();
}

mysqli_close($conn);

function sendVerificationEmail($email, $otp_code, $fullname)
{
    require 'PHPMailer/PHPMailer/src/Exception.php';
    require 'PHPMailer/PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/PHPMailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aliprimebank@gmail.com';
        $mail->Password = 'kodk kzue xsae fdsg';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('noreply@techHub.shop', 'TechHub');
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
    <title>Sign Up - TechHub</title>
    <link rel="icon" href="images/hf.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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

        /* Main Container */
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 120px 2rem 2rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: #00d4ff;
            text-align: center;
            margin-bottom: 2rem;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Sign-up Container */
        .signup-container {
            background: rgba(26, 35, 50, 0.8);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 20px;
            padding: 3rem;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.8s ease-out 0.2s both;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Profile Upload */
        .profile-upload {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00d4ff, #0099cc);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border: 3px solid rgba(0, 212, 255, 0.3);
            overflow: hidden;
            position: relative;
            transition: all 0.3s ease;
        }

        .profile-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.3);
        }

        .profile-preview i {
            font-size: 3rem;
            color: #0a0e1a;
        }

        .profile-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .file-upload-label {
            display: inline-block;
            background: linear-gradient(135deg, #00d4ff, #0099cc);
            color: #0a0e1a;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
            border: none;
        }

        .file-upload-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 212, 255, 0.4);
        }

        .file-upload-input {
            display: none;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #ccd6f6;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .required-field::after {
            content: ' *';
            color: #ff6b6b;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.2rem;
            background: rgba(10, 14, 26, 0.8);
            border: 2px solid rgba(0, 212, 255, 0.2);
            border-radius: 12px;
            color: #ccd6f6;
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-control:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.1);
            background: rgba(10, 14, 26, 0.9);
        }

        .form-control::placeholder {
            color: rgba(204, 214, 246, 0.5);
        }

        .password-requirements {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: rgba(204, 214, 246, 0.7);
            line-height: 1.4;
        }

        /* Checkbox Group */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(0, 212, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(0, 212, 255, 0.1);
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: #00d4ff;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            flex: 1;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .checkbox-group a {
            color: #00d4ff;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .checkbox-group a:hover {
            text-shadow: 0 0 5px rgba(0, 212, 255, 0.5);
        }

        /* reCAPTCHA Styling */
        .g-recaptcha {
            margin: 1.5rem 0;
            display: flex;
            justify-content: center;
        }

        /* Submit Button */
        .btn-signup {
            width: 100%;
            padding: 1.2rem;
            background: linear-gradient(135deg, #00d4ff, #0099cc);
            color: #0a0e1a;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(0, 212, 255, 0.4);
        }

        .btn-signup:active {
            transform: translateY(0);
        }

        /* Login Link */
        .login-link {
            text-align: center;
            color: #ccd6f6;
            font-size: 0.95rem;
        }

        .login-link a {
            color: #00d4ff;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .login-link a:hover {
            text-shadow: 0 0 5px rgba(0, 212, 255, 0.5);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: linear-gradient(135deg, #1a2332, #0a0e1a);
            margin: 5% auto;
            padding: 2rem;
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .close-modal {
            color: #ccd6f6;
            float: right;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }

        .modal-title {
            color: #00d4ff;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        .modal-body {
            color: #ccd6f6;
            line-height: 1.7;
        }

        .modal-body h3 {
            color: #00d4ff;
            margin: 1.5rem 0 0.8rem;
            font-size: 1.2rem;
        }

        .modal-body ul {
            margin: 0.5rem 0 1rem 1.5rem;
        }

        .modal-body li {
            margin-bottom: 0.5rem;
        }

        .modal-footer {
            margin-top: 2rem;
            text-align: center;
        }

        .modal-btn {
            background: linear-gradient(135deg, #00d4ff, #0099cc);
            color: #0a0e1a;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 212, 255, 0.4);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .container {
                padding: 100px 1rem 2rem;
            }

            .signup-container {
                padding: 2rem 1.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .profile-preview {
                width: 100px;
                height: 100px;
            }

            .profile-preview i {
                font-size: 2.5rem;
            }

            .modal-content {
                margin: 10% auto;
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .auth-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .sign-in-btn, .sign-up-btn {
                padding: 0.6rem 1rem;
                font-size: 0.8rem;
            }

            .checkbox-group {
                flex-direction: column;
                gap: 0.5rem;
            }

            .checkbox-group input[type="checkbox"] {
                align-self: flex-start;
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
    <div class="container">
        <h1 class="page-title">Create Your Account</h1>

        <div class="signup-container">
            <form action="sign-up.php" method="POST" enctype="multipart/form-data">
                <div class="profile-upload">
                    <div class="profile-preview" id="profilePreview">
                        <i class="fas fa-user"></i>
                    </div>
                    <label for="profile_image" class="file-upload-label">
                        <i class="fas fa-camera"></i> Upload Profile Picture
                    </label>
                    <input type="file" name="profile_image" id="profile_image" class="file-upload-input" accept="image/jpeg,image/png,image/jpg">
                </div>

                <div class="form-group">
                    <label for="fullname" class="required-field">Full Name</label>
                    <input type="text" class="form-control" id="fullname" name="fullname" required placeholder="Enter your full name">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email" class="required-field">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="your@email.com">
                    </div>
                    <div class="form-group">
                        <label for="username" class="required-field">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required placeholder="Choose a username">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="required-field">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Create a strong password">
                        <div class="password-requirements">
                            Password must be at least 8 characters and include uppercase, lowercase, number, and special character.
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="required-field">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" class="form-control" id="address" name="address" placeholder="Your address ">
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="Your phone number ">
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">I agree to the <a href="#" id="termsModalBtn">Terms of Service</a> and <a href="#" id="privacyModalBtn">Privacy Policy</a></label>
                </div>

                <div class="g-recaptcha" data-sitekey="6LdnIIIrAAAAAMpZsTlgMjk2cfmt8XTwhnBoRseU"></div>
                
                <button type="submit" class="btn-signup">Create Account</button>

                <div class="login-link">
                    Already have an account? <a href="sign-in.php">Sign In</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Terms of Service Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeTermsModal">&times;</span>
            <h2 class="modal-title">Terms of Service</h2>
            <div class="modal-body">
                <p>Welcome to Tech Hub. By creating an account and using our services, you agree to the following terms and conditions:</p>

                <h3>1. Account Registration</h3>
                <p>You must provide accurate and complete information when creating your account. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>

                <h3>2. User Conduct</h3>
                <p>You agree to use our services only for lawful purposes and in accordance with these Terms. You agree not to:</p>
                <ul>
                    <li>Use our services in any way that violates any applicable law or regulation</li>
                    <li>Engage in any conduct that restricts or inhibits anyone's use or enjoyment of our services</li>
                    <li>Attempt to gain unauthorized access to our systems or user accounts</li>
                    <li>Use our services to transmit any material that is unlawful, threatening, abusive, or otherwise objectionable</li>
                </ul>

                <h3>3. Orders and Payments</h3>
                <p>All orders are subject to product availability and confirmation of the order price. Payment must be received prior to the acceptance of an order. TechHub reserves the right to refuse or cancel any orders at any time.</p>

                <h3>4. Shipping and Delivery</h3>
                <p>Shipping and delivery times are estimates only and cannot be guaranteed. TechHub is not liable for any delays in receiving your order.</p>

                <h3>5. Returns and Refunds</h3>
                <p>Please review our Returns Policy for information about returning products and receiving refunds.</p>

                <h3>6. Modifications to the Service</h3>
                <p>TechHub reserves the right to modify or discontinue, temporarily or permanently, our services with or without notice.</p>

                <h3>7. Termination</h3>
                <p>TechHub may terminate your access to all or any part of our services at any time, with or without cause, with or without notice.</p>

                <h3>8. Governing Law</h3>
                <p>These Terms shall be governed by and construed in accordance with the laws of the Philippines.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn" id="acceptTermsBtn">I Understand</button>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closePrivacyModal">&times;</span>
            <h2 class="modal-title">Privacy Policy</h2>
            <div class="modal-body">
                <p>Your privacy is important to us. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our website and services.</p>

                <h3>1. Information We Collect</h3>
                <p>We may collect personal information that you voluntarily provide when registering for an account, placing an order, or contacting us. This may include:</p>
                <ul>
                    <li>Contact information (name, email address, phone number, address)</li>
                    <li>Account credentials (username and password)</li>
                    <li>Payment information</li>
                    <li>Profile information and preferences</li>
                    <li>Order history and transaction details</li>
                </ul>

                <h3>2. How We Use Your Information</h3>
                <p>We may use the information we collect to:</p>
                <ul>
                    <li>Create and manage your account</li>
                    <li>Process and fulfill your orders</li>
                    <li>Communicate with you about your orders, account, or inquiries</li>
                    <li>Send you marketing communications (if you opt in)</li>
                    <li>Improve our website, products, and services</li>
                    <li>Comply with legal obligations</li>
                </ul>

                <h3>3. Information Sharing</h3>
                <p>We may share your information with third parties only in the following circumstances:</p>
                <ul>
                    <li>With service providers who perform services on our behalf</li>
                    <li>To comply with legal obligations</li>
                    <li>To protect our rights, privacy, safety, or property</li>
                    <li>In connection with a business transfer or acquisition</li>
                </ul>

                <h3>4. Data Security</h3>
                <p>We implement appropriate security measures to protect your personal information. However, no method of transmission over the Internet or electronic storage is 100% secure.</p>

                <h3>5. Your Rights</h3>
                <p>You may access, update, or delete your personal information by logging into your account or contacting us directly.</p>

                <h3>6. Changes to This Privacy Policy</h3>
                <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn" id="acceptPrivacyBtn">I Understand</button>
            </div>
        </div>
    </div>


    <script>
        // Profile image preview
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profilePreview');
                    preview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview">`;
                }
                reader.readAsDataURL(file);
            }
        });

        // Modal functionality
        const termsModal = document.getElementById('termsModal');
        const privacyModal = document.getElementById('privacyModal');

        document.getElementById('termsModalBtn').addEventListener('click', function(e) {
            e.preventDefault();
            termsModal.style.display = 'block';
        });

        document.getElementById('privacyModalBtn').addEventListener('click', function(e) {
            e.preventDefault();
            privacyModal.style.display = 'block';
        });

        document.getElementById('closeTermsModal').addEventListener('click', function() {
            termsModal.style.display = 'none';
        });

        document.getElementById('closePrivacyModal').addEventListener('click', function() {
            privacyModal.style.display = 'none';
        });

        document.getElementById('acceptTermsBtn').addEventListener('click', function() {
            termsModal.style.display = 'none';
            document.getElementById('terms').checked = true;
        });

        document.getElementById('acceptPrivacyBtn').addEventListener('click', function() {
            privacyModal.style.display = 'none';
            document.getElementById('terms').checked = true;
        });

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target == termsModal) {
                termsModal.style.display = 'none';
            }
            if (e.target == privacyModal) {
                privacyModal.style.display = 'none';
            }
        });

        // Form validation with enhanced visual feedback
        const form = document.querySelector('form');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        // Real-time password validation
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            const requirementsDiv = this.parentNode.querySelector('.password-requirements');
            
            if (password.length > 0) {
                if (passwordRegex.test(password)) {
                    this.style.borderColor = '#4ade80';
                    requirementsDiv.style.color = '#4ade80';
                    requirementsDiv.innerHTML = '✓ Password meets all requirements';
                } else {
                    this.style.borderColor = '#ef4444';
                    requirementsDiv.style.color = '#ef4444';
                    requirementsDiv.innerHTML = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
                }
            } else {
                this.style.borderColor = 'rgba(0, 212, 255, 0.2)';
                requirementsDiv.style.color = 'rgba(204, 214, 246, 0.7)';
                requirementsDiv.innerHTML = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
            }
        });

        // Real-time password confirmation
        confirmPasswordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.style.borderColor = '#4ade80';
                } else {
                    this.style.borderColor = '#ef4444';
                }
            } else {
                this.style.borderColor = 'rgba(0, 212, 255, 0.2)';
            }
        });

        // Form submission validation
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Password match validation
            if (password !== confirmPassword) {
                e.preventDefault();
                showNotification('Passwords do not match.', 'error');
                return false;
            }

            // Password strength validation
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            if (!passwordRegex.test(password)) {
                e.preventDefault();
                showNotification('Password must be at least 8 characters and include uppercase, lowercase, number, and special character.', 'error');
                return false;
            }

            // Captcha validation
            const captchaResponse = grecaptcha.getResponse();
            if (captchaResponse.length === 0) {
                e.preventDefault();
                showNotification('Please complete the CAPTCHA verification.', 'error');
                return false;
            }

            // Terms agreement validation
            if (!document.getElementById('terms').checked) {
                e.preventDefault();
                showNotification('You must agree to the Terms of Service and Privacy Policy.', 'error');
                return false;
            }

            // Show loading state
            const submitBtn = document.querySelector('.btn-signup');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            submitBtn.disabled = true;
        });

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                <span>${message}</span>
                <button class="notification-close" onclick="this.parentElement.remove()">×</button>
            `;
            
            // Add notification styles if not already present
            if (!document.querySelector('.notification-styles')) {
                const style = document.createElement('style');
                style.className = 'notification-styles';
                style.innerHTML = `
                    .notification {
                        position: fixed;
                        top: 100px;
                        right: 20px;
                        background: rgba(26, 35, 50, 0.95);
                        border: 1px solid rgba(0, 212, 255, 0.3);
                        border-radius: 12px;
                        padding: 1rem 1.5rem;
                        color: #ccd6f6;
                        display: flex;
                        align-items: center;
                        gap: 0.8rem;
                        z-index: 3000;
                        max-width: 400px;
                        backdrop-filter: blur(10px);
                        animation: slideInRight 0.3s ease-out;
                    }
                    
                    .notification-error {
                        border-color: rgba(239, 68, 68, 0.5);
                    }
                    
                    .notification-error i {
                        color: #ef4444;
                    }
                    
                    .notification-close {
                        background: none;
                        border: none;
                        color: #ccd6f6;
                        font-size: 1.2rem;
                        cursor: pointer;
                        margin-left: auto;
                        transition: color 0.3s ease;
                    }
                    
                    .notification-close:hover {
                        color: #00d4ff;
                    }
                    
                    @keyframes slideInRight {
                        from {
                            opacity: 0;
                            transform: translateX(100%);
                        }
                        to {
                            opacity: 1;
                            transform: translateX(0);
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // Add smooth scroll behavior for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading animation to form inputs
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentNode.classList.add('input-focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentNode.classList.remove('input-focused');
            });
        });
    </script>

</body>
</html>
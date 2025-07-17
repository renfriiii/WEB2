<?php
session_start();


// Include your environment-aware database connection
include 'db_connect.php';

// Initialize variables
$error = '';
$username_email = '';

// Step 1: Request Password Reset
if (isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error_message = "Please enter your email address.";
    } else {
        // Check if email exists in the database
        $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Generate OTP
            $otp_code = sprintf("%06d", rand(0, 999999));
            $otp_purpose = 'PASSWORD_RESET';
            $current_time = date('Y-m-d H:i:s');
            $otp_expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Update the user record with OTP information
            $update_stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_purpose = ?, otp_created_at = ?, otp_expires_at = ?, otp_is_used = 0 WHERE id = ?");
            $update_stmt->bind_param("ssssi", $otp_code, $otp_purpose, $current_time, $otp_expires_at, $user['id']);

            if ($update_stmt->execute()) {
                // Send password reset email with OTP
                if (sendResetEmail($email, $otp_code, $user['fullname'])) {
                    // Store the email in session for the next step
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['otp_purpose'] = $otp_purpose;

                    $success_message = "A verification code has been sent to your email. Please check your inbox and enter the code to reset your password.";
                } else {
                    $error_message = "Failed to send verification email. Please try again.";
                }
            } else {
                $error_message = "Something went wrong. Please try again later.";
            }

            $update_stmt->close();
        } else {
            // For security reasons, don't reveal if the email exists or not
            $success_message = "If your email is registered with us, you will receive a password reset code.";
        }

        $stmt->close();
    }
}

// Step 2: Verify OTP and show password reset form
if (isset($_POST['verify_otp'])) {
    if (!isset($_SESSION['reset_email']) || !isset($_SESSION['otp_purpose'])) {
        header("Location: forgot-password.php");
        exit();
    }

    $email = $_SESSION['reset_email'];
    $otp_purpose = $_SESSION['otp_purpose'];

    // Collect and combine OTP digits
    $otp_digits = array();
    for ($i = 1; $i <= 6; $i++) {
        if (isset($_POST["otp_digit_$i"])) {
            $otp_digits[] = $_POST["otp_digit_$i"];
        } else {
            $error_message = "Please enter all OTP digits.";
            break;
        }
    }

    if (count($otp_digits) == 6) {
        $entered_otp = implode("", $otp_digits);

        // Validate OTP in database
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND otp_code = ? AND otp_purpose = ? AND otp_is_used = 0");
        $stmt->bind_param("sss", $email, $entered_otp, $otp_purpose);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Check if OTP has expired
            $check_stmt = $conn->prepare("SELECT otp_expires_at FROM users WHERE id = ?");
            $check_stmt->bind_param("i", $user['id']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $user_data = $check_result->fetch_assoc();

            $current_time = date('Y-m-d H:i:s');
            $otp_expires_at = $user_data['otp_expires_at'];

            if ($current_time > $otp_expires_at) {
                $error_message = "The verification code has expired. Please request a new one.";
            } else {
                // Store the user ID in session for the password reset step
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['is_otp_verified'] = true;
            }

            $check_stmt->close();
        } else {
            $error_message = "Invalid verification code. Please try again.";
        }

        $stmt->close();
    }
}

// Step 3: Reset Password
if (isset($_POST['reset_password'])) {
    if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['is_otp_verified']) || $_SESSION['is_otp_verified'] !== true) {
        header("Location: forgot-password.php");
        exit();
    }

    $user_id = $_SESSION['reset_user_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate password
    if (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password and mark OTP as used
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, otp_is_used = 1 WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user_id);

        if ($update_stmt->execute()) {
            // Clear all reset session variables
            unset($_SESSION['reset_email']);
            unset($_SESSION['otp_purpose']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['is_otp_verified']);

            $success_message = "Your password has been successfully reset. You can now login with your new password.";
        } else {
            $error_message = "Failed to reset password. Please try again later.";
        }

        $update_stmt->close();
    }
}

// Handle resend OTP
if (isset($_POST['resend_otp'])) {
    if (!isset($_SESSION['reset_email']) || !isset($_SESSION['otp_purpose'])) {
        header("Location: forgot-password.php");
        exit();
    }

    $email = $_SESSION['reset_email'];
    $otp_purpose = $_SESSION['otp_purpose'];

    // Generate new OTP
    $new_otp = sprintf("%06d", rand(0, 999999));
    $current_time = date('Y-m-d H:i:s');
    $otp_expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Update OTP in database
    $update_stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_created_at = ?, otp_expires_at = ?, otp_is_used = 0 WHERE email = ? AND otp_purpose = ?");
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
        if (sendResetEmail($email, $new_otp, $fullname)) {
            $success_message = "A new verification code has been sent to your email.";
        } else {
            $error_message = " $result";
        }
    } else {
        $error_message = "Error generating new verification code. Please try again.";
    }

    $update_stmt->close();
}

mysqli_close($conn);

// Function to send password reset email
function sendResetEmail($email, $otp_code, $fullname)
{
    require 'PHPMailer/PHPMailer/src/Exception.php';
    require 'PHPMailer/PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/PHPMailer/src/SMTP.php';



    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aliprimebank@gmail.com';
        $mail->Password = 'kodk kzue xsae fdsg';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('noreply@techHub.shop', 'TechHub');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Reset Your TechHub Password';
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <table style='width: 100%; padding: 20px; background-color: #f4f4f4;'>
                    <tr>
                        <td style='text-align: center;'>
                            <h2 style='color: #0071c5;'>Password Reset Request</h2>
                            <p>Hello {$fullname},</p>
                            <p>We received a request to reset your TechHub account password. Please use the verification code below to complete your password reset:</p>
                            <h1 style='color: #0071c5; font-size: 36px; letter-spacing: 4px;'>{$otp_code}</h1>
                            <p>This code will expire in <strong>15 minutes</strong>.</p>
                            <p>If you did not request a password reset, you can safely ignore this email.</p>
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
        $mail->AltBody = "Hello {$fullname}, Your verification code for TechHub password reset is: {$otp_code}. This code will expire in 15 minutes.";



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
    <title>Forgot Password - TechHub</title>
    <link rel="icon" href="images/hf.png">
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
            background: linear-gradient(135deg, #00d4ff, #0099cc);
            color: #0a0e1a;
            border: 2px solid transparent;
        }

        .sign-up-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 212, 255, 0.4);
        }

     /* Reset and Base Styles */
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

/* Header Styles (keeping your existing header) */
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
    background: linear-gradient(135deg, #00d4ff, #0099cc);
    color: #0a0e1a;
    border: 2px solid transparent;
}

.sign-up-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 212, 255, 0.4);
}

/* Main Content */
.main-content {
    padding-top: 120px;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

/* Animated Background Particles */
.main-content::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 25% 25%, rgba(0, 212, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 75% 75%, rgba(0, 153, 204, 0.1) 0%, transparent 50%);
    animation: float 6s ease-in-out infinite;
    pointer-events: none;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}

/* Page Title */
.page-title {
    text-align: center;
    padding: 0 0 40px;
    font-size: 2.5rem;
    font-weight: 700;
    color: #00d4ff;
    text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
    margin-bottom: 10px;
}

.page-subtitle {
    text-align: center;
    font-size: 1.1rem;
    color: #8892b0;
    margin-bottom: 40px;
}

/* Form Container */
.form-container {
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

.form-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #00d4ff, transparent);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

/* Icon */
.icon {
    font-size: 4rem;
    color: #00d4ff;
    margin-bottom: 30px;
    text-shadow: 0 0 30px rgba(0, 212, 255, 0.6);
    display: block;
    text-align: center;
}

/* Form Message */
.form-message {
    font-size: 1.1rem;
    color: #ccd6f6;
    margin-bottom: 35px;
    line-height: 1.7;
    text-align: center;
}

/* Form Groups */
.form-group {
    margin-bottom: 25px;
    position: relative;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #ccd6f6;
    font-size: 0.95rem;
}

.form-input {
    width: 100%;
    padding: 15px 20px;
    border: 1px solid rgba(0, 212, 255, 0.3);
    border-radius: 12px;
    font-size: 1rem;
    background: rgba(10, 14, 26, 0.95);
    color: #ccd6f6;
    transition: all 0.3s ease;
    position: relative;
    backdrop-filter: blur(10px);
}

.form-input::placeholder {
    color: #64748b;
}

.form-input:focus {
    border-color: #00d4ff;
    box-shadow: 
        0 0 0 3px rgba(0, 212, 255, 0.2),
        0 0 20px rgba(0, 212, 255, 0.5);
    outline: none;
    background: rgba(10, 14, 26, 0.95);
    text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
}

/* Submit Button */
.submit-btn {
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

.submit-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s;
}

.submit-btn:hover::before {
    left: 100%;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 
        0 10px 25px rgba(0, 212, 255, 0.4),
        0 0 30px rgba(0, 212, 255, 0.3);
}

.submit-btn:active {
    transform: translateY(0);
}

/* Back Link */
.back-link {
    color: #00d4ff;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-block;
    margin-top: 20px;
    text-align: center;
    width: 100%;
}

.back-link:hover {
    color: #0099cc;
    text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
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

/* Password Requirements */
.password-requirements {
    margin-top: 15px;
    font-size: 0.9rem;
    color: #8892b0;
    text-align: left;
    background: rgba(0, 212, 255, 0.05);
    padding: 15px;
    border-radius: 10px;
    border: 1px solid rgba(0, 212, 255, 0.1);
}

.password-requirements ul {
    padding-left: 20px;
    margin-top: 8px;
}

.password-requirements li {
    margin-bottom: 5px;
}

/* Success and Error Messages */
.success-message {
    color: #00ff88;
    font-weight: 600;
    margin: 25px 0;
    padding: 15px;
    background: rgba(0, 255, 136, 0.1);
    border: 1px solid rgba(0, 255, 136, 0.3);
    border-radius: 10px;
    text-align: center;
}

.error-message {
    color: #ff6b6b;
    font-weight: 600;
    margin: 25px 0;
    padding: 15px;
    background: rgba(255, 107, 107, 0.1);
    border: 1px solid rgba(255, 107, 107, 0.3);
    border-radius: 10px;
    text-align: center;
}

/* Login Link */
.login-link {
    display: inline-block;
    margin-top: 20px;
    padding: 14px 35px;
    background: linear-gradient(135deg, #1a2332, #0a0e1a);
    color: #00d4ff;
    text-decoration: none;
    border: 1px solid rgba(0, 212, 255, 0.3);
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.login-link:hover {
    background: linear-gradient(135deg, #00d4ff, #0099cc);
    color: #0a0e1a;
    box-shadow: 0 5px 15px rgba(0, 212, 255, 0.3);
    transform: translateY(-2px);
}

/* Password Field with Toggle */
.password-field {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #8892b0;
    font-size: 1.2rem;
    transition: color 0.3s ease;
}

.password-toggle:hover {
    color: #00d4ff;
}

/* Loading State */
.loading {
    opacity: 0.7;
    pointer-events: none;
}

.loading .submit-btn {
    background: rgba(0, 212, 255, 0.5);
    cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 768px) {
    .main-content {
        padding: 100px 20px 40px;
    }

    .form-container {
        padding: 30px 25px;
        margin: 0 15px;
    }

    .page-title {
        font-size: 2rem;
    }

    .otp-inputs {
        gap: 10px;
    }

    .otp-input {
        width: 45px;
        height: 55px;
        font-size: 1.5rem;
    }

    nav {
        padding: 0 1rem;
    }

    .nav-links {
        display: none;
    }

    .auth-buttons {
        gap: 0.5rem;
    }

    .sign-in-btn, .sign-up-btn {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .form-container {
        padding: 25px 20px;
    }

    .otp-inputs {
        gap: 8px;
    }

    .otp-input {
        width: 40px;
        height: 50px;
        font-size: 1.3rem;
    }

    .page-title {
        font-size: 1.8rem;
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

    <!-- Forgot Password Section -->
    <section>
        <h1 class="page-title">Forgot Password</h1>

        <div class="form-container">
            <?php if (!empty($success_message) && (!isset($_SESSION['reset_email']) || (isset($_SESSION['is_otp_verified']) && $_SESSION['is_otp_verified'] === true && isset($_POST['reset_password'])))): ?>
                <!-- Final Success Message (After Password Reset) -->
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="success-message">
                    <?php echo $success_message; ?>
                </div>
                <a href="sign-in.php" class="login-link">Sign In Now</a>

            <?php elseif (isset($_SESSION['is_otp_verified']) && $_SESSION['is_otp_verified'] === true): ?>
                <!-- Password Reset Form (Step 3) -->
                <div class="icon">
                    <i class="fas fa-key"></i>
                </div>
                <p class="form-message">
                    Create a new password for your account.
                </p>

                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="resetPasswordForm">
                    <div class="form-group password-field">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" required>
                        <span class="password-toggle" id="newPasswordToggle"><i class="far fa-eye"></i></span>
                    </div>

                    <div class="password-requirements">
                        <p>Password must contain:</p>
                        <ul>
                            <li>At least 8 characters</li>
                            <li>Letters and numbers</li>
                            <li>At least one special character</li>
                        </ul>
                    </div>

                    <div class="form-group password-field">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                        <span class="password-toggle" id="confirmPasswordToggle"><i class="far fa-eye"></i></span>
                    </div>

                    <button type="submit" name="reset_password" class="submit-btn">Reset Password</button>
                </form>

            <?php elseif (isset($_SESSION['reset_email']) && isset($_SESSION['otp_purpose'])): ?>
                <!-- OTP Verification Form (Step 2) -->
                <div class="icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <p class="form-message">
                    We've sent a verification code to <span style="font-weight: 500; color: var(--secondary);"><?php echo htmlspecialchars($_SESSION['reset_email']); ?></span>.
                    Please enter the 6-digit code below to continue.
                </p>

                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="success-message">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="otp-inputs">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <input type="text" class="otp-input" name="otp_digit_<?php echo $i; ?>" maxlength="1" required
                                pattern="[0-9]" inputmode="numeric" autocomplete="off"
                                data-index="<?php echo $i; ?>">
                        <?php endfor; ?>
                    </div>

                    <button type="submit" name="verify_otp" class="submit-btn">Verify Code</button>
                </form>

                <div class="resend-section">
                    <span class="resend-text">Didn't receive the code?</span>
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="resend_otp" class="resend-btn">Resend Code</button>
                    </form>
                </div>

                <a href="forgot-password.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Start Over
                </a>

            <?php else: ?>
                <!-- Email Request Form (Step 1) -->
                <div class="icon">
                    <i class="fas fa-unlock-alt"></i>
                </div>
                <p class="form-message">
                    Enter your registered email address and we'll send you a verification code to reset your password.
                </p>

                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="success-message">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" required>
                    </div>

                    <button type="submit" name="request_reset" class="submit-btn">Send Reset Code</button>
                </form>

                <a href="sign-in.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Sign In
                </a>
            <?php endif; ?>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Account dropdown functionality
            const accountBtn = document.getElementById('accountBtn');
            const accountDropdown = document.getElementById('accountDropdown');

            if (accountBtn) {
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
            }

            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mainNav = document.getElementById('mainNav');

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    mainNav.classList.toggle('active');
                });
            }

            // Password visibility toggle
            const passwordToggles = document.querySelectorAll('.password-toggle');

            passwordToggles.forEach(function(toggle) {
                toggle.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    const icon = this.querySelector('i');

                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });

            // OTP input handling
            const otpInputs = document.querySelectorAll('.otp-input');

            if (otpInputs.length > 0) {
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
                otpInputs[0].focus();
            }

            // Password validation
            const resetPasswordForm = document.getElementById('resetPasswordForm');

            if (resetPasswordForm) {
                resetPasswordForm.addEventListener('submit', function(event) {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    let isValid = true;
                    let errorMessage = '';

                    // Check password length
                    if (newPassword.length < 8) {
                        errorMessage = 'Password must be at least 8 characters long.';
                        isValid = false;
                    }
                    // Check for letters and numbers
                    else if (!/^(?=.*[A-Za-z])(?=.*\d)/.test(newPassword)) {
                        errorMessage = 'Password must contain both letters and numbers.';
                        isValid = false;
                    }
                    // Check for special character
                    else if (!/[^A-Za-z0-9]/.test(newPassword)) {
                        errorMessage = 'Password must contain at least one special character.';
                        isValid = false;
                    }
                    // Check passwords match
                    else if (newPassword !== confirmPassword) {
                        errorMessage = 'Passwords do not match.';
                        isValid = false;
                    }

                    if (!isValid) {
                        event.preventDefault();
                        const errorDiv = document.querySelector('.error-message');

                        if (errorDiv) {
                            errorDiv.textContent = errorMessage;
                        } else {
                            const newErrorDiv = document.createElement('div');
                            newErrorDiv.className = 'error-message';
                            newErrorDiv.textContent = errorMessage;

                            const submitBtn = document.querySelector('.submit-btn');
                            resetPasswordForm.insertBefore(newErrorDiv, submitBtn);
                        }
                    }
                });
            }
        });
    </script>

    <script src="js/cart.js"></script>

</body>

</html>
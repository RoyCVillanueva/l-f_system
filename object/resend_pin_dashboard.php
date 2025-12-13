<?php
session_start();
require_once '../class/system.php';
require_once '../class/database.php';
require_once 'entities.php';
require_once 'email_service.php';

// Check if user is logged in but email is not verified
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

// Skip for admin users
if ($_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}

$user = new User();
$userData = $user->getUserById($_SESSION['user_id']);

if ($userData && $userData['email_verified']) {
    // Email already verified, redirect to dashboard
    header('Location: dashboard.php');
    exit;
}

try {
    $emailService = new EmailService();
    $pinCode = '';
    
    // Generate and send new PIN
    $emailSent = $emailService->verifyEmail($userData['email'], $userData['username'], $pinCode);
    
    if ($emailSent) {
        // Store PIN in session for verification
        $_SESSION['dashboard_pin_verification'] = [
            'pin_code' => $pinCode,
            'pin_expiry' => time() + (15 * 60), // 15 minutes
            'email' => $userData['email'],
            'user_id' => $_SESSION['user_id']
        ];
        
        $_SESSION['message'] = "A new PIN has been sent to your email!";
    } else {
        $_SESSION['pin_error'] = "Failed to resend PIN. Please try again.";
    }
} catch (Exception $e) {
    $_SESSION['pin_error'] = "Resend error: " . $e->getMessage();
}

header('Location: dashboard.php');
exit;
?>
<?php
session_start();
require_once '../class/system.php';
require_once '../class/database.php';
require_once 'entities.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

// Skip for admin users
if ($_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Check if PIN verification session exists
if (!isset($_SESSION['dashboard_pin_verification'])) {
    $_SESSION['pin_error'] = 'No verification PIN found. Please request a new one.';
    header('Location: dashboard.php');
    exit;
}

// Check if PIN has expired
if (time() > $_SESSION['dashboard_pin_verification']['pin_expiry']) {
    unset($_SESSION['dashboard_pin_verification']);
    $_SESSION['pin_error'] = 'PIN has expired. Please request a new one.';
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin_code'])) {
    $enteredPin = trim($_POST['pin_code']);
    $storedPin = $_SESSION['dashboard_pin_verification']['pin_code'];
    
    if (empty($enteredPin)) {
        $_SESSION['pin_error'] = "Please enter the PIN code";
    } elseif ($enteredPin !== $storedPin) {
        $_SESSION['pin_error'] = "Invalid PIN code. Please check your email and try again.";
    } else {
        try {
            $userModel = new User();
            
            // Mark email as verified in database
            $result = $userModel->markEmailAsVerified($_SESSION['dashboard_pin_verification']['user_id']);
            
            if ($result) {
                // Clear verification session
                unset($_SESSION['dashboard_pin_verification']);
                
                // Set success flag
                $_SESSION['verification_success'] = true;
                $_SESSION['message'] = 'Email verified successfully! You now have full access to the system.';
            } else {
                $_SESSION['pin_error'] = "Failed to verify email. Please try again.";
            }
            
        } catch (Exception $e) {
            $_SESSION['pin_error'] = "Verification error: " . $e->getMessage();
        }
    }
}

header('Location: dashboard.php');
exit;
?>
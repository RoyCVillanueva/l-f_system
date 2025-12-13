<?php
session_start();
require_once '../class/system.php';
require_once '../class/database.php';
require_once 'entities.php';
require_once 'email_service.php';

// Redirect if no pending verification
if (!isset($_SESSION['pending_verification'])) {
    header('Location: register.php');
    exit;
}

// Check if PIN has expired
if (time() > $_SESSION['pending_verification']['pin_expiry']) {
    unset($_SESSION['pending_verification']);
    $_SESSION['error'] = 'PIN has expired. Please register again.';
    header('Location: register.php');
    exit;
}

$error = '';
$success = '';

// Handle PIN verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_pin') {
    $enteredPin = trim($_POST['pin_code']);
    $storedPin = $_SESSION['pending_verification']['pin_code'];
    
    if (empty($enteredPin)) {
        $error = "Please enter the PIN code";
    } elseif ($enteredPin !== $storedPin) {
        $error = "Invalid PIN code. Please check your email and try again.";
    } else {
        try {
            $userModel = new User();
            $emailService = new EmailService();
            
        $userId = $userModel->generateUserId('user'); // Explicitly pass 'user' role
        $userData = $_SESSION['pending_verification'];

        $result = $userModel->create(
        $userId, 
        $userData['username'], 
        $userData['email'], 
        $userData['password'], 
        $userData['phone_number'], 
        'user' // This should match the role used in generateUserId
);
            
            if ($result['success']) {
                // Mark email as verified in database
                $userModel->markEmailAsVerified($userId);
                
                // Send welcome email
                $emailService->sendWelcomeEmail($userData['email'], $userData['username']);
                
                // Clear pending verification
                unset($_SESSION['pending_verification']);
                
                $_SESSION['success'] = 'Registration completed successfully! You can now login.';
                header('Location: login.php');
                exit;
            } else {
                // Show more detailed error for debugging
                $error = "Failed to create user account: " . ($result['error'] ?? 'Unknown error');
            }
            
        } catch (Exception $e) {
            $error = "Verification error: " . $e->getMessage();
        }
    }
}

// Handle resend PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_pin') {
    try {
        $emailService = new EmailService();
        $pinCode = '';
        $userData = $_SESSION['pending_verification'];
        
        $emailSent = $emailService->verifyEmail($userData['email'], $userData['username'], $pinCode);
        
        if ($emailSent) {
            // Update PIN in session
            $_SESSION['pending_verification']['pin_code'] = $pinCode;
            $_SESSION['pending_verification']['pin_expiry'] = time() + (15 * 60);
            
            $success = "A new PIN has been sent to your email!";
        } else {
            $error = "Failed to resend PIN. Please try again.";
        }
    } catch (Exception $e) {
        $error = "Resend error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Lost and Found System</title>
    <link rel="stylesheet" href="styles/register.css">
    <style>
        .pin-input {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 8px;
            text-align: center;
            width: 200px;
            margin: 0 auto;
        }
        .verification-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .timer {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>Verify Your Email</h1>
            <p>Enter the 6-digit PIN sent to your email</p>
        </div>
        
        <div class="verification-info">
            <p><strong>Email:</strong> <?php echo $_SESSION['pending_verification']['email']; ?></p>
            <p class="timer">PIN expires in: <span id="timer">15:00</span></p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <input type="hidden" name="action" value="verify_pin">
            
            <div class="form-group">
                <label for="pin_code">6-Digit PIN Code *</label>
                <input type="text" id="pin_code" name="pin_code" required 
                       maxlength="6" pattern="[0-9]{6}" 
                       class="pin-input" placeholder="000000"
                       title="Enter the 6-digit PIN from your email">
                <small>Enter the 6-digit PIN sent to your email address</small>
            </div>
            
            <button type="submit" class="btn">Verify & Complete Registration</button>
        </form>
        
        <form action="" method="POST" style="margin-top: 15px;">
            <input type="hidden" name="action" value="resend_pin">
            <button type="submit" class="btn btn-secondary">Resend PIN</button>
        </form>
        
        <div class="login-link">
            <a href="register.php">← Back to Registration</a>
        </div>
    </div>

    <script>
        // Timer countdown
        let timeLeft = 15 * 60; // 15 minutes in seconds
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('timer').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft > 0) {
                timeLeft--;
                setTimeout(updateTimer, 1000);
            } else {
                document.getElementById('timer').textContent = 'EXPIRED';
                alert('PIN has expired. Please request a new one.');
            }
        }
        
        // Start timer
        updateTimer();
        
        // Auto-focus on PIN input
        document.getElementById('pin_code').focus();
        
        // Auto-tab between PIN digits (optional enhancement)
        document.getElementById('pin_code').addEventListener('input', function(e) {
            if (this.value.length === 6) {
                this.blur();
            }
        });
    </script>
</body>
</html>
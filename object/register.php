<?php
session_start();
require_once '../class/system.php';
require_once '../class/database.php';
require_once 'entities.php';
require_once 'email_service.php';

function initializeDatabase() {
    $db = DatabaseService::getInstance()->getConnection();
    
}
initializeDatabase();

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $_POST = sanitizeInput($_POST);
    
    // Basic validation
    if (empty($_POST['username']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['confirm_password'])) {
        $error = "All fields are required!";
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match!";
    } elseif (strlen($_POST['password']) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        try {
            $userModel = new User();
            $emailService = new EmailService();
            
            // Check if username or email already exists
            $db = DatabaseService::getInstance()->getConnection();
            $checkStmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$_POST['username'], $_POST['email']]);
            $existingUser = $checkStmt->fetch();
            
            if ($existingUser) {
                $error = "Username or email already exists!";
            } else {
                // Generate PIN code for verification
                $pinCode = '';
                $emailSent = $emailService->verifyEmail($_POST['email'], $_POST['username'], $pinCode);
                
                if ($emailSent) {
                    // Store verification data in session for PIN verification
                    $_SESSION['pending_verification'] = [
                        'username' => $_POST['username'],
                        'email' => $_POST['email'],
                        'password' => $_POST['password'],
                        'phone_number' => !empty($_POST['phone_number']) ? $_POST['phone_number'] : null,
                        'pin_code' => $pinCode,
                        'pin_expiry' => time() + (15 * 60) // 15 minutes expiry
                    ];
                    
                    $success = "Registration initiated! Please check your email for the 6-digit verification PIN to complete your registration.";
                    // Redirect to PIN verification page
                    header('Location: verify_pin.php');
                    exit;
                } else {
                    $error = "Failed to send verification email. Please try again.";
                }
            }
        } catch (Exception $e) {
            $error = "Registration error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost and Found System - Register</title>
    <link rel="stylesheet" href="styles/register.css">
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>Welcome!</h1>
            <p>Create your account</p>
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
            <input type="hidden" name="action" value="register">
            
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>" 
                       placeholder="Choose a username">
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>" 
                       placeholder="Enter your email">
            </div>
            
            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="tel" id="phone_number" name="phone_number" 
                       value="<?php echo isset($_POST['phone_number']) ? $_POST['phone_number'] : ''; ?>" 
                       placeholder="Optional phone number">
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Enter your password (min. 6 characters)">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required 
                       placeholder="Confirm your password">
            </div>
            
            <button type="submit" class="btn">Register</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>
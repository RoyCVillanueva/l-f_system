<?php
session_start();
require_once '../class/system.php';
require_once '../class/database.php';
require_once 'entities.php';

function initializeDatabase() {
    $db = DatabaseService::getInstance()->getConnection();
    
    // Insert default data (only categories, no demo accounts)
    $defaultData = [
        "INSERT IGNORE INTO category (category_id, category_name) VALUES 
        (1, 'Electronics'),
        (2, 'Clothing'),
        (3, 'Accessories'),
        (4, 'Documents'),
        (5, 'Jewelry'),
        (6, 'Keys'),
        (7, 'Bags'),
        (8, 'Books')"
    ];
    
    foreach ($defaultData as $query) {
        try {
            $db->exec($query);
        } catch (PDOException $e) {
            // Data might already exist, continue
        }
    }
    // Check if admin account exists, if not create one
try {
        $checkAdmin = $db->prepare("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
        $checkAdmin->execute();
        $adminExists = $checkAdmin->fetch();
        
        if (!$adminExists) {
            $adminId = 'ADM001';
            $adminUsername = 'admin';
            $adminEmail = 'admin@lostfound.com';
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $adminPhone = '+1234567890';
            
            $createAdmin = $db->prepare("
                INSERT INTO users (user_id, username, email, password_hash, phone_number, role, email_verified) 
                VALUES (?, ?, ?, ?, ?, 'admin', 1)
            ");
            
            if ($createAdmin->execute([$adminId, $adminUsername, $adminEmail, $adminPassword, $adminPhone])) {
                error_log("Admin account created successfully: admin / admin123");
            } else {
                error_log("Failed to create admin account");
            }
        } else {
            error_log("Admin account already exists");
        }
    } catch (PDOException $e) {
        error_log("Admin creation check error: " . $e->getMessage());
    }
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
    if ($_SESSION['role'] === 'admin') {
        header('Location: dashboard.php?tab=admin');
    } else {
        header('Location: dashboard.php?tab=report-item');
    }
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $_POST = sanitizeInput($_POST);
    
    $username = $_POST['username'];
    $password = $_POST['password']; // Define the password variable
    
    $userModel = new User();
    $user = $userModel->getUserByUsername($username);

    if ($user && password_verify($password, $user['password_hash'])) {
        // Store verification status in session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email_verified'] = $user['email_verified']; // Store verification status
        
        // Update last login
        $db = DatabaseService::getInstance()->getConnection();
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $updateStmt->execute([$user['user_id']]);
        
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost and Found System - Login</title>
    <link rel="stylesheet" href="styles/login.css">
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Welcome!</h1>
            <p>Please login to continue</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required placeholder="Enter your username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>
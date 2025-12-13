<?php
require_once '../class/system.php';
require_once '../class/database.php';

// Include PHPMailer
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailService {
    private $systemConfig;
    private $mail;
    
    public function __construct() {
        $this->systemConfig = new SystemConfig();
        $this->mail = new PHPMailer(true);
        $this->setupMailer();
    }
    
    private function setupMailer() {
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host       = 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = 'roychristiansilayvillanueva@gmail.com';
            $this->mail->Password   = 'oihiniiewjpjbzto'; // Consider using environment variables
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = 587;
            
            // Sender info
            $this->mail->setFrom('roychristiansilayvillanueva@gmail.com', 'Lost and Found System');
            $this->mail->addReplyTo('roychristiansilayvillanueva@gmail.com', 'Support');
            
            // Character set
            $this->mail->CharSet = 'UTF-8';
            
            // Debugging (enable for troubleshooting)
            if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
                $this->mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
                $this->mail->Debugoutput = 'error_log';
            } else {
                $this->mail->SMTPDebug = 0; // Disable in production
            }
            
        } catch (Exception $e) {
            error_log("PHPMailer setup error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function verifyEmail($email, $username, &$pinCode) {
    // Generate 6-digit PIN
    $pinCode = sprintf("%06d", mt_rand(1, 999999));
    
    $subject = "Email Verification PIN - Lost and Found System";
    $message = "
        <html>
        <head>
            <title>Email Verification</title>
        </head>
        <body>
            <h2>Email Verification</h2>
            <p>Hello $username,</p>
            <p>Your verification PIN is: <strong>$pinCode</strong></p>
            <p>This PIN will expire in 15 minutes.</p>
            <p>If you didn't request this, please ignore this email.</p>
        </body>
        </html>
    ";
    
    return $this->sendEmail($email, $subject, $message);
}
    
    public function sendWelcomeEmail($email, $username) {
        try {
            $subject = "Welcome to Lost and Found System!";
            $message = $this->getWelcomeEmailTemplate($username);
            
            return $this->sendEmail($email, $subject, $message);
            
        } catch (Exception $e) {
            error_log("Error sending welcome email: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendEmail($to, $subject, $message) {
        try {
            // Reset recipients for new email
            $this->mail->clearAddresses();
            $this->mail->clearReplyTos();
            
            $this->mail->addAddress($to);
            
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $message;
            $this->mail->AltBody = strip_tags($message);
            
            $this->mail->send();
            error_log("Email sent successfully to: " . $to);
            return true;
            
        } catch (Exception $e) {
            error_log("PHPMailer Error for {$to}: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    // ... rest of your existing methods (getPinEmailBody, getWelcomeEmailTemplate) remain the same
    public function getPinEmailBody($recipientName, $pinCode) {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background-color: #f5f5f5;
                    padding: 20px;
                }

                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }

                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 40px 20px;
                    text-align: center;
                    color: white;
                }

                .header h1 {
                    font-size: 24px;
                    margin-bottom: 10px;
                }

                .header p {
                    font-size: 14px;
                    opacity: 0.9;
                }

                .content {
                    padding: 40px 30px;
                    text-align: center;
                }

                .greeting {
                    font-size: 18px;
                    color: #333;
                    margin-bottom: 20px;
                }

                .message {
                    font-size: 14px;
                    color: #666;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }

                .pin-container {
                    background: #f8f9fa;
                    border: 2px dashed #667eea;
                    border-radius: 10px;
                    padding: 30px;
                    margin: 30px 0;
                }

                .pin-label {
                    font-size: 12px;
                    color: #666;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    margin-bottom: 15px;
                }

                .pin-code {
                    font-size: 48px;
                    font-weight: bold;
                    color: #667eea;
                    letter-spacing: 8px;
                    font-family: 'Courier New', monospace;
                }

                .warning {
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 15px;
                    margin: 20px 0;
                    text-align: left;
                }

                .warning-title {
                    font-weight: bold;
                    color: #856404;
                    margin-bottom: 5px;
                    font-size: 14px;
                }

                .warning-text {
                    color: #856404;
                    font-size: 13px;
                    line-height: 1.5;
                }

                .expiry {
                    font-size: 13px;
                    color: #dc3545;
                    margin-top: 15px;
                    font-weight: 600;
                }

                .footer {
                    background: #f8f9fa;
                    padding: 20px;
                    text-align: center;
                    border-top: 1px solid #e0e0e0;
                }

                .footer p {
                    font-size: 12px;
                    color: #999;
                    line-height: 1.5;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <div style='font-size: 48px; margin-bottom: 10px;'>🔐</div>
                    <h1>Verification Code</h1>
                    <p>Lost and Found System</p>
                </div>

                <div class='content'>
                    <p class='greeting'>Hello, <strong>{$recipientName}</strong>!</p>

                    <p class='message'>
                        We received a request to verify your account. 
                        Please use the PIN code below to complete your verification.
                    </p>

                    <div class='pin-container'>
                        <div class='pin-label'>Your Verification PIN</div>
                        <div class='pin-code'>{$pinCode}</div>
                    </div>

                    <div class='warning'>
                        <div class='warning-title'>Security Notice</div>
                        <div class='warning-text'>
                            • Never share this PIN with anyone<br>
                            • Our team will never ask for your PIN<br>
                            • If you didn't request this code, please ignore this email
                        </div>
                    </div>

                    <p class='expiry'>This PIN will expire in 15 minutes.</p>

                    <p class='message'>
                        If you have any questions, please contact our support team.
                    </p>
                </div>

                <div class='footer'>
                    <p>
                        <strong>Lost and Found System</strong><br>
                        This is an automated email. Please do not reply to this message.<br>
                        © " . date('Y') . " Lost and Found System. All rights reserved.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getWelcomeEmailTemplate($username) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Welcome Aboard!</h1>
                </div>
                <div class='content'>
                    <h2>Hello, {$username}!</h2>
                    <p>Your email has been successfully verified and your account is now active!</p>
                    
                    <h3>What you can do:</h3>
                    <ul>
                        <li>📝 Report lost items</li>
                        <li>🔍 Browse found items</li>
                        <li>✅ Claim items you've lost</li>
                        <li>📊 Track your reports</li>
                    </ul>
                    
                    <p>Get started by logging into your account and exploring the features.</p>
                    
                    <p>If you have any questions, feel free to contact our support team.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Lost and Found System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>
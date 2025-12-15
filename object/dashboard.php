<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../class/system.php';
require_once '../class/database.php';
require_once 'entities.php';

// Email verification check
$isAdmin = $_SESSION['role'] === 'admin';
$current_user_id = $_SESSION['user_id'];

// Check if email is verified - Skip verification for admin users only
if ($isAdmin) {
    // Admin users bypass email verification
    $verification_required = false;
} else {
    // For regular users, check if email is verified
    // First check session, then database if needed
    if (isset($_SESSION['email_verified']) && $_SESSION['email_verified']) {
        $verification_required = false;
    } else {
        $user = new User();
        $isVerified = $user->isEmailVerified($current_user_id);
        
        if ($isVerified) {
            // Update session for future requests
            $_SESSION['email_verified'] = true;
            $verification_required = false;
        } else {
            $verification_required = true;
        }
    }
}

// Check for verification success
$verification_success = false;
if (isset($_SESSION['verification_success']) && $_SESSION['verification_success']) {
    $verification_success = true;
    unset($_SESSION['verification_success']); // Clear the flag so it only shows once
}

// Only initialize database and continue if email is verified OR user is admin
if (!$verification_required) {
    function initializeDatabase() {
        $db = DatabaseService::getInstance()->getConnection();
        
        // Insert default categories
        $defaultData = [
            "INSERT IGNORE INTO category (category_id, category_name) VALUES 
            (1, 'Electronics'),
            (2, 'Clothing'),
            (3, 'Accessories'),
            (4, 'Documents'),
            (5, 'Jewelry'),
            (6, 'Keys'),
            (7, 'Bags'),
            (8, 'Books'),
            (9, 'Cash')"
        ];
        
        foreach ($defaultData as $query) {
            try {
                $db->exec($query);
            } catch (PDOException $e) {
                // Data might already exist
            }
        }
    }

    initializeDatabase();
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function debug($data, $label = 'DEBUG') {
    error_log("[$label] " . print_r($data, true));
    echo "<!-- [$label] " . htmlspecialchars(print_r($data, true)) . " -->";
}

function handleMultipleFileUpload() {
    $uploadedPaths = [];
    
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = SystemConfig::UPLOAD_DIR;
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                $filePath = $uploadDir . $fileName;
                
                // Validate file type and size
                $fileType = mime_content_type($tmpName);
                $fileSize = $_FILES['images']['size'][$key];
                
                if (in_array($fileType, SystemConfig::ALLOWED_FILE_TYPES) && 
                    $fileSize <= SystemConfig::MAX_FILE_SIZE) {
                    
                    if (move_uploaded_file($tmpName, $filePath)) {
                        $uploadedPaths[] = $filePath;
                    }
                }
            }
        }
    }
    
    return $uploadedPaths;
}

$isAdmin = $_SESSION['role'] === 'admin';
$current_user_id = $_SESSION['user_id'];

// Load user reports for My Reports tab
if (!$verification_required) {
    $report = new Report();
    $userReports = $report->getReportsByUserId($current_user_id);
}

// Load user claims for Track Claims tab
if (!$verification_required) {
    $claim = new Claim();
    $userClaims = $claim->getClaimsByUserId($current_user_id);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_POST = sanitizeInput($_POST);
    
    // Debug: Log all POST data
    error_log("Form submitted with action: " . ($_POST['action'] ?? 'NO ACTION'));
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_item':
                AddItem();
                break;
            case 'submit_claim':
                SubmitClaim();
                break;
            case 'update_claim_status':
                UpdateClaimStatus();
                break;
            case 'handover_item':
                HandoverItem();
                break;
            case 'update_report':
                UpdateReport();
                break;
            case 'delete_report':
                DeleteReport();
                break;
            case 'update_report_status':
                UpdateReportStatus();
                break;
            case 'confirm_return':
                ConfirmReturn();
                break;
            case 'confirm_found_return':
                ConfirmFoundReturn();
                break;
            case 'mark_notification_read':
                MarkNotificationRead();
                break;
            case 'mark_all_notifications_read':
                MarkAllNotificationsRead();
                break;
            case 'generate_report':
                GenerateReport();
                break;
            case 'mark_as_found':
                MarkAsFound();
                break;
            default:
                error_log("Unknown action: " . $_POST['action']);
                break;
        }
    }
}

function AddItem() {
    global $current_user_id;
    
    $dbService = DatabaseService::getInstance();
    
    try {
        $dbService->beginTransaction();
        
        // Debug: Check if user exists
        $userCheck = $dbService->getConnection()->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $userCheck->execute([$current_user_id]);
        $userExists = $userCheck->fetch();
        
        if (!$userExists) {
            throw new Exception("User with ID '$current_user_id' does not exist in the database.");
        }
        
        error_log("User exists: " . $current_user_id);
        
        $item = new Item();
        $report = new Report();
        $location = new Location();
        $itemImage = new ItemImage();
        
        $itemId = $item->generateItemId();
        $reportId = $report->generateReportId();

        // Debug: Log generated IDs
        error_log("Generated Item ID: " . $itemId);
        error_log("Generated Report ID: " . $reportId);

        // Handle multiple file uploads
        $imagePaths = handleMultipleFileUpload();
        
        $locationName = $_POST['location_name'];
        $locationId = $location->getLocationIdByName($locationName);
        
        error_log("Location Name: " . $locationName);
        error_log("Location ID: " . $locationId);
        
        if (!$locationId) {
            $locationId = $location->generateLocationId();
            error_log("Creating new location with ID: " . $locationId);
            $location->create($locationId, $locationName);
        }
        
        // Debug: Log all values before insertion
        error_log("Creating item with:");
        error_log("  item_id: " . $itemId);
        error_log("  description: " . $_POST['description']);
        error_log("  category_id: " . $_POST['category_id']);
        error_log("  location_id: " . $locationId);
        error_log("  reported_by: " . $current_user_id);
        
        // Create item with logged-in user as reporter
        $item->create($itemId, $_POST['description'], $_POST['category_id'], $locationId, $current_user_id);
        error_log("Item created successfully");
        
        // Save images to database
        if (!empty($imagePaths)) {
            foreach ($imagePaths as $imagePath) {
                $imageId = $itemImage->generateImageId();
                $itemImage->create($imageId, $itemId, $imagePath);
            }
            error_log("Saved " . count($imagePaths) . " images");
        }
        
        // Create report with report type (lost/found) and dates
        $reportType = $_POST['report_type'];
        $date_lost = ($reportType == 'lost') ? $_POST['date_lost'] : null;
        $date_found = ($reportType == 'found') ? $_POST['date_found'] : null;

        error_log("Creating report with type: " . $reportType);
        error_log("Date lost: " . $date_lost);
        error_log("Date found: " . $date_found);

        $report->create($reportId, 'pending', $reportType, $itemId, $current_user_id, $date_lost, $date_found);
        
        $dbService->commit();
        
        $message = 'Item reported successfully as ' . $reportType . '! Report ID: ' . $reportId;
        if (count($imagePaths) > 0) {
            $message .= ' with ' . count($imagePaths) . ' images';
        }
        
        $_SESSION['message'] = $message;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=browse-items');
        exit;
        
    } catch (Exception $e) {
        $dbService->rollBack();
        error_log("Error adding item: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $_SESSION['error'] = 'Failed to report item: ' . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Function to update report
function UpdateReport() {
    global $current_user_id;
    
    $dbService = DatabaseService::getInstance();
    
    try {
        $dbService->beginTransaction();
        
        $report_id = $_POST['report_id'];
        $item_id = $_POST['item_id'];
        
        // Verify ownership
        $report = new Report();
        $reportDetails = $report->getReportById($report_id);
        
        if (!$reportDetails || $reportDetails['user_id'] != $current_user_id) {
            throw new Exception("You don't have permission to edit this report.");
        }
        
        if ($reportDetails['status'] == 'returned' || $reportDetails['status'] == 'confirmed') {
            throw new Exception("Cannot edit report that has been " . $reportDetails['status'] . ".");
        }
        
        $item = new Item();
        $location = new Location();
        $itemImage = new ItemImage();
        
        // Update location
        $locationName = $_POST['location_name'];
        $locationId = $location->getLocationIdByName($locationName);
        
        if (!$locationId) {
            $locationId = $location->generateLocationId();
            $location->create($locationId, $locationName);
        }
        
        // Update item
        $item->update($item_id, $_POST['description'], $_POST['category_id'], $locationId);
        
        // Handle image deletions
        if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $image_id) {
                $itemImage->delete($image_id);
            }
        }
        
        // Handle new image uploads
        $newImagePaths = handleMultipleFileUpload();
        if (!empty($newImagePaths)) {
            foreach ($newImagePaths as $imagePath) {
                $imageId = $itemImage->generateImageId();
                $itemImage->create($imageId, $item_id, $imagePath);
            }
        }
        
        // Update report timestamp
        $report->updateTimestamp($report_id);
        
        $dbService->commit();
        
        $_SESSION['message'] = 'Report updated successfully!';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=my-reports');
        exit;
        
    } catch (Exception $e) {
        $dbService->rollBack();
        error_log("Error updating report: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update report: ' . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=edit-report&id=' . $report_id);
        exit;
    }
}

// Function to delete report with proper constraint handling
function DeleteReport() {
    global $current_user_id;
    
    $dbService = DatabaseService::getInstance();
    
    try {
        $dbService->beginTransaction();
        
        $report_id = $_POST['report_id'];
        
        // Verify ownership and status
        $report = new Report();
        $reportDetails = $report->getReportById($report_id);
        
        if (!$reportDetails || $reportDetails['user_id'] != $current_user_id) {
            throw new Exception("You don't have permission to delete this report.");
        }
        
        if (!in_array($reportDetails['status'], ['pending', 'confirmed'])) {
            throw new Exception("Can only delete reports with pending or confirmed status.");
        }
        
        // Check if there are any claims
        $claim = new Claim();
        $claims = $claim->getClaimsByReportId($report_id);
        
        if (!empty($claims)) {
            throw new Exception("Cannot delete report that has claims.");
        }
        
        // Delete the report with proper constraint handling
        if ($report->deleteWithConstraints($report_id)) {
            $dbService->commit();
            $_SESSION['message'] = 'Report deleted successfully!';
        } else {
            $dbService->rollBack();
            $_SESSION['error'] = 'Failed to delete report.';
        }
        
    } catch (Exception $e) {
        $dbService->rollBack();
        error_log("Error deleting report: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to delete report: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=my-reports');
    exit;
}

// Function to submit claim for found items with validation and image upload
function SubmitClaim() {
    global $current_user_id;
    
    try {
        $report_id = $_POST['report_id'];
        $description = $_POST['claim_description'];
        
        // Validate required fields
        if (empty($report_id) || empty($description)) {
            throw new Exception("Please provide all required information.");
        }
        
        $claim = new Claim();
        $report = new Report();
        
        // Get report details
        $reportDetails = $report->getReportById($report_id);
        if (!$reportDetails) {
            throw new Exception("Report not found.");
        }
        
        // Validation checks
        if ($reportDetails['report_type'] != 'found') {
            throw new Exception("Can only claim found items.");
        }
        
        if ($reportDetails['user_id'] == $current_user_id) {
            throw new Exception("You cannot claim your own found item.");
        }
        
        if ($reportDetails['status'] == 'returned') {
            throw new Exception("This item has already been returned to its owner.");
        }
        
        // Check for existing approved claims
        $existingClaims = $claim->getClaimsByReportId($report_id);
        $hasApprovedClaim = false;
        $userHasPendingClaim = false;
        
        foreach ($existingClaims as $existingClaim) {
            if ($existingClaim['status'] == 'approved' || $existingClaim['status'] == 'completed') {
                $hasApprovedClaim = true;
            }
            if ($existingClaim['claimed_by'] == $current_user_id) {
                if ($existingClaim['status'] == 'pending') {
                    $userHasPendingClaim = true;
                } elseif ($existingClaim['status'] == 'approved') {
                    throw new Exception("You already have an approved claim for this item.");
                } elseif ($existingClaim['status'] == 'rejected') {
                    // Allow user to submit new claim if previous was rejected
                    continue;
                }
            }
        }
        
        if ($hasApprovedClaim) {
            throw new Exception("This item already has an approved claim.");
        }
        
        if ($userHasPendingClaim) {
            throw new Exception("You already have a pending claim for this item.");
        }
        
        // All validations passed, create the claim
        $claimId = $claim->generateClaimId();
        
        error_log("Submitting claim: ID={$claimId}, Report={$report_id}, User={$current_user_id}");
        
        $result = $claim->create($claimId, 'pending', $report_id, $current_user_id, $description);
        
        if ($result) {
            // Handle claim image uploads
            $uploadedImages = handleClaimImageUpload($claimId);
            
            if (!empty($uploadedImages)) {
                error_log("Uploaded " . count($uploadedImages) . " images for claim {$claimId}");
                // Here you would typically save image references to database
                // You'll need to create a new function in the Claim class or create a new class
                saveClaimImages($claimId, $uploadedImages);
            }
            
            error_log("Claim submitted successfully: {$claimId}");
            $_SESSION['message'] = 'Claim submitted successfully! Claim ID: ' . $claimId;
        } else {
            error_log("Claim submission failed");
            $_SESSION['error'] = 'Failed to submit claim. Please try again.';
        }
        
    } catch (Exception $e) {
        error_log("Error submitting claim: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to submit claim: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=browse-items');
    exit;
}

// New function to handle claim image uploads
function handleClaimImageUpload($claimId) {
    $uploadedPaths = [];
    
    if (!empty($_FILES['claim_images']['name'][0])) {
        // Create claim-specific upload directory
        $uploadDir = SystemConfig::UPLOAD_DIR . 'claims/' . $claimId . '/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Limit to 5 images
        $fileCount = count($_FILES['claim_images']['name']);
        if ($fileCount > 5) {
            throw new Exception("Maximum 5 images allowed for claims.");
        }
        
        foreach ($_FILES['claim_images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['claim_images']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = uniqid() . '_' . basename($_FILES['claim_images']['name'][$key]);
                $filePath = $uploadDir . $fileName;
                
                // Validate file type and size
                $fileType = mime_content_type($tmpName);
                $fileSize = $_FILES['claim_images']['size'][$key];
                
                if (in_array($fileType, SystemConfig::ALLOWED_FILE_TYPES) && 
                    $fileSize <= SystemConfig::MAX_FILE_SIZE) {
                    
                    if (move_uploaded_file($tmpName, $filePath)) {
                        $uploadedPaths[] = [
                            'path' => $filePath,
                            'name' => $_FILES['claim_images']['name'][$key],
                            'size' => $fileSize
                        ];
                    }
                } else {
                    throw new Exception("Invalid file type or size for image: " . $_FILES['claim_images']['name'][$key]);
                }
            }
        }
    }
    
    return $uploadedPaths;
}

// Function to save claim images to database
function saveClaimImages($claimId, $images) {
    try {
        $db = DatabaseService::getInstance()->getConnection();
        
        foreach ($images as $image) {
            $imageId = uniqid('claim_img_', true);
            $stmt = $db->prepare("INSERT INTO claim_images (image_id, claim_id, image_path, file_name, file_size) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$imageId, $claimId, $image['path'], $image['name'], $image['size']]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error saving claim images: " . $e->getMessage());
        return false;
    }
}

// Function to confirm return of lost item
function ConfirmReturn() {
    global $current_user_id;
    
    try {
        $report_id = $_POST['report_id'];
        
        if (empty($report_id)) {
            throw new Exception("Report ID is required.");
        }
        
        $report = new Report();
        $result = $report->confirmReturn($report_id, $current_user_id);
        
        if ($result && $result['success']) {
            $_SESSION['message'] = 'Item successfully marked as returned!';
            if ($result['claimant_id']) {
                $_SESSION['message'] .= ' Claimant: ' . $result['claimant_id'];
            }
        } else {
            throw new Exception("Failed to confirm return.");
        }
        
    } catch (Exception $e) {
        error_log("Error confirming return: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to confirm return: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=my-reports');
    exit;
}

// Function to confirm return of found item (by owner who lost it)
function ConfirmFoundReturn() {
    global $current_user_id;
    
    try {
        $report_id = $_POST['report_id'];
        
        if (empty($report_id)) {
            throw new Exception("Report ID is required.");
        }
        
        $report = new Report();
        $reportDetails = $report->getReportById($report_id);
        
        // Check if report exists and is a found item
        if (!$reportDetails) {
            throw new Exception("Report not found.");
        }
        
        if ($reportDetails['report_type'] != 'found') {
            throw new Exception("Only found items can use this return confirmation.");
        }
        
        // Check if there's an approved claim by current user
        $claim = new Claim();
        $claims = $claim->getClaimsByReportId($report_id);
        
        $userHasApprovedClaim = false;
        foreach ($claims as $itemClaim) {
            if ($itemClaim['status'] == 'approved' && $itemClaim['claimed_by'] == $current_user_id) {
                $userHasApprovedClaim = true;
                break;
            }
        }
        
        if (!$userHasApprovedClaim) {
            throw new Exception("You don't have an approved claim for this item.");
        }
        
        // Update report status to returned
        $result = $report->updateStatus($report_id, 'returned');
        
        if ($result) {
            // Update claim status to completed
            foreach ($claims as $itemClaim) {
                if ($itemClaim['status'] == 'approved' && $itemClaim['claimed_by'] == $current_user_id) {
                    $claim->updateStatus($itemClaim['claim_id'], 'completed', 'Item returned to owner.');
                    break;
                }
            }
            
            $_SESSION['message'] = 'Item successfully marked as returned!';
        } else {
            throw new Exception("Failed to update item status.");
        }
        
    } catch (Exception $e) {
        error_log("Error confirming found item return: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to confirm return: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=my-reports');
    exit;
}

function UpdateClaimStatus() {
    global $isAdmin, $current_user_id;
    
    error_log("UpdateClaimStatus function called");
    
    if (!$isAdmin) {
        $_SESSION['error'] = 'Access denied. Admin rights required.';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=admin');
        exit;
    }
    
    try {
        // Validate required fields
        if (empty($_POST['claim_id']) || empty($_POST['status'])) {
            throw new Exception("Missing required fields: claim_id or status");
        }
        
        $claim = new Claim();
        $report = new Report();
        
        $claimId = $_POST['claim_id'];
        $status = $_POST['status'];
        $adminNotes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
        
        error_log("Updating claim {$claimId} to status {$status}");
        
        // Verify the claim exists
        $claimDetails = $claim->getClaimById($claimId);
        if (!$claimDetails) {
            throw new Exception("Claim not found with ID: {$claimId}");
        }
        
        $reportId = $claimDetails['report_id'];
        
        // If approving a claim, check for other approved claims
        if ($status === 'approved') {
            $existingClaims = $claim->getClaimsByReportId($reportId);
            $otherApprovedClaims = [];
            
            foreach ($existingClaims as $existingClaim) {
                if ($existingClaim['claim_id'] != $claimId && 
                    ($existingClaim['status'] == 'approved' || $existingClaim['status'] == 'completed')) {
                    $otherApprovedClaims[] = $existingClaim;
                }
            }
            
            // If there are other approved claims, reject them
            foreach ($otherApprovedClaims as $otherClaim) {
                error_log("Rejecting previously approved claim: " . $otherClaim['claim_id']);
                $claim->updateStatus($otherClaim['claim_id'], 'rejected', 
                    'Another claim was approved for this item.');
                $claim->sendClaimRejectedNotification($otherClaim['claim_id'], 'Another claim was approved for this item.');
            }
        }
        
        // Update the current claim status
        $result = $claim->updateStatus($claimId, $status, $adminNotes);
        
        if ($result) {
            error_log("Claim status updated successfully");
            
            // Send notifications based on claim status
            if ($status === 'approved') {
                $claim->sendClaimApprovedNotification($claimId);
    
                // Check if this is a found item - don't automatically mark as returned
                $reportDetails = $report->getReportById($reportId);
    
                if ($reportDetails['report_type'] == 'found') {
                    // For found items, just update status to confirmed
                    $report->updateStatus($reportId, 'confirmed');
                    $_SESSION['message'] = 'Claim approved successfully! The item owner can now confirm return.';
                } else {
                    // For lost items, mark as returned (owner already confirmed)
                    $report->updateStatus($reportId, 'returned');
                    $_SESSION['message'] = 'Claim approved successfully! Item marked as returned.';
                }
            } elseif ($status === 'rejected') {
                $claim->sendClaimRejectedNotification($claimId, $adminNotes);
                // Check if there are any other approved claims
                $remainingClaims = $claim->getClaimsByReportId($reportId);
                $hasApprovedClaim = false;
                
                foreach ($remainingClaims as $remainingClaim) {
                    if ($remainingClaim['status'] == 'approved' || $remainingClaim['status'] == 'completed') {
                        $hasApprovedClaim = true;
                        break;
                    }
                }
                
                // If no approved claims remain, set report back to pending
                if (!$hasApprovedClaim) {
                    error_log("No approved claims remaining, setting report back to pending");
                    $report->updateStatus($reportId, 'pending');
                }
                
                $_SESSION['message'] = 'Claim rejected successfully!';
            }
        } else {
            throw new Exception("Database update failed");
        }
        
    } catch (Exception $e) {
        error_log("Error updating claim status: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update claim status: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=admin');
    exit;
}

function UpdateReportStatus() {
    global $isAdmin;
    
    if (!$isAdmin) {
        $_SESSION['error'] = 'Access denied. Admin rights required.';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=admin');
        exit;
    }
    
    try {
        $report_id = $_POST['report_id'];
        $status = $_POST['status'];
        
        $report = new Report();
        $result = $report->updateStatus($report_id, $status);
        
        if ($result) {
            // Send notification when report is confirmed
            if ($status === 'confirmed') {
                $report->sendReportConfirmedNotification($report_id, $_SESSION['user_id']);
            }
            
            $_SESSION['message'] = 'Report status updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to update report status.';
        }
        
    } catch (Exception $e) {
        error_log("Error updating report status: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update report status: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=admin');
    exit;
}

// Function to mark notification as read
function MarkNotificationRead() {
    global $current_user_id;
    
    try {
        $notification_id = $_POST['notification_id'];
        
        if (empty($notification_id)) {
            throw new Exception("Notification ID is required.");
        }
        
        $notification = new Notification();
        $result = $notification->markAsRead($notification_id, $current_user_id);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Failed to mark notification as read.");
        }
        
    } catch (Exception $e) {
        error_log("Error marking notification read: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Function to mark all notifications as read
function MarkAllNotificationsRead() {
    global $current_user_id;
    
    try {
        $notification = new Notification();
        $result = $notification->markAllAsRead($current_user_id);
        
        if ($result) {
            $_SESSION['message'] = 'All notifications marked as read!';
        } else {
            throw new Exception("Failed to mark notifications as read.");
        }
        
    } catch (Exception $e) {
        error_log("Error marking all notifications read: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to mark notifications as read: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=notifications');
    exit;
}

function GenerateReport() {
    try {
        $startDate = $_POST['start_date'] ?? date('Y-m-01'); // Default to first day of current month
        $endDate = $_POST['end_date'] ?? date('Y-m-d'); // Default to today
        $reportType = $_POST['report_type'] ?? 'all';
        $format = $_POST['format'] ?? 'view'; // view, pdf, csv
        
        $stats = new Statistics();
        
        // Get report data
        $items = $stats->getItemsByDateRange($startDate, $endDate, 
                $reportType !== 'all' ? $reportType : null);
        $summary = $stats->getSummaryByDateRange($startDate, $endDate);
        $categories = $stats->getCategoriesByDateRange($startDate, $endDate);
        
        // Store report data in session for display
        $_SESSION['report_data'] = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'report_type' => $reportType,
            'items' => $items,
            'summary' => $summary,
            'categories' => $categories,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        // Handle different output formats
        if ($format === 'csv') {
            exportReportToCSV($_SESSION['report_data']);
            exit;
        } elseif ($format === 'pdf') {
            exportReportToPDF($_SESSION['report_data']);
            exit;
        } else {
            $_SESSION['message'] = "Report generated successfully!";
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=statistics#report-results');
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Error generating report: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to generate report: ' . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=statistics');
        exit;
    }
}

function exportReportToCSV($reportData) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lostfound_report_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add header row
    fputcsv($output, ['Lost and Found System Report']);
    fputcsv($output, ['Date Range: ' . $reportData['start_date'] . ' to ' . $reportData['end_date']]);
    fputcsv($output, ['Generated: ' . $reportData['generated_at']]);
    fputcsv($output, []); // Empty row
    
    // Summary section
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Type', 'Total', 'Returned', 'Confirmed', 'Pending']);
    
    $lostSummary = $foundSummary = ['Total' => 0, 'Returned' => 0, 'Confirmed' => 0, 'Pending' => 0];
    foreach ($reportData['summary'] as $summary) {
        if ($summary['report_type'] === 'lost') {
            $lostSummary = $summary;
        } else {
            $foundSummary = $summary;
        }
    }
    
    fputcsv($output, ['Lost', $lostSummary['count'] ?? 0, $lostSummary['returned_count'] ?? 0, 
                      $lostSummary['confirmed_count'] ?? 0, $lostSummary['pending_count'] ?? 0]);
    fputcsv($output, ['Found', $foundSummary['count'] ?? 0, $foundSummary['returned_count'] ?? 0, 
                      $foundSummary['confirmed_count'] ?? 0, $foundSummary['pending_count'] ?? 0]);
    fputcsv($output, []); // Empty row
    
    // Items section
    fputcsv($output, ['DETAILED ITEMS LIST']);
    fputcsv($output, ['Report ID', 'Type', 'Status', 'Date', 'Description', 'Category', 'Location', 'Reporter']);
    
    foreach ($reportData['items'] as $item) {
        $dateField = $item['report_type'] === 'lost' ? $item['date_lost'] : $item['date_found'];
        $date = $dateField ? date('Y-m-d', strtotime($dateField)) : date('Y-m-d', strtotime($item['report_date']));
        
        fputcsv($output, [
            $item['report_id'],
            ucfirst($item['report_type']),
            ucfirst($item['status']),
            $date,
            $item['description'],
            $item['category_name'],
            $item['location_name'],
            $item['reporter']
        ]);
    }
    
    fclose($output);
    exit;
}

function MarkAsFound() {
    global $current_user_id;
    
    try {
        $report_id = $_POST['report_id'];
        
        if (empty($report_id)) {
            throw new Exception("Report ID is required.");
        }
        
        $report = new Report();
        $reportDetails = $report->getReportById($report_id);
        
        // Check if report exists and is a lost item
        if (!$reportDetails) {
            throw new Exception("Report not found.");
        }
        
        if ($reportDetails['report_type'] != 'lost') {
            throw new Exception("Only lost items can be marked as found.");
        }
        
        // Check if current user is the reporter
        if ($reportDetails['user_id'] != $current_user_id) {
            throw new Exception("Only the person who reported this lost item can mark it as found.");
        }
        
        // Check if item is already returned
        if ($reportDetails['status'] == 'returned') {
            throw new Exception("This item has already been marked as returned.");
        }
        
        // Update report status to returned
        $result = $report->updateStatus($report_id, 'returned');
        
        if ($result) {
            // Send notification if there are any pending claims
            $claim = new Claim();
            $claims = $claim->getClaimsByReportId($report_id);
            
            foreach ($claims as $pendingClaim) {
                if ($pendingClaim['status'] == 'pending') {
                    $claim->updateStatus($pendingClaim['claim_id'], 'rejected', 
                        'Item was found by the owner.');
                    $claim->sendClaimRejectedNotification($pendingClaim['claim_id'], 
                        'Item was found by the owner.');
                }
            }
            
            $_SESSION['message'] = 'Item successfully marked as found!';
        } else {
            throw new Exception("Failed to update item status.");
        }
        
    } catch (Exception $e) {
        error_log("Error marking item as found: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to mark item as found: ' . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=browse-items');
    exit;
}


if ($isAdmin) {
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'admin';
} else {
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'browse-items';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost and Found System - Home Page</title>
    <link rel="stylesheet" href="styles/dashboard.css">
</head>
<body>
    <div class="container">
<header class="<?php echo $verification_required ? 'verification-required-header' : ''; ?>">
    <h1>Lost and Found System</h1>
    <div class="user-info">
        <?php if (!$verification_required): ?>
            <!-- Show notification bell when verified or if admin -->
            <div class="notification-bell">
                <a href="?tab=notifications" class="notification-link">
                    <span class="bell-icon">🔔</span>
                    <?php
                    $notification = new Notification();
                    $unreadCount = $notification->getUnreadCount($current_user_id);
                    if ($unreadCount > 0): ?>
                        <span class="notification-badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        <?php endif; ?>
        
        Welcome, <?php echo $_SESSION['username']; ?>
        <?php if (!$verification_required): ?>
            <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
        <?php endif; ?>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</header>
        
<?php if ($verification_required && !$isAdmin): ?>
    <!-- Only show verification banner for regular users who need verification -->
    <div class="verification-banner warning">
        <div class="verification-header">
            <div class="verification-icon"></div>
            <div class="verification-content">
                <h3>Email Verification Required</h3>
                <p>Please verify your email address to access the Lost and Found System.</p>
                <?php if (isset($_SESSION['email'])): ?>
                    <p class="email-address"><strong>We've sent a 6-digit PIN code to: <?php echo $_SESSION['email']; ?></strong></p>
                <?php else: ?>
                    <p class="email-address"><strong>We've sent a 6-digit PIN code to your registered email address.</strong></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="verification-form">
            <form action="verify_pin_dashboard.php" method="POST" class="pin-form">
                <div class="pin-input-group">
                    <label for="pin_code">Enter 6-digit PIN:</label>
                    <div class="pin-input-container">
                        <input type="text" id="pin_code" name="pin_code"  
                               maxlength="6" pattern="[0-9]{6}" 
                               placeholder="000000" class="pin-input" required>
                        <span class="pin-input-hint">Enter the PIN sent to your email</span>
                    </div>
                </div>
                
                <div class="verification-buttons">
                    <button type="submit" class="btn-verify">Verify Email</button>
                    <a href="resend_pin_dashboard.php" class="btn-resend">Resend PIN</a>
                    <a href="logout.php" class="btn-logout">Logout</a>
                </div>
            </form>
            
            <?php if (isset($_SESSION['pin_error'])): ?>
                <div class="notification error">
                    <div class="error-icon">⚠️</div>
                    <div class="error-message">
                        <?php echo $_SESSION['pin_error']; unset($_SESSION['pin_error']); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="notification success">
                    <div class="success-icon">✅</div>
                    <div class="success-message">
                        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="verification-help">
            <h4>Need help?</h4>
            <ul>
                <li>Check your spam folder if you don't see the email</li>
                <li>Make sure you entered the correct email address during registration</li>
                <li>The PIN expires in 15 minutes</li>
                <li>Contact support if you continue having issues</li>
            </ul>
        </div>
    </div>
    
<?php else: ?>
    <!-- Show the full dashboard when verified OR if admin -->
    <?php if ($verification_success): ?>
    <div class="verification-success-banner success">
        <div class="success-icon">✅</div>
        <div class="success-content">
            <h3>Email Verified Successfully!</h3>
            <p>Your email has been verified and you now have full access to all system features.</p>
        </div>
        <button class="close-banner" onclick="this.parentElement.style.display='none'">×</button>
    </div>
    <?php endif; ?>
    
    <!-- Main dashboard content -->
<div class="tabs-container">
    <a href="?tab=browse-items" class="tab tab-browse-items <?php echo $active_tab == 'browse-items' ? 'active' : ''; ?>">
        Browse Items
    </a>
    <a href="?tab=report-item" class="tab tab-report-item <?php echo $active_tab == 'report-item' ? 'active' : ''; ?>">
        Report Item
    </a>
    <a href="?tab=my-reports" class="tab tab-my-reports <?php echo $active_tab == 'my-reports' ? 'active' : ''; ?>">
        My Reports
    </a>
    <a href="?tab=track-claims" class="tab tab-track-claims <?php echo $active_tab == 'track-claims' ? 'active' : ''; ?>">
        Track Claims
    </a>
    <a href="?tab=notifications" class="tab tab-notifications <?php echo $active_tab == 'notifications' ? 'active' : ''; ?>">
        Notifications
        <?php if ($unreadCount > 0): ?>
            <span class="tab-badge"><?php echo $unreadCount; ?></span>
        <?php endif; ?>
    </a>
    <?php if ($isAdmin): ?>
        <a href="?tab=admin" class="tab tab-admin <?php echo $active_tab == 'admin' ? 'active' : ''; ?>">
            Admin
        </a>
        <a href="?tab=statistics" class="tab tab-statistics <?php echo $active_tab == 'statistics' ? 'active' : ''; ?>">
            Statistics
        </a>
    <?php endif; ?>
</div>
    </div>
        
        <div class="tab-content">
            <?php if ($active_tab == 'report-item'): ?>
    <div class="form-section">
        <h2>Report Lost or Found Item</h2>
        
        <?php
        // Check if coming from "I Found This Item" button
        $prefilledData = [];
        if (isset($_GET['found_lost_item']) && isset($_GET['report_id'])) {
            $lostReportId = $_GET['report_id'];
            $report = new Report();
            $lostReport = $report->getReportById($lostReportId);
            
            if ($lostReport && $lostReport['report_type'] == 'lost') {
                // Get item details
                $item = new Item();
                $itemDetails = $item->getItemById($lostReport['item_id']);
                
                // Get location directly from the report details (it should be included in getReportById)
                // Since Location class doesn't have getLocationById(), use the location_name from report
                $locationName = isset($lostReport['location_name']) ? $lostReport['location_name'] : '';
                
                // If location_name is not in report, try to get it from location table using a direct query
                if (empty($locationName)) {
                    $db = DatabaseService::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT location_name FROM location WHERE location_id = ?");
                    $stmt->execute([$itemDetails['location_id']]);
                    $locationResult = $stmt->fetch(PDO::FETCH_ASSOC);
                    $locationName = $locationResult ? $locationResult['location_name'] : '';
                }
                
                // Set prefilled data
                $prefilledData = [
                    'description' => htmlspecialchars($itemDetails['description']),
                    'category_id' => $itemDetails['category_id'],
                    'location_name' => htmlspecialchars($locationName),
                    'report_type' => 'found',
                    'is_from_lost_report' => true,
                    'lost_report_id' => $lostReportId
                ];
                
                echo '<div class="notification info" style="margin-bottom: 20px;">';
                echo '<p><strong>Note: You are reporting a found item that matches a lost report.</strong></p>';
                echo '<p>The form has been pre-filled with details from the lost report (Report ID: ' . $lostReportId . ').</p>';
                echo '<p>Please verify all information is correct and update the location to where you actually found the item.</p>';
                echo '</div>';
            }
        }
        ?>
        
        <div class="report-type-selector">
            <button type="button" class="report-type-btn <?php echo (!isset($prefilledData['report_type']) || $prefilledData['report_type'] == 'lost') ? 'active' : ''; ?>" data-type="lost">I Lost an Item</button>
            <button type="button" class="report-type-btn <?php echo (isset($prefilledData['report_type']) && $prefilledData['report_type'] == 'found') ? 'active' : ''; ?>" data-type="found">I Found an Item</button>
        </div>
        
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="report_type" id="report_type" value="<?php echo isset($prefilledData['report_type']) ? $prefilledData['report_type'] : 'lost'; ?>">
            <?php if (isset($prefilledData['lost_report_id'])): ?>
                <input type="hidden" name="related_lost_report_id" value="<?php echo $prefilledData['lost_report_id']; ?>">
            <?php endif; ?>
            
<div class="form-group">
    <label for="description">Item Description <span class="required-asterisk">*</span></label>
    <textarea id="description" name="description" required placeholder="Describe the item in detail (color, brand, distinctive features, etc.)"><?php echo isset($prefilledData['description']) ? $prefilledData['description'] : ''; ?></textarea>
</div>

<div class="form-group">
    <label for="category_id">Category <span class="required-asterisk">*</span></label>
    <select id="category_id" name="category_id" required>
        <option value="">Select Category</option>
        <?php
        $category = new Category();
        $categories = $category->getAll();
        foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['category_id']; ?>" <?php echo (isset($prefilledData['category_id']) && $prefilledData['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                <?php echo $cat['category_name']; ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-group">
    <label for="location_name">Location <span class="required-asterisk">*</span></label>
    <input type="text" id="location_name" name="location_name" required 
           placeholder="Where did you lose/find the item?"
           value="<?php echo isset($prefilledData['location_name']) ? $prefilledData['location_name'] : ''; ?>">
    <?php if (isset($prefilledData['is_from_lost_report'])): ?>
        <small style="color: #666; display: block; margin-top: 5px;">
            Note that this location is from the lost report. Please update to where you actually found the item.
        </small>
    <?php endif; ?>
</div>

<div class="date-fields">
    <div class="form-group date-lost-field" style="<?php echo (isset($prefilledData['report_type']) && $prefilledData['report_type'] == 'found') ? 'display: none;' : 'display: block;'; ?>">
        <label for="date_lost">Date Lost <span class="required-asterisk">*</span></label>
        <input type="date" id="date_lost" name="date_lost" 
               max="<?php echo date('Y-m-d'); ?>"
               value="<?php echo date('Y-m-d'); ?>">
    </div>
    
    <div class="form-group date-found-field" style="<?php echo (isset($prefilledData['report_type']) && $prefilledData['report_type'] == 'found') ? 'display: block;' : 'display: none;'; ?>">
        <label for="date_found">Date Found <span class="required-asterisk">*</span></label>
        <input type="date" id="date_found" name="date_found" 
               max="<?php echo date('Y-m-d'); ?>"
               value="<?php echo date('Y-m-d'); ?>">
    </div>
</div>
            
            <div class="form-group">
                <label for="images">Upload Images (Optional)</label>
                <input type="file" id="images" name="images[]" multiple accept="image/*" onchange="previewImages(event)">
                <small>You can select multiple images. Max file size: 20MB each. Supported formats: JPG, PNG, GIF</small>
            
            <!-- Image preview container -->
            <div id="image-preview-container" class="image-preview-container" style="margin-top: 15px; display: none;">
                <h4 style="margin-bottom: 10px; font-size: 16px; color: #495057;">Selected Images:</h4>
                <div id="image-previews" class="image-previews" style="display: flex; flex-wrap: wrap; gap: 10px;"></div>
            </div>
            </div>
            
            <button type="submit" class="btn">Submit Report</button>
        </form>
    </div>

<?php elseif ($active_tab == 'browse-items'): ?>
    <div class="browse-section">
        <h2>Browse Lost & Found Items</h2>
        <?php
        // Get the lost report ID from URL if coming from found-this-item button
        if (isset($_GET['found_lost_item']) && isset($_GET['report_id'])) {
            $lostReportId = $_GET['report_id'];
            $report = new Report();
            $lostReport = $report->getReportById($lostReportId);
            
            if ($lostReport && $lostReport['report_type'] == 'lost') {
                echo '<div class="notification info" style="margin-bottom: 20px;">';
                echo '<p><strong>You are reporting a found item that matches a lost item!</strong></p>';
                echo '<p>The form below has been pre-filled with details from the lost report. Please verify and adjust the information as needed.</p>';
                echo '<p>Lost Report ID: ' . $lostReportId . '</p>';
                echo '</div>';
            }
        }
        ?>
        <div class="filters">
            <form method="GET" action="">
                <input type="hidden" name="tab" value="browse-items">
                
                <div class="filter-row">
                    <div class="form-group">
                        <label for="search">Search Items</label>
                        <input type="text" id="search" name="search" 
                               placeholder="Search by description, category, location..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category_filter">Category</label>
                        <select id="category_filter" name="category_filter">
                            <option value="">All Categories</option>
                            <?php
                            $category = new Category();
                            $categories = $category->getAll();
                            foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>" 
                                    <?php echo (isset($_GET['category_filter']) && $_GET['category_filter'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo $cat['category_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="report_type_filter">Type</label>
                        <select id="report_type_filter" name="report_type_filter">
                            <option value="">All Types</option>
                            <option value="lost" <?php echo (isset($_GET['report_type_filter']) && $_GET['report_type_filter'] == 'lost') ? 'selected' : ''; ?>>Lost Items</option>
                            <option value="found" <?php echo (isset($_GET['report_type_filter']) && $_GET['report_type_filter'] == 'found') ? 'selected' : ''; ?>>Found Items</option>
                        </select>
                    </div>
                    
                    <div class="form-group filter-actions">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="?tab=browse-items" class="btn btn-secondary">Clear All</a>
                    </div>
                </div>
            </form>
        </div>
        
        <?php
        // Display search results info if searching
        if (isset($_GET['search']) && !empty($_GET['search'])): 
            $item = new Item();
            $filters = [];
            if (isset($_GET['category_filter']) && !empty($_GET['category_filter'])) {
                $filters['category_id'] = $_GET['category_filter'];
            }
            if (isset($_GET['report_type_filter']) && !empty($_GET['report_type_filter'])) {
                $filters['report_type'] = $_GET['report_type_filter'];
            }
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            $items = $item->getAllItemsWithReports($filters);
            ?>
            <div class="search-results-info">
                <div class="search-summary">
                    Found <strong><?php echo count($items); ?></strong> items matching 
                    "<?php echo htmlspecialchars($_GET['search']); ?>"
                    <?php if (isset($_GET['category_filter']) && !empty($_GET['category_filter'])): 
                        $cat = new Category();
                        $category_name = $cat->getById($_GET['category_filter'])['category_name'];
                    ?>
                        in category <strong><?php echo $category_name; ?></strong>
                    <?php endif; ?>
                    <?php if (isset($_GET['report_type_filter']) && !empty($_GET['report_type_filter'])): ?>
                        of type <strong><?php echo ucfirst($_GET['report_type_filter']); ?></strong>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="items-grid">
            <?php
            $item = new Item();
            $filters = [];
            if (isset($_GET['category_filter']) && !empty($_GET['category_filter'])) {
                $filters['category_id'] = $_GET['category_filter'];
            }
            if (isset($_GET['report_type_filter']) && !empty($_GET['report_type_filter'])) {
                $filters['report_type'] = $_GET['report_type_filter'];
            }
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            $items = $item->getAllItemsWithReports($filters);
            
            if (empty($items)): ?>
                <div class="no-items">
                    <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                        <h4>No items found</h4>
                        <p>No items match your search criteria. Try adjusting your search terms or filters.</p>
                        <a href="?tab=browse-items" class="btn">View All Items</a>
                    <?php else: ?>
                        <h4>No items found</h4>
                        <p>There are no items matching your current filter criteria.</p>
                        <a href="?tab=browse-items" class="btn">View All Items</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
    <div class="item-card">
        <div class="item-header">
            <span class="report-type-badge <?php echo $item['report_type']; ?>">
                <?php echo ucfirst($item['report_type']); ?>
            </span>
            <span class="item-status status-<?php echo $item['status']; ?>">
                <?php echo ucfirst($item['status']); ?>
            </span>
        </div>
        
        <?php if (!empty($item['image_path'])): ?>
            <div class="item-image">
                <img src="<?php echo $item['image_path']; ?>" alt="<?php echo htmlspecialchars($item['description']); ?>">
            </div>
        <?php else: ?>
            <div class="item-image">
                <span>No Image Available</span>
            </div>
        <?php endif; ?>

        <div class="item-details">
            <h3><?php echo htmlspecialchars($item['category_name']); ?></h3> 
            <div class="item-description">
                <p><strong>Description:</strong> <?php echo htmlspecialchars($item['description']); ?></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($item['location_name']); ?></p>
                <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($item['created_at'])); ?></p>
            <?php if ($item['report_type'] == 'lost' && !empty($item['date_lost'])): ?>
                <p><strong>Date Lost:</strong> <?php echo date('M j, Y', strtotime($item['date_lost'])); ?></p>
            <?php elseif ($item['report_type'] == 'found' && !empty($item['date_found'])): ?>
                <p><strong>Date Found:</strong> <?php echo date('M j, Y', strtotime($item['date_found'])); ?></p>
            <?php endif; ?>
            </div>  
        </div>

        <?php 
        // Check if this item can be claimed
        $canBeClaimed = false;
        $claimMessage = '';
        
        if ($item['report_type'] == 'found' && $item['user_id'] != $current_user_id) {
            if ($item['status'] == 'returned') {
                $claimMessage = 'This item has been returned to its owner';
            } else {
                // Check if there are any approved claims for this report
                $claimCheck = new Claim();
                $existingClaims = $claimCheck->getClaimsByReportId($item['report_id']);
                
                $hasApprovedClaim = false;
                $userHasPendingClaim = false;
                
                foreach ($existingClaims as $existingClaim) {
                    if ($existingClaim['status'] == 'approved' || $existingClaim['status'] == 'completed') {
                        $hasApprovedClaim = true;
                    }
                    if ($existingClaim['claimed_by'] == $current_user_id) {
                        if ($existingClaim['status'] == 'pending') {
                            $userHasPendingClaim = true;
                        } elseif ($existingClaim['status'] == 'approved') {
                            $hasApprovedClaim = true;
                        }
                    }
                }
                
                if ($hasApprovedClaim) {
                    $claimMessage = 'This item has an approved claim';
                } elseif ($userHasPendingClaim) {
                    $claimMessage = 'You have already submitted a claim for this item';
                } else {
                    $canBeClaimed = true;
                }
            }
        }
        ?>
        
        <div class="claim-section">
            <?php if ($item['report_type'] == 'found' && $item['user_id'] != $current_user_id): ?>
                <?php if ($canBeClaimed): ?>
                    <button class="btn btn-claim" onclick="toggleClaimForm('<?php echo $item['report_id']; ?>')">
                        Claim This Item
                    </button>
                    
    <div id="claim-form-<?php echo $item['report_id']; ?>" class="claim-form" style="display: none;">
            <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit_claim">
            <input type="hidden" name="report_id" value="<?php echo $item['report_id']; ?>">
        
        <div class="form-group">
        <label for="claim_description_<?php echo $item['report_id']; ?>">
        Why do you think this is your item? <span class="required-asterisk">*</span>
        </label>
        <textarea id="claim_description_<?php echo $item['report_id']; ?>" 
              name="claim_description" 
              required 
              placeholder="Please provide detailed information to support your claim:
• Describe distinctive features of the item
• Mention when and where you lost it
• Provide any serial numbers or unique identifiers
• Explain any modifications or personalization
• Share purchase details or proof of ownership"></textarea>
</div>
        
        <!-- Image Upload Section -->
        <div class="claim-upload-container" id="claim-upload-container-<?php echo $item['report_id']; ?>">
            <div class="claim-upload-header">
                <h4>Upload Supporting Images</h4>
            </div>
            
            <div class="claim-upload-instructions">
                <p>Upload images that help verify your ownership claim:</p>
                <ul>
                    <li>Photos of you with the item</li>
                    <li>Previous photos showing the item's condition</li>
                </ul>
                <p><small>Max 5 images, 20MB each. Supported: JPG, PNG, GIF</small></p>
            </div>
            
            <div class="form-group">
                <label for="claim_images_<?php echo $item['report_id']; ?>">
                    Upload Supporting Images
                </label>
                <input type="file" 
                    id="claim_images_<?php echo $item['report_id']; ?>" 
                    name="claim_images[]" 
                    multiple 
                    accept="image/*"
                    onchange="previewClaimImages(event, '<?php echo $item['report_id']; ?>')">
            </div>
            
            <!-- Image Preview Container -->
            <div id="claim-image-preview-<?php echo $item['report_id']; ?>" 
                 class="claim-image-previews" 
                 style="<?php echo isset($imagePreviews) && !empty($imagePreviews) ? 'display: grid;' : 'display: none;'; ?>">
                <!-- Image previews will be inserted here by JavaScript -->
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Submit Claim</button>
            <button type="button" class="btn btn-secondary" onclick="toggleClaimForm('<?php echo $item['report_id']; ?>')">Cancel</button>
        </div>
    </form>
</div>
                <?php else: ?>
                    <div class="claim-message">
                        <?php echo $claimMessage; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($item['report_type'] == 'lost' && $item['user_id'] != $current_user_id && $item['status'] != 'returned'): ?>
                <div class="found-item-section">
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?tab=report-item&found_lost_item=1&report_id=<?php echo $item['report_id']; ?>" 
                       class="btn btn-found-item">
                        I Found This Item
                    </a>
                    <small>Click to report this item as found</small>
                </div>
            <?php endif; ?>
            
            <?php
            // Display claimant information for returned found items
            if ($item['report_type'] == 'found' && $item['status'] == 'returned') {
                $claimCheck = new Claim();
                $approvedClaims = $claimCheck->getClaimsByReportId($item['report_id']);
                $claimantInfo = null;
                
                foreach ($approvedClaims as $claim) {
                    if ($claim['status'] == 'approved' || $claim['status'] == 'completed') {
                        $claimantInfo = $claim;
                        break;
                    }
                }
                
                if ($claimantInfo): ?>
                    <div class="claimant-info" style="margin-top: 10px; padding: 10px; background: #e8f5e8; border-radius: 5px; border-left: 4px solid #28a745;">
                        <p style="margin: 0; font-size: 14px; color: #155724;">
                            <strong>Claimed by: <?php echo $claimantInfo['claimed_by']; ?></strong>
                        </p>
                    </div>
                <?php endif;
            }
            ?>
        </div>
    </div>
<?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($active_tab == 'my-reports'): ?>
    <div class="my-reports-section">
        <h2>My Reports</h2>
        <?php
        if (empty($userReports)): ?>
            <div class="no-items" style="text-align: center; padding: 40px; color: #666; font-style: italic;">
                <div class="no-items-icon"></div>
                <h4>No Reports Yet</h4>
                <p>You haven't reported any items yet.</p>
                <a href="?tab=report-item" class="btn" style="margin-top: 15px;">Report Your First Item</a>
            </div>
        <?php else: ?>
            <div class="items-grid">
                <?php foreach ($userReports as $reportItem): ?>
                    <div class="item-card">
                        <div class="item-header">
                            <span class="report-type-badge <?php echo $reportItem['report_type']; ?>">
                                <?php echo ucfirst($reportItem['report_type']); ?>
                            </span>
                            <span class="item-status status-<?php echo $reportItem['status']; ?>">
                                <?php echo ucfirst($reportItem['status']); ?>
                            </span>
                            <span class="report-id" style="font-size: 12px; color: #666; font-family: monospace;">
                                ID: <?php echo $reportItem['report_id']; ?>
                            </span>
                        </div>
                        
                        <?php 
                        // Get images
                        $item = new Item();
                        $images = $item->getItemImages($reportItem['item_id']);
                        ?>
                        
                        <div class="report-images-container" style="margin: 15px 0;">
                            <?php if (!empty($images)): ?>
                                <div class="image-thumbnails" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <?php foreach ($images as $imageIndex => $image): ?>
                                        <div class="image-thumb" style="width: 100px; height: 100px; border-radius: 8px; overflow: hidden; border: 2px solid #e9ecef; position: relative; cursor: pointer; background: #f8f9fa;">
                                            <img src="<?php echo $image['image_path']; ?>" 
                                                 alt="Item Image <?php echo $imageIndex + 1; ?>" 
                                                 style="width: 100%; height: 100%; object-fit: cover;"
                                                 onclick="openImageModal('<?php echo $image['image_path']; ?>', '<?php echo htmlspecialchars(addslashes($reportItem['description'])); ?>')">
                                            <?php if (count($images) > 1): ?>
                                                <div style="position: absolute; bottom: 0; right: 0; background: rgba(0,0,0,0.7); color: white; font-size: 10px; padding: 2px 5px; border-radius: 4px 0 0 0;">
                                                    <?php echo $imageIndex + 1; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-images" style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;">
                                    <div style="font-size: 36px; color: #adb5bd; margin-bottom: 10px;">📷</div>
                                    <p style="margin: 0; color: #6c757d; font-style: italic;">No images uploaded</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Item Details -->
                        <div class="item-details">
                            <h3><?php echo $reportItem['category_name']; ?></h3>
                            <p><strong>Description:</strong> <?php echo htmlspecialchars($reportItem['description']); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($reportItem['location_name']); ?></p>
                            <p><strong>Reported:</strong> <?php echo date('M j, Y g:i A', strtotime($reportItem['created_at'])); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($reportItem['updated_at'])); ?></p>
                            
                            <?php if ($reportItem['report_type'] == 'lost' && !empty($reportItem['date_lost'])): ?>
                                <p><strong>Date Lost:</strong> <?php echo date('M j, Y', strtotime($reportItem['date_lost'])); ?></p>
                            <?php elseif ($reportItem['report_type'] == 'found' && !empty($reportItem['date_found'])): ?>
                                <p><strong>Date Found:</strong> <?php echo date('M j, Y', strtotime($reportItem['date_found'])); ?></p>
                            <?php endif; ?>
                            
                            <?php 
                            // Get claims for this report
                            $claim = new Claim();
                            $claims = $claim->getClaimsByReportId($reportItem['report_id']);
                            ?>
                            
                            <?php if (!empty($claims)): ?>
                                <div class="claims-section" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                    <h4 style="margin-bottom: 10px; font-size: 16px; color: #2c3e50;">Claims on this item:</h4>
                                    <?php foreach ($claims as $claimItem): ?>
                                        <div class="claim-item" style="background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 5px;">
                                            <p style="margin: 0 0 5px 0;"><strong>Claim ID:</strong> <?php echo $claimItem['claim_id']; ?></p>
                                            <p style="margin: 0 0 5px 0;"><strong>Status:</strong> 
                                                <span style="padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;
                                                    background: <?php 
                                                        if ($claimItem['status'] == 'approved') echo '#d4edda'; 
                                                        elseif ($claimItem['status'] == 'rejected') echo '#f8d7da';
                                                        else echo '#fff3cd';
                                                    ?>; 
                                                    color: <?php 
                                                        if ($claimItem['status'] == 'approved') echo '#155724'; 
                                                        elseif ($claimItem['status'] == 'rejected') echo '#721c24';
                                                        else echo '#856404';
                                                    ?>;">
                                                    <?php echo ucfirst($claimItem['status']); ?>
                                                </span>
                                            </p>
                                            <p style="margin: 0 0 5px 0;"><strong>Claim Description:</strong> <?php echo htmlspecialchars($claimItem['claim_description']); ?></p>
                                            <?php if (!empty($claimItem['admin_notes'])): ?>
                                                <p style="margin: 0;"><strong>Admin Notes:</strong> <?php echo htmlspecialchars($claimItem['admin_notes']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="item-actions" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; display: flex; gap: 10px; flex-wrap: wrap;">
                            <?php if (in_array($reportItem['status'], ['pending', 'confirmed'])): ?>
                                <a href="?tab=edit-report&id=<?php echo $reportItem['report_id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            <?php endif; ?>
                            
                            <?php if (in_array($reportItem['status'], ['pending', 'confirmed'])): ?>
                                <form action="" method="POST" style="margin: 0; display: inline;">
                                    <input type="hidden" name="action" value="delete_report">
                                    <input type="hidden" name="report_id" value="<?php echo $reportItem['report_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this report?')">Delete</button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($reportItem['report_type'] == 'lost' && $reportItem['status'] == 'confirmed'): ?>
                                <?php
                                // Check if there's an approved claim
                                $hasApprovedClaim = false;
                                if (!empty($claims)) {
                                    foreach ($claims as $claimItem) {
                                        if ($claimItem['status'] == 'approved') {
                                            $hasApprovedClaim = true;
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <?php if ($hasApprovedClaim): ?>
                                    <form action="" method="POST" style="margin: 0; display: inline;">
                                        <input type="hidden" name="action" value="confirm_return">
                                        <input type="hidden" name="report_id" value="<?php echo $reportItem['report_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm that you have received your lost item?')">Confirm Return</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($reportItem['report_type'] == 'found' && $reportItem['status'] == 'confirmed'): ?>
                                <?php
                                // Check if current user has an approved claim for this found item
                                $hasApprovedClaim = false;
                                if (!empty($claims)) {
                                    foreach ($claims as $claimItem) {
                                        if ($claimItem['status'] == 'approved' && $claimItem['claimed_by'] == $current_user_id) {
                                            $hasApprovedClaim = true;
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <?php if ($hasApprovedClaim): ?>
                                    <form action="" method="POST" style="margin: 0; display: inline;">
                                        <input type="hidden" name="action" value="confirm_found_return">
                                        <input type="hidden" name="report_id" value="<?php echo $reportItem['report_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm that you have received the found item?')">Confirm Return</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- "Mark as Found" button for lost items -->
                            <?php if ($reportItem['report_type'] == 'lost' && in_array($reportItem['status'], ['pending', 'confirmed'])): ?>
                                <form action="" method="POST" style="margin: 0; display: inline;">
                                    <input type="hidden" name="action" value="mark_as_found">
                                    <input type="hidden" name="report_id" value="<?php echo $reportItem['report_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Mark this lost item as found? This will update its status to returned.')">Mark as Found</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($active_tab == 'track-claims'): ?>
    <div class="track-claims-section">
        <h2>My Claims</h2>
        <?php
        if (empty($userClaims)): ?>
            <div class="no-items" style="text-align: center; padding: 40px; color: #666; font-style: italic;">
                <div class="no-items-icon"></div>
                <h4>No Claims Yet</h4>
                <p>You haven't submitted any claims yet.</p>
                <a href="?tab=browse-items" class="btn" style="margin-top: 15px;">Browse Items to Claim</a>
            </div>
        <?php else: ?>
            <div class="claims-grid">
                <?php foreach ($userClaims as $claim): 
                    // Get report details for this claim
                    $report = new Report();
                    $reportDetails = $report->getReportById($claim['report_id']);
                    
                    // Get item details
                    $item = new Item();
                    $itemDetails = $item->getItemById($reportDetails['item_id']);
                    
                    // Get category name
                    $category = new Category();
                    $categoryDetails = $category->getById($itemDetails['category_id']);
                ?>
                    <div class="claim-card">
                        <div class="claim-header">
                            <div class="claim-ids">
                                <span class="claim-id">Claim ID: <code><?php echo $claim['claim_id']; ?></code></span>
                                <span class="report-id">Report ID: <code><?php echo $claim['report_id']; ?></code></span>
                            </div>
                            <div class="claim-date">
                                Submitted: <?php echo date('M j, Y g:i A', strtotime($claim['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="claim-body">
                            <div class="claim-item-info">
                                <h4><?php echo htmlspecialchars($itemDetails['description']); ?></h4>
                                <div class="claim-meta">
                                    <span class="meta-item">
                                        <strong>Category:</strong> <?php echo $categoryDetails['category_name']; ?>
                                    </span>
                                    <span class="meta-item">
                                        <strong>Item Type:</strong> <?php echo ucfirst($reportDetails['report_type']); ?>
                                    </span>
                                    <span class="meta-item">
                                        <strong>Report Status:</strong> 
                                        <span class="item-status status-<?php echo $reportDetails['status']; ?>">
                                            <?php echo ucfirst($reportDetails['status']); ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="claim-status-section">
                                <div class="status-display">
                                    <strong>Claim Status:</strong>
                                    <span class="claim-status-badge status-<?php echo $claim['status']; ?>">
                                        <?php echo ucfirst($claim['status']); ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($claim['admin_notes'])): ?>
                                    <div class="admin-notes" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #6c757d;">
                                        <strong>Admin Notes:</strong>
                                        <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($claim['admin_notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="claim-description">
                                <label><strong>Your Claim Description:</strong></label>
                                <div class="description-text" style="padding: 10px; background: #f8f9fa; border-radius: 5px; margin-top: 5px;">
                                    <?php echo nl2br(htmlspecialchars($claim['claim_description'])); ?>
                                </div>
                            </div>
                            
                            <?php 
                            // Get claim images if any
                            $claimImages = [];
                            try {
                                $db = DatabaseService::getInstance()->getConnection();
                                $stmt = $db->prepare("SELECT * FROM claim_images WHERE claim_id = ?");
                                $stmt->execute([$claim['claim_id']]);
                                $claimImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e) {
                                // Table might not exist yet
                            }
                            ?>
                            
                            <?php if (!empty($claimImages)): ?>
                                <div class="claim-images" style="margin-top: 15px;">
                                    <label><strong>Supporting Images:</strong></label>
                                    <div class="image-thumbnails" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px;">
                                        <?php foreach ($claimImages as $image): ?>
                                            <div class="image-thumb" style="width: 80px; height: 80px; border-radius: 5px; overflow: hidden; border: 2px solid #e9ecef; cursor: pointer;">
                                                <img src="<?php echo $image['image_path']; ?>" 
                                                     alt="Claim Image" 
                                                     style="width: 100%; height: 100%; object-fit: cover;"
                                                     onclick="openImageModal('<?php echo $image['image_path']; ?>', 'Claim supporting image')">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="claim-actions">
                            <?php if ($claim['status'] == 'pending'): ?>
                                <div class="pending-info" style="padding: 10px; background: #fff3cd; border-radius: 5px; text-align: center;">
                                    <p style="margin: 0; color: #856404;">
                                        <strong> Your claim is under review by admin.</strong>
                                    </p>
                                </div>
                            <?php elseif ($claim['status'] == 'approved'): ?>
                                <?php if ($reportDetails['report_type'] == 'found' && $reportDetails['status'] == 'confirmed'): ?>
                                    <div class="approved-info" style="padding: 10px; background: #d4edda; border-radius: 5px; text-align: center;">
                                        <p style="margin: 0; color: #155724;">
                                            <strong>Your claim has been approved!</strong>
                                        </p>
                                        <p style="margin: 5px 0 0 0; color: #155724;">
                                            Please contact the admin to arrange item pickup.
                                        </p>
                                    </div>
                                <?php elseif ($reportDetails['report_type'] == 'lost'): ?>
                                    <div class="approved-info" style="padding: 10px; background: #d4edda; border-radius: 5px; text-align: center;">
                                        <p style="margin: 0; color: #155724;">
                                            <strong>Your claim has been approved!</strong>
                                        </p>
                                        <p style="margin: 5px 0 0 0; color: #155724;">
                                            The item owner has been notified.
                                        </p>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($claim['status'] == 'completed'): ?>
                                <div class="completed-info" style="padding: 10px; background: #cce5ff; border-radius: 5px; text-align: center;">
                                    <p style="margin: 0; color: #004085;">
                                        <strong>Claim completed!</strong>
                                    </p>
                                    <p style="margin: 5px 0 0 0; color: #004085;">
                                        Item has been successfully returned.
                                    </p>
                                </div>
                            <?php elseif ($claim['status'] == 'rejected'): ?>
                                <div class="rejected-info" style="padding: 10px; background: #f8d7da; border-radius: 5px; text-align: center;">
                                    <p style="margin: 0; color: #721c24;">
                                        <strong>Your claim has been rejected.</strong>
                                    </p>
                                    <?php if (!empty($claim['admin_notes'])): ?>
                                        <p style="margin: 5px 0 0 0; color: #721c24;">
                                            Reason: <?php echo htmlspecialchars($claim['admin_notes']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($active_tab == 'notifications'): ?>
    <div class="notifications-section">
        <div class="notifications-header">
            <h2>Notifications</h2>
            <?php
            $notification = new Notification();
            $notifications = $notification->getUserNotifications($current_user_id);
            $unreadCount = $notification->getUnreadCount($current_user_id);
            
            if ($unreadCount > 0): ?>
                <form action="" method="POST" class="mark-all-read-form">
                    <input type="hidden" name="action" value="mark_all_notifications_read">
                    <button type="submit" class="btn btn-secondary btn-sm">Mark All as Read</button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div class="no-notifications">
                <div class="no-notifications-icon"></div>
                <h3>No notifications</h3>
                <p>You don't have any notifications yet.</p>
            </div>
        <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" 
                         data-notification-id="<?php echo $notif['notification_id']; ?>">
                        <div class="notification-icon">
                            <?php
                            $icon = '📢'; // default
                            switch ($notif['type']) {
                                case 'report_confirmed': $icon = '✅'; break;
                                case 'claim_approved': $icon = '🎉'; break;
                                case 'claim_rejected': $icon = '❌'; break;
                                case 'item_returned': $icon = '📦'; break;
                            }
                            echo $icon;
                            ?>
                        </div>
                        <div class="notification-content">
                            <h4><?php echo htmlspecialchars($notif['title']); ?></h4>
                            <p><?php echo htmlspecialchars($notif['message']); ?></p>
                            <span class="notification-time">
                                <?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?>
                            </span>
                        </div>
                        <?php if (!$notif['is_read']): ?>
                            <div class="notification-actions">
                                <button class="mark-read-btn" title="Mark as read">✓</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php elseif ($active_tab == 'edit-report' && isset($_GET['id'])): ?>
    <div class="edit-report-section">
        <h2>Edit Report</h2>
        <?php
        $report_id = $_GET['id'];
        $report = new Report();
        $item = new Item();
        $reportDetails = $report->getReportById($report_id);
        
        // Check if report exists and belongs to current user
        if (!$reportDetails || $reportDetails['user_id'] != $current_user_id) {
            echo '<div class="notification error">Report not found or you do not have permission to edit it.</div>';
        } elseif ($reportDetails['status'] == 'returned') {
        echo '<div class="notification error">Cannot edit report that has been returned.</div>';
        } else {
            $itemDetails = $item->getItemById($reportDetails['item_id']);
            $itemImages = $item->getItemImages($reportDetails['item_id']);
            ?>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_report">
                <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
                <input type="hidden" name="item_id" value="<?php echo $reportDetails['item_id']; ?>">
                
                <div class="form-group">
                    <label for="description">Item Description <span class="required-asterisk">*</span></label>
                    <textarea id="description" name="description" required placeholder="Describe the item in detail"><?php echo htmlspecialchars($itemDetails['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="category_id">Category <span class="required-asterisk">*</span></label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php
                        $category = new Category();
                        $categories = $category->getAll();
                        foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" 
                                <?php echo ($itemDetails['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                <?php echo $cat['category_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="location_name">Location <span class="required-asterisk">*</span></label>
                    <input type="text" id="location_name" name="location_name" required 
                        value="<?php echo htmlspecialchars($reportDetails['location_name']); ?>"
                        placeholder="Where did you lose/find the item?">
                </div>
                
                <div class="form-group">
                    <label>Report Type</label>
                    <div class="readonly-field">
                        <?php echo ucfirst($reportDetails['report_type']); ?> (Cannot change report type)
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Current Images</label>
                    <?php if (!empty($itemImages)): ?>
                        <div class="current-images" style="margin-bottom: 20px;">
                            <div class="image-previews" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px;">
                                <?php foreach ($itemImages as $image): ?>
                                    <div class="image-preview-item existing-image" style="position: relative; border: 2px solid #e9ecef; border-radius: 8px; overflow: hidden; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                        <img src="<?php echo $image['image_path']; ?>" alt="Current Image" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                        <div style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7); color: white; padding: 4px; font-size: 10px; text-align: center;">
                                            <label class="delete-checkbox" style="color: white; margin: 0; font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;">
                                                <input type="checkbox" name="delete_images[]" value="<?php echo $image['image_id']; ?>">
                                                Delete
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p style="color: #6c757d; font-style: italic;">No images uploaded</p>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="new_images">Add New Images (Optional)</label>
                    <input type="file" id="new_images" name="new_images[]" multiple accept="image/*" onchange="previewImages(event, 'new')">
                    <small>You can select multiple images to add. Max file size: 20MB each. Supported formats: JPG, PNG, GIF</small>
                    
                    <!-- Image preview container for new images -->
                    <div id="new-image-preview-container" class="image-preview-container" style="margin-top: 15px; display: none;">
                        <h4 style="margin-bottom: 10px; font-size: 16px; color: #495057;">New Images:</h4>
                        <div id="new-image-previews" class="image-previews" style="display: flex; flex-wrap: wrap; gap: 10px;"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Report</button>
                    <a href="?tab=my-reports" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
            <?php
        }
        ?>
    </div>

<?php elseif ($active_tab == 'admin' && $isAdmin): ?>
    <?php
    // Initialize all data variables at the top
    $report = new Report();
    $claim = new Claim();
    
    // Get all data needed for admin panel
    $allReports = $report->getAllReportsWithDetails();
    $pendingClaims = $claim->getClaimsByStatus('pending');
    ?>
    
    <div class="admin-section" id="admin-panel">
        <h2>Admin Panel</h2>
        
        <div class="admin-tabs-navigation">
            <button type="button" class="admin-tab-btn active" data-tab="reports">
                All Reports
            </button>
            <button type="button" class="admin-tab-btn" data-tab="claims">
                Pending Claims
            </button>
        </div>
        
        <div class="admin-tabs-content">
            <!-- Reports Tab -->
            <div id="reports-tab" class="admin-tab-pane active">
                <div class="tab-header">
                    <h3>All Reports</h3>
                    <span class="tab-badge"><?php echo count($allReports); ?> reports</span>
                </div>
                
                <?php if (empty($allReports)): ?>
                    <div class="no-items">
                        <h4>No reports found</h4>
                        <p>There are no reports in the system yet.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Type</th>
                                    <th>Item Description</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th>Reporter</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
    <?php foreach ($allReports as $reportItem): ?>
        <tr>
            <td><code><?php echo $reportItem['report_id']; ?></code></td>
            <td>
                <span class="report-type-badge <?php echo $reportItem['report_type']; ?>">
                    <?php echo ucfirst($reportItem['report_type']); ?>
                </span>
            </td>
            <td class="description-cell"><?php echo htmlspecialchars($reportItem['description']); ?></td>
            <td><?php echo $reportItem['category_name']; ?></td>
            <td><?php echo htmlspecialchars($reportItem['location_name']); ?></td>
            <td>
                <?php 
                // Fetch reporter username
                $db = DatabaseService::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT username FROM users WHERE user_id = ?");
                $stmt->execute([$reportItem['user_id']]);
                $reporter = $stmt->fetch(PDO::FETCH_ASSOC);
                echo htmlspecialchars($reporter['username'] ?? 'Unknown');
                ?>
            </td>
            <td>
                <span class="item-status status-<?php echo $reportItem['status']; ?>">
                    <?php echo ucfirst($reportItem['status']); ?>
                </span>
            </td>
            <td><?php echo date('M j, Y', strtotime($reportItem['created_at'])); ?></td>
            <td>
                <?php if ($reportItem['status'] == 'pending'): ?>
                    <form action="" method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="update_report_status">
                        <input type="hidden" name="report_id" value="<?php echo $reportItem['report_id']; ?>">
                        <input type="hidden" name="status" value="confirmed">
                        <button type="submit" class="btn btn-success btn-sm" 
                                onclick="return confirm('Mark this report as confirmed?')">
                            Confirm
                        </button>
                    </form>
                <?php elseif ($reportItem['status'] == 'confirmed'): ?>
                    <form action="" method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="update_report_status">
                        <input type="hidden" name="report_id" value="<?php echo $reportItem['report_id']; ?>">
                        <input type="hidden" name="status" value="returned">
                        <button type="submit" class="btn btn-primary btn-sm" 
                                onclick="return confirm('Mark this item as returned?')">
                            Mark Returned
                        </button>
                    </form>
                <?php else: ?>
                    <span class="text-muted">No actions</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Claims Tab -->
            <div id="claims-tab" class="admin-tab-pane">
                <div class="tab-header">
                    <h3>Pending Claims</h3>
                    <span class="tab-badge"><?php echo count($pendingClaims); ?> pending</span>
                </div>
                
                <?php if (empty($pendingClaims)): ?>
                    <div class="no-items">
                        <h4>No pending claims</h4>
                    </div>
                <?php else: ?>
                    <div class="claims-grid">
                        <?php foreach ($pendingClaims as $claimItem): ?>
                            <div class="claim-card">
                                <div class="claim-header">
                                    <div class="claim-ids">
                                        <span class="claim-id">Claim: <code><?php echo $claimItem['claim_id']; ?></code></span>
                                        <span class="report-id">Report: <code><?php echo $claimItem['report_id']; ?></code></span>
                                    </div>
                                    <div class="claim-date">
                                        <?php echo date('M j, Y g:i A', strtotime($claimItem['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="claim-body">
                                    <div class="claim-item-info">
                                        <h4><?php echo htmlspecialchars($claimItem['item_description']); ?></h4>
                                        <div class="claim-meta">
                                            <span class="meta-item">
                                                <strong>Claimant:</strong>
                                                (<?php echo htmlspecialchars($claimItem['claimant_email']); ?>)</span>
                                            <span class="meta-item">
                                                <strong>Reporter:</strong> 
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="claim-description">
                                        <label>Claim Description:</label>
                                        <div class="description-text">
                                            <?php echo nl2br(htmlspecialchars($claimItem['claim_description'])); ?>
                                        </div>
                                    </div>
                                    
                                    <?php 
                                    // Get existing claims for this report
                                    $existingClaims = $claim->getClaimsByReportId($claimItem['report_id']);
                                    $approvedCount = 0;
                                    foreach ($existingClaims as $existingClaim) {
                                        if ($existingClaim['status'] == 'approved') $approvedCount++;
                                    }
                                    ?>
                                    
                                    <?php if ($approvedCount > 0): ?>
                                        <div class="claim-warning">
                                            <span class="warning-icon">⚠️</span>
                                            This item already has <?php echo $approvedCount; ?> approved claim(s). 
                                            Approving this claim will replace the existing approved claim(s).
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="claim-actions">
                                    <form action="" method="POST" class="claim-action-form approve-form">
                                        <input type="hidden" name="action" value="update_claim_status">
                                        <input type="hidden" name="claim_id" value="<?php echo $claimItem['claim_id']; ?>">
                                        <input type="hidden" name="status" value="approved">
                                        
                                        <div class="form-group">
                                            <label for="admin_notes_approve_<?php echo $claimItem['claim_id']; ?>">
                                                Admin Notes (Optional)
                                            </label>
                                            <textarea 
                                                id="admin_notes_approve_<?php echo $claimItem['claim_id']; ?>"
                                                name="admin_notes" 
                                                placeholder="Add any notes for the claimant..."
                                                class="form-control"
                                            ></textarea>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-success btn-block" 
                                                onclick="return confirm('Approve this claim? This will automatically mark the item as returned.')">
                                            Approve & Complete
                                        </button>
                                    </form>
                                    
                                    <form action="" method="POST" class="claim-action-form reject-form">
                                        <input type="hidden" name="action" value="update_claim_status">
                                        <input type="hidden" name="claim_id" value="<?php echo $claimItem['claim_id']; ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        
                                        <div class="form-group">
                                            <label for="admin_notes_reject_<?php echo $claimItem['claim_id']; ?>">
                                                Reason for Rejection <span class="required-asterisk">*</span>
                                            </label>
                                            <textarea 
                                                id="admin_notes_reject_<?php echo $claimItem['claim_id']; ?>"
                                                name="admin_notes" 
                                                placeholder="Explain why this claim is being rejected..."
                                                class="form-control"
                                                required
                                            ></textarea>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-danger btn-block" 
                                                onclick="return confirm('Reject this claim?')">
                                            Reject Claim
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php elseif ($active_tab == 'statistics'): ?>
    <div class="statistics-section">
        <h2>System Statistics & KPIs</h2>
        
        <?php
        $stats = new Statistics();
        $totalReports = $stats->getTotalReports();
        $totalLost = $stats->getTotalLostItems();
        $totalFound = $stats->getTotalFoundItems();
        $returnedItems = $stats->getReturnedItemsCount();
        $pendingClaims = $stats->getPendingClaimsCount();
        $totalUsers = $stats->getTotalUsers();
        $reportsLast7Days = $stats->getReportsLast7Days();
        $returnedLast7Days = $stats->getItemsReturnedLast7Days();
        ?>
        
        <!-- Main KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-content">
                    <h3><?php echo $totalReports; ?></h3>
                    <p>Total Reports</p>
                    <div class="kpi-trend">
                        <span class="trend-up">+<?php echo $reportsLast7Days; ?> this week</span>
                    </div>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-content">
                    <h3><?php echo $totalLost; ?></h3>
                    <p>Lost Items</p>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-content">
                    <h3><?php echo $totalFound; ?></h3>
                    <p>Found Items</p>
                </div>
            </div>
            
            <div class="kpi-card success">
                <div class="kpi-content">
                    <h3><?php echo $returnedItems; ?></h3>
                    <p>Items Returned</p>
                    <div class="kpi-trend">
                        <span class="trend-up">+<?php echo $returnedLast7Days; ?> this week</span>
                    </div>
                </div>
            </div>
            
            <div class="kpi-card warning">
                <div class="kpi-content">
                    <h3><?php echo $pendingClaims; ?></h3>
                    <p>Pending Claims</p>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-content">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
        </div>

        <!-- Detailed Statistics Sections -->
        <div class="stats-sections">
            <!-- Status Distribution -->
            <div class="stats-section">
                <h3>Report Status Distribution</h3>
                <div class="stats-chart">
                    <?php
                    $statusDistribution = $stats->getStatusDistribution();
                    foreach ($statusDistribution as $status): 
                        $percentage = $totalReports > 0 ? round(($status['count'] / $totalReports) * 100, 1) : 0;
                    ?>
                        <div class="chart-item">
                            <div class="chart-label">
                                <span class="status-indicator status-<?php echo $status['status']; ?>"></span>
                                <?php echo ucfirst($status['status']); ?>
                            </div>
                            <div class="chart-bar">
                                <div class="chart-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div class="chart-value"><?php echo $status['count']; ?> (<?php echo $percentage; ?>%)</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Category Statistics -->
            <div class="stats-section">
                <h3>Items by Category</h3>
                <div class="category-stats">
                    <?php
                    $categoryStats = $stats->getCategoryStats();
                    foreach ($categoryStats as $category): 
                        if ($category['report_count'] > 0):
                            $returnRate = $category['report_count'] > 0 ? 
                                round(($category['returned_count'] / $category['report_count']) * 100, 1) : 0;
                    ?>
                        <div class="category-item">
                            <div class="category-name"><?php echo $category['category_name']; ?></div>
                            <div class="category-numbers">
                                <span class="total"><?php echo $category['report_count']; ?> reports</span>
                                <span class="returned"><?php echo $category['returned_count']; ?> returned</span>
                                <span class="rate"><?php echo $returnRate; ?>% success</span>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>

            <!-- Claim Statistics -->
            <div class="stats-section">
                <h3>Claim Status</h3>
                <div class="claim-stats">
                    <?php
                    $claimStats = $stats->getClaimStats();
                    foreach ($claimStats as $claim): 
                    ?>
                        <div class="claim-stat-item">
                            <span class="claim-status"><?php echo ucfirst($claim['status']); ?></span>
                            <span class="claim-count"><?php echo $claim['count']; ?></span>
                            <span class="claim-percentage"><?php echo $claim['percentage']; ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top Locations -->
            <div class="stats-section">
                <h3>Top Locations</h3>
                <div class="location-stats">
                    <?php
                    $locationStats = $stats->getLocationStats();
                    foreach ($locationStats as $location): 
                    ?>
                        <div class="location-item">
                            <span class="location-name"><?php echo $location['location_name']; ?></span>
                            <span class="location-count"><?php echo $location['report_count']; ?> reports</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($isAdmin): ?>
            <!-- Monthly Trends -->
            <div class="stats-section">
                <h3>Monthly Trends (Last 6 Months)</h3>
                <div class="trends-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Reports</th>
                                <th>Lost</th>
                                <th>Found</th>
                                <th>Returned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $monthlyTrends = $stats->getMonthlyTrends();
                            foreach ($monthlyTrends as $trend): 
                            ?>
                                <tr>
                                    <td><?php echo $trend['month']; ?></td>
                                    <td><?php echo $trend['total_reports']; ?></td>
                                    <td><?php echo $trend['lost_count']; ?></td>
                                    <td><?php echo $trend['found_count']; ?></td>
                                    <td class="highlight-cell"><?php echo $trend['returned_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<!-- Top Reporters -->
<div class="stats-section">
    <h3>Top Reporters</h3>
    <div class="top-reporters">
        <?php
        $topReporters = $stats->getTopReporters();
        
        if (empty($topReporters)): ?>
            <div style="text-align: center; padding: 20px; color: #6c757d; font-style: italic;">
                No reporter data available
            </div>
        <?php else: ?>
            <div class="reporter-table">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <th style="padding: 10px; text-align: left; width: 40%;">Reporter</th>
                            <th style="padding: 10px; text-align: center;">Total Reports</th>
                            <th style="padding: 10px; text-align: center;">Items Returned</th>
                            <th style="padding: 10px; text-align: center;">Success Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topReporters as $reporter): 
                            $successRate = $reporter['report_count'] > 0 ? 
                                round(($reporter['returned_count'] / $reporter['report_count']) * 100, 1) : 0;
                            
                            // Determine success rate color
                            $rateColor = '#dc3545'; // red
                            if ($successRate >= 80) {
                                $rateColor = '#28a745'; // green
                            } elseif ($successRate >= 50) {
                                $rateColor = '#ffc107'; // yellow
                            }
                        ?>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 10px; vertical-align: middle;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #6f42c1; 
                                         display: flex; align-items: center; justify-content: center; color: white; 
                                         font-weight: bold; font-size: 14px;">
                                        <?php echo strtoupper(substr($reporter['username'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($reporter['username']); ?></strong>
                                        <div style="font-size: 12px; color: #6c757d;">
                                            ID: <?php echo $reporter['user_id']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 10px; text-align: center; vertical-align: middle;">
                                <span style="font-weight: bold; font-size: 16px;"><?php echo $reporter['report_count']; ?></span>
                            </td>
                            <td style="padding: 10px; text-align: center; vertical-align: middle;">
                                <span style="font-weight: bold; font-size: 16px; color: #28a745;">
                                    <?php echo $reporter['returned_count']; ?>
                                </span>
                            </td>
                            <td style="padding: 10px; text-align: center; vertical-align: middle;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                    <div style="width: 100px; background: #e9ecef; border-radius: 10px; height: 10px; overflow: hidden;">
                                        <div style="width: <?php echo $successRate; ?>%; height: 100%; 
                                             background: <?php echo $rateColor; ?>;"></div>
                                    </div>
                                    <span style="font-weight: bold; color: <?php echo $rateColor; ?>;">
                                        <?php echo $successRate; ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px; font-size: 14px; color: #495057;">
                <strong>Summary:</strong> 
                <?php
                $totalReports = array_sum(array_column($topReporters, 'report_count'));
                $totalReturned = array_sum(array_column($topReporters, 'returned_count'));
                $avgSuccessRate = $totalReports > 0 ? round(($totalReturned / $totalReports) * 100, 1) : 0;
                ?>
                Top 10 reporters filed <?php echo $totalReports; ?> reports with 
                <?php echo $totalReturned; ?> items returned (<?php echo $avgSuccessRate; ?>% average success rate)
            </div>
        <?php endif; ?>
    </div>
</div>
            <?php endif; ?>
        </div>
    <div class="report-generation-section" style="margin-top: 40px; padding: 25px; background: #f8f9fa; border-radius: 10px; border: 1px solid #dee2e6;">
    <h3 style="margin-bottom: 25px; color: #343a40;">Generate Detailed Report</h3>
    
    <form method="POST" action="" id="report-form">
        <input type="hidden" name="action" value="generate_report">
        <input type="hidden" name="format" value="view" id="report-format">
        
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="start_date" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                    Start Date <span class="required-asterisk">*</span>
                </label>
                <input type="date" id="start_date" name="start_date" required 
                    value="<?php echo date('Y-m-01'); ?>" 
                    max="<?php echo date('Y-m-d'); ?>"
                    style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; font-size: 14px;">
            </div>

            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="end_date" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                    End Date <span class="required-asterisk">*</span>
                </label>
                <input type="date" id="end_date" name="end_date" required 
                    value="<?php echo date('Y-m-d'); ?>"
                    max="<?php echo date('Y-m-d'); ?>"
                    style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; font-size: 14px;">
            </div>
            
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Report Type</label>
                <select id="report_type" name="report_type" 
                        style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; font-size: 14px;">
                    <option value="all">All Items (Lost & Found)</option>
                    <option value="lost">Lost Items Only</option>
                    <option value="found">Found Items Only</option>
                </select>
            </div>
        </div>
        
        <div class="quick-date-buttons" style="margin-bottom: 20px;">
            <p style="margin-bottom: 10px; font-weight: 600; color: #495057;">Quick Date Range:</p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="date-range-btn" data-days="7" style="padding: 8px 15px; background: #e9ecef; border: 1px solid #ced4da; border-radius: 5px; cursor: pointer; font-size: 13px;">Last 7 Days</button>
                <button type="button" class="date-range-btn" data-days="30" style="padding: 8px 15px; background: #e9ecef; border: 1px solid #ced4da; border-radius: 5px; cursor: pointer; font-size: 13px;">Last 30 Days</button>
                <button type="button" class="date-range-btn" data-days="90" style="padding: 8px 15px; background: #e9ecef; border: 1px solid #ced4da; border-radius: 5px; cursor: pointer; font-size: 13px;">Last 90 Days</button>
                <button type="button" class="date-range-btn" data-type="month" style="padding: 8px 15px; background: #e9ecef; border: 1px solid #ced4da; border-radius: 5px; cursor: pointer; font-size: 13px;">This Month</button>
                <button type="button" class="date-range-btn" data-type="week" style="padding: 8px 15px; background: #e9ecef; border: 1px solid #ced4da; border-radius: 5px; cursor: pointer; font-size: 13px;">This Week</button>
            </div>
        </div>
        
        <div class="report-actions" style="display: flex; gap: 15px; margin-top: 25px;">
            <button type="button" onclick="generateReport('view')" style="padding: 12px 25px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 500;">
                Generate Report
            </button>
            <button type="button" onclick="generateReport('csv')" style="padding: 12px 25px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 500;">
                Download CSV
            </button>
        </div>
    </form>
</div>

<!-- Report Results Section -->
<?php if (isset($_SESSION['report_data'])): 
    $reportData = $_SESSION['report_data'];
    unset($_SESSION['report_data']); // Clear after display
?>
<div id="report-results" style="margin-top: 40px; padding: 25px; background: white; border-radius: 10px; border: 1px solid #dee2e6; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div class="report-header" style="border-bottom: 2px solid #007bff; padding-bottom: 15px; margin-bottom: 25px;">
        <h3 style="color: #007bff; margin-bottom: 10px;">Generated Report</h3>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <p style="margin: 5px 0; color: #495057;">
                    <strong>Date Range:</strong> <?php echo date('F j, Y', strtotime($reportData['start_date'])) . ' to ' . date('F j, Y', strtotime($reportData['end_date'])); ?>
                </p>
                <p style="margin: 5px 0; color: #495057;">
                    <strong>Report Type:</strong> <?php echo $reportData['report_type'] === 'all' ? 'All Items' : ucfirst($reportData['report_type']) . ' Items'; ?>
                </p>
            </div>
            <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">
                Generated: <?php echo date('F j, Y g:i A', strtotime($reportData['generated_at'])); ?>
            </p>
        </div>
    </div>
    
    <!-- Summary Section -->
    <div class="report-summary" style="margin-bottom: 30px;">
        <h4 style="color: #495057; margin-bottom: 15px; border-bottom: 1px solid #e9ecef; padding-bottom: 8px;">Summary</h4>
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <?php 
            $lostSummary = $foundSummary = ['count' => 0, 'returned_count' => 0, 'confirmed_count' => 0, 'pending_count' => 0];
            foreach ($reportData['summary'] as $summary) {
                if ($summary['report_type'] === 'lost') {
                    $lostSummary = $summary;
                } else {
                    $foundSummary = $summary;
                }
            }
            ?>
            <div style="flex: 1; min-width: 250px; background: #fff5f5; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545;">
                <h5 style="color: #dc3545; margin-top: 0; margin-bottom: 15px;">Lost Items</h5>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <div><strong>Total:</strong> <?php echo $lostSummary['count']; ?></div>
                    <div><strong>Returned:</strong> <?php echo $lostSummary['returned_count']; ?></div>
                    <div><strong>Confirmed:</strong> <?php echo $lostSummary['confirmed_count']; ?></div>
                    <div><strong>Pending:</strong> <?php echo $lostSummary['pending_count']; ?></div>
                </div>
            </div>
            
            <div style="flex: 1; min-width: 250px; background: #f0f9ff; padding: 20px; border-radius: 8px; border-left: 4px solid #17a2b8;">
                <h5 style="color: #17a2b8; margin-top: 0; margin-bottom: 15px;">Found Items</h5>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <div><strong>Total:</strong> <?php echo $foundSummary['count']; ?></div>
                    <div><strong>Returned:</strong> <?php echo $foundSummary['returned_count']; ?></div>
                    <div><strong>Confirmed:</strong> <?php echo $foundSummary['confirmed_count']; ?></div>
                    <div><strong>Pending:</strong> <?php echo $foundSummary['pending_count']; ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Category Breakdown -->
    <div class="category-breakdown" style="margin-bottom: 30px;">
        <h4 style="color: #495057; margin-bottom: 15px; border-bottom: 1px solid #e9ecef; padding-bottom: 8px;">Category Breakdown</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
            <?php 
            $categoryStats = [];
            foreach ($reportData['categories'] as $cat) {
                $categoryName = $cat['category_name'];
                if (!isset($categoryStats[$categoryName])) {
                    $categoryStats[$categoryName] = ['lost' => 0, 'found' => 0];
                }
                $categoryStats[$categoryName][$cat['report_type']] = $cat['item_count'];
            }
            
            foreach ($categoryStats as $category => $counts):
                $total = $counts['lost'] + $counts['found'];
            ?>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #6f42c1;">
                <strong><?php echo $category; ?></strong>
                <div style="margin-top: 8px; font-size: 14px;">
                    <div>Total: <?php echo $total; ?></div>
                    <div>Lost: <?php echo $counts['lost']; ?></div>
                    <div>Found: <?php echo $counts['found']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Detailed Items List -->
    <div class="items-list">
        <h4 style="color: #495057; margin-bottom: 15px; border-bottom: 1px solid #e9ecef; padding-bottom: 8px;">Detailed Items List</h4>
        
        <?php if (empty($reportData['items'])): ?>
            <div style="text-align: center; padding: 40px; color: #6c757d;">
                <p style="font-size: 18px; margin-bottom: 10px;">📭</p>
                <p>No items found in the selected date range.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Report ID</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Type</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Status</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Date</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Description</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Category</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Location</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Reporter</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['items'] as $item): 
                            $dateField = $item['report_type'] === 'lost' ? $item['date_lost'] : $item['date_found'];
                            $date = $dateField ? date('M j, Y', strtotime($dateField)) : date('M j, Y', strtotime($item['report_date']));
                        ?>
                        <tr style="border-bottom: 1px solid #e9ecef;">
                        <td style="padding: 10px; vertical-align: top;"><code><?php echo $item['report_id']; ?></code></td>
                            <td style="padding: 10px; vertical-align: top;">
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; 
                                    background: <?php echo $item['report_type'] === 'lost' ? '#fff5f5' : '#f0f9ff'; ?>; 
                                    color: <?php echo $item['report_type'] === 'lost' ? '#dc3545' : '#17a2b8'; ?>;">
                                    <?php echo ucfirst($item['report_type']); ?>
                                </span>
                            </td>
                            <td style="padding: 10px; vertical-align: top;">
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;
                                    background: <?php 
                                        if ($item['status'] === 'returned') echo '#d4edda';
                                        elseif ($item['status'] === 'confirmed') echo '#fff3cd';
                                        else echo '#f8d7da';
                                    ?>; 
                                    color: <?php 
                                        if ($item['status'] === 'returned') echo '#155724';
                                        elseif ($item['status'] === 'confirmed') echo '#856404';
                                        else echo '#721c24';
                                    ?>;">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                            </td>
                            <td style="padding: 10px; vertical-align: top;"><?php echo $date; ?></td>
                            <td style="padding: 10px; vertical-align: top;"><?php echo htmlspecialchars($item['description']); ?></td>
                            <td style="padding: 10px; vertical-align: top;"><?php echo $item['category_name']; ?></td>
                            <td style="padding: 10px; vertical-align: top;"><?php echo htmlspecialchars($item['location_name']); ?></td>
                            <td style="padding: 10px; vertical-align: top;">
                                <?php 
                                // Check if item has user_id
                                if (isset($item['user_id'])) {
                                    // Fetch reporter username
                                    $db = DatabaseService::getInstance()->getConnection();
                                    $stmt = $db->prepare("SELECT username FROM users WHERE user_id = ?");
                                    $stmt->execute([$item['user_id']]);
                                    $reporter = $stmt->fetch(PDO::FETCH_ASSOC);
                                    echo htmlspecialchars($reporter['username'] ?? 'Unknown');
                                } else {
                                    echo 'Unknown';
                                }
                                ?>
                                </td>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px; text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <p style="margin: 0; color: #495057;">
                    <strong>Total Items:</strong> <?php echo count($reportData['items']); ?> | 
                    <strong>Lost:</strong> <?php echo $lostSummary['count']; ?> | 
                    <strong>Found:</strong> <?php echo $foundSummary['count']; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
    </div>
<?php endif; ?>

                </div>
    </div>
        <?php endif; ?>
    </div>

<script>
// Function to toggle claim forms
function toggleClaimForm(reportId) {
    const form = document.getElementById('claim-form-' + reportId);
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}

// Function to initialize admin tabs
function initAdminTabs() {
    console.log('Initializing admin tabs...');
    
    const tabButtons = document.querySelectorAll('.admin-tab-btn');
    const tabPanes = document.querySelectorAll('.admin-tab-pane');
    
    console.log('Found ' + tabButtons.length + ' tab buttons');
    console.log('Found ' + tabPanes.length + ' tab panes');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            console.log('Tab clicked: ' + targetTab);
            
            // Remove active class from all buttons and panes
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Add active class to clicked button and corresponding pane
            this.classList.add('active');
            const targetPane = document.getElementById(targetTab + '-tab');
            if (targetPane) {
                targetPane.classList.add('active');
                console.log('Activated tab: ' + targetTab);
            } else {
                console.error('Tab pane not found: ' + targetTab + '-tab');
            }
        });
    });
    
    // Set initial active tab if none is active
    const activeTabs = document.querySelectorAll('.admin-tab-pane.active');
    if (activeTabs.length === 0 && tabPanes.length > 0) {
        tabPanes[0].classList.add('active');
        console.log('Set initial active tab');
    }
}

// Report type selector functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize report type selector
    const reportTypeButtons = document.querySelectorAll('.report-type-btn');
    const reportTypeInput = document.getElementById('report_type');
    
    if (reportTypeButtons.length > 0) {
        reportTypeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                reportTypeInput.value = type;
                
                // Update active state
                reportTypeButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                console.log('Report type set to: ' + type);
            });
        });
    }
    
    // Initialize admin tabs if we're on admin page
    if (document.querySelector('.admin-section')) {
        console.log('Admin section detected, initializing tabs...');
        initAdminTabs();
    }
});

// Enhanced image modal with gallery navigation
let currentImageIndex = 0;
let currentImages = [];

function openImageModal(imageSrc, description) {
    // Get all images for this report
    const reportImages = document.querySelectorAll('.report-images-container .image-thumb img');
    currentImages = Array.from(reportImages).map(img => img.src);
    
    // Find the clicked image index
    currentImageIndex = currentImages.findIndex(src => src === imageSrc);
    
    // Create modal
    const modalOverlay = document.createElement('div');
    modalOverlay.className = 'image-modal active';
    modalOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.9);
        z-index: 1000;
        display: flex;
        justify-content: center;
        align-items: center;
    `;
    
    // Create gallery container
    const galleryContainer = document.createElement('div');
    galleryContainer.className = 'image-gallery';
    
    // Create navigation buttons
    const prevButton = document.createElement('button');
    prevButton.className = 'gallery-nav';
    prevButton.innerHTML = '←';
    prevButton.onclick = showPrevImage;
    prevButton.disabled = currentImages.length <= 1;
    
    const nextButton = document.createElement('button');
    nextButton.className = 'gallery-nav';
    nextButton.innerHTML = '→';
    nextButton.onclick = showNextImage;
    nextButton.disabled = currentImages.length <= 1;
    
    // Create modal content
    const modalContent = document.createElement('div');
    modalContent.className = 'image-modal-content';
    modalContent.style.cssText = `
        position: relative;
        max-width: 90%;
        max-height: 90%;
    `;
    
    // Create image
    const modalImage = document.createElement('img');
    modalImage.className = 'image-modal-img';
    modalImage.src = imageSrc;
    modalImage.alt = description;
    
    // Create close button
    const closeButton = document.createElement('button');
    closeButton.className = 'image-modal-close';
    closeButton.innerHTML = '×';
    closeButton.onclick = closeModal;
    
    // Create description
    const descElement = document.createElement('div');
    descElement.className = 'image-modal-description';
    descElement.textContent = description;
    
    // Create image counter
    const counterElement = document.createElement('div');
    counterElement.className = 'gallery-counter';
    counterElement.textContent = `${currentImageIndex + 1} / ${currentImages.length}`;
    
    // Assemble modal
    modalContent.appendChild(modalImage);
    modalContent.appendChild(closeButton);
    modalContent.appendChild(counterElement);
    
    galleryContainer.appendChild(prevButton);
    galleryContainer.appendChild(modalContent);
    galleryContainer.appendChild(nextButton);
    modalOverlay.appendChild(galleryContainer);
    
    if (description) {
        modalOverlay.appendChild(descElement);
    }
    
    // Add to page
    document.body.style.overflow = 'hidden';
    document.body.appendChild(modalOverlay);
    
    // Add keyboard navigation
    document.addEventListener('keydown', handleKeyboardNavigation);
}

function showPrevImage() {
    if (currentImages.length <= 1) return;
    
    currentImageIndex = (currentImageIndex - 1 + currentImages.length) % currentImages.length;
    updateModalImage();
}

function showNextImage() {
    if (currentImages.length <= 1) return;
    
    currentImageIndex = (currentImageIndex + 1) % currentImages.length;
    updateModalImage();
}

function updateModalImage() {
    const modalImg = document.querySelector('.image-modal-img');
    const counter = document.querySelector('.gallery-counter');
    
    if (modalImg && counter) {
        modalImg.src = currentImages[currentImageIndex];
        counter.textContent = `${currentImageIndex + 1} / ${currentImages.length}`;
        
        // Add fade effect
        modalImg.style.opacity = '0';
        setTimeout(() => {
            modalImg.style.opacity = '1';
            modalImg.style.transition = 'opacity 0.3s ease';
        }, 50);
    }
}

function handleKeyboardNavigation(e) {
    const modal = document.querySelector('.image-modal.active');
    if (!modal) return;
    
    switch(e.key) {
        case 'ArrowLeft':
            e.preventDefault();
            showPrevImage();
            break;
        case 'ArrowRight':
            e.preventDefault();
            showNextImage();
            break;
        case 'Escape':
            e.preventDefault();
            closeModal();
            break;
    }
}

function closeModal() {
    const modal = document.querySelector('.image-modal.active');
    if (modal) {
        document.body.removeChild(modal);
        document.body.style.overflow = 'auto';
        document.removeEventListener('keydown', handleKeyboardNavigation);
    }
}

// Close modal when clicking outside image
document.addEventListener('click', function(e) {
    const modal = document.querySelector('.image-modal.active');
    if (modal && e.target === modal) {
        closeModal();
    }
});

// Fallback initialization for admin tabs
if (document.querySelector('.admin-section') && typeof initAdminTabs === 'function') {
    setTimeout(function() {
        initAdminTabs();
    }, 500);
}
// Report type selector functionality with date field toggling
document.addEventListener('DOMContentLoaded', function() {
    // Initialize report type selector
    const reportTypeButtons = document.querySelectorAll('.report-type-btn');
    const reportTypeInput = document.getElementById('report_type');
    const dateLostField = document.querySelector('.date-lost-field');
    const dateFoundField = document.querySelector('.date-found-field');
    
    function toggleDateFields(type) {
        if (type === 'lost') {
            dateLostField.style.display = 'block';
            dateFoundField.style.display = 'none';
            // Make date lost required, date found not required
            document.getElementById('date_lost').required = true;
            document.getElementById('date_found').required = false;
        } else {
            dateLostField.style.display = 'none';
            dateFoundField.style.display = 'block';
            // Make date found required, date lost not required
            document.getElementById('date_lost').required = false;
            document.getElementById('date_found').required = true;
        }
    }
    
    if (reportTypeButtons.length > 0) {
        reportTypeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                reportTypeInput.value = type;
                
                // Update active state
                reportTypeButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Toggle date fields
                toggleDateFields(type);
                
                console.log('Report type set to: ' + type);
            });
        });
        
        // Initialize with correct date fields
        toggleDateFields(reportTypeInput.value);
    }
    
    // Initialize admin tabs if we're on admin page
    if (document.querySelector('.admin-section')) {
        console.log('Admin section detected, initializing tabs...');
        initAdminTabs();
    }
});

// Notification functionality
document.addEventListener('DOMContentLoaded', function() {
    // Mark notification as read
    document.querySelectorAll('.mark-read-btn').forEach(button => {
        button.addEventListener('click', function() {
            const notificationItem = this.closest('.notification-item');
            const notificationId = notificationItem.getAttribute('data-notification-id');
            
            markNotificationAsRead(notificationId, notificationItem);
        });
    });
    
    // Auto-mark as read when clicking notification (for unread items)
    document.querySelectorAll('.notification-item.unread').forEach(item => {
        item.addEventListener('click', function(e) {
            // Don't trigger if clicking the mark-read button
            if (!e.target.closest('.mark-read-btn')) {
                const notificationId = this.getAttribute('data-notification-id');
                markNotificationAsRead(notificationId, this);
            }
        });
    });
});

function markNotificationAsRead(notificationId, notificationElement) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_notification_read&notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            notificationElement.classList.remove('unread');
            notificationElement.classList.add('read');
            
            // Remove the mark-read button
            const markReadBtn = notificationElement.querySelector('.mark-read-btn');
            if (markReadBtn) {
                markReadBtn.remove();
            }
            
            // Update notification badge count
            updateNotificationBadge();
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function updateNotificationBadge() {
    // This would typically refresh the page or update via AJAX
    // For now, we'll just reload the badge by refreshing the count
    location.reload();
}
// Auto-hide success banner after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successBanner = document.querySelector('.verification-success-banner');
    if (successBanner) {
        setTimeout(function() {
            successBanner.style.display = 'none';
        }, 5000); // Hide after 5 seconds
    }
    
    // Auto-focus on PIN input when verification banner is shown
    const pinInput = document.getElementById('pin_code');
    if (pinInput) {
        pinInput.focus();
    }
});
function generateReport(format) {
    document.getElementById('report-format').value = format;
    document.getElementById('report-form').submit();
}

// Quick date range buttons
document.querySelectorAll('.date-range-btn').forEach(button => {
    button.addEventListener('click', function() {
        const days = this.getAttribute('data-days');
        const type = this.getAttribute('data-type');
        
        const endDate = new Date();
        let startDate = new Date();
        
        if (days) {
            startDate.setDate(endDate.getDate() - parseInt(days));
        } else if (type === 'week') {
            // Start of current week (Monday)
            startDate.setDate(endDate.getDate() - endDate.getDay() + 1);
        } else if (type === 'month') {
            // Start of current month
            startDate = new Date(endDate.getFullYear(), endDate.getMonth(), 1);
        }
        
        // Format dates as YYYY-MM-DD
        document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
        document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
    });
});

// Set max dates to today
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('end_date').max = today;
    document.getElementById('start_date').max = today;
});

// Image preview functionality
function previewImages(event, type = 'report') {
    const files = event.target.files;
    const previewContainer = type === 'new' ? 
        document.getElementById('new-image-preview-container') : 
        document.getElementById('image-preview-container');
    const previewsDiv = type === 'new' ? 
        document.getElementById('new-image-previews') : 
        document.getElementById('image-previews');
    
    // Clear previous previews
    previewsDiv.innerHTML = '';
    
    if (files.length === 0) {
        previewContainer.style.display = 'none';
        return;
    }
    
    // Show preview container
    previewContainer.style.display = 'block';
    
    // Process each file
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        // Validate file type
        if (!file.type.match('image.*')) {
            alert(`File "${file.name}" is not an image. Please select image files only.`);
            continue;
        }
        
        // Validate file size (20MB = 20 * 1024 * 1024 bytes)
        if (file.size > 20 * 1024 * 1024) {
            alert(`File "${file.name}" is too large. Maximum size is 20MB.`);
            continue;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewDiv = document.createElement('div');
            previewDiv.className = 'image-preview-item';
            previewDiv.style.cssText = `
                position: relative;
                width: 120px;
                height: 120px;
                border-radius: 8px;
                overflow: hidden;
                border: 2px solid #e9ecef;
                background: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = `
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
            `;
            img.alt = `Preview ${i + 1}`;
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.innerHTML = '×';
            removeBtn.style.cssText = `
                position: absolute;
                top: 5px;
                right: 5px;
                background: #dc3545;
                color: white;
                border: none;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
                padding: 0;
            `;
            removeBtn.onclick = function() {
                removeImagePreview(file.name, event.target, previewDiv, type);
            };
            
            // File info overlay
            const fileInfo = document.createElement('div');
            fileInfo.style.cssText = `
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(0,0,0,0.7);
                color: white;
                padding: 4px;
                font-size: 10px;
                text-align: center;
            `;
            fileInfo.textContent = `${file.name} (${formatFileSize(file.size)})`;
            
            previewDiv.appendChild(img);
            previewDiv.appendChild(removeBtn);
            previewDiv.appendChild(fileInfo);
            previewsDiv.appendChild(previewDiv);
        };
        
        reader.readAsDataURL(file);
    }
}

// Remove image preview and update file input
function removeImagePreview(fileName, fileInput, previewDiv, type) {
    // Remove preview
    previewDiv.remove();
    
    // Create a new FileList without the removed file
    const dataTransfer = new DataTransfer();
    const files = fileInput.files;
    
    for (let i = 0; i < files.length; i++) {
        if (files[i].name !== fileName) {
            dataTransfer.items.add(files[i]);
        }
    }
    
    // Update file input
    fileInput.files = dataTransfer.files;
    
    // Trigger change event to update preview count
    fileInput.dispatchEvent(new Event('change'));
    
    // Hide preview container if no images left
    const previewContainer = type === 'new' ? 
        document.getElementById('new-image-preview-container') : 
        document.getElementById('image-preview-container');
    const previewsDiv = type === 'new' ? 
        document.getElementById('new-image-previews') : 
        document.getElementById('image-previews');
    
    if (previewsDiv.children.length === 0) {
        previewContainer.style.display = 'none';
    }
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

// Drag and drop functionality
function setupDragAndDrop() {
    const dropAreas = document.querySelectorAll('input[type="file"]');
    
    dropAreas.forEach(dropArea => {
        const parent = dropArea.parentElement;
        
        // Add drag and drop events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            parent.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Highlight drop area
        ['dragenter', 'dragover'].forEach(eventName => {
            parent.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            parent.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            parent.style.background = '#e8f4fd';
            parent.style.border = '2px dashed #3498db';
        }
        
        function unhighlight() {
            parent.style.background = '';
            parent.style.border = '';
        }
        
        // Handle drop
        parent.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length) {
                // Update file input
                const dataTransfer = new DataTransfer();
                const existingFiles = dropArea.files;
                
                // Keep existing files
                for (let i = 0; i < existingFiles.length; i++) {
                    dataTransfer.items.add(existingFiles[i]);
                }
                
                // Add new files
                for (let i = 0; i < files.length; i++) {
                    if (files[i].type.match('image.*') && files[i].size <= 20 * 1024 * 1024) {
                        dataTransfer.items.add(files[i]);
                    } else {
                        alert(`File "${files[i].name}" is not a valid image or exceeds 20MB limit.`);
                    }
                }
                
                dropArea.files = dataTransfer.files;
                
                // Trigger change event to show previews
                dropArea.dispatchEvent(new Event('change'));
            }
        }
    });
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    setupDragAndDrop();
    
    // Update file input labels to show count
    const fileInputs = document.querySelectorAll('input[type="file"][multiple]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const label = this.nextElementSibling;
            if (label && label.tagName === 'SMALL') {
                if (this.files.length > 0) {
                    label.textContent = `${this.files.length} image(s) selected. Max file size: 20MB each. Supported formats: JPG, PNG, GIF`;
                } else {
                    label.textContent = 'You can select multiple images. Max file size: 20MB each. Supported formats: JPG, PNG, GIF';
                }
            }
        });
    });
});

// Claim image preview functionality
function previewClaimImages(event, reportId) {
    const files = event.target.files;
    const previewContainer = document.getElementById('claim-image-preview-' + reportId);
    
    // Clear previous previews
    previewContainer.innerHTML = '';
    
    if (files.length === 0) {
        previewContainer.style.display = 'none';
        return;
    }
    
    // Show preview container
    previewContainer.style.display = 'grid';
    previewContainer.style.gridTemplateColumns = 'repeat(auto-fill, minmax(100px, 1fr))';
    previewContainer.style.gap = '10px';
    previewContainer.style.marginTop = '10px';
    
    // Process each file
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        // Validate file type
        if (!file.type.match('image.*')) {
            alert(`File "${file.name}" is not an image. Please select image files only.`);
            continue;
        }
        
        // Validate file size (20MB = 20 * 1024 * 1024 bytes)
        if (file.size > 20 * 1024 * 1024) {
            alert(`File "${file.name}" is too large. Maximum size is 20MB.`);
            continue;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewDiv = document.createElement('div');
            previewDiv.style.cssText = `
                position: relative;
                width: 100px;
                height: 100px;
                border-radius: 8px;
                overflow: hidden;
                border: 2px solid #e9ecef;
                background: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = `
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
            `;
            img.alt = `Claim Image ${i + 1}`;
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.innerHTML = '×';
            removeBtn.style.cssText = `
                position: absolute;
                top: 5px;
                right: 5px;
                background: #dc3545;
                color: white;
                border: none;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                font-size: 14px;
                font-weight: bold;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
                padding: 0;
            `;
            removeBtn.onclick = function() {
                removeClaimImagePreview(file.name, event.target, previewDiv, reportId);
            };
            
            // File info overlay
            const fileInfo = document.createElement('div');
            fileInfo.style.cssText = `
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(0,0,0,0.7);
                color: white;
                padding: 2px;
                font-size: 8px;
                text-align: center;
            `;
            fileInfo.textContent = `${file.name.substring(0, 15)}...`;
            
            previewDiv.appendChild(img);
            previewDiv.appendChild(removeBtn);
            previewDiv.appendChild(fileInfo);
            previewContainer.appendChild(previewDiv);
        };
        
        reader.readAsDataURL(file);
    }
}

// Remove claim image preview and update file input
function removeClaimImagePreview(fileName, fileInput, previewDiv, reportId) {
    // Remove preview
    previewDiv.remove();
    
    // Create a new FileList without the removed file
    const dataTransfer = new DataTransfer();
    const files = fileInput.files;
    
    for (let i = 0; i < files.length; i++) {
        if (files[i].name !== fileName) {
            dataTransfer.items.add(files[i]);
        }
    }
    
    // Update file input
    fileInput.files = dataTransfer.files;
    
    // Trigger change event to update preview count
    fileInput.dispatchEvent(new Event('change'));
    
    // Hide preview container if no images left
    const previewContainer = document.getElementById('claim-image-preview-' + reportId);
    if (previewContainer.children.length === 0) {
        previewContainer.style.display = 'none';
    }
}

// Initialize claim image functionality
document.addEventListener('DOMContentLoaded', function() {
    // Set up claim image previews
    const claimFileInputs = document.querySelectorAll('input[name="claim_images[]"]');
    claimFileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const reportId = this.id.replace('claim_images_', '');
            previewClaimImages({target: this}, reportId);
        });
    });
});
</script>

</body>
</html>
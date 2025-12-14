<?php
require_once '../class/system.php';
require_once '../class/database.php';

class Category {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseService::getInstance()->getConnection();
    }
    
    public function getAll() {
        $stmt = $this->db->prepare("SELECT * FROM category ORDER BY category_name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getById($category_id) {
        $stmt = $this->db->prepare("SELECT * FROM category WHERE category_id = ?");
        $stmt->execute([$category_id]);
        return $stmt->fetch();
    }
    
    public function create($category_id, $category_name) {
        $stmt = $this->db->prepare("INSERT INTO category (category_id, category_name) VALUES (?, ?)");
        return $stmt->execute([$category_id, $category_name]);
    }
    
    public function exists($category_id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM category WHERE category_id = ?");
        $stmt->execute([$category_id]);
        return $stmt->fetchColumn() > 0;
    }
}

class Location {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseService::getInstance()->getConnection();
    }
    
    public function create($location_id, $location_name) {
        try {
            $stmt = $this->db->prepare("INSERT INTO location (location_id, location_name) VALUES (?, ?)");
            return $stmt->execute([$location_id, $location_name]);
        } catch (PDOException $e) {
            error_log("Location creation error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getLocationIdByName($location_name) {
        $stmt = $this->db->prepare("SELECT location_id FROM location WHERE location_name = ?");
        $stmt->execute([$location_name]);
        return $stmt->fetchColumn();
    }
    
    public function generateLocationId() {
        $stmt = $this->db->prepare("SELECT location_id FROM location ORDER BY location_id DESC LIMIT 1");
        $stmt->execute();
        $lastId = $stmt->fetchColumn();
        
        if ($lastId) {
            return intval($lastId) + 1;
        } else {
            return 1;
        }
    }
    public function getLocationById($locationId) {
    $db = DatabaseService::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM location WHERE location_id = ?");
    $stmt->execute([$locationId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
}

class User {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseService::getInstance()->getConnection();
    }
    
    public function authenticate($username, $password) {
    try {
        $db = DatabaseService::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user = $this->getUserByUsername($username);
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}
    
public function generateUserId($role = 'user') {
    $db = DatabaseService::getInstance()->getConnection();
    $prefix = ($role === 'admin') ? 'ADM' : 'USR';
    
    $stmt = $db->prepare("
        SELECT user_id FROM users 
        WHERE user_id LIKE ? 
        ORDER BY user_id DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $lastId = $stmt->fetchColumn();
    
    if ($lastId) {
        $number = intval(substr($lastId, 3)) + 1;
    } else {
        $number = 1;
    }
    
    return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
}

public function updateVerificationToken($user_id, $token, $expires) {
    $stmt = $this->db->prepare("
        UPDATE users 
        SET verification_token = ?, token_expires = ? 
        WHERE user_id = ?
    ");
    return $stmt->execute([$token, $expires, $user_id]);
}

public function verifyEmail($token) {
    try {
        // Check if token exists and is not expired
        $stmt = $this->db->prepare("
            SELECT user_id, token_expires 
            FROM users 
            WHERE verification_token = ? AND email_verified = FALSE
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("Invalid or expired verification token.");
        }
        
        // Check if token is expired
        if (strtotime($user['token_expires']) < time()) {
            throw new Exception("Verification token has expired.");
        }
        
        // Mark email as verified and clear token
        $updateStmt = $this->db->prepare("
            UPDATE users 
            SET email_verified = TRUE, verification_token = NULL, token_expires = NULL 
            WHERE user_id = ?
        ");
        return $updateStmt->execute([$user['user_id']]);
        
    } catch (Exception $e) {
        error_log("Email verification error: " . $e->getMessage());
        throw $e;
    }
}

public function getUserByVerificationToken($token) {
    $stmt = $this->db->prepare("
        SELECT user_id, username, email, token_expires 
        FROM users 
        WHERE verification_token = ? AND email_verified = FALSE
    ");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

public function getUserByUsername($username) {
    $db = DatabaseService::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function isEmailVerified($userId) {
    $db = DatabaseService::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT email_verified FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result && $result['email_verified'] == 1;
}

public function create($userId, $username, $email, $password, $phoneNumber = null, $role = 'user') {
    $db = DatabaseService::getInstance()->getConnection();
    
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (user_id, username, email, password_hash, phone_number, role, email_verified, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([$userId, $username, $email, $hashedPassword, $phoneNumber, $role]);
        
        return [
            'success' => $result,
            'user_id' => $userId,
            'error' => $result ? null : 'Database execution failed'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

public function getUserById($user_id) {
        try {
            $db = DatabaseService::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user by ID: " . $e->getMessage());
            return false;
        }
    }
public function markEmailAsVerified($userId) {
    $db = DatabaseService::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE users SET email_verified = 1 WHERE user_id = ?");
    return $stmt->execute([$userId]);
}
}


class Item {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseService::getInstance()->getConnection();
    }
    
    public function create($item_id, $description, $category_id, $location_id, $reported_by) {
        try {
            $stmt = $this->db->prepare("INSERT INTO item (item_id, description, category_id, location_id, reported_by) VALUES (?, ?, ?, ?, ?)");
            return $stmt->execute([$item_id, $description, $category_id, $location_id, $reported_by]);
        } catch (PDOException $e) {
            error_log("Item creation error: " . $e->getMessage());
            error_log("Values: item_id=$item_id, category_id=$category_id, location_id=$location_id, reported_by=$reported_by");
            throw $e;
        }
    }

    public function getById($item_id) {
    $stmt = $this->db->prepare("SELECT i.*, c.category_name, l.location_name 
                               FROM item i 
                               JOIN category c ON i.category_id = c.category_id 
                               JOIN location l ON i.location_id = l.location_id 
                               WHERE i.item_id = ?");
    $stmt->execute([$item_id]);
    return $stmt->fetch();
    }
    
    
    public function delete($item_id) {
        $stmt = $this->db->prepare("DELETE FROM item WHERE item_id = ?");
        return $stmt->execute([$item_id]);
    }
    
    public function generateItemId() {
        $stmt = $this->db->prepare("SELECT item_id FROM item ORDER BY item_id DESC LIMIT 1");
        $stmt->execute();
        $lastId = $stmt->fetchColumn();
        
        if ($lastId) {
            $prefix = substr($lastId, 0, 3);
            $number = intval(substr($lastId, 3)) + 1;
        } else {
            $prefix = 'ITM';
            $number = 1;
        }
        
        return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    public function getItemById($item_id) {
        $stmt = $this->db->prepare("SELECT * FROM item WHERE item_id = ?");
        $stmt->execute([$item_id]);
        return $stmt->fetch();
    }
    
    public function update($item_id, $description, $category_id, $location_id) {
        $stmt = $this->db->prepare("
            UPDATE item 
            SET description = ?, category_id = ?, location_id = ? 
            WHERE item_id = ?
        ");
        return $stmt->execute([$description, $category_id, $location_id, $item_id]);
    }
    
    public function getItemImages($item_id) {
        $stmt = $this->db->prepare("SELECT * FROM item_images WHERE item_id = ? ORDER BY uploaded_at");
        $stmt->execute([$item_id]);
        return $stmt->fetchAll();
    }

public function getAllItemsWithReports($filters = []) {
    $sql = "
        SELECT i.*, l.location_name, c.category_name, r.report_type, 
               r.status, r.report_id, r.user_id, r.created_at,
               r.date_lost, r.date_found,
               (SELECT image_path FROM item_images WHERE item_id = i.item_id LIMIT 1) as image_path
        FROM item i 
        LEFT JOIN location l ON i.location_id = l.location_id 
        LEFT JOIN category c ON i.category_id = c.category_id 
        LEFT JOIN report r ON i.item_id = r.item_id 
        WHERE 1=1
    ";
    
    $params = [];

    if (!empty($filters['category_id'])) {
        $sql .= " AND i.category_id = ?";
        $params[] = $filters['category_id'];
    }

    if (!empty($filters['location_id'])) {
        $sql .= " AND i.location_id = ?";
        $params[] = $filters['location_id'];
    }

    if (!empty($filters['report_type'])) {
        $sql .= " AND r.report_type = ?";
        $params[] = $filters['report_type'];
    }

    if (!empty($filters['search'])) {
        $sql .= " AND (i.description LIKE ? OR c.category_name LIKE ? OR l.location_name LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $sql .= " ORDER BY r.created_at DESC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
}

class ItemImage {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseService::getInstance()->getConnection();
    }
    
    public function create($image_id, $item_id, $image_path) {
        $stmt = $this->db->prepare("INSERT INTO item_images (image_id, item_id, image_path) VALUES (?, ?, ?)");
        return $stmt->execute([$image_id, $item_id, $image_path]);
    }
    
    public function generateImageId() {
        $stmt = $this->db->prepare("SELECT image_id FROM item_images ORDER BY image_id DESC LIMIT 1");
        $stmt->execute();
        $lastId = $stmt->fetchColumn();
        
        if ($lastId) {
            $number = intval(substr($lastId, 3)) + 1;
        } else {
            $number = 1;
        }
        
        return 'IMG' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
    
    public function getByItemId($item_id) {
        $stmt = $this->db->prepare("SELECT * FROM item_images WHERE item_id = ? ORDER BY uploaded_at");
        $stmt->execute([$item_id]);
        return $stmt->fetchAll();
    }

    public function deleteByItemId($item_id) {
    $stmt = $this->db->prepare("DELETE FROM item_images WHERE item_id = ?");
    return $stmt->execute([$item_id]);
    }
    
    public function delete($image_id) {
        // First get the image path to delete the file
        $stmt = $this->db->prepare("SELECT image_path FROM item_images WHERE image_id = ?");
        $stmt->execute([$image_id]);
        $image_path = $stmt->fetchColumn();
        
        if ($image_path && file_exists($image_path)) {
            unlink($image_path);
        }
        
        // Then delete from database
        $stmt = $this->db->prepare("DELETE FROM item_images WHERE image_id = ?");
        return $stmt->execute([$image_id]);
    }
}

class Report {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseService::getInstance()->getConnection();
    }
    
    public function create($report_id, $status, $report_type, $item_id, $user_id, $date_lost = null, $date_found = null) {
    $stmt = $this->db->prepare("INSERT INTO report (report_id, status, report_type, item_id, user_id, date_lost, date_found) VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$report_id, $status, $report_type, $item_id, $user_id, $date_lost, $date_found]);
    }
    
    public function generateReportId() {
        $stmt = $this->db->prepare("SELECT report_id FROM report ORDER BY report_id DESC LIMIT 1");
        $stmt->execute();
        $lastId = $stmt->fetchColumn();
        
        if ($lastId) {
            $prefix = substr($lastId, 0, 3);
            $number = intval(substr($lastId, 3)) + 1;
        } else {
            $prefix = 'REP';
            $number = 1;
        }
        
        return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
    
public function getReportsByUser($user_id) {
    $stmt = $this->db->prepare("
        SELECT r.*, i.description, i.item_id, l.location_name, c.category_name,
               (SELECT GROUP_CONCAT(claim_id) FROM claim WHERE report_id = r.report_id) as claim_ids
        FROM report r
        LEFT JOIN item i ON r.item_id = i.item_id
        LEFT JOIN location l ON i.location_id = l.location_id
        LEFT JOIN category c ON i.category_id = c.category_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC); // Explicitly fetch as associative array
    
    // Get claims for each report
    foreach ($reports as &$report) {
        if ($report['claim_ids']) {
            $claimStmt = $this->db->prepare("
                SELECT * FROM claim 
                WHERE report_id = ? 
                ORDER BY created_at DESC
            ");
            $claimStmt->execute([$report['report_id']]);
            $report['claims'] = $claimStmt->fetchAll(PDO::FETCH_ASSOC); // Fetch as associative array
        } else {
            $report['claims'] = [];
        }
        
        // Get images for the item
        $imageStmt = $this->db->prepare("
            SELECT * FROM item_image 
            WHERE item_id = ?
        ");
        $imageStmt->execute([$report['item_id']]);
        $report['images'] = $imageStmt->fetchAll(PDO::FETCH_ASSOC); // Fetch as associative array
    }
    
    return $reports;
}
    public function getReportById($report_id) {
    $stmt = $this->db->prepare("
        SELECT r.*, i.description, i.item_id, i.category_id, l.location_name, c.category_name,
               u.username, u.email,
               (SELECT GROUP_CONCAT(claim_id) FROM claim WHERE report_id = r.report_id) as claim_ids
        FROM report r
        LEFT JOIN item i ON r.item_id = i.item_id
        LEFT JOIN location l ON i.location_id = l.location_id
        LEFT JOIN category c ON i.category_id = c.category_id
        LEFT JOIN users u ON r.user_id = u.user_id
        WHERE r.report_id = ?
    ");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();
    
    if ($report && $report['claim_ids']) {
        $claimStmt = $this->db->prepare("
            SELECT * FROM claim 
            WHERE report_id = ? 
            ORDER BY created_at DESC
        ");
        $claimStmt->execute([$report_id]);
        $report['claims'] = $claimStmt->fetchAll();
    } else {
        $report['claims'] = [];
    }
    
    return $report;
    }
    
    public function updateTimestamp($report_id) {
        $stmt = $this->db->prepare("UPDATE report SET updated_at = CURRENT_TIMESTAMP WHERE report_id = ?");
        return $stmt->execute([$report_id]);
    }
    
    public function deleteWithConstraints($report_id) {
        try {
            error_log("Starting deletion process for report: " . $report_id);
            
            // First, get the item_id associated with this report
            $stmt = $this->db->prepare("SELECT item_id FROM report WHERE report_id = ?");
            $stmt->execute([$report_id]);
            $item_id = $stmt->fetchColumn();
            
            if (!$item_id) {
                error_log("No item found for report: " . $report_id);
                return false;
            }
            
            error_log("Found item_id: " . $item_id . " for report: " . $report_id);
            
            // Step 1: Check for claims and delete them first if they exist
            $claimStmt = $this->db->prepare("SELECT claim_id FROM claim WHERE report_id = ?");
            $claimStmt->execute([$report_id]);
            $claims = $claimStmt->fetchAll();
            
            if (!empty($claims)) {
                error_log("Found " . count($claims) . " claims for report: " . $report_id);
                
                // Delete related handover logs first
                foreach ($claims as $claim) {
                    $handoverStmt = $this->db->prepare("DELETE FROM handover_log WHERE claim_id = ?");
                    $handoverStmt->execute([$claim['claim_id']]);
                    error_log("Deleted handover logs for claim: " . $claim['claim_id']);
                }
                
                // Then delete the claims
                $deleteClaimsStmt = $this->db->prepare("DELETE FROM claim WHERE report_id = ?");
                $deleteClaimsStmt->execute([$report_id]);
                error_log("Deleted claims for report: " . $report_id);
            }
            
            // Step 2: Delete the report itself
            $deleteReportStmt = $this->db->prepare("DELETE FROM report WHERE report_id = ?");
            $deleteReportStmt->execute([$report_id]);
            error_log("Deleted report: " . $report_id);
            
            // Step 3: Delete item images
            $deleteImagesStmt = $this->db->prepare("DELETE FROM item_images WHERE item_id = ?");
            $deleteImagesStmt->execute([$item_id]);
            error_log("Deleted images for item: " . $item_id);
            
            // Step 4: Finally delete the item
            $deleteItemStmt = $this->db->prepare("DELETE FROM item WHERE item_id = ?");
            $deleteItemStmt->execute([$item_id]);
            error_log("Deleted item: " . $item_id);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error in deleteWithConstraints: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            return false;
        }
    }
    
    // Keep the original delete method for backward compatibility
    public function delete($report_id) {
        return $this->deleteWithConstraints($report_id);
    }

    
    public function getAllReportsWithDetails() {
        $stmt = $this->db->prepare("
            SELECT r.*, i.description, l.location_name, c.category_name, u.username
            FROM report r
            LEFT JOIN item i ON r.item_id = i.item_id
            LEFT JOIN location l ON i.location_id = l.location_id
            LEFT JOIN category c ON i.category_id = c.category_id
            LEFT JOIN users u ON r.user_id = u.user_id
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function updateStatus($report_id, $status) {
        try {
            $stmt = $this->db->prepare("UPDATE report SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE report_id = ?");
            $result = $stmt->execute([$status, $report_id]);
            error_log("Report status update - ID: {$report_id}, Status: {$status}, Result: " . ($result ? "SUCCESS" : "FAILED"));
            return $result;
        } catch (PDOException $e) {
            error_log("Report status update error: " . $e->getMessage());
            return false;
        }
    }

    public function canBeClaimed($report_id, $user_id) {
        $stmt = $this->db->prepare("
            SELECT r.*, 
                   (SELECT COUNT(*) FROM claim c WHERE c.report_id = r.report_id AND c.status IN ('approved', 'completed')) as approved_claims
            FROM report r 
            WHERE r.report_id = ?
        ");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch();
        
        if (!$report) {
            return false;
        }
        
        // Check if user is the reporter
        if ($report['user_id'] == $user_id) {
            return false;
        }
        
        // Check report status
        if ($report['status'] == 'returned' || $report['status'] == 'confirmed') {
            return false;
        }
        
        // Check if there are approved claims
        if ($report['approved_claims'] > 0) {
            return false;
        }
        
        // Check if user already has a pending claim
        $claimStmt = $this->db->prepare("
            SELECT COUNT(*) FROM claim 
            WHERE report_id = ? AND claimed_by = ? AND status = 'pending'
        ");
        $claimStmt->execute([$report_id, $user_id]);
        $userPendingClaims = $claimStmt->fetchColumn();
        
        if ($userPendingClaims > 0) {
            return false;
        }
        
        return true;
    }
    public function confirmReturn($report_id, $user_id) {
    try {
        // Verify the report belongs to the user and is a lost item
        $stmt = $this->db->prepare("
            SELECT r.*, c.claim_id, c.claimed_by 
            FROM report r 
            LEFT JOIN claim c ON r.report_id = c.report_id AND c.status = 'approved'
            WHERE r.report_id = ? AND r.user_id = ? AND r.report_type = 'lost'
        ");
        $stmt->execute([$report_id, $user_id]);
        $report = $stmt->fetch();
        
        if (!$report) {
            throw new Exception("Report not found or you don't have permission.");
        }
        
        if ($report['status'] == 'returned') {
            throw new Exception("Item is already marked as returned.");
        }
        
        if (!$report['claim_id']) {
            throw new Exception("No approved claim found for this item.");
        }
        
        // Update report status to returned
        $updateStmt = $this->db->prepare("UPDATE report SET status = 'returned' WHERE report_id = ?");
        $result = $updateStmt->execute([$report_id]);
        
        if ($result) {
            // Also update the claim status to completed
            $claimStmt = $this->db->prepare("UPDATE claim SET status = 'completed' WHERE claim_id = ?");
            $claimStmt->execute([$report['claim_id']]);
            
            return [
                'success' => true,
                'claimant_id' => $report['claimed_by']
            ];
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error confirming return: " . $e->getMessage());
        throw $e;
    }
}
public function sendReportConfirmedNotification($report_id, $admin_id) {
    $notification = new Notification();
    $reportDetails = $this->getReportById($report_id);
    
    if ($reportDetails && $reportDetails['user_id']) {
        $notification_id = $notification->generateNotificationId();
        $title = "Report Confirmed";
        $message = "Your {$reportDetails['report_type']} item report (#{$report_id}) has been confirmed by admin.";
        
        return $notification->create(
            $notification_id, 
            $reportDetails['user_id'], 
            $title, 
            $message, 
            'report_confirmed',
            $report_id
        );
    }
    return false;
}

public function sendItemReturnedNotification($report_id, $claimant_id) {
    $notification = new Notification();
    $reportDetails = $this->getReportById($report_id);
    
    if ($reportDetails && $claimant_id) {
        $notification_id = $notification->generateNotificationId();
        $title = "Item Returned";
        $message = "The item you claimed (#{$report_id}) has been marked as returned by the owner.";
        
        return $notification->create(
            $notification_id, 
            $claimant_id, 
            $title, 
            $message, 
            'item_returned',
            $report_id
        );
    }
    return false;
}
}

class Claim {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseService::getInstance()->getConnection();
    }
    
    public function create($claim_id, $status, $report_id, $claimed_by, $claim_description = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO claim (claim_id, status, report_id, claimed_by, claim_description) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([$claim_id, $status, $report_id, $claimed_by, $claim_description]);
            error_log("Claim creation result: " . ($result ? "SUCCESS" : "FAILED"));
            return $result;
        } catch (PDOException $e) {
            error_log("Claim creation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function generateClaimId() {
        $stmt = $this->db->prepare("SELECT claim_id FROM claim ORDER BY claim_id DESC LIMIT 1");
        $stmt->execute();
        $lastId = $stmt->fetchColumn();
        
        if ($lastId) {
            $number = intval(substr($lastId, 3)) + 1;
            return 'CLM' . str_pad($number, 3, '0', STR_PAD_LEFT);
        } else {
            return 'CLM001';
        }
    }
    
    public function getClaimsByStatus($status) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, r.report_type, i.description as item_description, 
                       u.username as claimant_username, u.email as claimant_email,
                       ur.username as reporter_username, ur.email as reporter_email,
                       r.item_id, i.reported_by, r.report_id
                FROM claim c
                LEFT JOIN report r ON c.report_id = r.report_id
                LEFT JOIN item i ON r.item_id = i.item_id
                LEFT JOIN users u ON c.claimed_by = u.user_id
                LEFT JOIN users ur ON r.user_id = ur.user_id
                WHERE c.status = ?
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$status]);
            $claims = $stmt->fetchAll();
            error_log("Found " . count($claims) . " claims with status: " . $status);
            return $claims;
        } catch (PDOException $e) {
            error_log("Error getting claims by status: " . $e->getMessage());
            return [];
        }
    }
    
    public function getClaimsByReportId($report_id) {
    $stmt = $this->db->prepare("
        SELECT c.*, u.username as claimant_username 
        FROM claim c 
        LEFT JOIN users u ON c.claimed_by = u.user_id 
        WHERE c.report_id = ? 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$report_id]);
    return $stmt->fetchAll();
}
    
    public function updateStatus($claim_id, $status, $admin_notes = null) {
        try {
            $stmt = $this->db->prepare("
                UPDATE claim 
                SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE claim_id = ?
            ");
            $result = $stmt->execute([$status, $admin_notes, $claim_id]);
            error_log("Claim status update - ID: {$claim_id}, Status: {$status}, Result: " . ($result ? "SUCCESS" : "FAILED"));
            return $result;
        } catch (PDOException $e) {
            error_log("Claim status update error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getClaimById($claim_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, r.report_type, i.description as item_description, r.report_id,
                       u.username as claimant_username, u.email as claimant_email,
                       ur.username as reporter_username
                FROM claim c
                LEFT JOIN report r ON c.report_id = r.report_id
                LEFT JOIN item i ON r.item_id = i.item_id
                LEFT JOIN users u ON c.claimed_by = u.user_id
                LEFT JOIN users ur ON r.user_id = ur.user_id
                WHERE c.claim_id = ?
            ");
            $stmt->execute([$claim_id]);
            $claim = $stmt->fetch();
            
            if ($claim) {
                error_log("Claim found: ID={$claim_id}, Report ID={$claim['report_id']}");
            } else {
                error_log("Claim not found: ID={$claim_id}");
            }
            
            return $claim;
        } catch (PDOException $e) {
            error_log("Error getting claim by ID: " . $e->getMessage());
            return false;
        }
    }
// In the Claim class, add these methods:
public function sendClaimApprovedNotification($claim_id) {
    $notification = new Notification();
    $claimDetails = $this->getClaimById($claim_id);
    
    if ($claimDetails && $claimDetails['claimed_by']) {
        $notification_id = $notification->generateNotificationId();
        $title = "Claim Approved!";
        $message = "Your claim (#{$claim_id}) has been approved! The item owner has been notified.";
        
        return $notification->create(
            $notification_id, 
            $claimDetails['claimed_by'], 
            $title, 
            $message, 
            'claim_approved',
            $claim_id
        );
    }
    return false;
}

public function sendClaimRejectedNotification($claim_id, $admin_notes = '') {
    $notification = new Notification();
    $claimDetails = $this->getClaimById($claim_id);
    
    if ($claimDetails && $claimDetails['claimed_by']) {
        $notification_id = $notification->generateNotificationId();
        $title = "Claim Rejected";
        $message = "Your claim (#{$claim_id}) has been rejected.";
        if (!empty($admin_notes)) {
            $message .= " Reason: " . $admin_notes;
        }
        
        return $notification->create(
            $notification_id, 
            $claimDetails['claimed_by'], 
            $title, 
            $message, 
            'claim_rejected',
            $claim_id
        );
    }
    return false;
}
}

class HandoverLog {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseService::getInstance()->getConnection();
    }
    
    public function create($claim_id, $admin_id) {
        $stmt = $this->db->prepare("
            INSERT INTO handover_log (claim_id, admin_id) 
            VALUES (?, ?)
        ");
        return $stmt->execute([$claim_id, $admin_id]);
    }
    
    public function getAllHandovers() {
        $stmt = $this->db->prepare("
            SELECT h.*, c.claim_id, u.username as admin_name, 
                   cl.claimed_by, cu.username as claimant_name
            FROM handover_log h
            LEFT JOIN claim c ON h.claim_id = c.claim_id
            LEFT JOIN users u ON h.admin_id = u.user_id
            LEFT JOIN users cu ON c.claimed_by = cu.user_id
            ORDER BY h.handover_date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

class Statistics {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseService::getInstance()->getConnection();
    }
    
    // Overall System KPIs
    public function getTotalReports() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM report");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    public function getTotalLostItems() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM report WHERE report_type = 'lost'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    public function getTotalFoundItems() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM report WHERE report_type = 'found'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    public function getReturnedItemsCount() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM report WHERE status = 'returned'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    public function getPendingClaimsCount() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM claim WHERE status = 'pending'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    public function getTotalUsers() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    // Recent Activity
    public function getReportsLast7Days() {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM report 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    public function getItemsReturnedLast7Days() {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM report 
            WHERE status = 'returned' 
            AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    // Category-wise Statistics
    public function getCategoryStats() {
        $stmt = $this->db->prepare("
            SELECT c.category_name, COUNT(r.report_id) as report_count,
                   SUM(CASE WHEN r.status = 'returned' THEN 1 ELSE 0 END) as returned_count
            FROM category c
            LEFT JOIN item i ON c.category_id = i.category_id
            LEFT JOIN report r ON i.item_id = r.item_id
            GROUP BY c.category_id, c.category_name
            ORDER BY report_count DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Status Distribution
    public function getStatusDistribution() {
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count 
            FROM report 
            GROUP BY status 
            ORDER BY count DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Monthly Trends
    public function getMonthlyTrends() {
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total_reports,
                SUM(CASE WHEN report_type = 'lost' THEN 1 ELSE 0 END) as lost_count,
                SUM(CASE WHEN report_type = 'found' THEN 1 ELSE 0 END) as found_count,
                SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_count
            FROM report 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 6
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // User Activity
    public function getTopReporters() {
        $stmt = $this->db->prepare("
            SELECT u.username, COUNT(r.report_id) as report_count,
                   SUM(CASE WHEN r.status = 'returned' THEN 1 ELSE 0 END) as returned_count
            FROM users u
            LEFT JOIN report r ON u.user_id = r.user_id
            WHERE r.report_id IS NOT NULL
            GROUP BY u.user_id, u.username
            ORDER BY report_count DESC
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Location-wise Statistics
    public function getLocationStats() {
        $stmt = $this->db->prepare("
            SELECT l.location_name, COUNT(r.report_id) as report_count
            FROM location l
            LEFT JOIN item i ON l.location_id = i.location_id
            LEFT JOIN report r ON i.item_id = r.item_id
            WHERE r.report_id IS NOT NULL
            GROUP BY l.location_id, l.location_name
            ORDER BY report_count DESC
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Claim Statistics
    public function getClaimStats() {
        $stmt = $this->db->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM claim)), 2) as percentage
            FROM claim 
            GROUP BY status
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getItemsByDateRange($startDate, $endDate, $reportType = null) {
    $db = DatabaseService::getInstance()->getConnection();
    
    $query = "
        SELECT 
            r.report_id,
            r.report_type,
            r.status,
            r.created_at as report_date,
            r.date_lost,
            r.date_found,
            i.description,
            i.item_id,
            c.category_name,
            l.location_name,
            u.username as reporter,
            u.email as reporter_email
        FROM report r
        JOIN item i ON r.item_id = i.item_id
        JOIN category c ON i.category_id = c.category_id
        JOIN location l ON i.location_id = l.location_id
        JOIN users u ON r.user_id = u.user_id
        WHERE DATE(r.created_at) BETWEEN ? AND ?
    ";
    
    $params = [$startDate, $endDate];
    
    if ($reportType) {
        $query .= " AND r.report_type = ?";
        $params[] = $reportType;
    }
    
    $query .= " ORDER BY r.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getSummaryByDateRange($startDate, $endDate) {
    $db = DatabaseService::getInstance()->getConnection();
    
    $query = "
        SELECT 
            r.report_type,
            COUNT(*) as count,
            SUM(CASE WHEN r.status = 'returned' THEN 1 ELSE 0 END) as returned_count,
            SUM(CASE WHEN r.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_count
        FROM report r
        WHERE DATE(r.created_at) BETWEEN ? AND ?
        GROUP BY r.report_type
        ORDER BY r.report_type
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getCategoriesByDateRange($startDate, $endDate) {
    $db = DatabaseService::getInstance()->getConnection();
    
    $query = "
        SELECT 
            c.category_name,
            COUNT(*) as item_count,
            r.report_type
        FROM report r
        JOIN item i ON r.item_id = i.item_id
        JOIN category c ON i.category_id = c.category_id
        WHERE DATE(r.created_at) BETWEEN ? AND ?
        GROUP BY c.category_name, r.report_type
        ORDER BY item_count DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}

class Notification {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseService::getInstance()->getConnection();
    }
    
    public function create($notification_id, $user_id, $title, $message, $type = 'system', $related_id = null) {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (notification_id, user_id, title, message, type, related_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$notification_id, $user_id, $title, $message, $type, $related_id]);
    }
    
    public function generateNotificationId() {
        $stmt = $this->db->prepare("SELECT notification_id FROM notifications ORDER BY notification_id DESC LIMIT 1");
        $stmt->execute();
        $lastId = $stmt->fetchColumn();
        
        if ($lastId) {
            $number = intval(substr($lastId, 3)) + 1;
        } else {
            $number = 1;
        }
        
        return 'NOT' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
    
    public function getUserNotifications($user_id, $unread_only = false) {
        $sql = "
            SELECT * FROM notifications 
            WHERE user_id = ?
        ";
        
        if ($unread_only) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
    
    public function markAsRead($notification_id, $user_id) {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE notification_id = ? AND user_id = ?
        ");
        return $stmt->execute([$notification_id, $user_id]);
    }
    
    public function markAllAsRead($user_id) {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ? AND is_read = FALSE
        ");
        return $stmt->execute([$user_id]);
    }
    
    public function getUnreadCount($user_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }
    
    public function deleteOldNotifications($days = 30) {
        $stmt = $this->db->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        return $stmt->execute([$days]);
    }
}
?>

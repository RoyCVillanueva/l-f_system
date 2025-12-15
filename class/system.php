<?php
// Basic system configuration
class SystemConfig {
    const UPLOAD_DIR = 'uploads/';
    const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB
    const ALLOWED_FILE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const BASE_URL = "http://localhost";
    const SYSTEM_EMAIL = "noreply@lostandfound.com";
    const SUPPORT_EMAIL = "support@lostandfound.com";
    public static function createUploadDir() {
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }
    }
}

// Initialize upload directory
SystemConfig::createUploadDir();
?>
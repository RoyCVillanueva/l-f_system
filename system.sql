CREATE DATABASE IF NOT EXISTS lost_and_found_system;
USE lost_and_found_system;

-- Users table with roles
CREATE TABLE IF NOT EXISTS users (
    user_id varchar(10) PRIMARY KEY,
    username varchar(50) UNIQUE NOT NULL,
    email varchar(100) NOT NULL,
    password_hash varchar(255) NOT NULL,
    phone_number varchar(20),
    role ENUM('user', 'admin') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    login_attempts INT DEFAULT 0,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Category table with ENUM values
CREATE TABLE IF NOT EXISTS category (
    category_id int PRIMARY KEY,
    category_name ENUM('Electronics','Clothing','Accessories','Documents','Jewelry','Keys','Bags','Books','Cash') NOT NULL
);

-- Location table
CREATE TABLE IF NOT EXISTS location (
    location_id int PRIMARY KEY,
    location_name varchar(40)
);

-- Item table
CREATE TABLE IF NOT EXISTS item (
    item_id varchar(10) PRIMARY KEY,
    description varchar(255),
    category_id int,
    location_id int,
    reported_by varchar(10),
    CONSTRAINT fk_category_id FOREIGN KEY(category_id) REFERENCES category(category_id),
    CONSTRAINT fk_location_id FOREIGN KEY(location_id) REFERENCES location(location_id),
    CONSTRAINT fk_reported_by FOREIGN KEY(reported_by) REFERENCES users(user_id)
);

-- Report table with status field - UPDATED WITH DATE LOST AND DATE FOUND
CREATE TABLE IF NOT EXISTS report (
    report_id varchar(10) PRIMARY KEY,
    status ENUM('pending', 'found', 'confirmed', 'returned') DEFAULT 'pending',
    report_type ENUM('lost', 'found') NOT NULL,
    item_id varchar(10),
    user_id varchar(10),
    date_lost DATE NULL,
    date_found DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_item_id FOREIGN KEY(item_id) REFERENCES item(item_id),
    CONSTRAINT fk_user_id FOREIGN KEY(user_id) REFERENCES users(user_id)
);

-- Item images table for multiple images
CREATE TABLE IF NOT EXISTS item_images (
    image_id varchar(10) PRIMARY KEY,
    item_id varchar(10),
    image_path varchar(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_item_images_item_id FOREIGN KEY(item_id) REFERENCES item(item_id)
);

-- Claim table
CREATE TABLE IF NOT EXISTS claim (
    claim_id varchar(10) PRIMARY KEY,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    report_id varchar(10),
    claimed_by varchar(10),
    claim_description TEXT,
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_claim_report_id FOREIGN KEY(report_id) REFERENCES report(report_id),
    CONSTRAINT fk_claimed_by FOREIGN KEY(claimed_by) REFERENCES users(user_id)
);

-- Handover log table
CREATE TABLE IF NOT EXISTS handover_log (
    handover_id int PRIMARY KEY AUTO_INCREMENT,
    claim_id varchar(10),
    admin_id varchar(10),
    handover_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_handover_claim_id FOREIGN KEY(claim_id) REFERENCES claim(claim_id),
    CONSTRAINT fk_admin_id FOREIGN KEY(admin_id) REFERENCES users(user_id)
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id varchar(10) PRIMARY KEY,
    user_id varchar(10) NOT NULL,
    title varchar(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('report_confirmed', 'claim_approved', 'claim_rejected', 'item_returned', 'system') DEFAULT 'system',
    related_id varchar(10), -- report_id or claim_id
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_user_id FOREIGN KEY(user_id) REFERENCES users(user_id)
);

-- Insert default categories 
INSERT IGNORE INTO category (category_id, category_name) VALUES 
(1, 'Electronics'),
(2, 'Clothing'),
(3, 'Accessories'),
(4, 'Documents'),
(5, 'Jewelry'),
(6, 'Keys'),
(7, 'Bags'),
(8, 'Books'),
(9, 'Cash');

ALTER TABLE users 
ADD COLUMN email_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN verification_token VARCHAR(255) NULL,
ADD COLUMN token_expires TIMESTAMP NULL;
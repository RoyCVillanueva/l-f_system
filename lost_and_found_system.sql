-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 13, 2025 at 03:39 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lost_and_found_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `category_id` int(11) NOT NULL,
  `category_name` enum('Electronics','Clothing','Accessories','Documents','Jewelry','Keys','Bags','Books','Cash') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`category_id`, `category_name`) VALUES
(1, 'Electronics'),
(2, 'Clothing'),
(3, 'Accessories'),
(4, 'Documents'),
(5, 'Jewelry'),
(6, 'Keys'),
(7, 'Bags'),
(8, 'Books'),
(9, 'Cash');

-- --------------------------------------------------------

--
-- Table structure for table `claim`
--

CREATE TABLE `claim` (
  `claim_id` varchar(10) NOT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `report_id` varchar(10) DEFAULT NULL,
  `claimed_by` varchar(10) DEFAULT NULL,
  `claim_description` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `claim`
--

INSERT INTO `claim` (`claim_id`, `status`, `report_id`, `claimed_by`, `claim_description`, `admin_notes`, `created_at`, `updated_at`) VALUES
('CLM001', 'pending', 'REP004', 'USR003', 'aojuuitarr', NULL, '2025-12-09 05:53:06', '2025-12-09 05:53:06');

-- --------------------------------------------------------

--
-- Table structure for table `handover_log`
--

CREATE TABLE `handover_log` (
  `handover_id` int(11) NOT NULL,
  `claim_id` varchar(10) DEFAULT NULL,
  `admin_id` varchar(10) DEFAULT NULL,
  `handover_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item`
--

CREATE TABLE `item` (
  `item_id` varchar(10) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `reported_by` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item`
--

INSERT INTO `item` (`item_id`, `description`, `category_id`, `location_id`, `reported_by`) VALUES
('ITM001', 'phone', 1, 1, 'ADM001'),
('ITM002', 'phone', 1, 2, 'USR002'),
('ITM003', 'phone', 1, 2, 'USR003'),
('ITM004', 'phone', 1, 2, 'USR002');

-- --------------------------------------------------------

--
-- Table structure for table `item_images`
--

CREATE TABLE `item_images` (
  `image_id` varchar(10) NOT NULL,
  `item_id` varchar(10) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `location`
--

CREATE TABLE `location` (
  `location_id` int(11) NOT NULL,
  `location_name` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `location`
--

INSERT INTO `location` (`location_id`, `location_name`) VALUES
(1, 'Airport'),
(2, 'wmsu');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` varchar(10) NOT NULL,
  `user_id` varchar(10) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('report_confirmed','claim_approved','claim_rejected','item_returned','system') DEFAULT 'system',
  `related_id` varchar(10) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `related_id`, `is_read`, `created_at`) VALUES
('NOT001', 'USR002', 'Report Confirmed', 'Your lost item report (#REP002) has been confirmed by admin.', 'report_confirmed', 'REP002', 0, '2025-11-19 22:40:30'),
('NOT002', 'USR002', 'Report Confirmed', 'Your found item report (#REP004) has been confirmed by admin.', 'report_confirmed', 'REP004', 0, '2025-12-09 05:53:34');

-- --------------------------------------------------------

--
-- Table structure for table `report`
--

CREATE TABLE `report` (
  `report_id` varchar(10) NOT NULL,
  `status` enum('pending','found','confirmed','returned') DEFAULT 'pending',
  `report_type` enum('lost','found') NOT NULL,
  `item_id` varchar(10) DEFAULT NULL,
  `user_id` varchar(10) DEFAULT NULL,
  `date_lost` date DEFAULT NULL,
  `date_found` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `report`
--

INSERT INTO `report` (`report_id`, `status`, `report_type`, `item_id`, `user_id`, `date_lost`, `date_found`, `created_at`, `updated_at`) VALUES
('REP001', 'pending', 'lost', 'ITM001', 'ADM001', NULL, NULL, '2025-11-09 01:36:48', '2025-11-09 01:36:48'),
('REP002', 'confirmed', 'lost', 'ITM002', 'USR002', '2025-11-05', NULL, '2025-11-19 17:02:52', '2025-11-19 22:40:30'),
('REP003', 'pending', 'lost', 'ITM003', 'USR003', '2025-12-09', NULL, '2025-12-09 05:40:17', '2025-12-09 05:40:17'),
('REP004', 'confirmed', 'found', 'ITM004', 'USR002', NULL, '2025-12-09', '2025-12-09 05:47:36', '2025-12-09 05:53:34');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(10) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `login_attempts` int(11) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `token_expires` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `phone_number`, `role`, `is_active`, `login_attempts`, `last_login`, `created_at`, `updated_at`, `email_verified`, `verification_token`, `token_expires`) VALUES
('ADM001', 'admin', 'admin@lostfound.com', '$2y$10$knIO1M2cuNgJV0R9oDUzf.oN/njkQ1kDNPkJihWVzzJFGeCDw7RHW', '+1234567890', 'admin', 1, 0, '2025-12-13 14:29:57', '2025-11-09 01:36:02', '2025-12-13 14:29:57', 0, NULL, NULL),
('USR001', 'myst', 'myst@gmail.com', '$2y$10$aZER61BxWRWr.65yNpx7M.EJgzWhjbdzQRkTKD9QKQsobC9Q3HkfG', NULL, 'user', 1, 0, '2025-12-09 05:14:58', '2025-11-11 02:16:34', '2025-12-09 05:14:58', 0, NULL, NULL),
('USR002', 'ree', 'rtard483@gmail.com', '$2y$10$P1ykBIv9ALxxYD7kSm1MJOsEgzowQXmycCBSPRjRHciYGLc7kLySa', NULL, 'user', 1, 0, '2025-12-09 05:45:24', '2025-11-19 16:47:52', '2025-12-09 05:45:24', 1, 'e88984dcbffb02b9ba8ffb9ad518d21ac754d39ace48ddce615339e266501041', '2025-11-20 09:47:52'),
('USR003', 'roy', 'villanuevaroysilay@gmail.com', '$2y$10$bxCNjACq7W2RO8.KwGXdWeafhaicKFeu.jgjKTi2SrevgqnFJMpRq', NULL, 'user', 1, 0, '2025-12-13 14:28:37', '2025-11-25 12:55:09', '2025-12-13 14:28:37', 1, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `claim`
--
ALTER TABLE `claim`
  ADD PRIMARY KEY (`claim_id`),
  ADD KEY `fk_claim_report_id` (`report_id`),
  ADD KEY `fk_claimed_by` (`claimed_by`);

--
-- Indexes for table `handover_log`
--
ALTER TABLE `handover_log`
  ADD PRIMARY KEY (`handover_id`),
  ADD KEY `fk_handover_claim_id` (`claim_id`),
  ADD KEY `fk_admin_id` (`admin_id`);

--
-- Indexes for table `item`
--
ALTER TABLE `item`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_category_id` (`category_id`),
  ADD KEY `fk_location_id` (`location_id`),
  ADD KEY `fk_reported_by` (`reported_by`);

--
-- Indexes for table `item_images`
--
ALTER TABLE `item_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `fk_item_images_item_id` (`item_id`);

--
-- Indexes for table `location`
--
ALTER TABLE `location`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notification_user_id` (`user_id`);

--
-- Indexes for table `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `fk_item_id` (`item_id`),
  ADD KEY `fk_user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `handover_log`
--
ALTER TABLE `handover_log`
  MODIFY `handover_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `claim`
--
ALTER TABLE `claim`
  ADD CONSTRAINT `fk_claim_report_id` FOREIGN KEY (`report_id`) REFERENCES `report` (`report_id`),
  ADD CONSTRAINT `fk_claimed_by` FOREIGN KEY (`claimed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `handover_log`
--
ALTER TABLE `handover_log`
  ADD CONSTRAINT `fk_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_handover_claim_id` FOREIGN KEY (`claim_id`) REFERENCES `claim` (`claim_id`);

--
-- Constraints for table `item`
--
ALTER TABLE `item`
  ADD CONSTRAINT `fk_category_id` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`),
  ADD CONSTRAINT `fk_location_id` FOREIGN KEY (`location_id`) REFERENCES `location` (`location_id`),
  ADD CONSTRAINT `fk_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `item_images`
--
ALTER TABLE `item_images`
  ADD CONSTRAINT `fk_item_images_item_id` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `report`
--
ALTER TABLE `report`
  ADD CONSTRAINT `fk_item_id` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`),
  ADD CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

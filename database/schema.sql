-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 05, 2026 at 08:45 AM
-- Server version: 10.11.19-MariaDB-cll-lve
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dewujosf_tracker`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendances`
--

CREATE TABLE `attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `office_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` datetime DEFAULT NULL,
  `check_in_photo` varchar(255) DEFAULT NULL,
  `check_in_latitude` decimal(10,7) DEFAULT NULL,
  `check_in_longitude` decimal(10,7) DEFAULT NULL,
  `check_in_accuracy` decimal(10,2) DEFAULT NULL,
  `check_in_distance` decimal(10,2) DEFAULT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `check_out_photo` varchar(255) DEFAULT NULL,
  `check_out_latitude` decimal(10,7) DEFAULT NULL,
  `check_out_longitude` decimal(10,7) DEFAULT NULL,
  `check_out_accuracy` decimal(10,2) DEFAULT NULL,
  `check_out_distance` decimal(10,2) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'PRESENT',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_requests`
--

CREATE TABLE `attendance_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `office_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('LATE','CHECK_IN','CHECK_OUT','LEAVE','OUTSIDE_OFFICE','OVERTIME') NOT NULL,
  `date` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `reason` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_request_attachments`
--

CREATE TABLE `attendance_request_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `request_id` bigint(20) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_tracking`
--

CREATE TABLE `attendance_tracking` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `office_id` bigint(20) UNSIGNED NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `accuracy` decimal(10,2) DEFAULT NULL,
  `speed` decimal(10,2) DEFAULT NULL,
  `bearing` decimal(10,2) DEFAULT NULL,
  `battery_level` int(11) DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `server_received_at` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(100) NOT NULL,
  `device_id` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `device_id` varchar(150) NOT NULL,
  `device_model` varchar(150) DEFAULT NULL,
  `app_version` varchar(30) DEFAULT NULL,
  `last_active_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `confirm_code` varchar(64) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `office_id` bigint(20) UNSIGNED NOT NULL,
  `position` varchar(150) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `role` enum('SUPER_ADMIN','ADMIN_KANTOR','EMPLOYEE','DRIVER') NOT NULL DEFAULT 'EMPLOYEE',
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `device` varchar(50) NOT NULL DEFAULT 'ANDROID'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `holiday_date` date NOT NULL,
  `name` varchar(150) NOT NULL,
  `office_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `check_in_radius` int(11) NOT NULL DEFAULT 50,
  `check_out_radius` int(11) NOT NULL DEFAULT 50,
  `tracking_interval_seconds` int(11) NOT NULL DEFAULT 600,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `timezone` enum('WIB','WITA','WIT') NOT NULL DEFAULT 'WIB'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_deliveries`
--

CREATE TABLE `vehicle_deliveries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `driver_id` bigint(20) UNSIGNED NOT NULL,
  `engine_number` varchar(100) NOT NULL,
  `chassis_number` varchar(100) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `vehicle_type` varchar(100) NOT NULL,
  `color` varchar(50) NOT NULL,
  `destination_office_id` bigint(20) UNSIGNED DEFAULT NULL,
  `destination_name` varchar(150) DEFAULT NULL,
  `destination_address` varchar(255) DEFAULT NULL,
  `destination_latitude` decimal(10,7) DEFAULT NULL,
  `destination_longitude` decimal(10,7) DEFAULT NULL,
  `destination_radius` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `pickup_photo` varchar(255) DEFAULT NULL,
  `pickup_time` datetime DEFAULT NULL,
  `pickup_latitude` decimal(10,7) DEFAULT NULL,
  `pickup_longitude` decimal(10,7) DEFAULT NULL,
  `arrival_photo` varchar(255) DEFAULT NULL,
  `arrival_time` datetime DEFAULT NULL,
  `arrival_latitude` decimal(10,7) DEFAULT NULL,
  `arrival_longitude` decimal(10,7) DEFAULT NULL,
  `arrival_distance` decimal(10,2) DEFAULT NULL,
  `arrival_notes` text DEFAULT NULL,
  `status` enum('IN_PROGRESS','ARRIVED','COMPLETED') NOT NULL DEFAULT 'IN_PROGRESS',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_delivery_tracking`
--

CREATE TABLE `vehicle_delivery_tracking` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `delivery_id` bigint(20) UNSIGNED NOT NULL,
  `driver_id` bigint(20) UNSIGNED NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `accuracy` decimal(10,2) DEFAULT NULL,
  `speed` decimal(10,2) DEFAULT NULL,
  `bearing` decimal(10,2) DEFAULT NULL,
  `battery_level` int(11) DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `server_received_at` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_date` (`employee_id`,`attendance_date`),
  ADD KEY `fk_attendance_office` (`office_id`);

--
-- Indexes for table `attendance_requests`
--
ALTER TABLE `attendance_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_request_employee` (`employee_id`),
  ADD KEY `fk_request_office` (`office_id`);

--
-- Indexes for table `attendance_request_attachments`
--
ALTER TABLE `attendance_request_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attachment_request` (`request_id`);

--
-- Indexes for table `attendance_tracking`
--
ALTER TABLE `attendance_tracking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tracking` (`employee_id`,`recorded_at`),
  ADD KEY `fk_tracking_office` (`office_id`),
  ADD KEY `idx_tracking_recorded_at` (`recorded_at`);

--
-- Indexes for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `fk_token_employee` (`employee_id`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_device_employee` (`employee_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_employee_office` (`office_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_holiday_date` (`holiday_date`),
  ADD KEY `idx_holiday_office` (`office_id`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `vehicle_deliveries`
--
ALTER TABLE `vehicle_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_delivery_driver` (`driver_id`),
  ADD KEY `fk_delivery_office` (`destination_office_id`);

--
-- Indexes for table `vehicle_delivery_tracking`
--
ALTER TABLE `vehicle_delivery_tracking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_delivery_tracking` (`delivery_id`,`recorded_at`),
  ADD KEY `fk_dtracking_driver` (`driver_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendances`
--
ALTER TABLE `attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_requests`
--
ALTER TABLE `attendance_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_request_attachments`
--
ALTER TABLE `attendance_request_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_tracking`
--
ALTER TABLE `attendance_tracking`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicle_deliveries`
--
ALTER TABLE `vehicle_deliveries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicle_delivery_tracking`
--
ALTER TABLE `vehicle_delivery_tracking`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendances`
--
ALTER TABLE `attendances`
  ADD CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `fk_attendance_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`);

--
-- Constraints for table `attendance_requests`
--
ALTER TABLE `attendance_requests`
  ADD CONSTRAINT `fk_request_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `fk_request_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`);

--
-- Constraints for table `attendance_request_attachments`
--
ALTER TABLE `attendance_request_attachments`
  ADD CONSTRAINT `fk_attachment_request` FOREIGN KEY (`request_id`) REFERENCES `attendance_requests` (`id`);

--
-- Constraints for table `attendance_tracking`
--
ALTER TABLE `attendance_tracking`
  ADD CONSTRAINT `fk_tracking_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `fk_tracking_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`);

--
-- Constraints for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `fk_token_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `fk_device_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employee_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`);

--
-- Constraints for table `holidays`
--
ALTER TABLE `holidays`
  ADD CONSTRAINT `fk_holiday_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`);

--
-- Constraints for table `vehicle_deliveries`
--
ALTER TABLE `vehicle_deliveries`
  ADD CONSTRAINT `fk_delivery_driver` FOREIGN KEY (`driver_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `fk_delivery_office` FOREIGN KEY (`destination_office_id`) REFERENCES `offices` (`id`);

--
-- Constraints for table `vehicle_delivery_tracking`
--
ALTER TABLE `vehicle_delivery_tracking`
  ADD CONSTRAINT `fk_dtracking_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `vehicle_deliveries` (`id`),
  ADD CONSTRAINT `fk_dtracking_driver` FOREIGN KEY (`driver_id`) REFERENCES `employees` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================================
-- Employee Tracker - Database Schema (MySQL / MariaDB)
-- ============================================================
-- Run this once on a fresh database, e.g.:
--   mysql -u root -p employee_tracker < schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- offices : master kantor. Radius & koordinat sepenuhnya data,
-- TIDAK ADA hardcode kantor di kode manapun (Android maupun API).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS offices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    address TEXT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    check_in_radius INT NOT NULL DEFAULT 50,
    check_out_radius INT NOT NULL DEFAULT 50,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- employees
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(50) NOT NULL UNIQUE,
    nip VARCHAR(50) NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password VARCHAR(255) NOT NULL,
    office_id BIGINT UNSIGNED NOT NULL,
    position VARCHAR(150) NULL,
    photo VARCHAR(255) NULL,
    role ENUM('SUPER_ADMIN','ADMIN_KANTOR','EMPLOYEE') NOT NULL DEFAULT 'EMPLOYEE',
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,

    CONSTRAINT fk_employee_office
        FOREIGN KEY (office_id) REFERENCES offices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- auth_tokens : simple bearer-token session store (login result)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auth_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    device_id VARCHAR(150) NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,

    CONSTRAINT fk_token_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- attendances
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    employee_id BIGINT UNSIGNED NOT NULL,
    office_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,

    check_in_time DATETIME NULL,
    check_in_photo VARCHAR(255) NULL,
    check_in_latitude DECIMAL(10,7) NULL,
    check_in_longitude DECIMAL(10,7) NULL,
    check_in_accuracy DECIMAL(10,2) NULL,
    check_in_distance DECIMAL(10,2) NULL,

    check_out_time DATETIME NULL,
    check_out_photo VARCHAR(255) NULL,
    check_out_latitude DECIMAL(10,7) NULL,
    check_out_longitude DECIMAL(10,7) NULL,
    check_out_accuracy DECIMAL(10,2) NULL,
    check_out_distance DECIMAL(10,2) NULL,

    status VARCHAR(30) NOT NULL DEFAULT 'PRESENT',

    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,

    UNIQUE KEY unique_employee_date (employee_id, attendance_date),

    CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_attendance_office FOREIGN KEY (office_id) REFERENCES offices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- attendance_tracking
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance_tracking (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    attendance_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    office_id BIGINT UNSIGNED NOT NULL,

    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy DECIMAL(10,2) NULL,
    speed DECIMAL(10,2) NULL,
    bearing DECIMAL(10,2) NULL,

    battery_level INT NULL,

    recorded_at DATETIME NOT NULL,
    server_received_at DATETIME NOT NULL,

    created_at DATETIME NOT NULL,

    UNIQUE KEY unique_tracking (attendance_id, recorded_at),

    CONSTRAINT fk_tracking_attendance FOREIGN KEY (attendance_id) REFERENCES attendances(id),
    CONSTRAINT fk_tracking_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_tracking_office FOREIGN KEY (office_id) REFERENCES offices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- attendance_requests (pengajuan)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    office_id BIGINT UNSIGNED NOT NULL,
    type ENUM('LATE','CHECK_IN','CHECK_OUT','LEAVE') NOT NULL,
    date DATE NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    time TIME NULL,
    reason TEXT NOT NULL,
    attachment VARCHAR(255) NULL,
    status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejection_reason TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,

    CONSTRAINT fk_request_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_request_office FOREIGN KEY (office_id) REFERENCES offices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- attendance_request_attachments (support multiple files per request)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance_request_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at DATETIME NOT NULL,

    CONSTRAINT fk_attachment_request FOREIGN KEY (request_id) REFERENCES attendance_requests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- devices (optional: track which device/app-install belongs to whom,
-- useful later for push notifications / force-logout other devices)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    device_id VARCHAR(150) NOT NULL,
    device_model VARCHAR(150) NULL,
    app_version VARCHAR(30) NULL,
    last_active_at DATETIME NULL,
    created_at DATETIME NOT NULL,

    CONSTRAINT fk_device_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA (contoh/demo, semua bisa diubah lewat DB/nanti web admin)
-- ============================================================

INSERT INTO offices (id, code, name, address, latitude, longitude, check_in_radius, check_out_radius, status, created_at) VALUES
(1, 'SBY', 'Kantor Surabaya',  'Jl. Contoh Surabaya',  -7.257472, 112.752090, 50, 100, 'ACTIVE', NOW()),
(2, 'LMG', 'Kantor Lamongan',  'Jl. Contoh Lamongan',  -7.116000, 112.416000, 50, 50,  'ACTIVE', NOW()),
(3, 'MJK', 'Kantor Mojokerto', 'Jl. Contoh Mojokerto', -7.470000, 112.440000, 50, 50,  'ACTIVE', NOW()),
(4, 'GSK', 'Kantor Gresik',    'Jl. Contoh Gresik',    -7.156000, 112.650000, 50, 50,  'ACTIVE', NOW());

-- Password untuk SEMUA akun demo di bawah adalah: demo123
-- Hash bcrypt (kompatibel dengan PHP password_verify di backend):
-- $2b$10$6F8b72jcEu1iTSBdKA90zeiCWZUd.f7I0EIPDJ0NMhqg0MB79pD6W
INSERT INTO employees (id, employee_code, nip, name, email, phone, password, office_id, position, photo, role, status, created_at) VALUES
(101, 'EMP001', '1990001', 'Ahmad', 'ahmad@example.com', '0811111111', '$2b$10$6F8b72jcEu1iTSBdKA90zeiCWZUd.f7I0EIPDJ0NMhqg0MB79pD6W', 1, 'Staff', NULL, 'EMPLOYEE', 'ACTIVE', NOW()),
(102, 'EMP002', '1990002', 'Budi',  'budi@example.com',  '0822222222', '$2b$10$6F8b72jcEu1iTSBdKA90zeiCWZUd.f7I0EIPDJ0NMhqg0MB79pD6W', 2, 'Staff', NULL, 'EMPLOYEE', 'ACTIVE', NOW()),
(103, 'EMP003', '1990003', 'Citra', 'citra@example.com', '0833333333', '$2b$10$6F8b72jcEu1iTSBdKA90zeiCWZUd.f7I0EIPDJ0NMhqg0MB79pD6W', 3, 'Admin Kantor', NULL, 'ADMIN_KANTOR', 'ACTIVE', NOW()),
(104, 'EMP004', '1990004', 'Deni',  'deni@example.com',  '0844444444', '$2b$10$6F8b72jcEu1iTSBdKA90zeiCWZUd.f7I0EIPDJ0NMhqg0MB79pD6W', 4, 'Staff', NULL, 'EMPLOYEE', 'ACTIVE', NOW()),
(1,   'ADM001', NULL,      'Super Admin', 'admin@example.com', '0800000000', '$2b$10$6F8b72jcEu1iTSBdKA90zeiCWZUd.f7I0EIPDJ0NMhqg0MB79pD6W', 1, 'Administrator', NULL, 'SUPER_ADMIN', 'ACTIVE', NOW());

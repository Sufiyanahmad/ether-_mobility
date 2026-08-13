CREATE DATABASE IF NOT EXISTS ether_mobility;
USE ether_mobility;

-- 1. Vehicles Table
CREATE TABLE IF NOT EXISTS vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_number VARCHAR(20) NOT NULL UNIQUE,
    model_name VARCHAR(50) NOT NULL,
    chassis_number VARCHAR(50) NOT NULL UNIQUE,
    motor_number VARCHAR(50) NOT NULL,
    battery_serial VARCHAR(50) NOT NULL,
    battery_type ENUM('Lithium-ion', 'Lead Acid') DEFAULT 'Lithium-ion',
    rc_number VARCHAR(50) NULL,
    rto_reg_date DATE NULL,
    insurance_policy VARCHAR(50) NULL,
    insurance_expiry DATE NULL,
    fitness_expiry DATE NULL,
    permit_expiry DATE NULL,
    status ENUM('Unassigned', 'Assigned', 'Maintenance') DEFAULT 'Unassigned',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Drivers Table
CREATE TABLE IF NOT EXISTS drivers (
    driver_id INT AUTO_INCREMENT PRIMARY KEY,
    reg_code VARCHAR(10) UNIQUE NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL UNIQUE,
    aadhaar_number VARCHAR(12) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    security_deposit DECIMAL(10,2) NOT NULL DEFAULT 10500.00,
    vehicle_id INT NULL UNIQUE,
    aadhaar_doc VARCHAR(255) NOT NULL,
    agreement_doc VARCHAR(255) NOT NULL,
    live_kyc_photo VARCHAR(255) NOT NULL,
    damage_deduction DECIMAL(10,2) DEFAULT 0.00,
    net_refunded DECIMAL(10,2) DEFAULT 0.00,
    offboarded_at DATETIME NULL,
    status ENUM('Active', 'Suspended', 'Settled') DEFAULT 'Active',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE SET NULL
);


USE ether_mobility;

-- Weekly Collection Ledger Table
CREATE TABLE IF NOT EXISTS weekly_collections (
    collection_id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,
    weekly_rent_due DECIMAL(10,2) DEFAULT 3500.00, -- ₹500 x 7 days = ₹3,500/week
    previous_arrears DECIMAL(10,2) DEFAULT 0.00,  -- Purana pending dues
    total_due DECIMAL(10,2) NOT NULL,              -- Rent + Previous Arrears
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    balance_due DECIMAL(10,2) NOT NULL,            -- Total Due - Amount Paid
    payment_status ENUM('Paid', 'Partial', 'Unpaid') DEFAULT 'Unpaid',
    payment_mode ENUM('Cash', 'UPI', 'Bank Transfer', 'Pending') DEFAULT 'Cash',
    collected_at DATETIME NULL,
    FOREIGN KEY (driver_id) REFERENCES drivers(driver_id) ON DELETE CASCADE,
    UNIQUE KEY unique_driver_week (driver_id, week_start_date)
);

USE ether_mobility;

-- Monthly Finance & Maintenance Tracking Ledger Table
CREATE TABLE IF NOT EXISTS monthly_earnings (
    earning_id INT AUTO_INCREMENT PRIMARY KEY,
    month_year VARCHAR(7) NOT NULL UNIQUE, -- Format: YYYY-MM (e.g. 2026-08)
    gross_collected DECIMAL(10,2) DEFAULT 0.00,
    total_vehicles_active INT DEFAULT 0,
    maintenance_deduction DECIMAL(10,2) DEFAULT 0.00, -- (Active Vehicles * 1500)
    net_profit DECIMAL(10,2) DEFAULT 0.00,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
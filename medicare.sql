-- =====================================================
-- MediCare Connect Database
-- Clean version — no sample data
-- Import this file once to set up the database
-- =====================================================

DROP DATABASE IF EXISTS medicare_connect;
CREATE DATABASE medicare_connect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE medicare_connect;

-- ── Doctors table ──────────────────────────────────
CREATE TABLE IF NOT EXISTS doctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  specialization VARCHAR(100) DEFAULT NULL,
  experience_years INT DEFAULT 0,
  allowed_days VARCHAR(100) DEFAULT NULL,
  allowed_slots VARCHAR(255) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_specialization (specialization),
  INDEX idx_name (name),
  INDEX idx_email (email)
);

-- ── Patients table ─────────────────────────────────
CREATE TABLE IF NOT EXISTS patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email)
);

-- ── Availability table ─────────────────────────────
CREATE TABLE IF NOT EXISTS availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT NOT NULL,
  day VARCHAR(20) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
  INDEX idx_doctor_day (doctor_id, day)
);

-- ── Appointments table ─────────────────────────────
CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_email VARCHAR(255) NOT NULL,
  patient_name VARCHAR(100) DEFAULT NULL,
  doctor_name VARCHAR(255) NOT NULL,
  doctor_id INT DEFAULT NULL,
  specialization VARCHAR(100) DEFAULT NULL,
  appointment_date DATE NOT NULL,
  time_slot VARCHAR(50) NOT NULL,
  appointment_time TIME DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  status ENUM('BOOKED','CANCELLED','pending','confirmed') NOT NULL DEFAULT 'BOOKED',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_patient_email (patient_email),
  INDEX idx_doctor_name (doctor_name),
  INDEX idx_doctor_id (doctor_id),
  INDEX idx_doctor_date (doctor_name, appointment_date),
  INDEX idx_status (status),
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
);

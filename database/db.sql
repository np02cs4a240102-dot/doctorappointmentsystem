-- Doctor Appointment System (Sprint 1) Database Script
-- Database: medicare_connect
-- Supports CANCELLED history + doctor-specific availability (days + time slots)

-- 1) Create database
CREATE DATABASE IF NOT EXISTS medicare_connect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE medicare_connect;

-- 2) Doctors table
CREATE TABLE IF NOT EXISTS doctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  specialization VARCHAR(100) NOT NULL,
  experience_years INT DEFAULT 0,
  availability VARCHAR(255) DEFAULT NULL,

  -- NEW: used by backend to enforce availability correctly
  allowed_days VARCHAR(50) DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat,Sun',
  allowed_slots VARCHAR(255) DEFAULT '9:00 AM,10:00 AM,11:00 AM,2:00 PM,3:00 PM,4:00 PM',

  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample doctors (insert only if table is empty)
INSERT INTO doctors (name, specialization, experience_years, availability, allowed_days, allowed_slots, status)
SELECT * FROM (
  SELECT
    'Dr. Asha Sharma' AS name,
    'Cardiologist' AS specialization,
    10 AS experience_years,
    'Sun–Mon, 9AM–11AM' AS availability,
    'Sun,Mon' AS allowed_days,
    '9:00 AM,10:00 AM,11:00 AM' AS allowed_slots,
    'ACTIVE' AS status
  UNION ALL
  SELECT
    'Dr. Raj Shrestha',
    'Dermatologist',
    7,
    'Mon–Tue, 10AM–12PM',
    'Mon,Tue',
    '10:00 AM,11:00 AM',
    'ACTIVE'
  UNION ALL
  SELECT
    'Dr. Sunil Adhikari',
    'Pediatrician',
    8,
    'Sun–Mon, 2PM–4PM',
    'Sun,Mon',
    '2:00 PM,3:00 PM,4:00 PM',
    'ACTIVE'
  UNION ALL
  SELECT
    'Dr. Neha Thapa',
    'Gynecologist',
    6,
    'Mon–Fri, 10AM–2PM',
    'Mon,Tue,Wed,Thu,Fri',
    '10:00 AM,11:00 AM,2:00 PM',
    'ACTIVE'
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM doctors LIMIT 1);

-- 3) Appointments table
CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_email VARCHAR(255) NOT NULL,
  doctor_name VARCHAR(255) NOT NULL,
  specialization VARCHAR(100) DEFAULT NULL,
  appointment_date DATE NOT NULL,
  time_slot VARCHAR(50) NOT NULL,
  status ENUM('BOOKED','CANCELLED') NOT NULL DEFAULT 'BOOKED',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_patient_email (patient_email),
  INDEX idx_doctor_date (doctor_name, appointment_date),
  INDEX idx_status (status)
);

-- NOTE:
-- No UNIQUE constraint on (doctor_name, appointment_date, time_slot)
-- because cancelled appointments remain in history.
-- Backend prevents double booking by checking only status='BOOKED'.
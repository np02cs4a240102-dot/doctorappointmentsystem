-- Medicare Connect Database
-- Import this file once to set up everything

DROP DATABASE IF EXISTS medicare_connect;
CREATE DATABASE medicare_connect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE medicare_connect;

-- Doctors table
CREATE TABLE IF NOT EXISTS doctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(100) UNIQUE DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  specialization VARCHAR(100) DEFAULT NULL,
  experience_years INT DEFAULT 0,
  allowed_days VARCHAR(100) DEFAULT 'Sun,Mon,Tue,Wed,Thu,Fri,Sat',
  allowed_slots VARCHAR(255) DEFAULT '9:00 AM,10:00 AM,11:00 AM,2:00 PM,3:00 PM,4:00 PM',
  password VARCHAR(255) DEFAULT 'doctor123',
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_specialization (specialization),
  INDEX idx_name (name),
  INDEX idx_email (email)
);

-- Patients table
CREATE TABLE IF NOT EXISTS patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email)
);

-- Availability table
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

-- Appointments table
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

-- Sample doctors
INSERT INTO doctors (name, email, phone, specialization, experience_years, allowed_days, allowed_slots, password, status) VALUES
('Dr. Asha Sharma', 'asha@medicare.com', '9876543210', 'Cardiologist', 10, 'Sun,Mon', '9:00 AM,10:00 AM,11:00 AM', 'doctor123', 'ACTIVE'),
('Dr. Raj Shrestha', 'raj@medicare.com', '9876543211', 'Dermatologist', 7, 'Mon,Tue', '10:00 AM,11:00 AM', 'doctor123', 'ACTIVE'),
('Dr. Sunil Adhikari', 'sunil@medicare.com', '9876543212', 'Pediatrician', 8, 'Sun,Mon', '2:00 PM,3:00 PM,4:00 PM', 'doctor123', 'ACTIVE'),
('Dr. Neha Thapa', 'neha@medicare.com', '9876543213', 'Gynecologist', 6, 'Mon,Tue,Wed,Thu,Fri', '10:00 AM,11:00 AM,2:00 PM', 'doctor123', 'ACTIVE');

-- Sample patients
INSERT INTO patients (name, email, phone, password) VALUES
('John Doe', 'john@email.com', '9876543214', 'patient123'),
('Jane Smith', 'jane@email.com', '9876543215', 'patient123'),
('Test Patient', 'patient@test.com', '9876543216', 'test123');

-- Sample availability for all doctors
INSERT INTO availability (doctor_id, day, start_time, end_time)
SELECT id, 'Sunday', '09:00:00', '11:00:00' FROM doctors WHERE name = 'Dr. Asha Sharma'
UNION ALL
SELECT id, 'Monday', '09:00:00', '11:00:00' FROM doctors WHERE name = 'Dr. Asha Sharma'
UNION ALL
SELECT id, 'Monday', '10:00:00', '12:00:00' FROM doctors WHERE name = 'Dr. Raj Shrestha'
UNION ALL
SELECT id, 'Tuesday', '10:00:00', '12:00:00' FROM doctors WHERE name = 'Dr. Raj Shrestha'
UNION ALL
SELECT id, 'Sunday', '14:00:00', '16:00:00' FROM doctors WHERE name = 'Dr. Sunil Adhikari'
UNION ALL
SELECT id, 'Monday', '14:00:00', '16:00:00' FROM doctors WHERE name = 'Dr. Sunil Adhikari'
UNION ALL
SELECT id, 'Monday', '10:00:00', '14:00:00' FROM doctors WHERE name = 'Dr. Neha Thapa'
UNION ALL
SELECT id, 'Tuesday', '10:00:00', '14:00:00' FROM doctors WHERE name = 'Dr. Neha Thapa'
UNION ALL
SELECT id, 'Wednesday', '10:00:00', '14:00:00' FROM doctors WHERE name = 'Dr. Neha Thapa'
UNION ALL
SELECT id, 'Thursday', '10:00:00', '14:00:00' FROM doctors WHERE name = 'Dr. Neha Thapa'
UNION ALL
SELECT id, 'Friday', '10:00:00', '14:00:00' FROM doctors WHERE name = 'Dr. Neha Thapa';
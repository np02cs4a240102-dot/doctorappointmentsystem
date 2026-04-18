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
  specialization VARCHAR(100) NOT NULL,
  experience_years INT DEFAULT 0,
  availability VARCHAR(255) DEFAULT NULL,
  allowed_days VARCHAR(50) DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat,Sun',
  allowed_slots VARCHAR(255) DEFAULT '9:00 AM,10:00 AM,11:00 AM,2:00 PM,3:00 PM,4:00 PM',
  password VARCHAR(255) DEFAULT 'doctor123',
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_specialization (specialization),
  INDEX idx_name (name)
);

-- Patients table (for patient login/signup)
CREATE TABLE IF NOT EXISTS patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email)
);

-- Availability table (doctor time slots)
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
INSERT INTO doctors (name, email, phone, specialization, experience_years, availability, allowed_days, allowed_slots, password, status) VALUES
('Dr. Asha Sharma', 'asha@medicare.com', '9876543210', 'Cardiologist', 10, 'Sun-Mon, 9AM-11AM', 'Sun,Mon', '9:00 AM,10:00 AM,11:00 AM', 'doctor123', 'ACTIVE'),
('Dr. Raj Shrestha', 'raj@medicare.com', '9876543211', 'Dermatologist', 7, 'Mon-Tue, 10AM-12PM', 'Mon,Tue', '10:00 AM,11:00 AM', 'doctor123', 'ACTIVE'),
('Dr. Sunil Adhikari', 'sunil@medicare.com', '9876543212', 'Pediatrician', 8, 'Sun-Mon, 2PM-4PM', 'Sun,Mon', '2:00 PM,3:00 PM,4:00 PM', 'doctor123', 'ACTIVE'),
('Dr. Neha Thapa', 'neha@medicare.com', '9876543213', 'Gynecologist', 6, 'Mon-Fri, 10AM-2PM', 'Mon,Tue,Wed,Thu,Fri', '10:00 AM,11:00 AM,2:00 PM', 'doctor123', 'ACTIVE');

-- Sample patients
INSERT INTO patients (name, email, password) VALUES
('John Doe', 'john@email.com', 'patient123'),
('Jane Smith', 'jane@email.com', 'patient123'),
('Test Patient', 'patient@test.com', 'test123');

-- Sample availability
INSERT INTO availability (doctor_id, day, start_time, end_time)
SELECT id, 'Sunday', '09:00:00', '11:00:00' FROM doctors WHERE name = 'Dr. Asha Sharma';

INSERT INTO availability (doctor_id, day, start_time, end_time)
SELECT id, 'Monday', '09:00:00', '11:00:00' FROM doctors WHERE name = 'Dr. Asha Sharma';

-- Sample appointments
INSERT INTO appointments (patient_email, patient_name, doctor_name, doctor_id, specialization, appointment_date, time_slot, reason, status)
SELECT 'john@email.com', 'John Doe', 'Dr. Asha Sharma', id, 'Cardiologist', CURDATE() + INTERVAL 1 DAY, '10:00 AM', 'Follow-up', 'BOOKED'
FROM doctors WHERE name = 'Dr. Asha Sharma';
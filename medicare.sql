CREATE DATABASE medicare_connect;
USE medicare_connect;

CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    specialization VARCHAR(100),
    password VARCHAR(255) DEFAULT 'doctor123',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    patient_name VARCHAR(100) NOT NULL,
    patient_email VARCHAR(100),
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    reason VARCHAR(255),
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

-- Sample data
INSERT INTO doctors (name, email, phone, specialization) VALUES 
('Dr. Asha Sharma', 'asha@medicare.com', '9876543210', 'Cardiologist');

INSERT INTO appointments (doctor_id, patient_name, patient_email, appointment_date, appointment_time, reason) VALUES
(1, 'Niru M.', 'niru@email.com', '2026-04-07', '10:30:00', 'Follow-up'),
(1, 'Rahul S.', 'rahul@email.com', '2026-04-08', '11:30:00', 'Follow-up'),
(1, 'Amit P.', 'amit@email.com', '2026-04-09', '12:00:00', 'Consultation');
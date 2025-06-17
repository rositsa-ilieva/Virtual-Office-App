-- Create database if not exists
CREATE DATABASE IF NOT EXISTS virtual_office;
USE virtual_office;

-- Drop existing tables if they exist (in correct order due to foreign key constraints)
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS queue_entries;
DROP TABLE IF EXISTS queue_statistics;
DROP TABLE IF EXISTS swap_requests;
DROP TABLE IF EXISTS time_slots;
DROP TABLE IF EXISTS queues;
DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('teacher', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    faculty_number VARCHAR(100) DEFAULT NULL,
    specialization VARCHAR(100) DEFAULT NULL,
    year_of_study VARCHAR(20) DEFAULT NULL,
    teacher_role VARCHAR(100) DEFAULT NULL,
    subjects TEXT,
    profile_photo VARCHAR(255) DEFAULT NULL
);

-- Create queues table
CREATE TABLE IF NOT EXISTS queues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    description TEXT,
    meeting_link VARCHAR(255),
    access_code VARCHAR(50),
    meeting_type VARCHAR(50),
    wait_time_method ENUM('manual','automatic') DEFAULT 'automatic',
    is_active BOOLEAN DEFAULT TRUE,
    is_automatic BOOLEAN DEFAULT FALSE,
    default_duration INT DEFAULT 15,
    max_students INT DEFAULT 10,
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    target_specialization VARCHAR(50) DEFAULT 'All',
    target_year VARCHAR(20) DEFAULT 'All',
    specialization_year_map TEXT,
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);

-- Create time_slots table
CREATE TABLE IF NOT EXISTS time_slots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    queue_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_id) REFERENCES queues(id) ON DELETE CASCADE
);

-- Create queue_entries table
CREATE TABLE IF NOT EXISTS queue_entries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    queue_id INT NOT NULL,
    student_id INT NOT NULL,
    position INT NOT NULL,
    status ENUM('waiting', 'in_meeting', 'done', 'skipped') DEFAULT 'waiting',
    comment TEXT,
    is_comment_public BOOLEAN DEFAULT TRUE,
    estimated_start_time DATETIME NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    FOREIGN KEY (queue_id) REFERENCES queues(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('student_message', 'teacher_reply', 'swap_request', 'swap_approved', 'swap_declined') NOT NULL,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    related_queue_id INT,
    related_user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (related_queue_id) REFERENCES queues(id),
    FOREIGN KEY (related_user_id) REFERENCES users(id)
);

-- Create queue_statistics table
CREATE TABLE IF NOT EXISTS queue_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    queue_id INT,
    total_entries INT DEFAULT 0,
    average_wait_time INT DEFAULT 0,
    average_meeting_duration INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_id) REFERENCES queues(id)
);

-- Create swap_requests table
CREATE TABLE IF NOT EXISTS swap_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    queue_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_id) REFERENCES queues(id),
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id),
    UNIQUE KEY unique_active_request (queue_id, sender_id, receiver_id, status)
);

-- Insert test users
INSERT INTO users (name, email, password, role, teacher_role, subjects) VALUES
('Maria Ivanova', 'maria@gmail.com', '12345678', 'teacher', 'Professor', 'Mathematics, Computer Science'),
('Peter Petrov', 'peter@gmail.com', '12345678', 'teacher', 'Assistant Professor', 'Physics, Informatics');

INSERT INTO users (name, email, password, role, faculty_number, specialization, year_of_study) VALUES
('Ivan Georgiev', 'ivan@gmail.com', '12345678', 'student', '12345', 'Computer Science', '3'),
('Anna Petrova', 'anna@gmail.com', '12345678', 'student', '12346', 'Computer Science', '2'),
('Georgi Dimitrov', 'georgi@gmail.com', '12345678', 'student', '12347', 'Information Systems', '4');

-- Insert meetings and queues with realistic timestamps
-- Past meeting (yesterday)
INSERT INTO queues (teacher_id, purpose, description, meeting_link, access_code, start_time, end_time, is_active) VALUES
(1, 'Database Systems - Past Session', 'Review of SQL queries and normalization', 'https://meet.google.com/past123', 'past123', 
 DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 23 HOUR), FALSE);

-- Current meeting (today)
INSERT INTO queues (teacher_id, purpose, description, meeting_link, access_code, start_time, end_time, is_active) VALUES
(1, 'Database Systems - Current Session', 'Advanced SQL and database design', 'https://meet.google.com/current123', 'current123',
 DATE_FORMAT(NOW(), '%Y-%m-%d 10:00:00'), DATE_FORMAT(NOW(), '%Y-%m-%d 12:00:00'), TRUE);

-- Future meeting (tomorrow)
INSERT INTO queues (teacher_id, purpose, description, meeting_link, access_code, start_time, end_time, is_active) VALUES
(2, 'Web Development - Future Session', 'JavaScript and React fundamentals', 'https://meet.google.com/future123', 'future123',
 DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 1 DAY), INTERVAL 2 HOUR), TRUE);

-- Add queue entries for past meeting
INSERT INTO queue_entries (queue_id, student_id, position, status, comment, started_at, ended_at) VALUES
(1, 3, 1, 'done', 'SQL optimization questions', DATE_SUB(NOW(), INTERVAL 25 HOUR), DATE_SUB(NOW(), INTERVAL 24.5 HOUR)),
(1, 4, 2, 'done', 'Database normalization help', DATE_SUB(NOW(), INTERVAL 24.5 HOUR), DATE_SUB(NOW(), INTERVAL 24 HOUR));

-- Add queue entries for current meeting
INSERT INTO queue_entries (queue_id, student_id, position, status, comment) VALUES
(2, 3, 1, 'in_meeting', 'Need help with complex queries'),
(2, 4, 2, 'waiting', 'Questions about database design'),
(2, 5, 3, 'waiting', 'Database optimization techniques');

-- Add queue entries for future meeting
INSERT INTO queue_entries (queue_id, student_id, position, status, comment) VALUES
(3, 3, 1, 'waiting', 'React hooks questions'),
(3, 4, 2, 'waiting', 'JavaScript async/await help');

-- Add some notifications
INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES
(1, 'student_message', 'I have a question about the database schema', 2, 3),
(3, 'teacher_reply', 'Sure, what would you like to know?', 2, 1),
(4, 'swap_request', 'Would you like to swap positions?', 2, 5),
(5, 'swap_approved', 'Position swap approved', 2, 4); 
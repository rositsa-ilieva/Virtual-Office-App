DROP DATABASE IF EXISTS virtual_office;
CREATE DATABASE virtual_office;
USE virtual_office;

CREATE TABLE IF NOT EXISTS users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('teacher', 'student') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  faculty_number VARCHAR(100) DEFAULT NULL,
  specialization VARCHAR(100) DEFAULT NULL,
  year_of_study VARCHAR(20) DEFAULT NULL
);

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
  default_duration INT DEFAULT 15, -- in minutes
  max_students INT DEFAULT 10, -- maximum number of students allowed in the queue
  start_time DATETIME NULL, -- queue start time
  end_time DATETIME NULL, -- queue end time
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  target_specialization VARCHAR(50) DEFAULT 'All',
  target_year VARCHAR(20) DEFAULT 'All',
  specialization_year_map TEXT DEFAULT NULL,
  FOREIGN KEY (teacher_id) REFERENCES users(id)
);

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

CREATE TABLE IF NOT EXISTS time_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  queue_id INT,
  start_time DATETIME,
  end_time DATETIME,
  is_available BOOLEAN DEFAULT true,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (queue_id) REFERENCES queues(id)
);

CREATE TABLE IF NOT EXISTS queue_statistics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  queue_id INT,
  total_entries INT DEFAULT 0,
  average_wait_time INT DEFAULT 0, -- in minutes
  average_meeting_duration INT DEFAULT 0, -- in minutes
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (queue_id) REFERENCES queues(id)
);

CREATE TABLE IF NOT EXISTS temporary_invites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  queue_entry_id INT,
  invited_user_id INT,
  invite_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('active','completed') DEFAULT 'active',
  FOREIGN KEY (queue_entry_id) REFERENCES queue_entries(id),
  FOREIGN KEY (invited_user_id) REFERENCES users(id)
);

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

ALTER TABLE queues ADD COLUMN max_students INT DEFAULT 10 AFTER default_duration;
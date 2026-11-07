-- Store student, teacher, and administrator accounts.
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
  department VARCHAR(100) NULL,
  student_id VARCHAR(50) NULL,
  phone VARCHAR(30) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

-- Add demo accounts for local testing; existing accounts are not overwritten.
INSERT IGNORE INTO users (name, email, password, role, created_at, updated_at) VALUES
  ('Admin User', 'admin@sdms.test', '$2y$12$WlC2bmBHnVu6UoETbTnGYei9yrvKnYfQ5F2ESNvtInVSU/EztJdvy', 'admin', NOW(), NOW()),
  ('Teacher User', 'teacher@sdms.test', '$2y$12$WlC2bmBHnVu6UoETbTnGYei9yrvKnYfQ5F2ESNvtInVSU/EztJdvy', 'teacher', NOW(), NOW()),
  ('Student User', 'student@sdms.test', '$2y$12$WlC2bmBHnVu6UoETbTnGYei9yrvKnYfQ5F2ESNvtInVSU/EztJdvy', 'student', NOW(), NOW());

-- Store student submissions and their teacher/admin reviews.
CREATE TABLE IF NOT EXISTS applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  document_path VARCHAR(255) NULL,
  status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  review_message TEXT NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT applications_student_fk FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT applications_teacher_fk FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
);
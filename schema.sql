CREATE DATABASE IF NOT EXISTS course_enrollment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE course_enrollment;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    course VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    about TEXT NOT NULL,
    profile_photo VARCHAR(255) NOT NULL,
    resume_file VARCHAR(255) NOT NULL,
    cover_letter_file VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    technologies VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_projects_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX idx_projects_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_project_files_project
        FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX idx_project_files_project (project_id)
) ENGINE=InnoDB;
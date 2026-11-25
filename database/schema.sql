-- =====================================================================
-- MOMS BEAUTY TOUCH - DATABASE SCHEMA & SEED DATA
-- Target DBMS: MySQL 5.7+ / 8.0+ / MariaDB 10.4+ (XAMPP Compatible)
-- Character Set: utf8mb4 (Unicode)
-- Storage Engine: InnoDB
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `moms_beauty_touch`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `moms_beauty_touch`;

-- ---------------------------------------------------------------------
-- 1. TABLE: admins
-- Stores admin accounts. No hardcoded credentials are included.
-- Initial admin account is created via secure one-time setup on first visit.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_admin_username` (`username`),
    UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. TABLE: login_attempts
-- Database-backed rate limiting to defend against brute-force attacks.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempt_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
    INDEX `idx_login_ip_time` (`ip_address`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. TABLE: business_settings
-- Singleton table (ID=1) preserving business identity & contact information.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `business_settings` (
    `id` INT UNSIGNED PRIMARY KEY DEFAULT 1 CHECK (`id` = 1),
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `phone_tel` VARCHAR(50) NOT NULL,
    `whatsapp` VARCHAR(50) NOT NULL,
    `rating` DECIMAL(3, 2) NOT NULL DEFAULT 4.52,
    `address` TEXT NOT NULL,
    `map_query` TEXT NOT NULL,
    `hours` VARCHAR(100) NOT NULL,
    `instagram` VARCHAR(255) NOT NULL DEFAULT '',
    `facebook` VARCHAR(255) NOT NULL DEFAULT '',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. TABLE: services
-- Makeup artistry service offerings.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(100) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `short` TEXT NOT NULL,
    `body` TEXT NOT NULL,
    `price` VARCHAR(100) NOT NULL DEFAULT '',
    `image_url` VARCHAR(255) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_service_slug` (`slug`),
    INDEX `idx_service_active_sort` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. TABLE: gallery
-- Portfolio photography items tagged by category.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gallery` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `image_url` VARCHAR(255) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `alt_text` VARCHAR(150) NOT NULL DEFAULT '',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_gallery_category` (`category`),
    INDEX `idx_gallery_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. TABLE: appointments
-- Appointment and bridal enquiry records with foreign key link to services.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appointments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ref_code` VARCHAR(20) NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `event_date` DATE NOT NULL,
    `preferred_time` VARCHAR(20) NOT NULL,
    `location` VARCHAR(200) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'accepted', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    `internal_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_appointment_ref` (`ref_code`),
    INDEX `idx_appointment_service` (`service_id`),
    INDEX `idx_appointment_phone` (`phone`),
    INDEX `idx_appointment_date` (`event_date`),
    INDEX `idx_appointment_status` (`status`),
    INDEX `idx_appointment_created` (`created_at`),
    CONSTRAINT `fk_appointments_service`
        FOREIGN KEY (`service_id`)
        REFERENCES `services` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. TABLE: testimonials
-- Client feedback and reviews.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `testimonials` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `review_text` TEXT NOT NULL,
    `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `is_published` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_testimonial_published` (`is_published`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. TABLE: contact_messages
-- Direct website messages submitted from the contact page.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_contact_read` (`is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA (Preserving All Original Business Content)
-- =====================================================================

-- 1. Seed Business Settings
INSERT INTO `business_settings` (
    `id`, `name`, `phone`, `phone_tel`, `whatsapp`, `rating`, `address`, `map_query`, `hours`, `instagram`, `facebook`
) VALUES (
    1,
    'Moms Beauty Touch',
    '+91 72198 84594',
    '+917219884594',
    '917219884594',
    4.52,
    'Near Manti Memorial High School, Naleswar Nagar, Kaner, Vasai-Virar, Maharashtra 401303, India',
    'Moms Beauty Touch, Near Manti Memorial High School, Naleswar Nagar, Kaner, Vasai-Virar, Maharashtra 401303',
    'Open 24 hours',
    'https://www.instagram.com/moms_beauty_touch',
    ''
) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 2. Seed Services
INSERT INTO `services` (`slug`, `title`, `short`, `body`, `price`, `image_url`, `active`, `sort_order`) VALUES
(
    'bridal',
    'Bridal Makeup',
    'A radiant, long-wearing look made around you, your outfit and your wedding day.',
    'Your bridal look is planned with you, from skin preparation to the final touch. We talk through your outfit, jewellery, skin and the feel you want, so the look stays true to you through every ceremony and photograph.',
    '',
    'assets/images/hero.jpg',
    1,
    1
),
(
    'party',
    'Party Makeup',
    'Polished glamour for celebrations, receptions and evenings out.',
    'From soft shimmer to bold evening glam, party makeup is tailored to the occasion, your outfit and the lighting you''ll be in, so you feel confident all night.',
    '',
    'assets/images/party.jpg',
    1,
    2
),
(
    'wedding',
    'Wedding Makeup',
    'For the bride''s family, bridesmaids and guests who want to look their best.',
    'Elegant makeup for mothers, sisters, bridesmaids and guests. Looks are coordinated so everyone feels beautiful and photographs well together.',
    '',
    'assets/images/studio.jpg',
    1,
    3
),
(
    'engagement',
    'Engagement Makeup',
    'Fresh, luminous makeup for your engagement and pre-wedding events.',
    'A soft, glowing look for your engagement ceremony, roka or pre-wedding shoot, designed to feel natural while still standing out in photographs.',
    '',
    'assets/images/hero.jpg',
    1,
    4
) ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);

-- 3. Seed Gallery Items (Preserving original 6 looks and categories)
INSERT INTO `gallery` (`image_url`, `category`, `alt_text`, `sort_order`) VALUES
('assets/images/hero.jpg', 'Bridal', 'Sample bridal look', 1),
('assets/images/party.jpg', 'Party', 'Sample party look', 2),
('assets/images/studio.jpg', 'Special Occasions', 'Makeup kit', 3),
('assets/images/party.jpg', 'Engagement', 'Sample engagement look', 4),
('assets/images/hero.jpg', 'Wedding', 'Sample wedding look', 5),
('assets/images/studio.jpg', 'Bridal', 'Bridal preparation', 6);


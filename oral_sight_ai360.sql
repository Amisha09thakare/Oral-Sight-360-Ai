-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 26, 2025 at 02:15 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `oral_sight_ai360`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor') NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `specialization` varchar(120) DEFAULT NULL,
  `experience` varchar(20) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `role`, `email`, `specialization`, `experience`, `profile_pic`) VALUES
(7, 'Meera Kulkarni', 'D3nt@l!2025', 'admin', NULL, NULL, NULL, NULL),
(8, 'Aarav Deshmukh', 'Rk!End0-55', 'admin', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `slot` enum('slot1','slot2','slot3','slot4') NOT NULL,
  `status` enum('pending','booked','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `appointment_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `slot`, `status`, `created_at`, `appointment_time`) VALUES
(68, 20, 8, '2025-09-27', '', '', '2025-09-18 05:38:00', '00:00:00'),
(69, 20, 7, '2025-10-06', '', 'cancelled', '2025-09-18 05:39:04', '00:00:00'),
(70, 20, 8, '2025-09-26', '', '', '2025-09-18 06:32:34', '00:00:00'),
(71, 27, 8, '2025-09-29', '', '', '2025-09-19 16:46:28', '00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `home_care`
--

CREATE TABLE `home_care` (
  `id` int(11) NOT NULL,
  `treatment_type` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `video_url1` varchar(255) DEFAULT NULL,
  `video_url2` varchar(255) DEFAULT NULL,
  `video_url3` varchar(255) DEFAULT NULL,
  `video_url4` varchar(255) DEFAULT NULL,
  `video1` varchar(255) DEFAULT NULL,
  `video2` varchar(255) DEFAULT NULL,
  `video3` varchar(255) DEFAULT NULL,
  `video4` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `home_care`
--

INSERT INTO `home_care` (`id`, `treatment_type`, `title`, `instructions`, `created_at`, `video_url1`, `video_url2`, `video_url3`, `video_url4`, `video1`, `video2`, `video3`, `video4`) VALUES
(1, 'Braces', 'Avoid Hard Foods', 'Do not chew ice or eat hard candy', '2025-09-05 12:48:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'Braces', 'Brush Carefully', 'Use a soft toothbrush around brackets', '2025-09-05 12:48:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Root Canal', 'Pain Relief', 'Take painkillers only as prescribed', '2025-09-05 12:48:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'Root Canal', 'Antibiotics Reminder', 'Complete your full course of antibiotics', '2025-09-05 12:48:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Teeth Whitening', 'Avoid Coffee/Tea', 'Avoid coffee, tea, and smoking for 48 hours', '2025-09-05 12:48:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'Dental Implants', 'Proper Cleaning', 'Clean implant area gently with soft brush', '2025-09-05 12:48:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `home_care_videos`
--

CREATE TABLE `home_care_videos` (
  `id` int(11) NOT NULL,
  `home_care_id` int(11) NOT NULL,
  `video_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `dob` date NOT NULL,
  `password` varchar(255) NOT NULL,
  `age` int(11) NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `phone` varchar(20) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `name`, `email`, `dob`, `password`, `age`, `gender`, `phone`, `doctor_id`, `created_at`) VALUES
(19, 'Mohini Sharma', 'mohini@gmail.com', '0000-00-00', '$2y$10$xddu7oQFbraE8ZjwGavyLeW7WfRdUqUxBUdd5g3gQaDZAQRcTEWiC', 12, 'female', '7458123698', 8, '2025-09-15 15:06:01'),
(20, 'Nirmayi Padole', 'nirmayi@gmail.com', '2025-09-10', '$2y$10$3Nw1oHen2HP1pkd/urmlruIErudurv9RDcTImkczZyWz6auhfBFyS', 54, 'female', '7972852369', 8, '2025-09-15 15:27:19'),
(21, 'sourabh walse', 'sourabh@gmail.com', '2025-09-10', '$2y$10$kg5MC25TxfooIy01ELSNaOxWy1SyYZgvcrIOd7FkNp1FfH/KiOyU2', 21, 'male', '8569741236', 8, '2025-09-15 15:54:36'),
(22, 'Suhani Agarwal', 'suhani@gmail.com', '2025-09-11', '$2y$10$0P2N4J4stqZI8MRo4HJeKeFSIoMCjG7NEdsYCPU6Ulw2aWVZ/vWXu', 54, 'female', '4758691236', 8, '2025-09-17 12:23:23'),
(23, 'Vivek Singh', 'vivek@gmail.com', '2012-02-13', '$2y$10$e4YEXVYqwgENJLhOZxU.COtrTZa2m0DJPvdhq4xNbk.ox6/YIpvam', 45, 'male', '7845692541', 8, '2025-09-17 16:55:55'),
(24, 'Avir Bise', 'avir@gmail.com', '2025-09-11', '$2y$10$fbdGRPWuPfpMzbPKJbORF.jViawjQCmvSND52BUJyXpRyBt2OyXby', 12, 'male', '7845691256', 7, '2025-09-17 17:37:54'),
(25, 'Kiran Mishra', 'kiran@gmail.com', '2025-09-12', '$2y$10$mKmaFREfypdkGKeeClwpIO9iYhRpfaNHHoKXhSk29.tkBBFAbfTS2', 45, 'female', '7458693215', 8, '2025-09-17 18:12:57'),
(26, 'Purav furky', 'purav@gmail.com', '2025-09-19', '$2y$10$LIwKHmimgntxGWbZF6u88.rqIdLgY3LE5yZ4w0eYaU41J/WxwEcBy', 25, 'male', '8547963215', 8, '2025-09-18 02:35:33'),
(27, 'Kasturi Joshi', 'kasturi@gmail.com', '2025-09-03', '$2y$10$RkGkNssQBGE0/YWI/dQcH.UqG/SgVYBXPq2NLCr6K7Q.RRGaC/S2u', 12, 'female', '7845963214', 8, '2025-09-18 04:01:17');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scans`
--

CREATE TABLE `scans` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `patient_name` varchar(150) NOT NULL,
  `scan_file` varchar(255) NOT NULL,
  `file_type` enum('png','jpg','jpeg','pdf','stl','obj','ply','glb','gltf') NOT NULL,
  `analysis_result` text DEFAULT NULL,
  `colored_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','analyzed','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shared_cases`
--

CREATE TABLE `shared_cases` (
  `id` int(11) NOT NULL,
  `case_type` varchar(20) NOT NULL,
  `case_id` int(11) NOT NULL,
  `shared_by` int(11) NOT NULL,
  `shared_with` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shared_cases_files`
--

CREATE TABLE `shared_cases_files` (
  `id` int(11) NOT NULL,
  `shared_case_id` int(11) NOT NULL,
  `file_path` varchar(1024) NOT NULL,
  `original_name` varchar(255) DEFAULT '',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shared_case_comments`
--

CREATE TABLE `shared_case_comments` (
  `id` int(11) NOT NULL,
  `shared_case_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shared_case_files`
--

CREATE TABLE `shared_case_files` (
  `id` int(11) NOT NULL,
  `shared_case_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `treatment_plans`
--

CREATE TABLE `treatment_plans` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `total_weeks` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `completed_weeks` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treatment_plans`
--

INSERT INTO `treatment_plans` (`id`, `patient_id`, `doctor_id`, `total_weeks`, `start_date`, `notes`, `completed_weeks`) VALUES
(12, 20, 8, 45, '2025-09-16', '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `treatment_progress`
--

CREATE TABLE `treatment_progress` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `total_weeks` int(11) DEFAULT NULL,
  `completed_weeks` int(11) DEFAULT 0,
  `visits` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treatment_progress`
--

INSERT INTO `treatment_progress` (`id`, `patient_id`, `total_weeks`, `completed_weeks`, `visits`) VALUES
(8, 19, 5, 1, 1),
(9, 21, 20, 6, 0),
(10, 20, 45, 40, 2),
(12, 26, 0, 0, 1),
(13, 27, 0, 0, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `home_care`
--
ALTER TABLE `home_care`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `home_care_videos`
--
ALTER TABLE `home_care_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `home_care_id` (`home_care_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `scans`
--
ALTER TABLE `scans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `shared_cases`
--
ALTER TABLE `shared_cases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shared_by` (`shared_by`),
  ADD KEY `shared_with` (`shared_with`);

--
-- Indexes for table `shared_cases_files`
--
ALTER TABLE `shared_cases_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shared_case_id` (`shared_case_id`);

--
-- Indexes for table `shared_case_comments`
--
ALTER TABLE `shared_case_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shared_case_id` (`shared_case_id`),
  ADD KEY `author_id` (`author_id`);

--
-- Indexes for table `shared_case_files`
--
ALTER TABLE `shared_case_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shared_case_id` (`shared_case_id`);

--
-- Indexes for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `treatment_progress`
--
ALTER TABLE `treatment_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_id` (`patient_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `home_care`
--
ALTER TABLE `home_care`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `home_care_videos`
--
ALTER TABLE `home_care_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scans`
--
ALTER TABLE `scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `shared_cases`
--
ALTER TABLE `shared_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `shared_cases_files`
--
ALTER TABLE `shared_cases_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shared_case_comments`
--
ALTER TABLE `shared_case_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shared_case_files`
--
ALTER TABLE `shared_case_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `treatment_progress`
--
ALTER TABLE `treatment_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `home_care_videos`
--
ALTER TABLE `home_care_videos`
  ADD CONSTRAINT `home_care_videos_ibfk_1` FOREIGN KEY (`home_care_id`) REFERENCES `home_care` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scans`
--
ALTER TABLE `scans`
  ADD CONSTRAINT `scans_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scans_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shared_cases`
--
ALTER TABLE `shared_cases`
  ADD CONSTRAINT `shared_cases_ibfk_1` FOREIGN KEY (`shared_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shared_cases_ibfk_2` FOREIGN KEY (`shared_with`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shared_cases_files`
--
ALTER TABLE `shared_cases_files`
  ADD CONSTRAINT `shared_cases_files_ibfk_1` FOREIGN KEY (`shared_case_id`) REFERENCES `shared_cases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shared_case_comments`
--
ALTER TABLE `shared_case_comments`
  ADD CONSTRAINT `shared_case_comments_ibfk_1` FOREIGN KEY (`shared_case_id`) REFERENCES `shared_cases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shared_case_comments_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shared_case_files`
--
ALTER TABLE `shared_case_files`
  ADD CONSTRAINT `shared_case_files_ibfk_1` FOREIGN KEY (`shared_case_id`) REFERENCES `shared_cases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `treatment_plans`
--
ALTER TABLE `treatment_plans`
  ADD CONSTRAINT `treatment_plans_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `treatment_plans_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `treatment_progress`
--
ALTER TABLE `treatment_progress`
  ADD CONSTRAINT `treatment_progress_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

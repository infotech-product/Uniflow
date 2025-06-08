-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 08, 2025 at 03:33 PM
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
-- Database: `uniflow_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `academy_courses`
--

CREATE TABLE `academy_courses` (
  `id` int(11) NOT NULL,
  `university_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `points_reward` int(11) DEFAULT 100,
  `duration_minutes` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academy_courses`
--

INSERT INTO `academy_courses` (`id`, `university_id`, `title`, `description`, `points_reward`, `duration_minutes`) VALUES
(1, 1, 'Financial Literacy Basics', 'Learn the fundamentals of personal finance, budgeting, and saving strategies.', 150, 45),
(2, 1, 'Understanding Student Loans', 'Comprehensive guide to student loan terms, repayment options, and debt management.', 200, 60),
(3, 2, 'Digital Banking Skills', 'Master online banking, mobile payments, and digital financial tools.', 120, 30),
(4, 3, 'Investment Fundamentals', 'Introduction to investing, risk management, and building wealth.', 250, 90),
(5, 4, 'Entrepreneurship for Students', 'Learn how to start and manage a small business while studying.', 300, 120),
(6, 5, 'Career Planning & Finance', 'Plan your career path and understand salary negotiations and benefits.', 180, 75),
(7, 6, 'Emergency Fund Management', 'Build and maintain emergency funds for financial security.', 160, 50),
(8, 7, 'Credit Score & Management', 'Understand credit scores, credit reports, and responsible credit use.', 140, 40);

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `action_type` enum('student_edit','loan_approve','loan_reject','payment_view','defaulters_view','system_change') NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `action_type`, `target_id`, `details`, `performed_at`) VALUES
(1, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 19:45:40'),
(2, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:00:39'),
(3, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:02:31'),
(4, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:03:37'),
(5, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:04:34'),
(6, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:09:35'),
(7, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:10:00'),
(8, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:10:40'),
(9, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:11:17'),
(10, '', NULL, 'Accessed admin dashboard', '2025-06-07 20:20:02'),
(11, '', NULL, 'Accessed admin dashboard', '2025-06-07 20:21:19'),
(12, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:21:49'),
(13, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:27:04'),
(14, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:27:58'),
(15, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:29:24'),
(16, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:29:59'),
(17, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:30:26'),
(18, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:30:52'),
(19, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:30:57'),
(20, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:31:01'),
(21, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:34:50'),
(22, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:41:15'),
(23, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:41:19'),
(24, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:41:23'),
(25, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:42:05'),
(26, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:42:08'),
(27, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:42:09'),
(28, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:42:16'),
(29, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:42:23'),
(30, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:42:27'),
(31, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:42:37'),
(32, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:43:10'),
(33, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:45:39'),
(34, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:45:49'),
(35, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:52:25'),
(36, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:52:32'),
(37, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:52:35'),
(38, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:53:40'),
(39, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:53:46'),
(40, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:53:54'),
(41, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:54:59'),
(42, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:55:01'),
(43, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:55:03'),
(44, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:55:08'),
(45, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:56:07'),
(46, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:56:25'),
(47, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:56:35'),
(48, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:58:13'),
(49, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:58:15'),
(50, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:59:18'),
(51, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 20:59:35'),
(52, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:00:13'),
(53, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:00:17'),
(54, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:00:18'),
(55, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:00:31'),
(56, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:00:34'),
(57, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:02:26'),
(58, '', 1, 'Admin logged out', '2025-06-07 21:03:37'),
(59, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:04:00'),
(60, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:04:07'),
(61, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:04:09'),
(62, 'loan_approve', 4, 'Approved loan application', '2025-06-07 21:06:43'),
(63, 'loan_approve', 13, 'Approved loan application', '2025-06-07 21:06:47'),
(64, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:07:31'),
(65, '', 7, 'Verified student account', '2025-06-07 21:07:41'),
(66, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:07:59'),
(67, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:14:49'),
(68, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:15:56'),
(69, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:18:09'),
(70, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:20:11'),
(71, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:22:38'),
(72, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 21:22:52'),
(73, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:24:18'),
(74, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:24:18'),
(75, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:24:27'),
(76, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:25:31'),
(77, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:26:04'),
(78, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:43:34'),
(79, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:45:04'),
(80, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:45:12'),
(81, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:45:15'),
(82, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:47:03'),
(83, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:47:50'),
(84, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:48:23'),
(85, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:48:38'),
(86, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:49:04'),
(87, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:49:25'),
(88, 'system_change', 1, 'Accessed repayments management', '2025-06-07 21:49:48'),
(89, '', 1, 'Generated overview report for month timeframe', '2025-06-07 21:59:06'),
(90, '', 1, 'Generated overview report for all timeframe', '2025-06-07 21:59:21'),
(91, '', 1, 'Generated student report for all timeframe', '2025-06-07 21:59:24'),
(92, '', 1, 'Generated university report for all timeframe', '2025-06-07 21:59:33'),
(93, '', 1, 'Generated overview report for all timeframe', '2025-06-07 21:59:46'),
(94, '', 1, 'Generated overview report for month timeframe', '2025-06-07 22:01:18'),
(95, '', 1, 'Generated overview report for month timeframe', '2025-06-07 22:01:36'),
(96, '', 1, 'Generated university report for month timeframe', '2025-06-07 22:01:39'),
(97, '', 1, 'Generated overview report for all timeframe', '2025-06-07 22:02:51'),
(98, '', 1, 'Generated university report for all timeframe', '2025-06-07 22:05:14'),
(99, '', 1, 'Generated overview report for all timeframe', '2025-06-07 22:05:20'),
(100, '', 1, 'Generated overview report for week timeframe', '2025-06-07 22:05:24'),
(101, '', 1, 'Accessed risk assessment dashboard', '2025-06-07 22:15:28'),
(102, '', 1, 'Accessed risk assessment dashboard', '2025-06-07 22:15:43'),
(103, '', 1, 'Accessed risk assessment dashboard', '2025-06-07 22:37:34'),
(104, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 22:55:26'),
(105, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 22:55:35'),
(106, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 22:56:28'),
(107, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 23:01:01'),
(108, '', 1, 'Generated overview report for month timeframe', '2025-06-07 23:01:07'),
(109, 'system_change', 1, 'Accessed admin dashboard', '2025-06-07 23:34:58');

-- --------------------------------------------------------

--
-- Table structure for table `allowance_verification_logs`
--

CREATE TABLE `allowance_verification_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `university_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `disbursement_date` date NOT NULL,
  `verified_by` enum('system','admin') DEFAULT 'system',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `allowance_verification_logs`
--

INSERT INTO `allowance_verification_logs` (`id`, `student_id`, `university_id`, `amount`, `disbursement_date`, `verified_by`, `created_at`) VALUES
(1, 3, 12, 1500.00, '2025-06-01', 'system', '2025-06-07 15:15:42');

-- --------------------------------------------------------

--
-- Table structure for table `gamification_points`
--

CREATE TABLE `gamification_points` (
  `student_id` int(11) NOT NULL,
  `university_id` int(11) NOT NULL,
  `points` int(11) DEFAULT 0,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gamification_points`
--

INSERT INTO `gamification_points` (`student_id`, `university_id`, `points`, `last_activity`) VALUES
(1, 8, 450, '2025-06-06 13:30:00'),
(2, 1, 320, '2025-06-05 12:20:00'),
(3, 2, 750, '2025-06-07 18:06:07'),
(4, 3, 120, '2025-06-03 08:15:00'),
(5, 4, 200, '2025-06-02 10:30:00'),
(6, 5, 670, '2025-06-01 16:00:00'),
(7, 6, 390, '2025-05-31 07:45:00'),
(8, 7, 150, '2025-05-30 09:20:00'),
(18, 2, 140, '2025-06-07 18:30:35');

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `university_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `interest_rate` decimal(5,2) DEFAULT 5.00,
  `status` enum('pending','approved','rejected','repaid','defaulted') DEFAULT 'pending',
  `disbursement_date` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `repayment_method` enum('auto','manual') DEFAULT NULL,
  `is_emergency_topup` tinyint(1) DEFAULT 0,
  `purpose` varchar(100) DEFAULT NULL,
  `ai_approval_confidence` decimal(5,2) DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `status_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`id`, `student_id`, `university_id`, `amount`, `amount_paid`, `interest_rate`, `status`, `disbursement_date`, `due_date`, `repayment_method`, `is_emergency_topup`, `purpose`, `ai_approval_confidence`, `rejection_reason`, `status_updated_at`, `created_at`) VALUES
(1, 1, 8, 300.00, 450.00, 5.00, 'approved', '2025-06-01 10:00:00', '2025-07-01', 'auto', 0, NULL, 85.50, NULL, '2025-06-07 15:21:47', '2025-06-01 07:30:00'),
(2, 2, 1, 500.00, 0.00, 5.00, 'repaid', '2025-05-15 14:30:00', '2025-06-15', 'manual', 0, NULL, 90.25, NULL, '2025-06-07 15:00:28', '2025-05-15 12:00:00'),
(3, 3, 12, 750.00, 750.00, 5.00, 'repaid', '2025-05-20 11:15:00', '2025-06-20', 'auto', 1, NULL, 75.80, NULL, '2025-06-07 18:06:07', '2025-05-20 08:45:00'),
(4, 4, 3, 200.00, 0.00, 5.00, 'approved', '2025-06-07 23:06:43', '2025-07-07', NULL, 0, NULL, 65.30, NULL, '2025-06-07 21:06:43', '2025-06-06 06:20:00'),
(5, 5, 4, 150.00, 0.00, 5.00, 'rejected', NULL, NULL, NULL, 0, NULL, 35.60, NULL, '2025-06-07 15:00:28', '2025-06-05 14:10:00'),
(6, 6, 5, 800.00, 0.00, 5.00, 'approved', '2025-06-02 13:45:00', '2025-07-02', 'auto', 0, NULL, 92.40, NULL, '2025-06-07 15:00:28', '2025-06-02 11:15:00'),
(7, 7, 6, 400.00, 0.00, 5.00, 'approved', '2025-05-25 09:30:00', '2025-06-25', 'manual', 1, NULL, 78.90, NULL, '2025-06-07 15:00:28', '2025-05-25 07:00:00'),
(8, 3, 12, 800.00, 800.00, 5.00, 'repaid', '2025-05-15 10:30:00', '2025-07-15', 'auto', 0, NULL, 85.50, NULL, '2025-06-07 18:05:01', '2025-05-15 08:30:00'),
(9, 3, 12, 500.00, 0.00, 5.00, 'repaid', '2025-04-01 14:20:00', '2025-06-01', 'manual', 0, NULL, 78.25, NULL, '2025-06-07 15:19:26', '2025-04-01 12:20:00'),
(10, 3, 12, 200.00, 200.00, 5.50, 'repaid', '2025-05-28 16:45:00', '2025-06-28', 'auto', 1, NULL, 72.80, NULL, '2025-06-07 18:02:21', '2025-05-28 14:45:00'),
(11, 3, 12, 150.00, 150.00, 5.00, 'repaid', '2025-06-07 15:40:18', '2025-07-07', 'auto', 0, NULL, 85.97, NULL, '2025-06-07 18:36:03', '2025-06-07 15:40:18'),
(12, 18, 2, 200.00, 200.00, 5.00, 'repaid', '2025-06-07 18:24:09', '2025-07-07', 'auto', 0, NULL, 82.94, NULL, '2025-06-07 18:30:35', '2025-06-07 18:24:09'),
(13, 19, 5, 500.00, 0.00, 5.00, 'approved', '2025-06-07 23:06:47', '2025-07-07', 'auto', 0, NULL, 64.94, NULL, '2025-06-07 21:06:47', '2025-06-07 20:34:33');

-- --------------------------------------------------------

--
-- Table structure for table `repayments`
--

CREATE TABLE `repayments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `university_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('mobile_money','bank_transfer','auto_debit') DEFAULT NULL,
  `transaction_reference` varchar(50) DEFAULT NULL,
  `is_partial` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `repayments`
--

INSERT INTO `repayments` (`id`, `loan_id`, `university_id`, `amount`, `method`, `transaction_reference`, `is_partial`, `created_at`) VALUES
(1, 1, 12, 200.00, 'mobile_money', 'MM2025051801234', 1, '2025-05-18 07:15:00'),
(2, 1, 12, 150.00, 'mobile_money', 'MM2025052201567', 1, '2025-05-22 12:30:00'),
(3, 1, 12, 100.00, 'auto_debit', 'AD2025060112890', 1, '2025-06-01 06:00:00'),
(4, 3, 12, 50.00, 'mobile_money', 'MM2025060304567', 1, '2025-06-03 11:20:00'),
(5, 10, 12, 200.00, 'auto_debit', 'payment', 0, '2025-06-07 18:02:21'),
(6, 8, 12, 800.00, 'bank_transfer', 'payment', 0, '2025-06-07 18:05:01'),
(7, 3, 12, 700.00, 'bank_transfer', 'payment', 0, '2025-06-07 18:06:07'),
(8, 12, 2, 200.00, 'auto_debit', 'payment', 0, '2025-06-07 18:30:35'),
(9, 11, 12, 150.00, '', 'payment', 0, '2025-06-07 18:36:03');

--
-- Triggers `repayments`
--
DELIMITER $$
CREATE TRIGGER `update_loan_amount_paid` AFTER INSERT ON `repayments` FOR EACH ROW BEGIN
    UPDATE loans 
    SET amount_paid = COALESCE((
        SELECT SUM(amount) 
        FROM repayments 
        WHERE loan_id = NEW.loan_id
    ), 0)
    WHERE id = NEW.loan_id;
    
    -- Update loan status to 'repaid' if fully paid
    UPDATE loans 
    SET status = 'repaid'
    WHERE id = NEW.loan_id 
    AND amount_paid >= amount 
    AND status = 'approved';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `university_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `fnb_account_number` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login_device` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `university_id`, `full_name`, `fnb_account_number`, `password_hash`, `is_verified`, `created_at`, `last_login_device`) VALUES
(1, 'fbda20-046', 8, 'Thabang Olebogeng', '6767676767', 'thabang123', 1, '2025-06-06 09:46:38', NULL),
(2, 'lim21-087', 20, 'Peo Rose Toistere', '6767676767', '$2y$10$ZwCfDrcrOEb6BLLP7szwo.bX9UtMxT3O2iVel7XTj2ggI6g.H6uCe', 1, '2025-06-07 02:00:47', NULL),
(3, 'abm22-078', 12, 'Mogaisi Sophie', '56353535333', '$2y$10$tvCDon15TpdUDopxLfXL2..obuUa.HlVHXz.nr762OYg34CTwW78e', 1, '2025-06-07 14:39:43', NULL),
(4, 'ub21-1234', 1, 'Keabetswe Mokoena', '1234567890', 'password123', 1, '2025-06-01 06:30:00', NULL),
(5, 'biust22-567', 2, 'Thabo Sekgoma', '2345678901', 'student456', 1, '2025-06-02 07:15:00', NULL),
(6, 'buan23-890', 3, 'Neo Pilane', '3456789012', 'neo2023', 1, '2025-06-03 08:45:00', NULL),
(7, 'bou24-345', 4, 'Mpho Kgosana', '4567890123', 'mpho789', 1, '2025-06-04 12:20:00', NULL),
(8, 'botho25-678', 5, 'Lesego Mthimkhulu', '5678901234', 'lesego2025', 1, '2025-06-05 09:30:00', NULL),
(9, 'baisago23-901', 6, 'Onkabetse Tau', '6789012345', 'onka901', 1, '2025-06-06 11:45:00', NULL),
(10, 'limkok24-234', 7, 'Boipelo Segwai', '7890123456', 'boipelo234', 1, '2025-06-01 14:00:00', NULL),
(18, 'biust18-0007', 2, 'matheo pheto', '542134680-', '$2y$10$BdhI2Bmy.6jqROuwhRvJY.ICXuJl.exC8vFiwXRH4D3PpAOPZJYf.', 1, '2025-06-07 18:23:40', NULL),
(19, 'botho22-098', 5, 'Thomas Kebine', '654223222332', '$2y$10$5VY62kAg8VnOMcpYsQBDXeXaE2TGuFLbwepQ/ahb.ZXh3YPq9WlVu', 1, '2025-06-07 20:34:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_course_completion`
--

CREATE TABLE `student_course_completion` (
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `points_earned` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_course_completion`
--

INSERT INTO `student_course_completion` (`student_id`, `course_id`, `completed_at`, `points_earned`) VALUES
(1, 1, '2025-06-01 08:30:00', 150),
(1, 2, '2025-06-02 12:45:00', 200),
(2, 1, '2025-06-03 07:20:00', 150),
(2, 3, '2025-06-04 14:10:00', 120),
(3, 4, '2025-06-05 09:35:00', 250),
(3, 6, '2025-06-06 11:20:00', 160),
(6, 1, '2025-05-28 13:40:00', 150),
(6, 5, '2025-05-30 08:15:00', 300),
(6, 7, '2025-06-01 10:50:00', 140),
(18, 3, '2025-06-07 18:26:06', 120);

-- --------------------------------------------------------

--
-- Table structure for table `student_financial_profiles`
--

CREATE TABLE `student_financial_profiles` (
  `student_id` int(11) NOT NULL,
  `university_id` int(11) NOT NULL,
  `ai_risk_score` decimal(5,2) DEFAULT 50.00,
  `dynamic_loan_limit` decimal(10,2) DEFAULT 500.00,
  `allowance_day` tinyint(2) DEFAULT NULL,
  `emergency_topup_unlocked` tinyint(1) DEFAULT 0,
  `last_loan_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_financial_profiles`
--

INSERT INTO `student_financial_profiles` (`student_id`, `university_id`, `ai_risk_score`, `dynamic_loan_limit`, `allowance_day`, `emergency_topup_unlocked`, `last_loan_date`) VALUES
(1, 8, 50.00, 500.00, 25, 0, NULL),
(2, 20, 50.00, 500.00, 25, 0, NULL),
(3, 12, 44.15, 650.00, 30, 0, '2025-06-07'),
(4, 3, 60.75, 400.00, 10, 0, NULL),
(5, 4, 55.00, 300.00, 25, 0, '2025-05-30'),
(6, 5, 25.80, 1200.00, 5, 1, '2025-06-01'),
(7, 6, 40.30, 800.00, 12, 1, '2025-05-15'),
(8, 7, 70.20, 250.00, 28, 0, NULL),
(18, 2, 47.20, 575.00, 25, 0, '2025-06-07'),
(19, 5, 51.00, 500.00, 25, 0, '2025-06-07');

-- --------------------------------------------------------

--
-- Table structure for table `super_admin`
--

CREATE TABLE `super_admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL CHECK (`username` = 'admin'),
  `password_hash` varchar(255) NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_action` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `super_admin`
--

INSERT INTO `super_admin` (`id`, `username`, `password_hash`, `last_login`, `last_action`, `created_at`) VALUES
(1, 'admin', '$2y$10$WQMC0jne0TPSlurek4cGdu.ARQJo/ip2tHZPbtBRQdTBdlSbx7dUG', '2025-06-07 23:04:00', '2025-06-07 21:04:00', '2025-06-07 19:30:46');

-- --------------------------------------------------------

--
-- Table structure for table `system_configurations`
--

CREATE TABLE `system_configurations` (
  `id` int(11) NOT NULL,
  `config_key` varchar(50) NOT NULL,
  `config_value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `universities`
--

CREATE TABLE `universities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(100) NOT NULL,
  `allowance_schedule` enum('monthly','semester','quarter') DEFAULT 'monthly',
  `is_dtef_partner` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `universities`
--

INSERT INTO `universities` (`id`, `name`, `location`, `allowance_schedule`, `is_dtef_partner`) VALUES
(1, 'University of Botswana', 'Gaborone', 'semester', 1),
(2, 'Botswana International University of Science and Technology (BIUST)', 'Palapye', 'semester', 1),
(3, 'Botswana University of Agriculture and Natural Resources', 'Gaborone', 'semester', 1),
(4, 'Botswana Open University', 'Gaborone', 'semester', 1),
(5, 'Botho University', 'Gaborone', 'semester', 1),
(6, 'Ba Isago University', 'Gaborone', 'semester', 1),
(7, 'Limkokwing University of Creative Technology', 'Gaborone', 'semester', 1),
(8, 'Gaborone University College of Law and Professional Studies', 'Gaborone', 'semester', 1),
(9, 'Botswana Accountancy College', 'Gaborone', 'semester', 1),
(10, 'Boitekanelo College', 'Tlokweng', 'semester', 1),
(11, 'New Era College of Arts, Science and Technology', 'Gaborone', 'semester', 1),
(12, 'ABM University College', 'Gaborone', 'semester', 1),
(13, 'Botswana College of Agriculture', 'Gaborone', 'semester', 1),
(14, 'Francistown College of Technical and Vocational Education', 'Francistown', 'semester', 1),
(15, 'Gaborone Technical College', 'Gaborone', 'semester', 1),
(16, 'London College of International Business Studies', 'Gaborone', 'semester', 1),
(17, 'Imperial School of Business and Science', 'Gaborone', 'semester', 1),
(18, 'Botswana Harvard AIDS Institute Partnership', 'Gaborone', 'semester', 1),
(19, 'Institute of Development Management', 'Gaborone', 'semester', 1),
(20, 'Botswana Institute of Chartered Accountants', 'Gaborone', 'semester', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academy_courses`
--
ALTER TABLE `academy_courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `university_id` (`university_id`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `allowance_verification_logs`
--
ALTER TABLE `allowance_verification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `university_id` (`university_id`);

--
-- Indexes for table `gamification_points`
--
ALTER TABLE `gamification_points`
  ADD PRIMARY KEY (`student_id`),
  ADD KEY `university_id` (`university_id`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_university_loans` (`university_id`,`status`);

--
-- Indexes for table `repayments`
--
ALTER TABLE `repayments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `university_id` (`university_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `idx_university_students` (`university_id`,`is_verified`);

--
-- Indexes for table `student_course_completion`
--
ALTER TABLE `student_course_completion`
  ADD PRIMARY KEY (`student_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `student_financial_profiles`
--
ALTER TABLE `student_financial_profiles`
  ADD PRIMARY KEY (`student_id`),
  ADD KEY `university_id` (`university_id`),
  ADD KEY `idx_financial_profile` (`ai_risk_score`,`dynamic_loan_limit`);

--
-- Indexes for table `super_admin`
--
ALTER TABLE `super_admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `system_configurations`
--
ALTER TABLE `system_configurations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

--
-- Indexes for table `universities`
--
ALTER TABLE `universities`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academy_courses`
--
ALTER TABLE `academy_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `allowance_verification_logs`
--
ALTER TABLE `allowance_verification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `repayments`
--
ALTER TABLE `repayments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `super_admin`
--
ALTER TABLE `super_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_configurations`
--
ALTER TABLE `system_configurations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `universities`
--
ALTER TABLE `universities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academy_courses`
--
ALTER TABLE `academy_courses`
  ADD CONSTRAINT `academy_courses_ibfk_1` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`);

--
-- Constraints for table `allowance_verification_logs`
--
ALTER TABLE `allowance_verification_logs`
  ADD CONSTRAINT `allowance_verification_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `allowance_verification_logs_ibfk_2` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`);

--
-- Constraints for table `gamification_points`
--
ALTER TABLE `gamification_points`
  ADD CONSTRAINT `gamification_points_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gamification_points_ibfk_2` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`);

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `loans_ibfk_2` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`);

--
-- Constraints for table `repayments`
--
ALTER TABLE `repayments`
  ADD CONSTRAINT `repayments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`),
  ADD CONSTRAINT `repayments_ibfk_2` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`);

--
-- Constraints for table `student_course_completion`
--
ALTER TABLE `student_course_completion`
  ADD CONSTRAINT `student_course_completion_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `student_course_completion_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `academy_courses` (`id`);

--
-- Constraints for table `student_financial_profiles`
--
ALTER TABLE `student_financial_profiles`
  ADD CONSTRAINT `student_financial_profiles_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_financial_profiles_ibfk_2` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

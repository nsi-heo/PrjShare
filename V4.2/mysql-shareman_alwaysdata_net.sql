-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: mysql-shareman.alwaysdata.net
-- Generation Time: Sep 30, 2025 at 06:41 PM
-- Server version: 10.11.13-MariaDB
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `shareman_db`
--
CREATE DATABASE IF NOT EXISTS `shareman_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `shareman_db`;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_by` varchar(50) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `modified_by` int(11) DEFAULT NULL,
  `modified_at` timestamp NULL DEFAULT NULL,
  `expense_mode` enum('classique','sejour') DEFAULT 'classique'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `group_id`, `title`, `amount`, `paid_by`, `created_by`, `created_at`, `modified_by`, `modified_at`, `expense_mode`) VALUES
(5, 3, 'Resto', 60.00, 'manu', 5, '2025-09-08 08:05:28', NULL, NULL, 'classique'),
(6, 3, 'Courses', 130.00, 'biz', 5, '2025-09-08 20:11:34', NULL, NULL, 'classique'),
(8, 4, 'Bouffe ', 52.00, 'manu', 10, '2025-09-20 19:04:12', NULL, NULL, 'classique'),
(9, 4, 'Grosse bouffe', 350.00, 'manu', 5, '2025-09-26 08:45:25', NULL, NULL, 'sejour'),
(10, 4, 'Concert', 160.00, 's.bizet', 5, '2025-09-26 11:01:11', NULL, NULL, 'sejour'),
(11, 5, 'Logement', 2100.00, 'biz', 10, '2025-09-26 12:31:20', 10, '2025-09-27 08:11:06', 'sejour'),
(12, 5, 'Resto', 100.00, 'manu', 10, '2025-09-27 17:26:00', NULL, NULL, 'classique');

-- --------------------------------------------------------

--
-- Table structure for table `expense_participants`
--

CREATE TABLE `expense_participants` (
  `id` int(11) NOT NULL,
  `expense_id` int(11) DEFAULT NULL,
  `member_name` varchar(50) NOT NULL,
  `share` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expense_participants`
--

INSERT INTO `expense_participants` (`id`, `expense_id`, `member_name`, `share`) VALUES
(21, 5, 'biz', 20.00),
(22, 5, 'manu', 20.00),
(23, 5, 'toto', 20.00),
(24, 6, 'biz', 43.33),
(25, 6, 'manu', 43.33),
(26, 6, 'toto', 43.33),
(34, 8, 'David', 7.43),
(35, 8, 'Guillaume', 7.43),
(36, 8, 'Jc', 7.43),
(37, 8, 'manu', 7.43),
(38, 8, 'Nike', 7.43),
(39, 8, 'Raph', 7.43),
(40, 8, 's.bizet', 7.43),
(55, 9, 'David', 50.00),
(56, 9, 'Guillaume', 50.00),
(57, 9, 'Jc', 50.00),
(58, 9, 'manu', 50.00),
(59, 9, 'Nike', 50.00),
(60, 9, 'Raph', 50.00),
(61, 9, 's.bizet', 50.00),
(62, 10, 'David', 22.86),
(63, 10, 'Guillaume', 22.86),
(64, 10, 'Jc', 22.86),
(65, 10, 'manu', 22.86),
(66, 10, 'Nike', 22.86),
(67, 10, 'Raph', 22.86),
(68, 10, 's.bizet', 22.86),
(72, 11, 'biz', 525.00),
(73, 11, 'dave', 525.00),
(74, 11, 'manu', 525.00),
(75, 11, 'Oliv', 525.00),
(76, 12, 'biz', 50.00),
(77, 12, 'manu', 50.00);

-- --------------------------------------------------------

--
-- Table structure for table `groups_table`
--

CREATE TABLE `groups_table` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `stay_mode_enabled` tinyint(1) DEFAULT 0,
  `stay_start_date` date DEFAULT NULL,
  `stay_end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `groups_table`
--

INSERT INTO `groups_table` (`id`, `name`, `description`, `created_by`, `created_at`, `stay_mode_enabled`, `stay_start_date`, `stay_end_date`) VALUES
(3, 'JoeBiz', '', 1, '2025-09-07 21:12:19', 0, NULL, NULL),
(4, 'La dromoise', '', 10, '2025-09-20 19:00:46', 1, '2025-09-26', '2025-09-30'),
(5, 'Ski 2026', 'Vacances ski février 2026', 10, '2025-09-26 12:26:28', 1, '2026-02-21', '2026-02-28');

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `member_name` varchar(50) NOT NULL,
  `status` enum('pending','active') DEFAULT 'pending',
  `joined_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`id`, `group_id`, `user_id`, `member_name`, `status`, `joined_at`) VALUES
(10, 3, 6, 'biz', 'active', '2025-09-07 21:12:24'),
(11, 3, 5, 'manu', 'active', '2025-09-07 21:12:32'),
(12, 3, 7, 'toto', 'active', '2025-09-08 07:07:56'),
(14, 4, 5, 'manu', 'active', '2025-09-20 19:01:07'),
(15, 4, 8, 's.bizet', 'active', '2025-09-20 19:01:20'),
(16, 4, NULL, 'David', 'active', '2025-09-20 19:01:40'),
(17, 4, NULL, 'Nike', 'active', '2025-09-20 19:02:11'),
(18, 4, NULL, 'Jc', 'active', '2025-09-20 19:02:20'),
(19, 4, NULL, 'Guillaume', 'active', '2025-09-20 19:02:39'),
(20, 4, NULL, 'Raph', 'active', '2025-09-20 19:03:14'),
(21, 5, 6, 'biz', 'active', '2025-09-26 12:30:16'),
(22, 5, 5, 'manu', 'active', '2025-09-26 12:30:25'),
(23, 5, 12, 'Oliv', 'active', '2025-09-26 12:30:35'),
(24, 5, NULL, 'dave', 'active', '2025-09-27 08:10:38');

-- --------------------------------------------------------

--
-- Table structure for table `member_stay_periods`
--

CREATE TABLE `member_stay_periods` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `member_name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `coefficient` decimal(5,2) DEFAULT 1.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member_stay_periods`
--

INSERT INTO `member_stay_periods` (`id`, `group_id`, `member_name`, `start_date`, `end_date`, `coefficient`, `created_at`, `updated_at`) VALUES
(1, 4, 'David', '2025-09-26', '2025-09-30', 2.00, '2025-09-26 08:43:10', '2025-09-26 11:00:19'),
(2, 4, 'Guillaume', '2025-09-28', '2025-09-30', 1.00, '2025-09-26 08:43:10', '2025-09-26 08:43:46'),
(3, 4, 'Jc', '2025-09-26', '2025-09-30', 1.00, '2025-09-26 08:43:10', '2025-09-26 08:43:10'),
(4, 4, 'manu', '2025-09-26', '2025-09-30', 1.00, '2025-09-26 08:43:10', '2025-09-26 08:43:10'),
(5, 4, 'Nike', '2025-09-26', '2025-09-30', 1.00, '2025-09-26 08:43:10', '2025-09-26 08:43:10'),
(6, 4, 'Raph', '2025-09-26', '2025-09-30', 1.00, '2025-09-26 08:43:10', '2025-09-26 08:43:10'),
(7, 4, 's.bizet', '2025-09-29', '2025-09-30', 1.00, '2025-09-26 08:43:10', '2025-09-26 08:43:58'),
(11, 5, 'biz', '2026-02-22', '2026-02-28', 1.00, '2025-09-26 12:30:16', '2025-09-26 12:31:51'),
(12, 5, 'manu', '2026-02-22', '2026-02-27', 1.00, '2025-09-26 12:30:25', '2025-09-26 12:32:00'),
(13, 5, 'Oliv', '2026-02-21', '2026-02-28', 1.00, '2025-09-26 12:30:35', '2025-09-26 12:30:35'),
(17, 5, 'dave', '2026-02-25', '2026-02-28', 1.00, '2025-09-27 08:10:38', '2025-09-27 17:25:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('visiteur','utilisateur','administrateur') DEFAULT 'visiteur',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `status`, `created_at`) VALUES
(1, 'admin', 'admin@shareman.com', '$2a$12$A8sixry0m0/fS1zWre9k/Om1vbyg51J8uoo/8G1AKB9Xqjnpz2TzO', 'administrateur', '2025-09-07 20:00:49'),
(5, 'manu', 'manucado@free.fr', '$2y$10$BwRgySzS6ugg6ibEshpi7.kjGoNKh4dycORo208xIhFzL8TrJDlFG', 'utilisateur', '2025-09-07 20:47:08'),
(6, 'biz', 't@t.fr', '$2y$10$EAd3syHhazdRiAVZs3k7S.Shf1flojyoiiBKoqJ0qdvIGfu.hgRsu', 'administrateur', '2025-09-07 20:49:21'),
(7, 'toto', 'toto@t.fr', '$2y$10$71CEL5YrbudXxI1V3SJnteB7uxFD8anPo.a8u24mCd2UFIUfSTx2q', 'utilisateur', '2025-09-07 20:56:34'),
(8, 's.bizet', 'pppuuubbb@yahoo.fr', '$2y$10$pn7hT7niHbfLeDVnYZRuZeOMnKHHY8OU99Dju5mu9eBjHcwVQtExu', 'administrateur', '2025-09-08 08:17:11'),
(9, 'sara', 't@t.fr', '$2y$10$chj7Psys1sQdvPu31Qo9JOYRbAehxJVdFpa8a9/qt2Gr2faVqiTS2', 'utilisateur', '2025-09-08 20:15:39'),
(10, 'manolo', 'manu.gatineau@l.fr', '$2y$10$udjhafmZLJZaiDKE55zG5OqSUa0QZgEGl2MiYY4lQnUEM7Dj6hUQy', 'administrateur', '2025-09-09 15:13:14'),
(12, 'Oliv', 'tt@tt.fr', '$2y$10$n2l2IHtL/B6IkbY9hnx87uZlsiLcpJPF.Pdzzd.sLXb.dK5c480my', 'utilisateur', '2025-09-23 15:48:03'),
(19, 'visiteurmanu', 'manucado@free.fr', '$2y$10$ujwfVZaAYvN52P7BiMBVl.EtccJgWynMwu3z3VHjX34UfX8M7t/US', 'utilisateur', '2025-09-27 08:05:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_expenses_modified_by` (`modified_by`),
  ADD KEY `idx_expenses_mode` (`group_id`,`expense_mode`);

--
-- Indexes for table `expense_participants`
--
ALTER TABLE `expense_participants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expense_id` (`expense_id`);

--
-- Indexes for table `groups_table`
--
ALTER TABLE `groups_table`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_stay_dates` (`stay_start_date`,`stay_end_date`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `member_stay_periods`
--
ALTER TABLE `member_stay_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member_stay` (`group_id`,`member_name`),
  ADD KEY `idx_group_member_stay` (`group_id`,`member_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `expense_participants`
--
ALTER TABLE `expense_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `groups_table`
--
ALTER TABLE `groups_table`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `member_stay_periods`
--
ALTER TABLE `member_stay_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups_table` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_expenses_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expense_participants`
--
ALTER TABLE `expense_participants`
  ADD CONSTRAINT `expense_participants_ibfk_1` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `groups_table`
--
ALTER TABLE `groups_table`
  ADD CONSTRAINT `groups_table_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups_table` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `member_stay_periods`
--
ALTER TABLE `member_stay_periods`
  ADD CONSTRAINT `member_stay_periods_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups_table` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

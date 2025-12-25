-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 25, 2025 at 07:10 AM
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
-- Database: `ecowaste_db`
--
CREATE DATABASE IF NOT EXISTS `ecowaste_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `ecowaste_db`;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

DROP TABLE IF EXISTS `drivers`;
CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `email`, `password`, `created_at`) VALUES
(5, 'Harshad', 'harshad@gmail.com', '$2y$10$M7EsYU9sD7HSJMTBnJKCE.dI.tBCgJPGb5HBDi5g5a1FSIPBQRYre', '2025-10-12 13:38:38'),
(6, 'aman', 'aman@gmail.com', '$2y$10$k0u1WZO86iIJnBbuKfOrq.1vYWiqyaQShL3ohXVJ1/E3rnXEGNXDq', '2025-10-12 14:07:18');

-- --------------------------------------------------------

--
-- Table structure for table `pickups`
--

DROP TABLE IF EXISTS `pickups`;
CREATE TABLE `pickups` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `area` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `pickup_date` date NOT NULL,
  `time_slot` varchar(50) NOT NULL,
  `waste_type` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Scheduled',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pickups`
--

INSERT INTO `pickups` (`id`, `user_id`, `driver_id`, `area`, `city`, `pickup_date`, `time_slot`, `waste_type`, `status`, `requested_at`, `updated_at`) VALUES
(12, 6, 5, 'Waghodia', 'Vadodara', '2025-10-13', 'Morning (9am-12pm)', 'Organic Waste', 'Completed', '2025-10-12 13:37:01', '2025-10-12 13:41:15'),
(13, 8, 6, 'vadodara', 'Vadodara', '2025-10-13', 'Morning (9am-12pm)', 'Organic Waste', 'Completed', '2025-10-12 14:06:06', '2025-10-12 14:09:22'),
(14, 9, 5, 'Waghodia', 'Vadodara', '2025-10-14', 'Morning (9am-12pm)', 'Recyclables', 'Assigned', '2025-10-13 05:13:04', '2025-10-13 05:14:20');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `is_admin`, `created_at`) VALUES
(1, 'Admin', 'admin@gmail.com', '$2y$10$IT6IkWhPDCiwtPY3hak68Otypu/FNpUXITZc7m1NVw.uBFQ6HEqT.', 1, '2025-07-26 05:01:21'),
(6, 'Ajay', 'ajay@gmail.com', '$2y$10$fiS6UEiRv4y/ksx4vCj3z.o972GwBjcHK2MFw.saM/r64.RhF0u/e', 0, '2025-10-12 13:35:52'),
(8, 'pankaj', 'pankaj@gmail.com', '$2y$10$yMuxw7TYVcf4jfD6OHWHf.Vzfq8O.Pcf26Yrt4Q7n9bxjdstHcxwe', 0, '2025-10-12 14:05:15'),
(9, 'sumit', 'sumit@gmail.com', '$2y$10$R6UGGBPknzX5rphR/sYtk./CPyxhh0fD8zOz4tjBBYNqrg9ukqYCS', 0, '2025-10-13 05:12:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `pickups`
--
ALTER TABLE `pickups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pickups`
--
ALTER TABLE `pickups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `pickups`
--
ALTER TABLE `pickups`
  ADD CONSTRAINT `pickups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

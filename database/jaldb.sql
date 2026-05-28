-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 27, 2026 at 09:27 PM
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
-- Database: `jaldb`
--

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `round` int(11) NOT NULL,
  `participant_a_id` int(11) DEFAULT NULL,
  `participant_b_id` int(11) DEFAULT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `status` enum('pending','ongoing','finished') NOT NULL DEFAULT 'pending',
  `match_order` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `matches`
--

INSERT INTO `matches` (`id`, `tournament_id`, `round`, `participant_a_id`, `participant_b_id`, `winner_id`, `status`, `match_order`) VALUES
(4, 3, 1, 4, 6, NULL, 'ongoing', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `link` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `is_read`, `created_at`, `link`) VALUES
(1, 1, 'Proposal turnamen baru \'Badminton BIB\' menunggu review Anda.', 1, '2026-05-26 15:40:12', NULL),
(2, 3, 'Proposal turnamen \'Badminton BIB\' Anda telah disetujui! Turnamen kini berstatus Open.', 1, '2026-05-26 16:05:02', NULL),
(3, 2, 'Pendaftaranmu di turnamen \'Badminton BIB\' telah disetujui oleh Organizer!', 0, '2026-05-26 16:44:30', NULL),
(4, 1, 'Proposal turnamen baru \'ML - JTE\' menunggu review Anda.', 1, '2026-05-26 16:47:06', NULL),
(5, 3, 'Proposal turnamen \'ML - JTE\' Anda telah disetujui! Turnamen kini berstatus Open.', 1, '2026-05-26 16:47:24', NULL),
(6, 1, 'Proposal turnamen baru \'tes\' menunggu review Anda.', 1, '2026-05-26 16:49:31', NULL),
(7, 2, 'Proposal turnamen \'tes\' Anda telah disetujui! Turnamen kini berstatus Open.', 0, '2026-05-26 16:49:43', NULL),
(8, 2, 'Peserta baru \"member1\" mendaftar di turnamen \"tes\".', 0, '2026-05-26 16:54:24', NULL),
(9, 3, 'Peserta baru \"organizer1\" mendaftar di turnamen \"ML - JTE\".', 1, '2026-05-26 16:55:59', NULL),
(10, 2, 'Pendaftaranmu di turnamen \'ML - JTE\' telah disetujui oleh Organizer!', 0, '2026-05-26 16:57:38', NULL),
(11, 3, 'Pendaftaranmu di turnamen \'tes\' telah disetujui oleh Organizer!', 0, '2026-05-26 16:58:57', NULL),
(12, 2, 'Peserta baru \"member2\" mendaftar di turnamen \"tes\".', 0, '2026-05-27 19:09:12', NULL),
(13, 4, 'Pendaftaranmu di turnamen \'tes\' telah disetujui oleh Organizer!', 0, '2026-05-27 19:09:23', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `participants`
--

CREATE TABLE `participants` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `participants`
--

INSERT INTO `participants` (`id`, `tournament_id`, `user_id`, `display_name`, `status`, `created_at`) VALUES
(1, 1, 2, 'iman', 'approved', '2026-05-26 16:43:28'),
(4, 3, 3, 'ndee', 'approved', '2026-05-26 16:54:24'),
(5, 2, 2, 'iman', 'approved', '2026-05-26 16:55:59'),
(6, 3, 4, 'iman', 'approved', '2026-05-27 19:09:12');

-- --------------------------------------------------------

--
-- Table structure for table `point_scores`
--

CREATE TABLE `point_scores` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `update_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sets`
--

CREATE TABLE `sets` (
  `id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `set_number` int(11) NOT NULL,
  `score_a` int(11) NOT NULL DEFAULT 0,
  `score_b` int(11) NOT NULL DEFAULT 0,
  `winner_id` int(11) DEFAULT NULL,
  `status` enum('ongoing','finished') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournaments`
--

CREATE TABLE `tournaments` (
  `id` int(11) NOT NULL,
  `organizer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `mode` enum('bracket','point') NOT NULL,
  `participant_type` enum('indinidual','team') NOT NULL,
  `sets_per_match` tinyint(4) DEFAULT NULL,
  `status` enum('pending','open','ongoing','finished','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tournaments`
--

INSERT INTO `tournaments` (`id`, `organizer_id`, `name`, `description`, `mode`, `participant_type`, `sets_per_match`, `status`, `created_at`) VALUES
(1, 3, 'Badminton BIB', 'pertandingan mempertaruhkan jati diri', 'bracket', 'team', 3, 'open', '2026-05-26 15:40:12'),
(2, 3, 'ML - JTE', 'pertarungan tim mempertaruhkan kelanjutan kehidupan', 'bracket', 'team', 1, 'open', '2026-05-26 16:47:06'),
(3, 2, 'tes', 'tes', 'bracket', 'team', 1, 'ongoing', '2026-05-26 16:49:31');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','member') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'admin', 'admin@bib.com', '$2y$10$zNCIIi4dAdzgJoi4OPQpkuWQTsvYmRPLZQ8yxVoSfLPZno.O5vn4W', 'admin', '2026-05-25 12:28:58'),
(2, 'organizer1', 'organizer@bib.com', '$2y$10$7Vz33MdDREpSTJU.xSGmz.E.N/4PvOuH5rWqvIvwMo2J4DSoOeBPS', 'member', '2026-05-25 12:28:58'),
(3, 'member1', 'member@bib.com', '$2y$10$j/VWw137se3OMGzmyqrwC.Ui0JCE/7OhrPRt/oHOrHv6SaXHUsY0a', 'member', '2026-05-26 15:37:01'),
(4, 'member2', 'member2@bib.com', '$2y$10$8dhy8ETiA.JaBsEdCw3tXuwEHGvECc9s0qon6LqajEGKC3CNgqdxq', 'member', '2026-05-25 12:28:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tournament_id` (`tournament_id`),
  ADD KEY `participant_a_id` (`participant_a_id`),
  ADD KEY `participant_b_id` (`participant_b_id`),
  ADD KEY `winner_id` (`winner_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `participants`
--
ALTER TABLE `participants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tournament_id` (`tournament_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `point_scores`
--
ALTER TABLE `point_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id` (`id`,`tournament_id`),
  ADD KEY `tournament_id` (`tournament_id`),
  ADD KEY `participant_id` (`participant_id`);

--
-- Indexes for table `sets`
--
ALTER TABLE `sets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `match_id` (`match_id`),
  ADD KEY `winner_id` (`winner_id`);

--
-- Indexes for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizer_id` (`organizer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usr` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `participants`
--
ALTER TABLE `participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `point_scores`
--
ALTER TABLE `point_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sets`
--
ALTER TABLE `sets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournaments`
--
ALTER TABLE `tournaments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `fk_matches_id` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_matches_participant_a` FOREIGN KEY (`participant_a_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_matches_participant_b` FOREIGN KEY (`participant_b_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_matches_winner` FOREIGN KEY (`winner_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `participants`
--
ALTER TABLE `participants`
  ADD CONSTRAINT `fk _participants_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `point_scores`
--
ALTER TABLE `point_scores`
  ADD CONSTRAINT `point_scores_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `point_scores_ibfk_2` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sets`
--
ALTER TABLE `sets`
  ADD CONSTRAINT `fk_sets_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sets_winner` FOREIGN KEY (`winner_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD CONSTRAINT `fk_tournament_orginazer` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

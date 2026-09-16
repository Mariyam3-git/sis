-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 25, 2026 at 02:54 PM
-- Server version: 8.0.44
-- PHP Version: 8.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `comium_gm`
--

-- --------------------------------------------------------

--
-- Table structure for table `agent_shifts`
--

CREATE TABLE `agent_shifts` (
  `id` int NOT NULL,
  `agent_id` int DEFAULT NULL,
  `outlet_id` int DEFAULT NULL,
  `shift_date` date DEFAULT NULL,
  `shift_type` enum('Morning','Afternoon','Night') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `login_time` timestamp NULL DEFAULT NULL,
  `logout_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agent_shifts`
--

INSERT INTO `agent_shifts` (`id`, `agent_id`, `outlet_id`, `shift_date`, `shift_type`, `login_time`, `logout_time`) VALUES
(1, 2, 1, '2026-02-12', 'Morning', '2026-02-12 22:18:58', '2026-02-12 22:20:30'),
(2, 2, 1, '2026-02-13', 'Morning', '2026-02-13 18:14:10', '2026-02-13 18:16:06'),
(3, 2, 8, '2026-02-16', 'Morning', '2026-02-16 16:31:26', '2026-02-16 17:35:45'),
(4, 2, 8, '2026-02-17', 'Morning', '2026-02-17 14:35:49', '2026-02-17 14:36:28');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action_type`, `record_id`, `timestamp`, `ip_address`) VALUES
(1, 1, 'LOGOUT', 1, '2026-02-12 21:54:19', NULL),
(2, 1, 'LOGIN', 1, '2026-02-12 22:16:03', NULL),
(3, 1, 'LOGOUT', 1, '2026-02-12 22:16:23', NULL),
(4, 1, 'LOGIN', 1, '2026-02-12 22:17:41', NULL),
(5, 1, 'ADD_AGENT', 2, '2026-02-12 22:18:29', NULL),
(6, 1, 'LOGOUT', 1, '2026-02-12 22:18:37', NULL),
(7, 2, 'LOGIN', 2, '2026-02-12 22:18:58', NULL),
(8, 2, 'LOGOUT', 2, '2026-02-12 22:20:30', NULL),
(9, 1, 'LOGIN', 1, '2026-02-12 22:20:41', NULL),
(10, 1, 'LOGIN', 1, '2026-02-13 13:48:54', NULL),
(11, 1, 'UPDATE_SERVICE', 4, '2026-02-13 13:55:33', NULL),
(12, 1, 'UPDATE_SERVICE', 4, '2026-02-13 13:56:17', NULL),
(13, 1, 'ADD_SERVICE', 5, '2026-02-13 13:57:41', NULL),
(14, 1, 'ADD_SERVICE', 6, '2026-02-13 13:57:59', NULL),
(15, 1, 'DELETE_SERVICE', 4, '2026-02-13 13:58:23', NULL),
(16, 1, 'DELETE_SERVICE', 2, '2026-02-13 13:58:27', NULL),
(17, 1, 'DELETE_SERVICE', 1, '2026-02-13 13:58:31', NULL),
(18, 1, 'DELETE_SERVICE', 3, '2026-02-13 13:58:38', NULL),
(19, 1, 'LOGOUT', 1, '2026-02-13 13:59:11', NULL),
(20, 2, 'LOGIN', 2, '2026-02-13 13:59:34', NULL),
(21, 2, 'LOGOUT', 2, '2026-02-13 14:06:31', NULL),
(22, 3, 'LOGIN', 3, '2026-02-13 14:06:49', NULL),
(23, 1, 'LOGIN', 1, '2026-02-13 14:11:08', NULL),
(24, 1, 'UPDATE_SUPERVISOR', 3, '2026-02-13 14:12:28', NULL),
(25, 1, 'LOGOUT', 1, '2026-02-13 14:12:32', NULL),
(26, 3, 'LOGIN', 3, '2026-02-13 14:12:56', NULL),
(27, 3, 'LOGOUT', 3, '2026-02-13 14:16:45', NULL),
(28, 1, 'LOGIN', 1, '2026-02-13 14:17:02', NULL),
(29, 1, 'DELETE_OUTLET', 2, '2026-02-13 14:17:13', NULL),
(30, 1, 'DELETE_OUTLET', 5, '2026-02-13 14:17:19', NULL),
(31, 1, 'DELETE_OUTLET', 3, '2026-02-13 14:17:24', NULL),
(32, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:17:28', NULL),
(33, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:27:47', NULL),
(34, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:27:58', NULL),
(35, 1, 'ADD_OUTLET', 6, '2026-02-13 14:27:58', NULL),
(36, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:30:50', NULL),
(37, 1, 'ADD_OUTLET', 6, '2026-02-13 14:30:50', NULL),
(38, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:35:53', NULL),
(39, 1, 'ADD_OUTLET', 6, '2026-02-13 14:35:53', NULL),
(40, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:36:36', NULL),
(41, 1, 'ADD_OUTLET', 6, '2026-02-13 14:36:36', NULL),
(42, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:37:02', NULL),
(43, 1, 'ADD_OUTLET', 6, '2026-02-13 14:37:02', NULL),
(44, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:38:02', NULL),
(45, 1, 'ADD_OUTLET', 6, '2026-02-13 14:38:02', NULL),
(46, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:38:32', NULL),
(47, 1, 'ADD_OUTLET', 6, '2026-02-13 14:38:32', NULL),
(48, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:38:47', NULL),
(49, 1, 'ADD_OUTLET', 6, '2026-02-13 14:38:47', NULL),
(50, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:39:10', NULL),
(51, 1, 'ADD_OUTLET', 6, '2026-02-13 14:39:10', NULL),
(52, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:39:46', NULL),
(53, 1, 'ADD_OUTLET', 6, '2026-02-13 14:39:46', NULL),
(54, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:40:07', NULL),
(55, 1, 'ADD_OUTLET', 6, '2026-02-13 14:40:07', NULL),
(56, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:40:29', NULL),
(57, 1, 'ADD_OUTLET', 6, '2026-02-13 14:40:29', NULL),
(58, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:40:57', NULL),
(59, 1, 'ADD_OUTLET', 6, '2026-02-13 14:40:57', NULL),
(60, 1, 'DELETE_OUTLET', 6, '2026-02-13 14:41:16', NULL),
(61, 1, 'ADD_OUTLET', 6, '2026-02-13 14:41:16', NULL),
(62, 1, 'UPDATE_OUTLET', 20, '2026-02-13 14:41:30', NULL),
(63, 1, 'UPDATE_OUTLET', 20, '2026-02-13 14:42:17', NULL),
(64, 1, 'UPDATE_OUTLET', 20, '2026-02-13 14:42:56', NULL),
(65, 1, 'UPDATE_OUTLET', 20, '2026-02-13 14:43:36', NULL),
(66, 1, 'ADD_OUTLET', 21, '2026-02-13 14:44:17', NULL),
(67, 1, 'ADD_OUTLET', 22, '2026-02-13 14:44:48', NULL),
(68, 1, 'ADD_OUTLET', 23, '2026-02-13 14:45:14', NULL),
(69, 1, 'ADD_OUTLET', 24, '2026-02-13 14:45:36', NULL),
(70, 1, 'ADD_OUTLET', 25, '2026-02-13 14:46:02', NULL),
(71, 1, 'ADD_OUTLET', 26, '2026-02-13 14:46:28', NULL),
(72, 1, 'ADD_OUTLET', 27, '2026-02-13 14:46:57', NULL),
(73, 1, 'ADD_OUTLET', 28, '2026-02-13 14:47:25', NULL),
(74, 1, 'ADD_OUTLET', 29, '2026-02-13 14:55:34', NULL),
(75, 1, 'DELETE_OUTLET', 7, '2026-02-13 14:56:32', NULL),
(76, 1, 'DELETE_OUTLET', 1, '2026-02-13 14:56:48', NULL),
(77, 1, 'DELETE_OUTLET', 4, '2026-02-13 14:57:10', NULL),
(78, 1, 'UPDATE_SUPERVISOR', 3, '2026-02-13 14:57:41', NULL),
(79, 1, 'UPDATE_AGENT', 2, '2026-02-13 14:58:05', NULL),
(80, 1, 'ADD_PURPOSE', 1, '2026-02-13 14:59:17', NULL),
(81, 1, 'ADD_PURPOSE', 2, '2026-02-13 14:59:35', NULL),
(82, 1, 'ADD_PURPOSE', 3, '2026-02-13 14:59:53', NULL),
(83, 1, 'ADD_PURPOSE', 4, '2026-02-13 15:00:13', NULL),
(84, 1, 'ADD_PURPOSE', 5, '2026-02-13 15:01:14', NULL),
(85, 1, 'ADD_PURPOSE', 6, '2026-02-13 15:01:47', NULL),
(86, 1, 'ADD_PURPOSE', 7, '2026-02-13 15:02:22', NULL),
(87, 1, 'ADD_PURPOSE', 8, '2026-02-13 15:02:34', NULL),
(88, 1, 'LOGOUT', 1, '2026-02-13 15:03:01', NULL),
(89, 2, 'LOGIN', 2, '2026-02-13 15:04:37', NULL),
(90, 2, 'LOGOUT', 2, '2026-02-13 15:05:59', NULL),
(91, 1, 'LOGIN', 1, '2026-02-13 15:06:13', NULL),
(92, 1, 'ADD_SERVICE', 7, '2026-02-13 15:06:38', NULL),
(93, 1, 'LOGOUT', 1, '2026-02-13 15:07:13', NULL),
(94, 2, 'LOGIN', 2, '2026-02-13 15:07:32', NULL),
(95, 2, 'ADD_VISITOR', 1, '2026-02-13 15:07:47', NULL),
(96, 2, 'UPDATE_VISITOR_STATUS', 1, '2026-02-13 15:08:06', NULL),
(97, 2, 'UPDATE_VISITOR_STATUS', 1, '2026-02-13 15:08:22', NULL),
(98, 2, 'LOGOUT', 2, '2026-02-13 15:09:16', NULL),
(99, 3, 'LOGIN', 3, '2026-02-13 15:09:35', NULL),
(100, 3, 'LOGOUT', 3, '2026-02-13 15:11:03', NULL),
(101, 3, 'LOGOUT', 3, '2026-02-13 15:11:03', NULL),
(102, 3, 'LOGOUT', 3, '2026-02-13 15:11:03', NULL),
(103, 1, 'LOGIN', 1, '2026-02-13 15:11:17', NULL),
(104, 1, 'LOGOUT', 1, '2026-02-13 15:37:48', NULL),
(105, 2, 'LOGIN', 2, '2026-02-13 15:38:03', NULL),
(106, 2, 'ADD_VISITOR', 2, '2026-02-13 15:38:38', NULL),
(107, 2, 'LOGOUT', 2, '2026-02-13 15:38:42', NULL),
(108, 1, 'LOGIN', 1, '2026-02-13 15:38:58', NULL),
(109, 1, 'LOGOUT', 1, '2026-02-13 15:45:17', NULL),
(110, 2, 'LOGIN', 2, '2026-02-13 15:45:30', NULL),
(111, 2, 'ADD_VISITOR', 3, '2026-02-13 15:46:16', NULL),
(112, 2, 'UPDATE_VISITOR_STATUS', 2, '2026-02-13 15:46:31', NULL),
(113, 2, 'UPDATE_VISITOR_STATUS', 2, '2026-02-13 15:53:46', NULL),
(114, 2, 'UPDATE_VISITOR_STATUS', 2, '2026-02-13 15:54:53', NULL),
(115, 2, 'UPDATE_VISITOR_STATUS', 2, '2026-02-13 15:55:20', NULL),
(116, 2, 'LOGOUT', 2, '2026-02-13 15:58:18', NULL),
(117, 1, 'LOGIN', 1, '2026-02-13 15:58:33', NULL),
(118, 1, 'LOGOUT', 1, '2026-02-13 16:16:37', NULL),
(119, 1, 'LOGIN', 1, '2026-02-13 16:16:50', NULL),
(120, 1, 'LOGOUT', 1, '2026-02-13 16:16:56', NULL),
(121, 2, 'LOGIN', 2, '2026-02-13 16:17:07', NULL),
(122, 2, 'LOGOUT', 2, '2026-02-13 16:23:41', NULL),
(123, 1, 'LOGIN', 1, '2026-02-13 16:23:52', NULL),
(124, 1, 'UPDATE_SERVICE', 10, '2026-02-13 16:25:59', NULL),
(125, 1, 'LOGOUT', 1, '2026-02-13 16:27:23', NULL),
(126, 2, 'LOGIN', 2, '2026-02-13 16:27:42', NULL),
(127, 2, 'ADD_VISITOR', 4, '2026-02-13 17:00:35', NULL),
(128, 2, 'ADD_VISITOR', 5, '2026-02-13 17:02:03', NULL),
(129, 2, 'UPDATE_VISITOR_STATUS', 5, '2026-02-13 17:03:33', NULL),
(130, 2, 'LOGOUT', 2, '2026-02-13 17:09:56', NULL),
(131, 3, 'LOGIN', 3, '2026-02-13 17:10:13', NULL),
(132, 3, 'LOGOUT', 3, '2026-02-13 17:17:37', NULL),
(133, 1, 'LOGIN', 1, '2026-02-13 17:17:48', NULL),
(134, 1, 'LOGOUT', 1, '2026-02-13 17:25:04', NULL),
(135, 3, 'LOGIN', 3, '2026-02-13 17:25:21', NULL),
(136, 3, 'LOGOUT', 3, '2026-02-13 17:40:34', NULL),
(137, 1, 'LOGIN', 1, '2026-02-13 17:40:46', NULL),
(138, 1, 'UPDATE_GENERAL_SETTINGS', NULL, '2026-02-13 17:55:17', NULL),
(139, 1, 'LOGOUT', 1, '2026-02-13 18:07:16', NULL),
(140, 1, 'LOGIN', 1, '2026-02-13 18:07:42', NULL),
(141, 1, 'LOGOUT', 1, '2026-02-13 18:10:18', NULL),
(142, 3, 'LOGIN', 3, '2026-02-13 18:11:55', NULL),
(143, 3, 'LOGOUT', 3, '2026-02-13 18:13:43', NULL),
(144, 2, 'LOGIN', 2, '2026-02-13 18:14:10', NULL),
(145, 2, 'ADD_VISITOR', 6, '2026-02-13 18:15:11', NULL),
(146, 2, 'UPDATE_VISITOR_STATUS', 6, '2026-02-13 18:15:21', NULL),
(147, 2, 'LOGOUT', 2, '2026-02-13 18:16:06', NULL),
(148, 1, 'LOGIN', 1, '2026-02-13 18:16:21', NULL),
(149, 1, 'ADD_AGENT', 4, '2026-02-13 18:17:14', NULL),
(150, 1, 'DELETE_AGENT', 4, '2026-02-13 18:17:38', NULL),
(151, 1, 'DELETE_AGENT', 4, '2026-02-13 18:18:34', NULL),
(152, 1, 'DELETE_AGENT', 4, '2026-02-13 18:18:54', NULL),
(153, 1, 'DELETE_AGENT', 4, '2026-02-13 18:19:16', NULL),
(154, 1, 'DELETE_AGENT', 4, '2026-02-13 18:19:23', NULL),
(155, 1, 'DELETE_AGENT', 4, '2026-02-13 18:19:39', NULL),
(156, 1, 'DELETE_AGENT', 4, '2026-02-13 18:19:45', NULL),
(157, 1, 'LOGOUT', 1, '2026-02-13 20:43:51', NULL),
(158, 1, 'LOGIN', 1, '2026-02-16 14:42:30', NULL),
(159, 1, 'LOGOUT', 1, '2026-02-16 14:43:08', NULL),
(160, 1, 'LOGIN', 1, '2026-02-16 14:43:39', NULL),
(161, 1, 'LOGOUT', 1, '2026-02-16 14:43:51', NULL),
(162, 2, 'LOGIN', 2, '2026-02-16 14:44:04', NULL),
(163, 2, 'ADD_VISITOR', 7, '2026-02-16 15:06:03', NULL),
(164, 2, 'ADD_VISITOR', 8, '2026-02-16 15:07:36', NULL),
(165, 2, 'UPDATE_VISITOR_STATUS', 8, '2026-02-16 15:07:50', NULL),
(166, 2, 'UPDATE_VISITOR_STATUS', 8, '2026-02-16 15:08:13', NULL),
(167, 2, 'UPDATE_VISITOR_STATUS', 8, '2026-02-16 15:08:25', NULL),
(168, 2, 'ADD_VISITOR', 9, '2026-02-16 15:08:58', NULL),
(169, 2, 'UPDATE_VISITOR_STATUS', 9, '2026-02-16 15:09:07', NULL),
(170, 2, 'UPDATE_VISITOR_STATUS', 9, '2026-02-16 15:19:03', NULL),
(171, 2, 'UPDATE_VISITOR_STATUS', 9, '2026-02-16 15:19:28', NULL),
(172, 2, 'ADD_VISITOR', 10, '2026-02-16 15:20:02', NULL),
(173, 2, 'MARK_PAID', 4, '2026-02-16 15:20:17', NULL),
(174, 2, 'UPDATE_VISITOR_STATUS', 10, '2026-02-16 15:20:23', NULL),
(175, 2, 'ADD_VISITOR', 11, '2026-02-16 15:21:34', NULL),
(176, 2, 'UPDATE_VISITOR_STATUS', 11, '2026-02-16 15:21:47', NULL),
(177, 2, 'LOGOUT', 2, '2026-02-16 15:22:34', NULL),
(178, 3, 'LOGIN', 3, '2026-02-16 15:22:55', NULL),
(179, 3, 'LOGOUT', 3, '2026-02-16 15:25:09', NULL),
(180, 1, 'LOGIN', 1, '2026-02-16 15:25:24', NULL),
(181, 1, 'LOGOUT', 1, '2026-02-16 16:25:36', NULL),
(182, 2, 'LOGIN', 2, '2026-02-16 16:25:49', NULL),
(183, 2, 'LOGOUT', 2, '2026-02-16 16:26:55', NULL),
(184, 1, 'LOGIN', 1, '2026-02-16 16:27:14', NULL),
(185, 1, 'DELETE_SERVICE', 10, '2026-02-16 16:28:08', NULL),
(186, 1, 'DELETE_SERVICE', 9, '2026-02-16 16:28:18', NULL),
(187, 1, 'DELETE_SERVICE', 7, '2026-02-16 16:28:40', NULL),
(188, 1, 'LOGOUT', 1, '2026-02-16 16:30:04', NULL),
(189, 2, 'LOGIN', 2, '2026-02-16 16:30:22', NULL),
(190, 2, 'LOGOUT', 2, '2026-02-16 16:30:51', NULL),
(191, 2, 'LOGIN', 2, '2026-02-16 16:31:26', NULL),
(192, 2, 'ADD_VISITOR', 12, '2026-02-16 16:31:38', NULL),
(193, 2, 'ADD_VISITOR', 13, '2026-02-16 17:21:48', NULL),
(194, 2, 'LOGOUT', 2, '2026-02-16 17:35:45', NULL),
(195, 1, 'LOGIN', 1, '2026-02-16 17:35:57', NULL),
(196, 1, 'LOGIN', 1, '2026-02-17 14:28:57', NULL),
(197, 1, 'LOGOUT', 1, '2026-02-17 14:32:36', NULL),
(198, 1, 'LOGIN', 1, '2026-02-17 14:33:14', NULL),
(199, 1, 'LOGOUT', 1, '2026-02-17 14:35:35', NULL),
(200, 2, 'LOGIN', 2, '2026-02-17 14:35:49', NULL),
(201, 2, 'ADD_VISITOR', 14, '2026-02-17 14:36:08', NULL),
(202, 2, 'LOGOUT', 2, '2026-02-17 14:36:28', NULL),
(203, 1, 'LOGIN', 1, '2026-02-18 14:27:50', '::1'),
(204, 1, 'LOGOUT', 1, '2026-02-18 14:36:45', '::1'),
(205, 3, 'LOGIN', 3, '2026-02-18 14:37:25', '::1'),
(206, 3, 'LOGOUT', 3, '2026-02-18 14:39:06', '::1'),
(207, 2, 'LOGIN', 2, '2026-02-18 14:39:19', '::1'),
(208, 2, 'LOGOUT', 2, '2026-02-18 14:39:48', '::1'),
(209, 1, 'LOGIN', 1, '2026-02-18 14:40:01', '::1'),
(210, 1, 'UPDATE_PRODUCT', 2, '2026-02-18 14:41:55', '::1'),
(211, 1, 'DELETE_PRODUCT', 3, '2026-02-18 14:42:08', '::1'),
(212, 1, 'DELETE_PRODUCT', 9, '2026-02-18 14:42:17', '::1'),
(213, 1, 'DELETE_PRODUCT', 4, '2026-02-18 14:42:23', '::1'),
(214, 1, 'DELETE_PRODUCT', 5, '2026-02-18 14:42:27', '::1'),
(215, 1, 'DELETE_PRODUCT', 10, '2026-02-18 14:42:33', '::1'),
(216, 1, 'DELETE_PRODUCT', 1, '2026-02-18 14:42:39', '::1'),
(217, 1, 'DELETE_PRODUCT', 6, '2026-02-18 14:42:45', '::1'),
(218, 1, 'LOGOUT', 1, '2026-02-18 15:30:56', '::1'),
(219, 3, 'LOGIN', 3, '2026-02-18 15:31:13', '::1'),
(220, 3, 'LOGOUT', 3, '2026-02-18 15:41:03', '::1'),
(221, 1, 'LOGIN', 1, '2026-02-18 15:41:39', '::1'),
(222, 1, 'LOGOUT', 1, '2026-02-18 15:56:57', '::1'),
(223, 2, 'LOGIN', 2, '2026-02-18 15:57:10', '::1'),
(224, 2, 'LOGOUT', 2, '2026-02-18 15:57:44', '::1'),
(225, 1, 'LOGIN', 1, '2026-02-18 15:58:08', '::1'),
(226, 1, 'LOGOUT', 1, '2026-02-18 16:10:17', '::1'),
(227, 1, 'LOGIN', 1, '2026-02-18 16:24:01', '::1'),
(228, 1, 'LOGOUT', 1, '2026-02-18 16:24:23', '::1'),
(229, 1, 'LOGIN', 1, '2026-02-19 07:48:01', '::1'),
(230, 1, 'LOGOUT', 1, '2026-02-19 07:55:18', '::1'),
(231, 3, 'LOGIN', 3, '2026-02-19 07:59:23', '::1'),
(232, 3, 'LOGOUT', 3, '2026-02-19 08:14:28', '::1'),
(233, 2, 'LOGIN', 2, '2026-02-19 08:14:59', '::1'),
(234, 2, 'ADD_VISITOR', 15, '2026-02-19 08:15:27', '::1'),
(235, 2, 'UPDATE_VISITOR_STATUS', 15, '2026-02-19 08:16:04', '::1'),
(236, 2, 'LOGOUT', 2, '2026-02-19 08:16:26', '::1'),
(237, 1, 'LOGIN', 1, '2026-02-19 08:16:35', '::1'),
(238, 1, 'LOGOUT', 1, '2026-02-19 09:17:36', '::1'),
(239, 1, 'LOGIN', 1, '2026-02-19 09:22:52', '::1'),
(240, 1, 'LOGOUT', 1, '2026-02-19 09:25:16', '::1'),
(241, 1, 'LOGIN', 1, '2026-02-19 10:40:39', '::1'),
(242, 1, 'LOGOUT', 1, '2026-02-19 10:44:59', '::1'),
(243, 1, 'LOGIN', 1, '2026-02-19 11:10:33', '::1'),
(244, 1, 'LOGIN', 1, '2026-02-19 12:14:33', '::1'),
(245, 3, 'LOGOUT', 3, '2026-02-19 13:04:24', '::1'),
(246, 1, 'LOGIN', 1, '2026-02-20 10:13:18', '::1'),
(247, 1, 'LOGIN', 1, '2026-02-23 07:42:09', '::1'),
(248, 1, 'ADD_STOCK_CATEGORY', 7, '2026-02-23 07:46:36', '::1'),
(249, 1, 'UPDATE_PRODUCT', 2, '2026-02-23 07:54:23', '::1'),
(250, 1, 'ADD_PRODUCT', 13, '2026-02-23 07:57:04', '::1'),
(251, 1, 'UPDATE_PRODUCT', 8, '2026-02-23 08:12:02', '::1'),
(252, 1, 'RECEIVE_STOCK', 8, '2026-02-23 13:02:50', '::1'),
(253, 1, 'LOGIN', 1, '2026-02-23 14:28:05', '::1'),
(254, 1, 'LOGIN', 1, '2026-02-24 08:24:23', '::1'),
(255, 1, 'LOGOUT', 1, '2026-02-24 11:50:08', '::1'),
(256, 3, 'LOGIN', 3, '2026-02-24 11:50:30', '::1'),
(257, 3, 'LOGOUT', 3, '2026-02-24 13:32:03', '::1'),
(258, 2, 'LOGIN', 2, '2026-02-24 13:32:17', '::1'),
(259, 2, 'ADD_VISITOR', 16, '2026-02-24 13:32:32', '::1'),
(260, 2, 'LOGOUT', 2, '2026-02-24 13:32:43', '::1'),
(261, 1, 'LOGIN', 1, '2026-02-24 14:26:05', '::1'),
(262, 1, 'LOGIN', 1, '2026-02-25 07:57:21', '::1'),
(263, 1, 'LOGOUT', 1, '2026-02-25 08:10:47', '::1'),
(264, 3, 'LOGIN', 3, '2026-02-25 08:11:06', '::1'),
(265, 3, 'STOCK_REQUEST', 1, '2026-02-25 08:11:53', '::1'),
(266, 3, 'LOGOUT', 3, '2026-02-25 08:11:57', '::1'),
(267, 1, 'LOGIN', 1, '2026-02-25 08:12:04', '::1'),
(268, 1, 'LOGOUT', 1, '2026-02-25 08:12:53', '::1'),
(269, 3, 'LOGIN', 3, '2026-02-25 08:12:59', '::1'),
(270, 3, 'LOGOUT', 3, '2026-02-25 08:13:03', '::1'),
(271, 1, 'LOGIN', 1, '2026-02-25 08:13:07', '::1'),
(272, 1, 'LOGOUT', 1, '2026-02-25 08:13:15', '::1'),
(273, 3, 'LOGIN', 3, '2026-02-25 08:13:19', '::1'),
(274, 3, 'LOGOUT', 3, '2026-02-25 08:14:49', '::1'),
(275, 1, 'LOGIN', 1, '2026-02-25 08:14:55', '::1'),
(276, 1, 'LOGIN', 1, '2026-02-25 11:12:03', '::1'),
(277, 1, 'BULK_UPDATE_THRESHOLD', NULL, '2026-02-25 11:36:20', '::1'),
(278, 1, 'BULK_UPDATE_THRESHOLD', NULL, '2026-02-25 11:39:33', '::1'),
(279, 1, 'RECEIVE_STOCK', 13, '2026-02-25 11:42:06', '::1'),
(280, 1, 'RECEIVE_STOCK', 14, '2026-02-25 11:42:58', '::1'),
(281, 1, 'LOGOUT', 1, '2026-02-25 11:44:47', '::1'),
(282, 3, 'LOGIN', 3, '2026-02-25 11:44:53', '::1'),
(283, 3, 'LOGOUT', 3, '2026-02-25 11:45:21', '::1'),
(284, 1, 'LOGIN', 1, '2026-02-25 11:45:27', '::1'),
(285, 1, 'ADD_SUPERVISOR', 8, '2026-02-25 11:46:27', '::1'),
(286, 1, 'LOGOUT', 1, '2026-02-25 11:46:33', '::1'),
(287, 8, 'LOGIN', 8, '2026-02-25 11:46:40', '::1'),
(288, 8, 'LOGOUT', 8, '2026-02-25 11:47:00', '::1'),
(289, 1, 'LOGIN', 1, '2026-02-25 11:47:05', '::1'),
(290, 1, 'TRANSFER_REQUEST', 3, '2026-02-25 11:55:30', '::1'),
(291, 1, 'TRANSFER_REQUEST', 4, '2026-02-25 11:56:28', '::1'),
(292, 1, 'LOGOUT', 1, '2026-02-25 11:56:33', '::1'),
(293, 8, 'LOGIN', 8, '2026-02-25 11:56:39', '::1'),
(294, 8, 'LOGOUT', 8, '2026-02-25 11:57:16', '::1'),
(295, 1, 'LOGIN', 1, '2026-02-25 11:57:22', '::1'),
(296, 1, 'STOCK_TRANSFER', 0, '2026-02-25 12:04:31', '::1'),
(297, 1, 'LOGOUT', 1, '2026-02-25 12:04:35', '::1'),
(298, 8, 'LOGIN', 8, '2026-02-25 12:04:40', '::1'),
(299, 8, 'LOGOUT', 8, '2026-02-25 12:04:57', '::1'),
(300, 1, 'LOGIN', 1, '2026-02-25 12:05:01', '::1'),
(301, 1, 'BULK_UPDATE_THRESHOLD', NULL, '2026-02-25 21:08:41', '::1'),
(302, 1, 'LOGOUT', 1, '2026-02-25 21:08:45', '::1'),
(303, 1, 'LOGIN', 1, '2026-02-25 21:08:49', '::1'),
(304, 1, 'BULK_UPDATE_THRESHOLD', NULL, '2026-02-25 21:09:11', '::1'),
(305, 1, 'LOGIN', 1, '2026-02-25 22:24:27', '::1'),
(306, 1, 'DELETE_OUTLET', 20, '2026-02-25 22:28:10', '::1'),
(307, 1, 'DELETE_OUTLET', 20, '2026-02-25 22:28:33', '::1'),
(308, 1, 'ADD_OUTLET', 20, '2026-02-25 22:28:33', '::1'),
(309, 1, 'UPDATE_OUTLET', 30, '2026-02-25 22:28:54', '::1'),
(310, 1, 'LOGOUT', 1, '2026-02-25 22:37:27', '::1'),
(311, 1, 'LOGIN', 1, '2026-02-25 22:38:33', '::1'),
(312, 1, 'LOGOUT', 1, '2026-02-25 22:38:40', '::1'),
(313, 3, 'LOGIN', 3, '2026-02-25 22:39:56', '::1'),
(314, 3, 'STOCK_REQUEST', 2, '2026-02-25 22:42:09', '::1'),
(315, 3, 'LOGOUT', 3, '2026-02-25 22:42:12', '::1'),
(316, 1, 'LOGIN', 1, '2026-02-25 22:42:17', '::1'),
(317, 1, 'ADD_AGENT', 9, '2026-02-25 22:43:47', '::1'),
(318, 1, 'LOGOUT', 1, '2026-02-25 22:43:55', '::1'),
(319, 9, 'LOGIN', 9, '2026-02-25 22:44:06', '::1'),
(320, 9, 'ADD_VISITOR', 17, '2026-02-25 22:44:45', '::1'),
(321, 9, 'LOGOUT', 9, '2026-02-25 22:45:21', '::1'),
(322, 1, 'LOGIN', 1, '2026-02-25 22:45:27', '::1'),
(323, 1, 'LOGOUT', 1, '2026-02-25 22:46:36', '::1'),
(324, 1, 'LOGIN', 1, '2026-02-25 22:47:15', '::1'),
(325, 1, 'FULFILL_STOCK_REQUEST', 2, '2026-02-25 14:51:48', '::1'),
(326, 1, 'FULFILL_STOCK_REQUEST', 1, '2026-02-25 14:52:04', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `cash_reconciliation`
--

CREATE TABLE `cash_reconciliation` (
  `id` int NOT NULL,
  `outlet_id` int DEFAULT NULL,
  `agent_id` int DEFAULT NULL,
  `expected_amount` decimal(10,2) DEFAULT '0.00',
  `actual_amount` decimal(10,2) DEFAULT '0.00',
  `discrepancy` decimal(10,2) GENERATED ALWAYS AS ((`expected_amount` - `actual_amount`)) STORED,
  `reconciliation_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `reconciled_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `complaint_reasons`
--

CREATE TABLE `complaint_reasons` (
  `id` int NOT NULL,
  `reason` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_general_ci DEFAULT 'General',
  `description` text COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaint_reasons`
--

INSERT INTO `complaint_reasons` (`id`, `reason`, `category`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Network coverage issue', 'Network', NULL, 1, 10, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(2, 'Slow data / internet issue', 'Network', NULL, 1, 20, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(3, 'SIM registration issue', 'SIM Services', NULL, 1, 30, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(4, 'SIM replacement issue', 'SIM Services', NULL, 1, 40, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(5, 'ComCach deposit issue', 'ComCach', NULL, 1, 50, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(6, 'ComCach withdrawal issue', 'ComCach', NULL, 1, 60, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(7, 'Airtime or bundle not received', 'Billing', NULL, 1, 70, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(8, 'Incorrect charge / billing dispute', 'Billing', NULL, 1, 80, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(9, 'Customer service follow-up', 'Service Quality', NULL, 1, 90, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(10, 'Other complaint', 'General', NULL, 1, 100, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- --------------------------------------------------------

--
-- Table structure for table `outlets`
--

CREATE TABLE `outlets` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `location` text COLLATE utf8mb4_general_ci,
  `region` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `outlets`
--

INSERT INTO `outlets` (`id`, `name`, `location`, `region`, `is_active`, `created_at`) VALUES
(1, 'Comium GM Main', 'City Center', 'Central', 0, '2026-02-12 14:59:18'),
(2, 'Comium GM North', 'Northern District', 'North', 0, '2026-02-12 14:59:18'),
(3, 'Comium GM South', 'Southern District', 'South', 0, '2026-02-12 14:59:18'),
(4, 'Comium GM Main', 'City Center', 'Central', 0, '2026-02-12 16:31:57'),
(5, 'Comium GM North', 'Northern District', 'North', 0, '2026-02-12 16:31:57'),
(6, 'Comium GM South', 'Southern District', 'South', 0, '2026-02-12 16:31:57'),
(7, 'HQ Showroom', 'Kairaba Avenue', 'West Coast Region', 0, '2026-02-13 08:57:58'),
(8, 'HQ Showroom', 'Kairaba Avenue', 'West Coast Region', 1, '2026-02-13 09:00:50'),
(9, 'Banjul Showroom', 'Banjul', 'Banjul', 1, '2026-02-13 09:05:53'),
(10, 'Brikama showroom', 'Brikama', 'Central River Region', 1, '2026-02-13 09:06:36'),
(11, 'Brikama 2', 'Brikama', 'Central River Region', 1, '2026-02-13 09:07:02'),
(12, 'Brikama Ba', 'Brikama Ba', 'Central River Region', 1, '2026-02-13 09:08:02'),
(13, 'Kaur', 'Kaur', 'Central River Region', 1, '2026-02-13 09:08:32'),
(14, 'Wassu', 'Wassu', 'Central River Region', 1, '2026-02-13 09:08:47'),
(15, 'Bansang', 'Bansang', 'Central River Region', 1, '2026-02-13 09:09:10'),
(16, 'Amdalai', 'Amdalai', 'North Bank Region', 1, '2026-02-13 09:09:46'),
(17, 'Barra', 'Barra', 'North Bank Region', 1, '2026-02-13 09:10:07'),
(18, 'Farafenni', 'Farafenni', 'North Bank Region', 1, '2026-02-13 09:10:29'),
(19, 'Bajakunda Showroom', 'Bajakunda', 'Upper River Region', 1, '2026-02-13 09:10:57'),
(20, 'Airport ', 'Airport ', 'West Coast Region', 0, '2026-02-13 09:11:16'),
(21, 'Brusubi Showroom', 'Brusubi', 'West Coast Region', 1, '2026-02-13 09:14:17'),
(22, 'Busumbala Showroom', 'Busumbala', 'West Coast Region', 1, '2026-02-13 09:14:48'),
(23, 'Gunjur Showroom', 'Gunjur', 'West Coast Region', 1, '2026-02-13 09:15:14'),
(24, 'Jabang Showroom', 'Jabang', 'West Coast Region', 1, '2026-02-13 09:15:36'),
(25, 'Sanyang Showroom', 'Sanyang', 'West Coast Region', 1, '2026-02-13 09:16:02'),
(26, 'Senegambia Showroom', 'Senegambia', 'West Coast Region', 1, '2026-02-13 09:16:28'),
(27, 'Sinchu Showroom', 'Sinchu', 'West Coast Region', 1, '2026-02-13 09:16:57'),
(28, 'Sukuta showroom', 'Sukuta', 'West Coast Region', 1, '2026-02-13 09:17:25'),
(29, 'Soma Showroom', 'Soma', 'Lower River Region', 1, '2026-02-13 09:25:34'),
(30, 'Airport ', 'Yundum', 'West Coast', 1, '2026-02-25 22:28:33');

-- --------------------------------------------------------

--
-- Table structure for table `outlet_stock`
--

CREATE TABLE `outlet_stock` (
  `id` int NOT NULL,
  `outlet_id` int NOT NULL,
  `product_id` int NOT NULL,
  `batch_id` int DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `low_stock_alert_sent` tinyint(1) DEFAULT '0',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `outlet_stock`
--

INSERT INTO `outlet_stock` (`id`, `outlet_id`, `product_id`, `batch_id`, `quantity`, `low_stock_alert_sent`, `last_updated`) VALUES
(1, 8, 2, 8, 1100, 0, '2026-02-25 14:52:04'),
(4, 8, 8, 13, 300, 0, '2026-02-25 11:56:28'),
(5, 8, 13, 14, 400, 0, '2026-02-25 12:04:31'),
(8, 9, 8, 13, 200, 0, '2026-02-25 11:56:28'),
(9, 9, 13, 14, 100, 0, '2026-02-25 12:04:31');

-- --------------------------------------------------------

--
-- Table structure for table `outlet_stock_backup`
--

CREATE TABLE `outlet_stock_backup` (
  `id` int NOT NULL DEFAULT '0',
  `outlet_id` int NOT NULL,
  `product_id` int NOT NULL,
  `batch_id` int DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `low_stock_alert_sent` tinyint(1) DEFAULT '0',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `outlet_stock_backup`
--

INSERT INTO `outlet_stock_backup` (`id`, `outlet_id`, `product_id`, `batch_id`, `quantity`, `low_stock_alert_sent`, `last_updated`) VALUES
(1, 8, 2, 8, 500, 0, '2026-02-23 13:02:49');

-- --------------------------------------------------------

--
-- Table structure for table `purpose_categories`
--

CREATE TABLE `purpose_categories` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `icon` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'bi-tag',
  `color` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '#6c757d',
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purpose_categories`
--

INSERT INTO `purpose_categories` (`id`, `name`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Technical', 'Network issues, installations, maintenance', 'bi-gear', '#dc3545', 1, 1, '2026-02-13 10:36:22'),
(2, 'Customer Care', 'Complaints, inquiries, feedback', 'bi-headset', '#0d6efd', 2, 1, '2026-02-13 10:36:22'),
(3, 'Marketing / Promotions', 'Campaigns, launches, partnerships', 'bi-megaphone', '#198754', 3, 1, '2026-02-13 10:36:22'),
(4, 'Sales / Business Development', 'Meetings, presentations, contracts', 'bi-graph-up', '#ffc107', 4, 1, '2026-02-13 10:36:22'),
(5, 'Administration / HR', 'Internal meetings, recruitment, training', 'bi-person-badge', '#6610f2', 5, 1, '2026-02-13 10:36:22'),
(6, 'Others / Miscellaneous', 'Any other type of visit', 'bi-three-dots', '#6c757d', 99, 1, '2026-02-13 10:36:22');

-- --------------------------------------------------------

--
-- Table structure for table `purpose_of_visit`
--

CREATE TABLE `purpose_of_visit` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `category_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `icon` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'bi-dot',
  `color` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '#6c757d'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purpose_of_visit`
--

INSERT INTO `purpose_of_visit` (`id`, `name`, `category_id`, `is_active`, `created_at`, `icon`, `color`) VALUES
(1, 'Feedback/Suggestion', 2, 1, '2026-02-13 09:29:17', 'bi-chat', '#0d6efd'),
(2, 'Complaint Submission', 2, 1, '2026-02-13 09:29:35', 'bi-chat', '#0d6efd'),
(3, 'Promtion Inquiry', 2, 1, '2026-02-13 09:29:53', 'bi-chat', '#0d6efd'),
(4, 'General Inquiry', 2, 1, '2026-02-13 09:30:13', 'bi-chat', '#0d6efd'),
(5, 'Mobile Money Deposit (Comcach)', 6, 1, '2026-02-13 09:31:14', 'bi-three-dots', '#6c757d'),
(6, 'Mobile Money Withdrawal (Comcach)', 6, 1, '2026-02-13 09:31:47', 'bi-three-dots', '#6c757d'),
(7, 'eSim Activation', 6, 1, '2026-02-13 09:32:22', 'bi-three-dots', '#6c757d'),
(8, 'Sim Replacement', 6, 1, '2026-02-13 09:32:34', 'bi-three-dots', '#6c757d');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int NOT NULL,
  `visitor_id` int DEFAULT NULL,
  `agent_id` int DEFAULT NULL,
  `outlet_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('Paid','Unpaid','Pending') COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `handle_started_at` datetime DEFAULT NULL,
  `handle_completed_at` datetime DEFAULT NULL,
  `handle_seconds` int DEFAULT 0,
  `invoice_number` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `visitor_id`, `agent_id`, `outlet_id`, `amount`, `payment_status`, `payment_date`, `invoice_number`) VALUES
(1, 3, 2, 8, 0.00, 'Paid', '2026-02-13 10:16:16', 'FREE-20260213-3'),
(2, 4, 2, 8, 0.00, 'Paid', '2026-02-13 11:30:35', 'FREE-20260213-4'),
(3, 7, 2, 8, 0.00, 'Paid', '2026-02-16 09:36:03', 'FREE-20260216-7'),
(4, 10, 2, 8, 150.00, 'Paid', '2026-02-16 09:50:17', 'TEMP-20260216-10'),
(5, 11, 2, 8, 1050.00, 'Pending', '2026-02-16 09:51:34', 'TEMP-20260216-11'),
(6, 12, 2, 8, 0.00, 'Paid', '2026-02-16 11:01:38', 'FREE-20260216-12'),
(7, 13, 2, 8, 0.00, 'Paid', '2026-02-16 11:51:48', 'FREE-20260216-13'),
(8, 14, 2, 8, 0.00, 'Paid', '2026-02-17 09:06:08', 'FREE-20260217-14'),
(9, 15, 2, 8, 0.00, 'Paid', '2026-02-19 08:15:27', 'FREE-20260219-15'),
(10, 16, 2, 8, 8.00, 'Pending', '2026-02-24 13:32:32', 'TEMP-20260224-16'),
(11, 17, 9, 8, 0.00, 'Paid', '2026-02-25 22:44:45', 'FREE-20260225-17');


-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_paid` tinyint(1) DEFAULT '1',
  `description` text COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_fixed_price` tinyint(1) DEFAULT '1' COMMENT 'TRUE = Fixed price, FALSE = Customer can pay any amount',
  `min_amount` decimal(10,2) DEFAULT NULL COMMENT 'Minimum amount for flexible pricing',
  `max_amount` decimal(10,2) DEFAULT NULL COMMENT 'Maximum amount for flexible pricing',
  `is_quantity_based` tinyint(1) DEFAULT '0' COMMENT 'TRUE = Price is per unit, multiply by quantity',
  `unit_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'unit' COMMENT 'e.g., SIM, GB, minute, etc.',
  `allow_custom_quantity` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `price`, `is_paid`, `description`, `is_active`, `created_at`, `is_fixed_price`, `min_amount`, `max_amount`, `is_quantity_based`, `unit_name`, `allow_custom_quantity`) VALUES
(1, 'Mobile Recharge', 10.00, 1, NULL, 0, '2026-02-12 14:59:18', 1, NULL, NULL, 0, 'unit', 1),
(2, 'Bill Payment', 5.00, 1, NULL, 0, '2026-02-12 14:59:18', 1, NULL, NULL, 0, 'unit', 1),
(3, 'Money Transfer', 15.00, 1, NULL, 0, '2026-02-12 14:59:18', 1, NULL, NULL, 0, 'unit', 1),
(4, 'Airtime Top-up', 8.00, 1, '', 0, '2026-02-12 14:59:18', 1, NULL, NULL, 1, 'minute', 1),
(5, 'New Sim card', 20.00, 1, '', 1, '2026-02-13 08:27:41', 1, NULL, NULL, 1, 'SIM', 1),
(6, 'New E-Sim', 150.00, 1, '', 1, '2026-02-13 08:27:59', 1, NULL, NULL, 1, 'SIM', 1),
(7, 'Unpaid', 0.00, 1, '', 0, '2026-02-13 09:36:38', 1, NULL, NULL, 0, 'unit', 1),
(8, 'Mobile Recharge', 10.00, 1, 'Prepaid mobile top-up', 1, '2026-02-13 10:10:59', 1, NULL, NULL, 0, 'unit', 1),
(9, 'Data Bundle', 15.00, 1, 'Internet data packages', 0, '2026-02-13 10:10:59', 1, NULL, NULL, 1, 'GB', 1),
(10, 'Airtime Transfer', 5.00, 1, 'Transfer airtime to another number', 0, '2026-02-13 10:10:59', 0, 10.00, 20000.00, 1, 'minute', 1),
(11, 'Bill Payment', 8.00, 1, 'Pay utility bills', 1, '2026-02-13 10:10:59', 1, NULL, NULL, 0, 'unit', 1),
(12, 'Money Transfer', 12.00, 1, 'Send money to other networks', 1, '2026-02-13 10:10:59', 1, NULL, NULL, 0, 'unit', 1),
(13, 'Customer Inquiry', 0.00, 0, 'General questions and inquiries', 1, '2026-02-13 10:10:59', 1, NULL, NULL, 0, 'unit', 1),
(14, 'Network Troubleshooting', 0.00, 0, 'Help with network issues', 1, '2026-02-13 10:10:59', 1, NULL, NULL, 0, 'unit', 1),
(15, 'SIM Swap Assistance', 0.00, 0, 'Help with SIM replacement process', 1, '2026-02-13 10:10:59', 1, NULL, NULL, 1, 'SIM', 1),
(16, 'Account Balance Check', 0.00, 0, 'Check account balance', 1, '2026-02-13 10:10:59', 1, NULL, NULL, 0, 'unit', 1),
(17, 'Service Activation Help', 0.00, 0, 'Help activating new services', 1, '2026-02-13 10:10:59', 1, NULL, NULL, 0, 'unit', 1),
(18, 'Complaint Lodging', 0.00, 0, 'Register customer complaints', 1, '2026-02-13 10:10:59', 1, NULL, NULL, 0, 'unit', 1);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Comium GM', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(2, 'company_name', 'Comium GM', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(3, 'company_email', 'info@comium.gm', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(4, 'company_phone', '+2206600309', '2026-02-13 12:17:05', '2026-02-13 12:25:17'),
(5, 'company_address', 'Banjul, The Gambia', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(6, 'timezone', 'Africa/Banjul', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(7, 'date_format', 'd/m/Y', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(8, 'currency', 'D', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(9, 'currency_symbol', 'D', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(10, 'email_notifications', '1', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(11, 'daily_report', '1', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(12, 'weekly_report', '1', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(13, 'monthly_report', '1', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(14, 'target_alert', '1', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(15, 'cash_discrepancy_alert', '1', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(16, 'report_emails', '', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(17, 'password_expiry', '90', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(18, 'max_login_attempts', '5', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(19, 'session_timeout', '30', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(20, 'two_factor_auth', '0', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(21, 'audit_log_retention', '365', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(22, 'hours_monday', '09:00-17:00', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(23, 'hours_tuesday', '09:00-17:00', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(24, 'hours_wednesday', '09:00-17:00', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(25, 'hours_thursday', '09:00-17:00', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(26, 'hours_friday', '09:00-17:00', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(27, 'hours_saturday', '09:00-13:00', '2026-02-13 12:17:05', '2026-02-13 12:17:05'),
(28, 'hours_sunday', 'closed', '2026-02-13 12:17:05', '2026-02-13 12:17:05');

-- --------------------------------------------------------

--
-- Table structure for table `stock_alerts`
--

CREATE TABLE `stock_alerts` (
  `id` int NOT NULL,
  `outlet_id` int NOT NULL,
  `product_id` int NOT NULL,
  `current_quantity` int NOT NULL,
  `threshold` int NOT NULL,
  `alert_type` enum('low','critical','expiring') COLLATE utf8mb4_general_ci DEFAULT 'low',
  `is_resolved` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_backup`
--

CREATE TABLE `stock_backup` (
  `id` int NOT NULL DEFAULT '0',
  `category_id` int DEFAULT NULL,
  `name` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `sku` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `unit_price` decimal(10,2) DEFAULT '0.00',
  `selling_price` decimal(10,2) DEFAULT '0.00',
  `low_stock_threshold` int DEFAULT '10',
  `reorder_quantity` int DEFAULT '50',
  `is_active` tinyint(1) DEFAULT '1',
  `track_serial` tinyint(1) DEFAULT '0',
  `expiry_date` date DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_backup`
--

INSERT INTO `stock_backup` (`id`, `category_id`, `name`, `sku`, `description`, `unit_price`, `selling_price`, `low_stock_threshold`, `reorder_quantity`, `is_active`, `track_serial`, `expiry_date`, `image_path`, `created_at`, `updated_at`) VALUES
(1, 1, 'Africell SIM', 'SIM-AFR-001', NULL, 25.00, 50.00, 20, 100, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-18 14:42:39'),
(2, 1, 'COMIUM SIM', 'SIM-QCL-002', '', 25.00, 50.00, 20, 100, 1, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-23 07:54:23'),
(3, 1, 'GamTel SIM', 'SIM-GAM-001', NULL, 25.00, 50.00, 20, 100, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-18 14:42:08'),
(4, 2, 'Africell D 10 Scratch', 'SCR-AFR-010', NULL, 8.00, 10.00, 30, 200, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-18 14:42:23'),
(5, 2, 'Africell D 25 Scratch', 'SCR-AFR-025', NULL, 22.00, 25.00, 30, 200, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-18 14:42:27'),
(6, 2, 'QCell D 10 Scratch', 'SCR-QCL-010', NULL, 8.00, 10.00, 30, 200, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-18 14:42:45'),
(7, 3, 'USB Charger', 'ACC-USB-001', NULL, 50.00, 100.00, 5, 20, 1, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-17 16:50:23'),
(8, 3, 'Earphones', 'ACC-EAR-001', '', 30.00, 75.00, 5, 20, 1, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-23 08:12:02'),
(9, 4, 'Africell 1GB Data', 'VOU-AFR-1GB', NULL, 40.00, 60.00, 15, 50, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-18 14:42:17'),
(10, 4, 'QCell 2GB Data', 'VOU-QCL-2GB', NULL, 70.00, 100.00, 15, 50, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-18 14:42:33'),
(13, 2, 'Evoucher', 'SCR-EVOUC-198', '', 10.00, 25.00, 10, 50, 1, 0, '2026-02-28', NULL, '2026-02-23 07:57:04', '2026-02-23 07:57:04');

-- --------------------------------------------------------

--
-- Table structure for table `stock_batches`
--

CREATE TABLE `stock_batches` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `batch_number` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supplier` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `quantity` int NOT NULL,
  `remaining_quantity` int NOT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `received_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `location` enum('warehouse','outlet') COLLATE utf8mb4_general_ci DEFAULT 'warehouse',
  `warehouse_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_batches`
--

INSERT INTO `stock_batches` (`id`, `product_id`, `batch_number`, `supplier`, `quantity`, `remaining_quantity`, `purchase_price`, `selling_price`, `received_date`, `expiry_date`, `notes`, `created_at`, `location`, `warehouse_id`) VALUES
(8, 2, 'BATCH-20260223', 'Comium', 500, 500, 15.00, 20.00, '2026-02-23', '2026-02-28', '', '2026-02-23 13:02:49', 'warehouse', NULL),
(13, 8, 'BATCH-20260225', 'Comium', 500, 500, 10.00, 20.00, '2026-02-25', '2027-01-25', '', '2026-02-25 11:42:06', 'warehouse', NULL),
(14, 13, 'BATCH-20260225', 'Comium', 500, 500, 10.00, 25.00, '2026-02-25', '2027-01-25', '', '2026-02-25 11:42:58', 'warehouse', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_categories`
--

CREATE TABLE `stock_categories` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `icon` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'bi-box',
  `color` varchar(20) COLLATE utf8mb4_general_ci DEFAULT '#6c757d',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_categories`
--

INSERT INTO `stock_categories` (`id`, `name`, `description`, `icon`, `color`, `is_active`, `created_at`) VALUES
(1, 'SIM Cards', 'All network SIM cards', 'bi-sim', '#28a745', 1, '2026-02-17 16:50:23'),
(2, 'Scratch Cards', 'Recharge scratch cards', 'bi-credit-card', '#ffc107', 1, '2026-02-17 16:50:23'),
(3, 'Accessories', 'Phone accessories, chargers, earphones', 'bi-headphones', '#17a2b8', 1, '2026-02-17 16:50:23'),
(4, 'Data Vouchers', 'Data bundle vouchers', 'bi-wifi', '#6610f2', 1, '2026-02-17 16:50:23'),
(5, 'Devices', 'Modems, routers, phones', 'bi-phone', '#dc3545', 1, '2026-02-17 16:50:23'),
(6, 'Other', 'Miscellaneous items', 'bi-box', '#6c757d', 1, '2026-02-17 16:50:23'),
(7, 'eSim', '', 'bi-cpu', '#ec187f', 1, '2026-02-23 07:46:36');

-- --------------------------------------------------------

--
-- Table structure for table `stock_counts`
--

CREATE TABLE `stock_counts` (
  `id` int NOT NULL,
  `outlet_id` int NOT NULL,
  `count_date` date NOT NULL,
  `counted_by` int NOT NULL,
  `status` enum('draft','in_progress','completed','verified') COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_count_items`
--

CREATE TABLE `stock_count_items` (
  `id` int NOT NULL,
  `stock_count_id` int NOT NULL,
  `product_id` int NOT NULL,
  `batch_id` int DEFAULT NULL,
  `system_quantity` int NOT NULL,
  `counted_quantity` int NOT NULL,
  `variance` int NOT NULL,
  `notes` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int NOT NULL,
  `outlet_id` int NOT NULL,
  `product_id` int NOT NULL,
  `batch_id` int DEFAULT NULL,
  `movement_type` enum('received','sold','returned','transferred','adjusted','damaged','released','received_from_warehouse','released_to_outlet') COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` int NOT NULL,
  `before_quantity` int NOT NULL,
  `after_quantity` int NOT NULL,
  `reference_id` int DEFAULT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_by` int NOT NULL,
  `approved_by` int DEFAULT NULL,
  `verified_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `location` enum('warehouse','outlet') COLLATE utf8mb4_general_ci DEFAULT 'outlet'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `outlet_id`, `product_id`, `batch_id`, `movement_type`, `quantity`, `before_quantity`, `after_quantity`, `reference_id`, `reference_type`, `unit_price`, `total_price`, `notes`, `created_by`, `approved_by`, `verified_by`, `created_at`, `location`) VALUES
(1, 8, 2, 8, 'received', 500, 0, 500, NULL, NULL, 15.00, 7500.00, '', 1, NULL, NULL, '2026-02-23 13:02:49', 'outlet'),
(2, 8, 8, 13, 'received', 500, 0, 500, NULL, NULL, 10.00, 5000.00, '', 1, NULL, NULL, '2026-02-25 11:42:06', 'outlet'),
(3, 8, 13, 14, 'received', 500, 0, 500, NULL, NULL, 10.00, 5000.00, '', 1, NULL, NULL, '2026-02-25 11:42:58', 'outlet'),
(4, 8, 8, 13, 'transferred', 100, 500, 400, NULL, NULL, NULL, NULL, 'Transferred to outlet 9: ', 1, NULL, NULL, '2026-02-25 11:55:30', 'outlet'),
(5, 9, 8, 13, 'transferred', 100, 0, 100, NULL, NULL, NULL, NULL, 'Received from outlet 8: ', 1, NULL, NULL, '2026-02-25 11:55:30', 'outlet'),
(6, 8, 8, 13, 'transferred', 100, 400, 300, NULL, NULL, NULL, NULL, 'Transferred to outlet 9: ', 1, NULL, NULL, '2026-02-25 11:56:28', 'outlet'),
(7, 9, 8, 13, 'transferred', 100, 100, 200, NULL, NULL, NULL, NULL, 'Received from outlet 8: ', 1, NULL, NULL, '2026-02-25 11:56:28', 'outlet'),
(8, 8, 13, 14, 'transferred', 100, 500, 400, NULL, NULL, NULL, NULL, 'Transferred to outlet 9: ', 1, NULL, NULL, '2026-02-25 12:04:31', 'outlet'),
(9, 9, 13, 14, 'transferred', 100, 0, 100, NULL, NULL, NULL, NULL, 'Received from outlet 8: ', 1, NULL, NULL, '2026-02-25 12:04:31', 'outlet'),
(10, 8, 2, NULL, 'received', 100, 500, 600, NULL, NULL, NULL, NULL, 'Fulfilled stock request: REQ-20260225-699F7AC1F1719', 1, NULL, NULL, '2026-02-25 14:51:48', 'outlet'),
(11, 8, 2, NULL, 'received', 500, 600, 1100, NULL, NULL, NULL, NULL, 'Fulfilled stock request: REQ-20260225-699EAEC95BE40', 1, NULL, NULL, '2026-02-25 14:52:04', 'outlet');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements_backup`
--

CREATE TABLE `stock_movements_backup` (
  `id` int NOT NULL DEFAULT '0',
  `outlet_id` int NOT NULL,
  `product_id` int NOT NULL,
  `batch_id` int DEFAULT NULL,
  `movement_type` enum('received','sold','returned','transferred','adjusted','damaged','released','received_from_warehouse','released_to_outlet') COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` int NOT NULL,
  `before_quantity` int NOT NULL,
  `after_quantity` int NOT NULL,
  `reference_id` int DEFAULT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_by` int NOT NULL,
  `approved_by` int DEFAULT NULL,
  `verified_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `location` enum('warehouse','outlet') COLLATE utf8mb4_general_ci DEFAULT 'outlet'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements_backup`
--

INSERT INTO `stock_movements_backup` (`id`, `outlet_id`, `product_id`, `batch_id`, `movement_type`, `quantity`, `before_quantity`, `after_quantity`, `reference_id`, `reference_type`, `unit_price`, `total_price`, `notes`, `created_by`, `approved_by`, `verified_by`, `created_at`, `location`) VALUES
(1, 8, 2, 8, 'received', 500, 0, 500, NULL, NULL, 15.00, 7500.00, '', 1, NULL, NULL, '2026-02-23 13:02:49', 'outlet');

-- --------------------------------------------------------

--
-- Table structure for table `stock_products`
--

CREATE TABLE `stock_products` (
  `id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `name` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `sku` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `unit_price` decimal(10,2) DEFAULT '0.00',
  `selling_price` decimal(10,2) DEFAULT '0.00',
  `low_stock_threshold` int DEFAULT '10',
  `reorder_quantity` int DEFAULT '50',
  `is_active` tinyint(1) DEFAULT '1',
  `track_serial` tinyint(1) DEFAULT '0',
  `expiry_date` date DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_products`
--

INSERT INTO `stock_products` (`id`, `category_id`, `name`, `sku`, `description`, `unit_price`, `selling_price`, `low_stock_threshold`, `reorder_quantity`, `is_active`, `track_serial`, `expiry_date`, `image_path`, `created_at`, `updated_at`) VALUES
(1, 1, 'Africell SIM', 'SIM-AFR-001', NULL, 25.00, 50.00, 10, 20, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-25 21:09:11'),
(2, 1, 'COMIUM SIM', 'SIM-QCL-002', '', 25.00, 50.00, 10, 20, 1, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-25 21:09:11'),
(3, 1, 'GamTel SIM', 'SIM-GAM-001', NULL, 25.00, 50.00, 10, 20, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-25 21:09:11'),
(4, 2, 'Africell D 10 Scratch', 'SCR-AFR-010', NULL, 8.00, 10.00, 10, 20, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-25 21:09:11'),
(5, 2, 'Africell D 25 Scratch', 'SCR-AFR-025', NULL, 22.00, 25.00, 10, 20, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-25 21:09:11'),
(6, 2, 'QCell D 10 Scratch', 'SCR-QCL-010', NULL, 8.00, 10.00, 10, 20, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-25 21:09:11'),
(7, 3, 'USB Charger', 'ACC-USB-001', NULL, 50.00, 100.00, 10, 20, 1, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-25 21:09:11'),
(8, 3, 'Earphones', 'ACC-EAR-001', '', 30.00, 75.00, 10, 20, 1, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-25 21:09:11'),
(9, 4, 'Africell 1GB Data', 'VOU-AFR-1GB', NULL, 40.00, 60.00, 10, 20, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-25 21:09:11'),
(10, 4, 'QCell 2GB Data', 'VOU-QCL-2GB', NULL, 70.00, 100.00, 10, 20, 0, 0, NULL, NULL, '2026-02-17 16:50:23', '2026-02-25 21:09:11'),
(13, 2, 'Evoucher', 'SCR-EVOUC-198', '', 10.00, 25.00, 10, 20, 1, 0, '2026-02-28', NULL, '2026-02-23 07:57:04', '2026-02-25 21:09:11');

-- --------------------------------------------------------

--
-- Table structure for table `stock_requests`
--

CREATE TABLE `stock_requests` (
  `id` int NOT NULL,
  `outlet_id` int NOT NULL,
  `supervisor_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `request_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `required_by_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected','fulfilled') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `fulfilled_by` int DEFAULT NULL,
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_general_ci,
  `request_notes` text COLLATE utf8mb4_general_ci,
  `admin_notes` text COLLATE utf8mb4_general_ci,
  `priority` enum('normal','urgent','critical') COLLATE utf8mb4_general_ci DEFAULT 'normal',
  `reference_number` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_requests`
--

INSERT INTO `stock_requests` (`id`, `outlet_id`, `supervisor_id`, `product_id`, `quantity`, `request_date`, `required_by_date`, `status`, `approved_by`, `approved_at`, `fulfilled_by`, `fulfilled_at`, `rejection_reason`, `request_notes`, `admin_notes`, `priority`, `reference_number`) VALUES
(1, 8, 3, 2, 500, '2026-02-25 08:11:53', '2026-02-28', 'fulfilled', 1, '2026-02-25 08:12:40', 1, '2026-02-25 14:52:04', NULL, '', NULL, 'normal', 'REQ-20260225-699EAEC95BE40'),
(2, 8, 3, 2, 100, '2026-02-25 22:42:09', '2026-02-28', 'fulfilled', 1, '2026-02-25 22:42:35', 1, '2026-02-25 14:51:48', NULL, '', NULL, 'normal', 'REQ-20260225-699F7AC1F1719');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfers`
--

CREATE TABLE `stock_transfers` (
  `id` int NOT NULL,
  `from_outlet_id` int NOT NULL,
  `to_outlet_id` int NOT NULL,
  `product_id` int NOT NULL,
  `batch_id` int DEFAULT NULL,
  `quantity` int NOT NULL,
  `status` enum('pending','approved','received','rejected') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `requested_by` int NOT NULL,
  `approved_by` int DEFAULT NULL,
  `request_notes` text COLLATE utf8mb4_general_ci,
  `completion_notes` text COLLATE utf8mb4_general_ci,
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `transferred_by` int DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_transfers`
--

INSERT INTO `stock_transfers` (`id`, `from_outlet_id`, `to_outlet_id`, `product_id`, `batch_id`, `quantity`, `status`, `requested_by`, `approved_by`, `request_notes`, `completion_notes`, `requested_at`, `approved_at`, `completed_at`, `transferred_by`, `received_by`, `received_at`) VALUES
(3, 8, 9, 8, 13, 100, 'received', 1, 1, '', NULL, '2026-02-25 11:55:30', '2026-02-25 11:55:30', NULL, NULL, 1, '2026-02-25 11:55:30'),
(4, 8, 9, 8, 13, 100, 'received', 1, 1, '', NULL, '2026-02-25 11:56:28', '2026-02-25 11:56:28', NULL, NULL, 1, '2026-02-25 11:56:28'),
(5, 8, 9, 13, 14, 100, 'received', 1, NULL, '', NULL, '2026-02-25 12:04:31', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_attendance`
--

CREATE TABLE `supervisor_attendance` (
  `id` int NOT NULL,
  `supervisor_id` int NOT NULL,
  `outlet_id` int NOT NULL,
  `duty_date` date NOT NULL,
  `shift` enum('Morning','Afternoon','Night') COLLATE utf8mb4_general_ci NOT NULL,
  `login_time` datetime DEFAULT NULL,
  `logout_time` datetime DEFAULT NULL,
  `status` enum('Scheduled','On Duty','Completed','Absent') COLLATE utf8mb4_general_ci DEFAULT 'Scheduled',
  `notes` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_duty`
--

CREATE TABLE `supervisor_duty` (
  `id` int NOT NULL,
  `supervisor_id` int NOT NULL,
  `outlet_id` int NOT NULL,
  `duty_date` date NOT NULL,
  `shift` enum('Morning','Afternoon','Night') COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `assigned_by` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `targets`
--

CREATE TABLE `targets` (
  `id` int NOT NULL,
  `target_type` enum('agent','outlet') COLLATE utf8mb4_general_ci NOT NULL,
  `assigned_to_id` int NOT NULL,
  `target_value` decimal(10,2) NOT NULL,
  `period_type` enum('daily','weekly','monthly') COLLATE utf8mb4_general_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `translations`
--

CREATE TABLE `translations` (
  `id` int NOT NULL,
  `language_code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `translation_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `translation_value` text COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `translations`
--

INSERT INTO `translations` (`id`, `language_code`, `translation_key`, `translation_value`) VALUES
(1, 'en', 'dashboard', 'Dashboard'),
(2, 'en', 'outlets', 'Outlets'),
(3, 'en', 'agents', 'Agents'),
(4, 'en', 'supervisors', 'Supervisors'),
(5, 'en', 'services', 'Services'),
(6, 'en', 'purposes', 'Visit Purposes'),
(7, 'en', 'reports', 'Reports'),
(8, 'en', 'settings', 'Settings'),
(9, 'en', 'logout', 'Logout'),
(10, 'en', 'welcome', 'Welcome'),
(11, 'en', 'visitors', 'Visitors'),
(12, 'en', 'sales', 'Sales'),
(13, 'en', 'stock', 'Stock'),
(14, 'en', 'cash', 'Cash Reconciliation'),
(15, 'en', 'save', 'Save'),
(16, 'en', 'cancel', 'Cancel'),
(17, 'en', 'delete', 'Delete'),
(18, 'en', 'edit', 'Edit'),
(19, 'en', 'add', 'Add'),
(20, 'en', 'search', 'Search'),
(21, 'en', 'filter', 'Filter'),
(22, 'en', 'export', 'Export'),
(23, 'en', 'print', 'Print'),
(24, 'en', 'total', 'Total'),
(25, 'en', 'amount', 'Amount'),
(26, 'en', 'status', 'Status'),
(27, 'en', 'date', 'Date'),
(28, 'en', 'time', 'Time'),
(29, 'en', 'action', 'Action'),
(30, 'en', 'confirm', 'Confirm'),
(31, 'en', 'success', 'Success'),
(32, 'en', 'error', 'Error'),
(33, 'en', 'warning', 'Warning'),
(34, 'en', 'info', 'Information'),
(35, 'fr', 'dashboard', 'Tableau de bord'),
(36, 'fr', 'outlets', 'Points de vente'),
(37, 'fr', 'agents', 'Agents'),
(38, 'fr', 'supervisors', 'Superviseurs'),
(39, 'fr', 'services', 'Services'),
(40, 'fr', 'purposes', 'Objets de visite'),
(41, 'fr', 'reports', 'Rapports'),
(42, 'fr', 'settings', 'Paramètres'),
(43, 'fr', 'logout', 'Déconnexion'),
(44, 'fr', 'welcome', 'Bienvenue'),
(45, 'fr', 'visitors', 'Visiteurs'),
(46, 'fr', 'sales', 'Ventes'),
(47, 'fr', 'stock', 'Stock'),
(48, 'fr', 'cash', 'Rapprochement'),
(49, 'fr', 'save', 'Enregistrer'),
(50, 'fr', 'cancel', 'Annuler'),
(51, 'fr', 'delete', 'Supprimer'),
(52, 'fr', 'edit', 'Modifier'),
(53, 'fr', 'add', 'Ajouter'),
(54, 'fr', 'search', 'Rechercher'),
(55, 'fr', 'filter', 'Filtrer'),
(56, 'fr', 'export', 'Exporter'),
(57, 'fr', 'print', 'Imprimer'),
(58, 'fr', 'total', 'Total'),
(59, 'fr', 'amount', 'Montant'),
(60, 'fr', 'status', 'Statut'),
(61, 'fr', 'date', 'Date'),
(62, 'fr', 'time', 'Heure'),
(63, 'fr', 'action', 'Action'),
(64, 'ar', 'dashboard', 'لوحة القيادة'),
(65, 'ar', 'outlets', 'الفروع'),
(66, 'ar', 'agents', 'الوكلاء'),
(67, 'ar', 'supervisors', 'المشرفون'),
(68, 'ar', 'services', 'الخدمات'),
(69, 'ar', 'purposes', 'أغراض الزيارة'),
(70, 'ar', 'reports', 'التقارير'),
(71, 'ar', 'settings', 'الإعدادات'),
(72, 'ar', 'logout', 'تسجيل الخروج'),
(73, 'ar', 'welcome', 'مرحباً'),
(74, 'ar', 'visitors', 'الزوار'),
(75, 'ar', 'sales', 'المبيعات'),
(76, 'ar', 'stock', 'المخزون'),
(77, 'ar', 'cash', 'تسوية النقدية'),
(78, 'ar', 'save', 'حفظ'),
(79, 'ar', 'cancel', 'إلغاء'),
(80, 'ar', 'delete', 'حذف'),
(81, 'ar', 'edit', 'تعديل'),
(82, 'ar', 'add', 'إضافة'),
(83, 'ar', 'search', 'بحث'),
(84, 'ar', 'filter', 'تصفية');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','super_admin','supervisor','agent') COLLATE utf8mb4_general_ci NOT NULL,
  `outlet_id` int DEFAULT NULL,
  `shift` enum('Morning','Afternoon','Night','Rotational') COLLATE utf8mb4_general_ci DEFAULT 'Morning',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `last_logout` timestamp NULL DEFAULT NULL,
  `theme_preference` enum('light','dark','auto') COLLATE utf8mb4_general_ci DEFAULT 'light',
  `language_preference` varchar(10) COLLATE utf8mb4_general_ci DEFAULT 'en'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `role`, `outlet_id`, `shift`, `is_active`, `created_at`, `last_login`, `last_logout`, `theme_preference`, `language_preference`) VALUES
(1, 'admin', '$2y$12$Mq1GuBgroFGGzudJ18VUr.qrV1e2S4TwdQwVruK2.OJMKqD13oXLu', 'System Administrator', 'admin', NULL, 'Morning', 1, '2026-02-12 14:59:18', '2026-02-25 22:47:15', '2026-02-25 22:46:36', 'light', 'en'),
(2, 'agent2', '$2y$10$d7/55YGl4pYnZSAIiGpr8Ok033DwhyzR12OTBh1.b7pfD9v6lcaj6', 'Isatou Joof', 'agent', 8, 'Morning', 1, '2026-02-12 16:48:29', '2026-02-24 13:32:17', '2026-02-24 13:32:43', 'light', 'en'),
(3, 'supervisor1', '$2y$12$bYBdviRjDriJCQnYQQ94G.xw9G43HfGMWnZw/N5EmRI2wh5ksAEya', 'Supervisor Comium', 'supervisor', 8, 'Morning', 1, '2026-02-13 08:36:19', '2026-02-25 22:39:56', '2026-02-25 22:42:12', 'light', 'en'),
(4, 'Samusa', '$2y$10$9d5C2e8705vZbhA2aSQeu.a6EbC4RevMWJ3HTPcwiaT.ARnkejT4C', 'Fatoumatta Samusa', 'agent', 8, 'Rotational', 0, '2026-02-13 12:47:14', NULL, NULL, 'light', 'en'),
(8, 'supervisor2', '$2y$10$EMtFki7UtAioh.Q8LWWXpuh2z67ld4eYegSV7g854untcIkHMTG4q', 'Comium Supervisor', 'supervisor', 9, 'Morning', 1, '2026-02-25 11:46:27', '2026-02-25 12:04:40', '2026-02-25 12:04:57', 'light', 'en'),
(9, 'agent3', '$2y$10$P/pZsul84.YOPOuXjcYw9eNtjWi8x4rl2RfoRJsbBbPwS2K4X2rAW', 'Fatoumatta Samusa', 'agent', 8, 'Morning', 1, '2026-02-25 22:43:47', '2026-02-25 22:44:06', '2026-02-25 22:45:21', 'light', 'en');

-- --------------------------------------------------------

--
-- Table structure for table `visitors`
--

CREATE TABLE `visitors` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `contact` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gender` enum('Male','Female','Other') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `purpose_id` int DEFAULT NULL,
  `service_id` int DEFAULT NULL,
  `complaint_reason_id` int DEFAULT NULL,
  `quantity` int DEFAULT '1',
  `agent_id` int DEFAULT NULL,
  `outlet_id` int DEFAULT NULL,
  `status` enum('Waiting','In Progress','Completed','Cancelled') COLLATE utf8mb4_general_ci DEFAULT 'Waiting',
  `notes` text COLLATE utf8mb4_general_ci,
  `visit_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `attended_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `handling_seconds` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visitors`
--

INSERT INTO `visitors` (`id`, `name`, `contact`, `gender`, `purpose_id`, `service_id`, `quantity`, `agent_id`, `outlet_id`, `status`, `notes`, `visit_time`, `updated_at`) VALUES
(1, 'Lamin Sanyang', '6600309', 'Male', 4, 7, 1, 2, 8, 'Completed', '', '2026-02-13 15:07:47', '2026-02-13 09:38:22'),
(2, 'Lamin Sanyang', '6600309', 'Male', 4, 7, 1, 2, 8, 'Completed', '', '2026-02-13 15:38:38', '2026-02-13 10:16:31'),
(3, 'Lamin Sanyang', '6600309', 'Male', 4, 18, 1, 2, 8, 'Completed', '', '2026-02-13 15:46:16', '2026-02-13 10:16:16'),
(4, 'Lamin Sanyang', '+2206600684', 'Male', 7, 15, 1, 2, 8, 'Completed', '', '2026-02-13 17:00:35', '2026-02-13 11:30:35'),
(5, 'Lamin Sanyang', '+2206600684', 'Male', 7, 6, 3, 2, 8, 'Completed', '', '2026-02-13 17:02:03', '2026-02-13 11:33:33'),
(6, 'Binta Jobe', '6600308', 'Female', 8, 6, 3, 2, 8, 'Completed', '', '2026-02-13 18:15:11', '2026-02-13 12:45:21'),
(7, 'Customer', '', 'Other', 5, 16, 1, 2, 8, 'Completed', 'Fast entry', '2026-02-16 15:06:03', '2026-02-16 09:36:03'),
(8, 'Customer', '', 'Other', 7, 6, 1, 2, 8, 'Completed', 'Fast entry', '2026-02-16 15:07:36', '2026-02-16 09:37:50'),
(9, 'Customer', '', 'Other', 7, 6, 3, 2, 8, 'Completed', 'Fast entry', '2026-02-16 15:08:58', '2026-02-16 09:39:07'),
(10, 'Customer', '', 'Other', 7, 6, 1, 2, 8, 'Completed', 'Fast entry', '2026-02-16 15:20:02', '2026-02-16 09:50:17'),
(11, 'Customer', '', 'Other', 7, 6, 7, 2, 8, 'Completed', 'Fast entry', '2026-02-16 15:21:34', '2026-02-16 09:51:47'),
(12, 'Customer', '', 'Other', 8, 15, 2, 2, 8, 'Completed', 'Fast entry', '2026-02-16 16:31:38', '2026-02-16 11:01:38'),
(13, 'Customer', '', 'Other', 8, 15, 2, 2, 8, 'Completed', 'Fast entry', '2026-02-16 17:21:48', '2026-02-16 11:51:48'),
(14, 'Customer', '', 'Other', 6, 16, 1, 2, 8, 'Completed', 'Fast entry', '2026-02-17 14:36:08', '2026-02-17 09:06:08'),
(15, 'Customer', '', 'Other', 8, 15, 1, 2, 8, 'Completed', 'Fast entry', '2026-02-19 08:15:27', '2026-02-19 08:15:27'),
(16, 'Customer', '', 'Other', 2, 11, 1, 2, 8, 'Waiting', 'Fast entry', '2026-02-24 13:32:32', '2026-02-24 13:32:32'),
(17, 'Customer', '', 'Other', 8, 17, 1, 9, 8, 'Completed', 'Fast entry', '2026-02-25 22:44:45', '2026-02-25 22:44:45');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agent_shifts`
--
ALTER TABLE `agent_shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`),
  ADD KEY `outlet_id` (`outlet_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cash_reconciliation`
--
ALTER TABLE `cash_reconciliation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `outlet_id` (`outlet_id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Indexes for table `complaint_reasons`
--
ALTER TABLE `complaint_reasons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_complaint_reason` (`reason`),
  ADD KEY `idx_complaint_reasons_active` (`is_active`,`sort_order`);

--
-- Indexes for table `outlets`
--
ALTER TABLE `outlets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `outlet_stock`
--
ALTER TABLE `outlet_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_outlet_product` (`outlet_id`,`product_id`,`batch_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `purpose_categories`
--
ALTER TABLE `purpose_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purpose_of_visit`
--
ALTER TABLE `purpose_of_visit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visitor_id` (`visitor_id`),
  ADD KEY `agent_id` (`agent_id`),
  ADD KEY `outlet_id` (`outlet_id`),
  ADD KEY `idx_sales_handle_seconds` (`handle_seconds`),
  ADD KEY `idx_sales_handle_times` (`handle_started_at`,`handle_completed_at`);


--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `stock_alerts`
--
ALTER TABLE `stock_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `outlet_id` (`outlet_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `stock_batches`
--
ALTER TABLE `stock_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `stock_categories`
--
ALTER TABLE `stock_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_counts`
--
ALTER TABLE `stock_counts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `outlet_id` (`outlet_id`),
  ADD KEY `counted_by` (`counted_by`);

--
-- Indexes for table `stock_count_items`
--
ALTER TABLE `stock_count_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_count_id` (`stock_count_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `outlet_id` (`outlet_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `stock_products`
--
ALTER TABLE `stock_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `stock_requests`
--
ALTER TABLE `stock_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_number` (`reference_number`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `fulfilled_by` (`fulfilled_by`),
  ADD KEY `idx_stock_requests_status` (`status`),
  ADD KEY `idx_stock_requests_outlet` (`outlet_id`);

--
-- Indexes for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_outlet_id` (`from_outlet_id`),
  ADD KEY `to_outlet_id` (`to_outlet_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `transferred_by` (`transferred_by`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `supervisor_attendance`
--
ALTER TABLE `supervisor_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `outlet_id` (`outlet_id`);

--
-- Indexes for table `supervisor_duty`
--
ALTER TABLE `supervisor_duty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_supervisor_duty` (`supervisor_id`,`outlet_id`,`duty_date`,`shift`),
  ADD KEY `outlet_id` (`outlet_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `targets`
--
ALTER TABLE `targets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `translations`
--
ALTER TABLE `translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_translation` (`language_code`,`translation_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `visitors`
--
ALTER TABLE `visitors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purpose_id` (`purpose_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `agent_id` (`agent_id`),
  ADD KEY `outlet_id` (`outlet_id`),
  ADD KEY `idx_visitors_complaint_reason` (`complaint_reason_id`),
  ADD KEY `idx_visitors_handling_time` (`handling_seconds`),
  ADD KEY `idx_visitors_attended_completed` (`attended_at`,`completed_at`),
  ADD KEY `idx_visitors_report_date_status` (`visit_time`,`status`,`outlet_id`,`agent_id`,`service_id`,`purpose_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agent_shifts`
--
ALTER TABLE `agent_shifts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=327;

--
-- AUTO_INCREMENT for table `cash_reconciliation`
--
ALTER TABLE `cash_reconciliation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_reasons`
--
ALTER TABLE `complaint_reasons`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `outlets`
--
ALTER TABLE `outlets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `outlet_stock`
--
ALTER TABLE `outlet_stock`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `purpose_categories`
--
ALTER TABLE `purpose_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `purpose_of_visit`
--
ALTER TABLE `purpose_of_visit`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;


--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `stock_alerts`
--
ALTER TABLE `stock_alerts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_batches`
--
ALTER TABLE `stock_batches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `stock_categories`
--
ALTER TABLE `stock_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `stock_counts`
--
ALTER TABLE `stock_counts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_count_items`
--
ALTER TABLE `stock_count_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `stock_products`
--
ALTER TABLE `stock_products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `stock_requests`
--
ALTER TABLE `stock_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `supervisor_attendance`
--
ALTER TABLE `supervisor_attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supervisor_duty`
--
ALTER TABLE `supervisor_duty`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `targets`
--
ALTER TABLE `targets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `translations`
--
ALTER TABLE `translations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `visitors`
--
ALTER TABLE `visitors`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agent_shifts`
--
ALTER TABLE `agent_shifts`
  ADD CONSTRAINT `agent_shifts_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `agent_shifts_ibfk_2` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`);

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `cash_reconciliation`
--
ALTER TABLE `cash_reconciliation`
  ADD CONSTRAINT `cash_reconciliation_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
  ADD CONSTRAINT `cash_reconciliation_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `outlet_stock`
--
ALTER TABLE `outlet_stock`
  ADD CONSTRAINT `outlet_stock_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
  ADD CONSTRAINT `outlet_stock_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `stock_products` (`id`),
  ADD CONSTRAINT `outlet_stock_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `stock_batches` (`id`);

--
-- Constraints for table `purpose_of_visit`
--
ALTER TABLE `purpose_of_visit`
  ADD CONSTRAINT `purpose_of_visit_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `purpose_categories` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`);

--
-- Constraints for table `stock_alerts`
--
ALTER TABLE `stock_alerts`
  ADD CONSTRAINT `stock_alerts_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
  ADD CONSTRAINT `stock_alerts_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `stock_products` (`id`),
  ADD CONSTRAINT `stock_alerts_ibfk_3` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_batches`
--
ALTER TABLE `stock_batches`
  ADD CONSTRAINT `stock_batches_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `stock_products` (`id`);

--
-- Constraints for table `stock_counts`
--
ALTER TABLE `stock_counts`
  ADD CONSTRAINT `stock_counts_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
  ADD CONSTRAINT `stock_counts_ibfk_2` FOREIGN KEY (`counted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_count_items`
--
ALTER TABLE `stock_count_items`
  ADD CONSTRAINT `stock_count_items_ibfk_1` FOREIGN KEY (`stock_count_id`) REFERENCES `stock_counts` (`id`),
  ADD CONSTRAINT `stock_count_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `stock_products` (`id`),
  ADD CONSTRAINT `stock_count_items_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `stock_batches` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `stock_products` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `stock_batches` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_6` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_products`
--
ALTER TABLE `stock_products`
  ADD CONSTRAINT `stock_products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `stock_categories` (`id`);

--
-- Constraints for table `stock_requests`
--
ALTER TABLE `stock_requests`
  ADD CONSTRAINT `stock_requests_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
  ADD CONSTRAINT `stock_requests_ibfk_2` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_requests_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `stock_products` (`id`),
  ADD CONSTRAINT `stock_requests_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_requests_ibfk_5` FOREIGN KEY (`fulfilled_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`from_outlet_id`) REFERENCES `outlets` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`to_outlet_id`) REFERENCES `outlets` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `stock_products` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_4` FOREIGN KEY (`batch_id`) REFERENCES `stock_batches` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_5` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_6` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_7` FOREIGN KEY (`transferred_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_8` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `supervisor_attendance`
--
ALTER TABLE `supervisor_attendance`
  ADD CONSTRAINT `supervisor_attendance_ibfk_1` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `supervisor_attendance_ibfk_2` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`);

--
-- Constraints for table `supervisor_duty`
--
ALTER TABLE `supervisor_duty`
  ADD CONSTRAINT `supervisor_duty_ibfk_1` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `supervisor_duty_ibfk_2` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
  ADD CONSTRAINT `supervisor_duty_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `targets`
--
ALTER TABLE `targets`
  ADD CONSTRAINT `targets_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `visitors`
--
ALTER TABLE `visitors`
  ADD CONSTRAINT `visitors_ibfk_1` FOREIGN KEY (`purpose_id`) REFERENCES `purpose_of_visit` (`id`),
  ADD CONSTRAINT `fk_visitors_complaint_reason` FOREIGN KEY (`complaint_reason_id`) REFERENCES `complaint_reasons` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `visitors_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `visitors_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `visitors_ibfk_4` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------
-- Complaint / Support dynamic form module added 2026-06-17
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `support_form_fields` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `field_label` VARCHAR(150) NOT NULL,
  `field_key` VARCHAR(80) NOT NULL,
  `field_type` ENUM('text','textarea','select','number','date','phone','email') NOT NULL DEFAULT 'text',
  `field_options` TEXT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_support_field_key` (`field_key`),
  KEY `idx_support_fields_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ticket_no` VARCHAR(40) NOT NULL,
  `submitted_by` INT NULL,
  `submitted_role` VARCHAR(30) NULL,
  `outlet_id` INT NULL,
  `customer_name` VARCHAR(120) NULL,
  `customer_phone` VARCHAR(30) NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT 'Complaint',
  `priority` ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
  `status` ENUM('Open','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Open',
  `main_reason` VARCHAR(100) NULL,
  `sub_reason_id` INT NULL,
  `subject` VARCHAR(180) NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_support_ticket_no` (`ticket_no`),
  KEY `idx_support_date_status` (`created_at`, `status`),
  KEY `idx_support_user` (`submitted_by`, `submitted_role`),
  KEY `idx_support_outlet` (`outlet_id`),
  KEY `idx_support_reason` (`main_reason`, `sub_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `support_ticket_values` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ticket_id` INT NOT NULL,
  `field_id` INT NOT NULL,
  `field_label` VARCHAR(150) NOT NULL,
  `field_value` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_support_values_ticket` (`ticket_id`),
  KEY `idx_support_values_field` (`field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `support_form_fields` (`field_label`, `field_key`, `field_type`, `field_options`, `is_required`, `is_active`, `sort_order`) VALUES
('MSISDN / Customer Number', 'msisdn_customer_number', 'phone', '', 0, 1, 20),
('Problem Details', 'problem_details', 'textarea', '', 1, 1, 30),
('Action Taken', 'action_taken', 'textarea', '', 0, 1, 40),
('Follow-up Required', 'follow_up_required', 'select', 'No\nYes', 0, 1, 50);

INSERT IGNORE INTO `complaint_reasons` (`category`, `reason`, `sort_order`, `is_active`) VALUES
('Network', 'No Service', 10, 1),
('Network', 'Poor Signal', 20, 1),
('Network', 'Data Not Working', 30, 1),
('Billing', 'Wrong Deduction', 40, 1),
('Billing', 'Bundle Not Received', 50, 1),
('SIM / Registration', 'SIM Swap Issue', 60, 1),
('SIM / Registration', 'Registration Update', 70, 1),
('ComCach', 'Cash-in Issue', 80, 1),
('ComCach', 'Withdrawal Issue', 90, 1),
('Customer Care', 'Follow-up Required', 100, 1),
('Customer Care', 'Other', 110, 1);
-- Shift Allocation + Shift Report module merged from Showroom Information System into SIS.
-- Safe to run on existing databases. The application also creates/repairs this table automatically.

CREATE TABLE IF NOT EXISTS `shift_assignments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `agent_id` INT NOT NULL,
  `shift_date` DATE NOT NULL,
  `shift_type` ENUM('morning','afternoon','night') NOT NULL DEFAULT 'morning',
  `shift_start` TIME NOT NULL DEFAULT '08:00:00',
  `shift_end` TIME NOT NULL DEFAULT '16:00:00',
  `status` ENUM('scheduled','present','late','absent','cancelled') NOT NULL DEFAULT 'scheduled',
  `no_show_reason` VARCHAR(60) NULL,
  `notes` TEXT NULL,
  `schedule_scope` ENUM('single','weekly','monthly','custom') NOT NULL DEFAULT 'single',
  `early_end_allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `overtime_allowed` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shift_agent_date` (`agent_id`, `shift_date`),
  KEY `idx_shift_date_status` (`shift_date`, `status`),
  KEY `idx_shift_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `shift_requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `assignment_id` INT NULL,
  `agent_id` INT NOT NULL,
  `request_type` ENUM('early_end','overtime') NOT NULL DEFAULT 'early_end',
  `reason` TEXT NULL,
  `status` ENUM('pending','approved','rejected','notified') NOT NULL DEFAULT 'pending',
  `supervisor_id` INT NULL,
  `supervisor_note` TEXT NULL,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_at` DATETIME NULL,
  `overtime_started_at` DATETIME NULL,
  `overtime_minutes` INT NOT NULL DEFAULT 0,
  `logout_deadline` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shift_requests_agent` (`agent_id`, `requested_at`),
  KEY `idx_shift_requests_assignment` (`assignment_id`, `request_type`, `status`),
  KEY `idx_shift_requests_status` (`status`, `request_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- No default privileged account is created. Create the initial owner explicitly
-- with a unique password through the secured Users & Roles workflow.

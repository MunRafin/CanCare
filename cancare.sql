-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Aug 17, 2025 at 06:36 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cancare`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `symptoms` text DEFAULT NULL,
  `appointment_status` enum('made','accepted','rejected','prescribed') DEFAULT 'made',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `symptoms`, `appointment_status`, `created_at`) VALUES
(5, 1, 7, '2025-08-17', '09:00:00', 'Headache', 'prescribed', '2025-08-17 09:35:35'),
(6, 1, 5, '2025-08-21', '09:00:00', 'Ache', 'made', '2025-08-17 09:41:30'),
(7, 1, 7, '2025-08-20', '09:00:00', 'Haha', 'made', '2025-08-17 11:28:11'),
(8, 1, 7, '2025-08-22', '09:00:00', 'Haha', 'prescribed', '2025-08-17 11:29:52'),
(9, 1, 7, '2025-08-19', '10:00:00', 'haha', 'prescribed', '2025-08-17 11:30:07'),
(10, 1, 7, '2025-08-18', '08:00:00', 'hihi', 'prescribed', '2025-08-17 11:30:54');

-- --------------------------------------------------------

--
-- Table structure for table `calorie_burned`
--

CREATE TABLE `calorie_burned` (
  `id` int(11) NOT NULL,
  `exercise_log_id` int(11) NOT NULL,
  `calories_burned` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `calorie_burned`
--

INSERT INTO `calorie_burned` (`id`, `exercise_log_id`, `calories_burned`) VALUES
(1, 1, 120),
(2, 2, 200),
(3, 3, 120),
(4, 4, 400),
(5, 5, 225),
(6, 6, 105),
(7, 7, 40),
(8, 8, 360),
(9, 9, 140),
(10, 10, 200);

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `service_days` varchar(100) DEFAULT NULL,
  `service_time` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `specialization`, `qualification`, `experience_years`, `bio`, `profile_image`, `service_days`, `service_time`) VALUES
(6, 5, 'Cardiologist', 'MBBS, FCPS (Cardiology)', 10, 'Experienced cardiologist specializing in heart diseases.', 'mahin.png', 'Sun, Tue, Thu', '09:00 AM - 12:00 PM'),
(8, 7, 'Gynecologist', 'MBBS, MS (Gynecology)', 7, 'Providing care for women\'s reproductive health.', 'farzana.png', 'Mon to Fri', '08:00 AM - 11:00 AM'),
(9, 8, 'Orthopedic Surgeon', 'MBBS, MS (Orthopedics)', 9, 'Expert in bone and joint surgeries.', 'nayema.png', 'Sat, Tue, Thu', '02:00 PM - 05:00 PM');

-- --------------------------------------------------------

--
-- Table structure for table `exercise_log`
--

CREATE TABLE `exercise_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `exercise_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `repetitions` int(11) NOT NULL,
  `sets` int(11) NOT NULL,
  `calorie_burned` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exercise_log`
--

INSERT INTO `exercise_log` (`id`, `user_id`, `exercise_id`, `log_date`, `repetitions`, `sets`, `calorie_burned`) VALUES
(1, 1, 53, '2025-07-26', 10, 3, 4620.00);

-- --------------------------------------------------------

--
-- Table structure for table `exercise_prs`
--

CREATE TABLE `exercise_prs` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `benefit` text DEFAULT NULL,
  `muscle_group` varchar(100) DEFAULT NULL,
  `calorie_burn_per_rep` decimal(10,2) NOT NULL COMMENT 'Calories burned per rep for 1kg body weight'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exercise_prs`
--

INSERT INTO `exercise_prs` (`id`, `name`, `benefit`, `muscle_group`, `calorie_burn_per_rep`) VALUES
(26, 'Bicycle Crunch', 'Engages entire core musculature', 'Abs, Obliques', 0.00),
(27, 'Ab Rollout', 'Challenges core stability and strength', 'Core', 0.00),
(28, 'Side Plank', 'Strengthens obliques and lateral core', 'Obliques', 0.00),
(29, 'Flutter Kick', 'Endurance exercise for lower abs', 'Lower Abs', 0.00),
(30, 'Toe Touch', 'Focuses on upper abdominal muscles', 'Upper Abs', 0.00),
(31, 'Push-up', 'Strengthens chest, shoulders, and triceps', 'Chest, Shoulders, Triceps', 3.50),
(32, 'Pull-up', 'Develops back and biceps strength', 'Back, Biceps', 4.20),
(33, 'Bench Press', 'Builds chest, shoulder, and tricep strength', 'Chest, Shoulders, Triceps', 4.00),
(34, 'Overhead Press', 'Strengthens shoulders and triceps', 'Shoulders, Triceps', 3.80),
(35, 'Bicep Curl', 'Isolates and builds bicep muscles', 'Biceps', 2.50),
(36, 'Tricep Dip', 'Targets triceps and chest', 'Triceps, Chest', 3.20),
(37, 'Lat Pulldown', 'Develops latissimus dorsi muscles', 'Back', 3.60),
(38, 'Shoulder Shrug', 'Strengthens trapezius muscles', 'Traps', 2.80),
(39, 'Incline Dumbbell Press', 'Focuses on upper chest development', 'Upper Chest, Shoulders', 3.90),
(40, 'Hammer Curl', 'Works biceps and forearms', 'Biceps, Forearms', 2.70),
(41, 'Squat', 'Builds leg and core strength', 'Quads, Hamstrings, Glutes', 5.00),
(42, 'Deadlift', 'Develops posterior chain strength', 'Hamstrings, Glutes, Back', 5.50),
(43, 'Lunge', 'Improves leg strength and balance', 'Quads, Hamstrings, Glutes', 4.50),
(44, 'Leg Press', 'Targets quadriceps muscles', 'Quads', 4.80),
(45, 'Calf Raise', 'Strengthens calf muscles', 'Calves', 2.00),
(46, 'Step-up', 'Works legs and improves balance', 'Quads, Hamstrings, Glutes', 4.20),
(47, 'Romanian Deadlift', 'Focuses on hamstring development', 'Hamstrings, Glutes', 4.70),
(48, 'Bulgarian Split Squat', 'Single-leg quad and glute exercise', 'Quads, Glutes', 4.60),
(49, 'Hip Thrust', 'Isolates and strengthens glutes', 'Glutes', 3.80),
(50, 'Wall Sit', 'Isometric quad endurance exercise', 'Quads', 2.50),
(51, 'Sit-up', 'Strengthens abdominal muscles', 'Abs', 2.80),
(52, 'Plank', 'Builds core endurance and stability', 'Core', 1.50),
(53, 'Russian Twist', 'Works obliques and rotational strength', 'Obliques', 2.20),
(54, 'Leg Raise', 'Targets lower abdominal muscles', 'Lower Abs', 3.00),
(55, 'Hanging Knee Raise', 'Advanced core and hip flexor exercise', 'Abs, Hip Flexors', 3.20),
(56, 'Bicycle Crunch', 'Engages entire core musculature', 'Abs, Obliques', 2.50),
(57, 'Ab Rollout', 'Challenges core stability and strength', 'Core', 3.50),
(58, 'Side Plank', 'Strengthens obliques and lateral core', 'Obliques', 1.80),
(59, 'Flutter Kick', 'Endurance exercise for lower abs', 'Lower Abs', 2.00),
(60, 'Toe Touch', 'Focuses on upper abdominal muscles', 'Upper Abs', 2.30);

-- --------------------------------------------------------

--
-- Table structure for table `food_datapg`
--

CREATE TABLE `food_datapg` (
  `id` int(11) NOT NULL,
  `food_name` varchar(100) NOT NULL,
  `protein_mg_pg` float NOT NULL,
  `carb_mg_pg` float NOT NULL,
  `fat_mg_pg` float NOT NULL,
  `vitamin_mg_pg` float NOT NULL,
  `mineral_mg_pg` float NOT NULL,
  `water_mg_pg` float NOT NULL,
  `calorie_per_g` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_datapg`
--

INSERT INTO `food_datapg` (`id`, `food_name`, `protein_mg_pg`, `carb_mg_pg`, `fat_mg_pg`, `vitamin_mg_pg`, `mineral_mg_pg`, `water_mg_pg`, `calorie_per_g`) VALUES
(1, 'Rice', 20, 780, 10, 5, 2, 150, 3.6),
(2, 'Wheat Bread', 90, 490, 30, 4, 2, 380, 2.5),
(3, 'Chicken Breast', 310, 0, 35, 3, 5, 600, 2),
(4, 'Salmon', 200, 0, 120, 2, 3, 670, 2.1),
(5, 'Eggs', 130, 100, 100, 15, 6, 640, 1.8),
(6, 'Milk', 33, 50, 4, 10, 7, 900, 0.6),
(7, 'Cheese', 250, 15, 300, 8, 10, 400, 4),
(8, 'Tofu', 80, 20, 45, 5, 8, 830, 1.1),
(9, 'Lentils (cooked)', 90, 200, 8, 6, 9, 690, 1.2),
(10, 'Almonds', 210, 220, 500, 3, 5, 50, 6),
(11, 'Spinach', 30, 40, 4, 20, 13, 850, 0.4),
(12, 'Broccoli', 28, 60, 4, 30, 9, 850, 0.5),
(13, 'Apple', 3, 130, 2, 5, 3, 850, 0.5),
(14, 'Banana', 12, 220, 2, 7, 4, 760, 0.9),
(15, 'Orange', 9, 120, 2, 15, 4, 830, 0.6),
(16, 'Beef', 260, 0, 200, 2, 6, 540, 2.8),
(17, 'Pork', 250, 0, 230, 1, 5, 500, 3.2),
(18, 'Potatoes', 20, 170, 1, 6, 4, 800, 0.8),
(19, 'Carrots', 8, 100, 1, 25, 5, 860, 0.4),
(20, 'Cucumber', 5, 30, 0, 10, 4, 920, 0.3),
(21, 'Tomato', 9, 40, 1, 12, 6, 910, 0.4),
(22, 'Oats', 170, 660, 70, 7, 6, 200, 4.1),
(23, 'Peanuts', 260, 160, 490, 3, 4, 60, 5.7),
(24, 'Yogurt', 50, 70, 20, 10, 7, 850, 1),
(25, 'Coconut', 30, 150, 330, 2, 5, 450, 4.2),
(26, 'Avocado', 20, 80, 150, 12, 6, 730, 2),
(27, 'Mango', 10, 150, 3, 10, 4, 820, 0.6),
(28, 'Strawberry', 8, 80, 1, 25, 4, 870, 0.5),
(29, 'Blueberry', 6, 90, 1, 18, 3, 850, 0.6),
(30, 'Sweet Potato', 15, 190, 1, 20, 5, 780, 0.9),
(31, 'Cooked Rice', 27, 282, 3, 0.001, 3, 684, 1.26),
(32, 'Masoor Dal (Cooked)', 66, 153, 8, 0.002, 5, 756, 0.95),
(33, 'Roti (Chapati)', 92, 460, 24, 0.003, 8, 300, 2.65),
(34, 'Panta Bhat', 25, 265, 2, 0.001, 3, 698, 1.2),
(35, 'Luchi (Fried)', 40, 350, 250, 0.002, 5, 350, 3.21),
(36, 'Hilsa Fish', 210, 0, 140, 0.005, 15, 630, 2.1),
(37, 'Rohu Fish', 170, 0, 50, 0.004, 12, 770, 1.13),
(38, 'Katla Fish', 180, 0, 60, 0.004, 13, 750, 1.2),
(39, 'Prawn (Chingri)', 240, 5, 15, 0.003, 20, 730, 1.3),
(40, 'Bhetki Fish', 190, 0, 25, 0.003, 10, 780, 0.96),
(41, 'Shorshe Shaak', 29, 47, 4, 0.9, 15, 910, 0.34),
(42, 'Aloo Posto', 30, 120, 90, 0.05, 12, 750, 1.65),
(43, 'Begun Bhaja', 15, 80, 120, 0.06, 8, 780, 1.45),
(44, 'Misti Kumro', 9, 60, 2, 0.2, 7, 920, 0.29),
(45, 'Uchche', 10, 35, 2, 0.3, 8, 930, 0.2),
(46, 'Murgir Jhol (Chicken Curry)', 180, 20, 100, 0.01, 12, 680, 1.72),
(47, 'Dim Bhaja (Fried Egg)', 130, 10, 110, 0.08, 9, 740, 1.55),
(48, 'Goru Mangsho (Beef)', 260, 0, 150, 0.005, 18, 580, 2.35),
(49, 'Khashir Mangsho (Mutton)', 250, 0, 180, 0.006, 17, 560, 2.42),
(50, 'Doi Chicken', 160, 50, 120, 0.02, 14, 660, 1.78),
(51, 'Mishti Doi', 35, 150, 35, 0.01, 12, 760, 1.04),
(52, 'Rasgulla', 25, 400, 15, 0.003, 6, 550, 1.85),
(53, 'Sandesh', 60, 300, 90, 0.01, 15, 540, 2.19),
(54, 'Chhana', 180, 30, 120, 0.01, 20, 650, 2.07),
(55, 'Ghee', 1, 1, 995, 0.001, 0.5, 3, 8.97),
(56, 'Mung Dal', 240, 630, 10, 0.4, 25, 100, 3.77),
(57, 'Cholar Dal', 180, 600, 35, 0.3, 22, 160, 3.74),
(58, 'Motor Shuti', 55, 140, 5, 0.3, 15, 780, 0.85),
(59, 'Kabuli Chana', 190, 630, 60, 0.3, 28, 110, 3.78),
(60, 'Masur Dal', 260, 600, 20, 0.4, 30, 90, 3.78),
(61, 'Aam (Mango)', 6, 150, 4, 0.3, 8, 830, 0.65),
(62, 'Kola (Banana)', 11, 230, 3, 0.2, 7, 750, 0.97),
(63, 'Peyara (Guava)', 25, 140, 5, 2.3, 15, 810, 0.73),
(64, 'Jam (Blackberry)', 7, 100, 3, 0.2, 9, 880, 0.45),
(65, 'Lebu (Lemon)', 7, 90, 2, 0.5, 8, 890, 0.41),
(66, 'Aloor Chop', 30, 150, 180, 0.1, 12, 620, 2.07),
(67, 'Muri (Puffed Rice)', 80, 780, 35, 0.05, 25, 80, 3.85),
(68, 'Jilipi', 20, 750, 120, 0.001, 5, 100, 4.18),
(69, 'Singara', 40, 200, 220, 0.08, 15, 520, 2.52),
(70, 'Tele Bhaja', 25, 120, 250, 0.06, 10, 590, 2.65),
(71, 'Mustard Oil', 0, 0, 1000, 0.001, 0.1, 0, 8.84),
(72, 'Gur (Jaggery)', 10, 950, 1, 0.02, 30, 80, 3.83),
(73, 'Atta (Wheat Flour)', 130, 750, 15, 0.05, 12, 120, 3.4),
(74, 'Radhuni', 15, 400, 25, 0.1, 45, 500, 1.85),
(75, 'Panch Phoron', 120, 650, 180, 0.2, 60, 80, 4.25),
(76, 'Cha (Milk Tea)', 15, 50, 35, 0.01, 5, 890, 0.51),
(77, 'Dudh (Milk)', 34, 50, 35, 0.04, 12, 870, 0.64),
(78, 'Narkel Pani', 2, 40, 2, 0.03, 7, 950, 0.19),
(79, 'Aamras', 5, 160, 2, 0.25, 6, 820, 0.69),
(80, 'Borhani', 20, 80, 15, 0.08, 10, 870, 0.55),
(81, 'Pabda Fish', 165, 0, 45, 0.003, 10, 780, 0.98),
(82, 'Ilish Paturi', 185, 20, 140, 0.006, 14, 650, 1.82),
(83, 'Chingri Malai', 210, 35, 110, 0.005, 22, 620, 1.85),
(84, 'Koi Fish', 175, 0, 35, 0.004, 11, 770, 0.95),
(85, 'Pomfret Fry', 180, 0, 90, 0.005, 13, 720, 1.48),
(86, 'Lau Shaak', 22, 42, 4, 0.8, 18, 910, 0.28),
(87, 'Neem Begun', 18, 85, 130, 0.07, 9, 760, 1.56),
(88, 'Potol Chorchori', 25, 75, 55, 0.6, 12, 830, 0.93),
(89, 'Mulo Shaak', 27, 55, 5, 1.2, 22, 890, 0.35),
(90, 'Enchor Bharta', 35, 120, 85, 0.5, 15, 740, 1.45),
(91, 'Matar Dal', 230, 610, 25, 0.35, 27, 110, 3.7),
(92, 'Bhaja Moong Dal', 255, 650, 15, 0.3, 30, 70, 3.82),
(93, 'Kala Chana', 190, 640, 55, 0.4, 30, 120, 3.8),
(94, 'Motor Dal', 220, 620, 30, 0.4, 25, 105, 3.72),
(95, 'Chholar Dal', 170, 590, 40, 0.3, 20, 180, 3.65),
(96, 'Khashir Rezala', 220, 25, 200, 0.007, 16, 550, 2.55),
(97, 'Murgir Curry', 190, 15, 120, 0.01, 13, 660, 1.78),
(98, 'Duck Curry', 200, 5, 180, 0.008, 15, 610, 2.3),
(99, 'Bhuna Khichuri', 85, 300, 90, 0.15, 25, 500, 2.25),
(100, 'Egg Curry', 125, 35, 140, 0.09, 12, 700, 1.72),
(101, 'Pantua', 25, 680, 180, 0.002, 8, 100, 4.42),
(102, 'Chomchom', 35, 620, 120, 0.003, 10, 210, 3.85),
(103, 'Langcha', 30, 650, 200, 0.002, 9, 110, 4.25),
(104, 'Mihidana', 15, 920, 40, 0.001, 5, 20, 4.25),
(105, 'Gurer Payesh', 45, 250, 60, 0.02, 18, 640, 1.75),
(106, 'Mochar Chop', 30, 130, 160, 0.2, 14, 660, 1.85),
(107, 'Beguni', 20, 100, 180, 0.1, 8, 690, 1.85),
(108, 'Phuchka', 25, 300, 150, 0.15, 12, 510, 2.35),
(109, 'Jhal Muri', 75, 750, 100, 0.2, 30, 100, 3.95),
(110, 'Tele Bhaja Mix', 40, 150, 230, 0.15, 20, 580, 2.65),
(111, 'Kathal (Jackfruit)', 18, 230, 1, 0.5, 10, 740, 0.95),
(112, 'Ata (Sugar Apple)', 17, 250, 6, 0.4, 12, 720, 1.04),
(113, 'Tetul (Tamarind)', 30, 620, 5, 0.6, 35, 310, 2.35),
(114, 'Kamranga (Star Fruit)', 5, 40, 1, 0.3, 6, 910, 0.19),
(115, 'Narikel (Coconut)', 33, 150, 330, 0.1, 8, 470, 3.45),
(116, 'Chhana Pora', 180, 300, 130, 0.02, 25, 370, 2.95),
(117, 'Ghol (Buttermilk)', 35, 40, 10, 0.03, 15, 900, 0.38),
(118, 'Dudher Sandesh', 85, 350, 150, 0.02, 20, 490, 3.05),
(119, 'Narkel Naru', 25, 750, 220, 0.01, 12, 30, 4.55),
(120, 'Aam Doi', 30, 180, 40, 0.25, 14, 730, 1.15),
(121, 'Panta Bhaat', 25, 260, 3, 0.001, 4, 700, 1.18),
(122, 'Khichuri', 75, 280, 85, 0.2, 22, 540, 2.15),
(123, 'Pitha (Steamed)', 50, 600, 35, 0.1, 15, 260, 3.15),
(124, 'Muri Ghonto', 100, 250, 110, 0.3, 25, 520, 2.3),
(125, 'Bhaat-er Bora', 65, 350, 150, 0.15, 20, 420, 2.95),
(126, 'Posto Bata', 180, 120, 450, 0.3, 35, 220, 4.35),
(127, 'Kasundi', 20, 150, 80, 0.8, 40, 710, 1.45),
(128, 'Aam Kasundi', 15, 180, 70, 1.2, 35, 700, 1.4),
(129, 'Narkel-er Chutney', 25, 350, 180, 0.5, 20, 420, 3.05),
(130, 'Bori (Sun-dried Lentil Dumpling)', 350, 550, 25, 0.4, 45, 30, 4.05);

-- --------------------------------------------------------

--
-- Table structure for table `food_logs`
--

CREATE TABLE `food_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `food_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `amount_in_grams` float NOT NULL,
  `calculated_calorie` float NOT NULL,
  `media_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_logs`
--

INSERT INTO `food_logs` (`id`, `user_id`, `food_id`, `log_date`, `amount_in_grams`, `calculated_calorie`, `media_path`, `description`) VALUES
(1, 1, 1, '2025-07-25', 500, 1800, NULL, NULL),
(2, 1, 5, '2025-07-25', 500, 900, NULL, NULL),
(3, 1, 5, '2025-07-26', 20, 36, NULL, NULL),
(4, 1, 37, '2025-08-05', 500, 565, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `medicine_id` int(11) NOT NULL,
  `medicine_name` varchar(100) NOT NULL,
  `producer_name` varchar(100) DEFAULT NULL,
  `core_ingredient` varchar(100) DEFAULT NULL,
  `amount_per_gram` decimal(6,3) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'g',
  `use_case` text DEFAULT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `side_effects` text DEFAULT NULL,
  `shelf_life` varchar(50) DEFAULT NULL,
  `storage_conditions` varchar(100) DEFAULT NULL,
  `prescription_required` tinyint(1) DEFAULT 0,
  `price_per_unit` decimal(8,2) DEFAULT NULL,
  `added_on` datetime DEFAULT current_timestamp(),
  `taking_condition` varchar(100) DEFAULT 'As directed by physician'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`medicine_id`, `medicine_name`, `producer_name`, `core_ingredient`, `amount_per_gram`, `unit`, `use_case`, `dosage`, `side_effects`, `shelf_life`, `storage_conditions`, `prescription_required`, `price_per_unit`, `added_on`, `taking_condition`) VALUES
(1, 'Paracetamol 500mg', 'GlaxoSmithKline', 'Paracetamol', 0.500, 'g', 'Pain relief, Fever reduction', '1 tablet every 6 hours', 'Nausea, Rash', '2 years', 'Store below 25°C, dry place', 0, 2.50, '2025-07-28 08:30:59', 'After meals'),
(2, 'Amoxicillin 250mg', 'ACI Limited', 'Amoxicillin', 0.250, 'g', 'Bacterial Infections', '1 capsule every 8 hours for 7 days', 'Diarrhea, Allergic reactions', '3 years', 'Store below 30°C', 1, 5.00, '2025-07-28 08:30:59', 'Before meals'),
(3, 'Aspirin 81mg', 'Bayer', 'Acetylsalicylic Acid', 0.081, 'g', 'Heart attack prevention', '1 tablet daily', 'Stomach upset, Bleeding risk', '3 years', 'Keep in a cool, dry place', 1, 3.00, '2025-07-28 08:30:59', 'With water'),
(4, 'Cetirizine 10mg', 'Square Pharmaceuticals', 'Cetirizine', 0.010, 'g', 'Allergy relief', '1 tablet daily', 'Drowsiness, Dry mouth', '3 years', 'Store below 25°C', 0, 4.00, '2025-07-28 08:30:59', 'At bedtime if drowsy'),
(5, 'Omeprazole 20mg', 'Incepta Pharmaceuticals', 'Omeprazole', 0.020, 'g', 'Gastric ulcer, Acid reflux', '1 capsule daily before breakfast', 'Headache, Abdominal pain', '2 years', 'Store in dry place', 1, 6.50, '2025-07-28 08:30:59', 'Empty stomach'),
(6, 'Azithromycin 500mg', 'Renata Limited', 'Azithromycin', 0.500, 'g', 'Bacterial infections', '1 tablet daily for 3 days', 'Nausea, Diarrhea', '3 years', 'Store below 30°C', 1, 12.00, '2025-07-28 08:30:59', 'After meals'),
(7, 'Metformin 500mg', 'Square Pharmaceuticals', 'Metformin', 0.500, 'g', 'Type 2 Diabetes', '1 tablet twice daily', 'Stomach upset, Diarrhea', '3 years', 'Keep at room temperature', 1, 3.50, '2025-07-28 08:30:59', 'With meals'),
(8, 'Atorvastatin 10mg', 'Aristopharma Ltd.', 'Atorvastatin', 0.010, 'g', 'Cholesterol reduction', '1 tablet daily at night', 'Muscle pain, Liver enzyme elevation', '3 years', 'Store below 25°C', 1, 7.00, '2025-07-28 08:30:59', 'At bedtime'),
(9, 'Losartan 50mg', 'Eskayef Pharmaceuticals', 'Losartan', 0.050, 'g', 'Hypertension', '1 tablet daily', 'Dizziness, Fatigue', '2 years', 'Keep in dry place', 1, 5.00, '2025-07-28 08:30:59', 'After meals'),
(10, 'Montelukast 10mg', 'ACI Limited', 'Montelukast', 0.010, 'g', 'Asthma, Allergic rhinitis', '1 tablet daily at night', 'Headache, Abdominal pain', '3 years', 'Store below 25°C', 1, 8.00, '2025-07-28 08:30:59', 'At bedtime'),
(11, 'Ibuprofen 400mg', 'GlaxoSmithKline', 'Ibuprofen', 0.400, 'g', 'Pain, Inflammation', '1 tablet every 6-8 hours', 'Stomach upset, Dizziness', '3 years', 'Cool, dry place', 0, 3.00, '2025-07-28 08:30:59', 'After meals'),
(12, 'Ranitidine 150mg', 'Square Pharmaceuticals', 'Ranitidine', 0.150, 'g', 'Acidity, Gastric ulcer', '1 tablet twice daily', 'Headache, Constipation', '2 years', 'Store below 25°C', 1, 2.50, '2025-07-28 08:30:59', 'Before meals'),
(13, 'Salbutamol Inhaler', 'Beximco Pharma', 'Salbutamol', 0.100, 'g', 'Asthma, Bronchospasm', '2 puffs as needed', 'Tremor, Palpitation', '2 years', 'Keep away from heat', 1, 250.00, '2025-07-28 08:30:59', 'As needed'),
(14, 'Clopidogrel 75mg', 'Renata Limited', 'Clopidogrel', 0.075, 'g', 'Blood thinner', '1 tablet daily', 'Bleeding, Bruising', '3 years', 'Cool, dry place', 1, 9.00, '2025-07-28 08:30:59', 'After meals'),
(15, 'Doxycycline 100mg', 'ACI Limited', 'Doxycycline', 0.100, 'g', 'Bacterial infections', '1 capsule twice daily', 'Nausea, Photosensitivity', '3 years', 'Store below 30°C', 1, 6.00, '2025-07-28 08:30:59', 'With meals'),
(16, 'Pantoprazole 40mg', 'Incepta Pharmaceuticals', 'Pantoprazole', 0.040, 'g', 'GERD, Peptic ulcer', '1 tablet daily before breakfast', 'Headache, Diarrhea', '3 years', 'Dry place', 1, 7.50, '2025-07-28 08:30:59', 'Empty stomach'),
(17, 'Levothyroxine 50mcg', 'Square Pharmaceuticals', 'Levothyroxine', 0.000, 'g', 'Hypothyroidism', '1 tablet daily', 'Palpitations, Anxiety', '2 years', 'Cool, dry place', 1, 3.50, '2025-07-28 08:30:59', 'Empty stomach'),
(18, 'Fexofenadine 120mg', 'Eskayef Pharmaceuticals', 'Fexofenadine', 0.120, 'g', 'Allergy relief', '1 tablet daily', 'Headache, Drowsiness', '3 years', 'Store below 25°C', 0, 6.00, '2025-07-28 08:30:59', 'As needed'),
(19, 'Calcium Carbonate 500mg', 'Beximco Pharma', 'Calcium Carbonate', 0.500, 'g', 'Calcium supplement', '1 tablet twice daily', 'Constipation, Bloating', '3 years', 'Room temperature', 0, 3.00, '2025-07-28 08:30:59', 'After meals'),
(20, 'Vitamin D3 1000 IU', 'Incepta Pharmaceuticals', 'Cholecalciferol', 0.025, 'g', 'Vitamin D deficiency', '1 tablet daily', 'Nausea, Fatigue', '3 years', 'Keep in dry place', 0, 4.00, '2025-07-28 08:30:59', 'After meals'),
(21, 'Loratadine 10mg', 'Renata Limited', 'Loratadine', 0.010, 'g', 'Allergy relief', '1 tablet daily', 'Drowsiness, Dry mouth', '3 years', 'Store below 30°C', 0, 3.00, '2025-07-28 08:30:59', 'At bedtime'),
(22, 'Domperidone 10mg', 'ACI Limited', 'Domperidone', 0.010, 'g', 'Nausea, Vomiting', '1 tablet three times daily', 'Dry mouth, Drowsiness', '2 years', 'Cool, dry place', 1, 2.50, '2025-07-28 08:30:59', 'Before meals'),
(23, 'Cefuroxime 500mg', 'Square Pharmaceuticals', 'Cefuroxime', 0.500, 'g', 'Bacterial infections', '1 tablet twice daily', 'Diarrhea, Rash', '3 years', 'Store below 30°C', 1, 15.00, '2025-07-28 08:30:59', 'After meals'),
(24, 'Metronidazole 400mg', 'Incepta Pharmaceuticals', 'Metronidazole', 0.400, 'g', 'Protozoal infections', '1 tablet three times daily', 'Nausea, Metallic taste', '2 years', 'Cool, dry place', 1, 3.00, '2025-07-28 08:30:59', 'After meals'),
(25, 'Hydrochlorothiazide 25mg', 'Aristopharma Ltd.', 'Hydrochlorothiazide', 0.025, 'g', 'Hypertension, Edema', '1 tablet daily', 'Electrolyte imbalance, Dizziness', '2 years', 'Store below 25°C', 1, 5.00, '2025-07-28 08:30:59', 'Morning'),
(26, 'Esomeprazole 40mg', 'Beximco Pharma', 'Esomeprazole', 0.040, 'g', 'GERD, Gastric ulcer', '1 tablet daily', 'Headache, Abdominal pain', '3 years', 'Dry place', 1, 8.50, '2025-07-28 08:30:59', 'Empty stomach'),
(27, 'Gliclazide 80mg', 'Renata Limited', 'Gliclazide', 0.080, 'g', 'Type 2 Diabetes', '1 tablet daily', 'Hypoglycemia, Weight gain', '3 years', 'Store below 30°C', 1, 4.00, '2025-07-28 08:30:59', 'With breakfast'),
(28, 'Diclofenac Sodium 50mg', 'Square Pharmaceuticals', 'Diclofenac Sodium', 0.050, 'g', 'Pain, Inflammation', '1 tablet twice daily', 'Stomach upset, Dizziness', '3 years', 'Keep at room temperature', 1, 3.50, '2025-07-28 08:30:59', 'After meals'),
(29, 'Chlorpheniramine 4mg', 'ACI Limited', 'Chlorpheniramine', 0.004, 'g', 'Allergy, Cold relief', '1 tablet every 6 hours', 'Drowsiness, Dry mouth', '2 years', 'Cool, dry place', 0, 2.00, '2025-07-28 08:30:59', 'At bedtime'),
(30, 'Zinc Sulfate 20mg', 'Eskayef Pharmaceuticals', 'Zinc Sulfate', 0.020, 'g', 'Zinc deficiency', '1 tablet daily', 'Nausea, Stomach upset', '3 years', 'Store in dry place', 0, 2.50, '2025-07-28 08:30:59', 'After meals');

-- --------------------------------------------------------

--
-- Table structure for table `medicine_log`
--

CREATE TABLE `medicine_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `med_name` varchar(100) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `time` time NOT NULL,
  `schedule_date` date NOT NULL,
  `status` enum('scheduled','done','missed') NOT NULL DEFAULT 'scheduled',
  `appointment_id` int(11) DEFAULT NULL,
  `prescription_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nutrient_needs`
--

CREATE TABLE `nutrient_needs` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `protein_mg` decimal(10,2) NOT NULL,
  `fat_mg` decimal(10,2) NOT NULL,
  `carbohydrate_mg` decimal(10,2) NOT NULL,
  `vitamins_mg` decimal(10,2) NOT NULL,
  `minerals_mg` decimal(10,2) NOT NULL,
  `fiber_mg` decimal(10,2) NOT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `height_cm` int(11) DEFAULT NULL COMMENT 'Height in centimeters',
  `weight_kg` decimal(5,2) DEFAULT NULL COMMENT 'Weight in kilograms',
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `past_diseases` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `insurance_provider` varchar(100) DEFAULT NULL,
  `insurance_policy_number` varchar(50) DEFAULT NULL,
  `smoking_status` enum('Never','Former','Current') DEFAULT NULL,
  `alcohol_consumption` enum('Never','Occasionally','Regularly') DEFAULT NULL,
  `exercise_frequency` enum('Never','Rarely','Weekly','Daily') DEFAULT NULL,
  `dietary_restrictions` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `prescribed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `appointment_id`, `prescribed_at`) VALUES
(1, 8, '2025-08-17 14:22:13');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_diet`
--

CREATE TABLE `prescription_diet` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `diet` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescription_diet`
--

INSERT INTO `prescription_diet` (`id`, `prescription_id`, `diet`) VALUES
(1, 1, 'na');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_habits`
--

CREATE TABLE `prescription_habits` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `habit` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescription_habits`
--

INSERT INTO `prescription_habits` (`id`, `prescription_id`, `habit`) VALUES
(1, 1, 'na');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_medical_tests`
--

CREATE TABLE `prescription_medical_tests` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medical_test` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescription_medical_tests`
--

INSERT INTO `prescription_medical_tests` (`id`, `prescription_id`, `medical_test`) VALUES
(1, 1, 'na');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_medicines`
--

CREATE TABLE `prescription_medicines` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medicine_name` varchar(100) NOT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `frequency` varchar(50) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescription_medicines`
--

INSERT INTO `prescription_medicines` (`id`, `prescription_id`, `medicine_name`, `dosage`, `frequency`, `duration`) VALUES
(1, 1, 'na', 'N/A', 'N/A', 'N/A');

-- --------------------------------------------------------

--
-- Table structure for table `prescription_supplements`
--

CREATE TABLE `prescription_supplements` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `supplement` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescription_supplements`
--

INSERT INTO `prescription_supplements` (`id`, `prescription_id`, `supplement`) VALUES
(1, 1, 'na');

-- --------------------------------------------------------

--
-- Table structure for table `step_logs`
--

CREATE TABLE `step_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `steps` int(11) NOT NULL,
  `goal` int(11) DEFAULT 10000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `step_logs`
--

INSERT INTO `step_logs` (`id`, `user_id`, `log_date`, `steps`, `goal`) VALUES
(1, 1, '2025-07-11', 5600, 10000),
(2, 1, '2025-07-12', 7200, 10000),
(3, 1, '2025-07-13', 9500, 10000),
(4, 1, '2025-07-14', 10200, 10000),
(5, 1, '2025-07-15', 8700, 10000),
(6, 1, '2025-07-16', 8000, 10000),
(7, 1, '2025-07-17', 4000, 10000),
(8, 1, '2025-07-18', 6500, 10000),
(9, 1, '2025-07-19', 9000, 10000),
(10, 1, '2025-07-20', 6200, 10000),
(11, 1, '2025-07-22', 7000, 10000),
(12, 1, '2025-07-26', 7000, 10000),
(13, 1, '2025-08-05', 5000, 10000);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `dob` date NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `address` text NOT NULL,
  `role` enum('admin','patient','doctor','nutritionist','trainer') NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `dob`, `gender`, `address`, `role`, `username`, `password`, `created_at`) VALUES
(1, 'Muntasir Rahman Rafin', 'mun.rafin@gmail.com', '01890308362', '2000-10-10', 'Male', 'Dhaka', 'patient', 'rafins', '$2y$10$qf0xX.WcRKLpQtm50Kwch.riVTD.lpl1nL1PRC5Gj2cw.RlBoVhPK', '2025-07-20 16:13:47'),
(5, 'Muhaimin Mahin', 'mahin@gmail.com', '01848353366', '2000-10-10', 'Male', 'Dhaka', 'doctor', 'mahin', '$2y$10$9J36E2xqRK0sU0Yo7D3Hne6b9.bmd2Tv9539V4o6.zld/QQVxNuzm', '2025-07-21 06:27:34'),
(7, 'Farzana Rahman', 'farzana@gmail.com', '11111111111', '2000-10-10', 'Female', 'Dhaka', 'doctor', 'rafind2', '$2y$10$OWgDZ1Pq6sK/iQW8FUexQOtLI0trxXMbK2rfsVAtQBaYoKUKooyku', '2025-07-22 11:53:01'),
(8, 'Nayema Kabeer', 'nayema@gmail.com', '11111111111', '2000-10-10', 'Female', 'Barishal', 'doctor', 'rafind3', '$2y$10$380ctRsLFz8.YFSAn0V0E.5HbCBsekMr9NaZfm9bCQnrkf0NgwoZ2', '2025-07-22 11:54:21'),
(9, 'Rafin', 'mun.rafin@gmail.comm', '11111111111', '2000-10-10', 'Male', 'dhaka', 'admin', 'rafina', '$2y$10$3gj1oe4Cnx4htJLSgKLH9.WGqQtiqws2wQz9KO.GqinPCLSO82XhG', '2025-08-12 09:02:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `calorie_burned`
--
ALTER TABLE `calorie_burned`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exercise_log_id` (`exercise_log_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `exercise_log`
--
ALTER TABLE `exercise_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `exercise_id` (`exercise_id`);

--
-- Indexes for table `exercise_prs`
--
ALTER TABLE `exercise_prs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `food_datapg`
--
ALTER TABLE `food_datapg`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `food_logs`
--
ALTER TABLE `food_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `food_id` (`food_id`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`medicine_id`);

--
-- Indexes for table `medicine_log`
--
ALTER TABLE `medicine_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `prescription_id` (`prescription_id`);

--
-- Indexes for table `nutrient_needs`
--
ALTER TABLE `nutrient_needs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `prescription_diet`
--
ALTER TABLE `prescription_diet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`);

--
-- Indexes for table `prescription_habits`
--
ALTER TABLE `prescription_habits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`);

--
-- Indexes for table `prescription_medical_tests`
--
ALTER TABLE `prescription_medical_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`);

--
-- Indexes for table `prescription_medicines`
--
ALTER TABLE `prescription_medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`);

--
-- Indexes for table `prescription_supplements`
--
ALTER TABLE `prescription_supplements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`);

--
-- Indexes for table `step_logs`
--
ALTER TABLE `step_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `calorie_burned`
--
ALTER TABLE `calorie_burned`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `exercise_log`
--
ALTER TABLE `exercise_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `exercise_prs`
--
ALTER TABLE `exercise_prs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `food_datapg`
--
ALTER TABLE `food_datapg`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `food_logs`
--
ALTER TABLE `food_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `medicine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `medicine_log`
--
ALTER TABLE `medicine_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nutrient_needs`
--
ALTER TABLE `nutrient_needs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `prescription_diet`
--
ALTER TABLE `prescription_diet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `prescription_habits`
--
ALTER TABLE `prescription_habits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `prescription_medical_tests`
--
ALTER TABLE `prescription_medical_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `prescription_medicines`
--
ALTER TABLE `prescription_medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `prescription_supplements`
--
ALTER TABLE `prescription_supplements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `step_logs`
--
ALTER TABLE `step_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `calorie_burned`
--
ALTER TABLE `calorie_burned`
  ADD CONSTRAINT `calorie_burned_ibfk_1` FOREIGN KEY (`exercise_log_id`) REFERENCES `exercise_logs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `fk_doctor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exercise_log`
--
ALTER TABLE `exercise_log`
  ADD CONSTRAINT `exercise_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exercise_log_ibfk_2` FOREIGN KEY (`exercise_id`) REFERENCES `exercise_prs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `food_logs`
--
ALTER TABLE `food_logs`
  ADD CONSTRAINT `food_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `food_logs_ibfk_2` FOREIGN KEY (`food_id`) REFERENCES `food_datapg` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nutrient_needs`
--
ALTER TABLE `nutrient_needs`
  ADD CONSTRAINT `nutrient_needs_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_diet`
--
ALTER TABLE `prescription_diet`
  ADD CONSTRAINT `prescription_diet_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_habits`
--
ALTER TABLE `prescription_habits`
  ADD CONSTRAINT `prescription_habits_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_medical_tests`
--
ALTER TABLE `prescription_medical_tests`
  ADD CONSTRAINT `prescription_medical_tests_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_medicines`
--
ALTER TABLE `prescription_medicines`
  ADD CONSTRAINT `prescription_medicines_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_supplements`
--
ALTER TABLE `prescription_supplements`
  ADD CONSTRAINT `prescription_supplements_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `step_logs`
--
ALTER TABLE `step_logs`
  ADD CONSTRAINT `step_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

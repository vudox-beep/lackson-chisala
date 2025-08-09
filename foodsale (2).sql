-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2025 at 02:24 PM
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
-- Database: `foodsale`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_settings`
--

INSERT INTO `admin_settings` (`setting_id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'site_name', 'FoodHub', 'Website name', '2025-08-02 13:24:37'),
(2, 'commission_rate', '10.00', 'Default commission rate for dealers', '2025-08-02 13:24:37'),
(3, 'auto_approve_listings', '0', 'Auto approve new listings (0=no, 1=yes)', '2025-08-02 13:24:37'),
(4, 'max_images_per_listing', '5', 'Maximum images allowed per listing', '2025-08-02 13:24:37');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dealers`
--

CREATE TABLE `dealers` (
  `dealer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `business_phone` varchar(20) DEFAULT NULL,
  `business_email` varchar(255) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','active','suspended') DEFAULT 'pending',
  `commission_rate` decimal(5,2) DEFAULT 10.00,
  `total_sales` decimal(10,2) DEFAULT 0.00,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `business_logo` varchar(255) DEFAULT NULL,
  `mission_statement` text DEFAULT NULL,
  `operating_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`operating_hours`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dealers`
--

INSERT INTO `dealers` (`dealer_id`, `user_id`, `business_name`, `business_type`, `business_address`, `business_phone`, `business_email`, `tax_id`, `license_number`, `status`, `commission_rate`, `total_sales`, `rating`, `total_reviews`, `created_at`, `updated_at`, `business_logo`, `mission_statement`, `operating_hours`) VALUES
(1, 1, 'lackson chisala', 'restaurant', NULL, '0770812506', 'luckyk5@gmail.com', NULL, NULL, 'active', 10.00, 0.00, 0.00, 0, '2025-08-02 13:29:16', '2025-08-02 13:44:18', NULL, NULL, NULL),
(2, 3, 'beans rice', 'restaurant', NULL, '0777757378', 'lesa@gmail.com', NULL, NULL, 'active', 10.00, 0.00, 0.00, 0, '2025-08-04 12:17:53', '2025-08-04 12:23:03', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `favorite_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `food_categories`
--

CREATE TABLE `food_categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_categories`
--

INSERT INTO `food_categories` (`category_id`, `name`, `description`, `image_url`, `is_active`, `created_at`) VALUES
(1, 'Main Courses', 'Full meals and main dishes', NULL, 1, '2025-08-02 13:24:36'),
(2, 'Appetizers', 'Starters and small plates', NULL, 1, '2025-08-02 13:24:36'),
(3, 'Desserts', 'Sweet treats and desserts', NULL, 1, '2025-08-02 13:24:36'),
(4, 'Beverages', 'Drinks and beverages', NULL, 1, '2025-08-02 13:24:36'),
(5, 'Salads', 'Fresh salads and healthy options', NULL, 1, '2025-08-02 13:24:36'),
(6, 'Soups', 'Hot and cold soups', NULL, 1, '2025-08-02 13:24:36'),
(7, 'lackson', 'very good', NULL, 1, '2025-08-02 14:23:27');

-- --------------------------------------------------------

--
-- Table structure for table `food_images`
--

CREATE TABLE `food_images` (
  `image_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_images`
--

INSERT INTO `food_images` (`image_id`, `listing_id`, `image_url`, `alt_text`, `is_primary`, `sort_order`, `created_at`) VALUES
(1, 1, 'uploads/dish_1_688e166c69261.jpg', NULL, 1, 0, '2025-08-02 13:45:16'),
(2, 2, 'uploads/dish_2_688e27913eb5a.jpg', NULL, 1, 0, '2025-08-02 14:58:25'),
(3, 3, 'uploads/dish_3_688e27d261d93.jpg', NULL, 1, 0, '2025-08-02 14:59:30'),
(4, 4, 'uploads/dish_4_688e285e71dfa.jpg', NULL, 1, 0, '2025-08-02 15:01:50'),
(5, 5, 'uploads/dish_5_688f5436ba1c5.jpg', NULL, 1, 0, '2025-08-03 12:21:10'),
(6, 6, 'uploads/dish_6_688f544283be8.jpg', NULL, 1, 0, '2025-08-03 12:21:22');

-- --------------------------------------------------------

--
-- Table structure for table `food_listings`
--

CREATE TABLE `food_listings` (
  `listing_id` int(11) NOT NULL,
  `dealer_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) DEFAULT NULL,
  `preparation_time` int(11) DEFAULT NULL,
  `serves` int(11) DEFAULT NULL,
  `spice_level` enum('mild','medium','hot','very_hot') DEFAULT NULL,
  `cuisine_type` varchar(100) DEFAULT NULL,
  `dietary_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dietary_info`)),
  `ingredients` text DEFAULT NULL,
  `nutrition_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`nutrition_info`)),
  `is_approved` tinyint(1) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `stock_quantity` int(11) DEFAULT 0,
  `views_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_daily_special` tinyint(1) DEFAULT 0,
  `special_price` decimal(10,2) DEFAULT NULL,
  `special_end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_listings`
--

INSERT INTO `food_listings` (`listing_id`, `dealer_id`, `category_id`, `subcategory_id`, `title`, `description`, `price`, `original_price`, `preparation_time`, `serves`, `spice_level`, `cuisine_type`, `dietary_info`, `ingredients`, `nutrition_info`, `is_approved`, `is_featured`, `stock_quantity`, `views_count`, `created_at`, `updated_at`, `is_daily_special`, `special_price`, `special_end_date`) VALUES
(1, 1, 3, NULL, 'pizza', 'very nice', 500.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 0, '2025-08-02 13:45:16', '2025-08-02 14:29:14', 0, NULL, NULL),
(2, 1, 4, NULL, 'wine 200', 'very sweet', 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, '2025-08-02 14:58:24', '2025-08-02 14:58:24', 0, NULL, NULL),
(3, 1, 4, NULL, 'red wine', 'very crazy', 200.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, '2025-08-02 14:59:30', '2025-08-02 14:59:30', 0, NULL, NULL),
(4, 1, 2, NULL, 'sweet dula', 'htyhh', 2.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, '2025-08-02 15:01:50', '2025-08-02 15:01:50', 0, NULL, NULL),
(5, 1, 2, NULL, 'sweet dula', 'htyhh', 2.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, '2025-08-03 12:21:10', '2025-08-03 12:21:10', 0, NULL, NULL),
(6, 1, 2, NULL, 'sweet dula', 'htyhh', 2.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, '2025-08-03 12:21:22', '2025-08-03 12:21:22', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `food_subcategories`
--

CREATE TABLE `food_subcategories` (
  `subcategory_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_subcategories`
--

INSERT INTO `food_subcategories` (`subcategory_id`, `category_id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 1, 'Steaks', NULL, 1, '2025-08-02 13:24:37'),
(2, 1, 'Pasta', NULL, 1, '2025-08-02 13:24:37'),
(3, 1, 'Seafood', NULL, 1, '2025-08-02 13:24:37'),
(4, 1, 'Chicken', NULL, 1, '2025-08-02 13:24:37'),
(5, 2, 'Finger Foods', NULL, 1, '2025-08-02 13:24:37'),
(6, 2, 'Dips', NULL, 1, '2025-08-02 13:24:37'),
(7, 2, 'Bread', NULL, 1, '2025-08-02 13:24:37'),
(8, 3, 'Cakes', NULL, 1, '2025-08-02 13:24:37'),
(9, 3, 'Ice Cream', NULL, 1, '2025-08-02 13:24:37'),
(10, 3, 'Pastries', NULL, 1, '2025-08-02 13:24:37'),
(11, 4, 'Coffee', NULL, 1, '2025-08-02 13:24:37'),
(12, 4, 'Tea', NULL, 1, '2025-08-02 13:24:37'),
(13, 4, 'Soft Drinks', NULL, 1, '2025-08-02 13:24:37'),
(14, 4, 'Juices', NULL, 1, '2025-08-02 13:24:37'),
(15, 5, 'Green Salads', NULL, 1, '2025-08-02 13:24:37'),
(16, 5, 'Caesar Salads', NULL, 1, '2025-08-02 13:24:37'),
(17, 5, 'Fruit Salads', NULL, 1, '2025-08-02 13:24:37'),
(18, 6, 'Hot Soups', NULL, 1, '2025-08-02 13:24:37'),
(19, 6, 'Cold Soups', NULL, 1, '2025-08-02 13:24:37');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `dealer_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','preparing','ready','delivered','cancelled') DEFAULT 'pending',
  `delivery_address` text DEFAULT NULL,
  `delivery_phone` varchar(20) DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `table_bookings`
--

CREATE TABLE `table_bookings` (
  `booking_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `dealer_id` int(11) NOT NULL,
  `dish_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `party_size` int(11) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `status` enum('pending','confirmed','rejected','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `table_bookings`
--

INSERT INTO `table_bookings` (`booking_id`, `customer_id`, `dealer_id`, `dish_id`, `customer_name`, `customer_email`, `customer_phone`, `booking_date`, `booking_time`, `party_size`, `special_requests`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 1, NULL, 'Lackson chisala', 'chisalaluckyk5@gmail.com', '0770812506', '2025-08-14', '09:00:00', 1, 'yhthht', 'confirmed', '2025-08-04 10:06:03', '2025-08-04 11:00:13'),
(2, 2, 1, NULL, 'Lackson chisala', 'chisalaluckyk5@gmail.com', 'xxx', '2025-08-12', '16:00:00', 11, 'cbbggb', 'confirmed', '2025-08-04 10:15:54', '2025-08-04 11:07:33'),
(3, 2, 1, NULL, 'Lackson chisala', 'chisalaluckyk5@gmail.com', 'xxx', '2025-08-12', '14:30:00', 1, '7u76u', 'pending', '2025-08-04 10:17:49', '2025-08-04 10:17:49'),
(4, 2, 1, 2, 'Lackson chisala', 'chisalaluckyk5@gmail.com', '0770812506', '2025-08-14', '09:30:00', 1, 'yhthht', 'pending', '2025-08-04 10:18:52', '2025-08-04 10:18:52'),
(5, 2, 1, 1, 'Lackson chisala', 'chisalaluckyk5@gmail.com', 'xxx', '2025-08-21', '12:00:00', 2, 'yyyjjy', 'pending', '2025-08-04 10:21:17', '2025-08-04 10:21:17'),
(6, 2, 1, NULL, 'Lackson chisala', 'chisalaluckyk5@gmail.com', 'xxx', '2025-08-20', '11:30:00', 1, '55y56u56', 'pending', '2025-08-04 10:24:56', '2025-08-04 10:24:56'),
(7, 2, 1, 1, 'Lackson chisala', 'chisalaluckyk5@gmail.com', 'xxx', '2025-08-15', '12:00:00', 6, 'yyrtyrtyrt', 'pending', '2025-08-04 10:25:43', '2025-08-04 10:25:43'),
(8, 2, 1, NULL, 'Lackson chisala', 'chisalaluckyk5@gmail.com', 'ttt', '2025-08-19', '13:30:00', 3, '', 'confirmed', '2025-08-04 10:30:41', '2025-08-04 11:31:51');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `role` enum('admin','business_owner','customer') NOT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `agreed_marketing` tinyint(1) DEFAULT 0,
  `remember_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `date_of_birth`, `business_name`, `business_type`, `role`, `is_approved`, `agreed_marketing`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'luckyk5', 'luckyk5@gmail.com', '$2y$10$VEUsABjlC0SgfgNPMK/3Be3/3ppHh.zGdDp/oeZRTYAyM6vRy0KRy', 'Lackson', 'chisala', '0770812506', '2000-12-09', 'lackson chisala', 'restaurant', 'business_owner', 0, 1, '683ac45c05398f7b9d8bf3250c5d9b21fb846ad643407f182bc58ea07bd47233', '2025-08-02 13:29:16', '2025-08-04 12:15:08'),
(2, 'chisalaluckyk5', 'chisalaluckyk5@gmail.com', '$2y$10$WIoJtbDSlWQJRioeDiNLlOGAkXL1K0FTSGoIbOZ3Lzv/rSTlaxzni', 'Lackson', 'chisala', '0770812506', '2001-12-03', 'lackson chisala', 'restaurant', 'customer', 0, 1, 'ad13c46cc49a9f42b15412d6204b34fe5d499bd8f628f5672e77527b8ae6123b', '2025-08-02 13:30:45', '2025-08-04 12:13:38'),
(3, 'lesa', 'lesa@gmail.com', '$2y$10$NdE3M7.r8ebB2W/arUtOO.JTnxHpqkVsJBGPw9hNutSh0.FuRTw6q', 'lesa', 'mutale', '0777757378', '2002-01-03', 'beans rice', 'restaurant', 'business_owner', 0, 1, 'cc46578dacb341ebd92d8141fc18cc8aa0c40e23feec56b21f00326efc8f34d1', '2025-08-04 12:17:53', '2025-08-04 12:23:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `unique_customer_listing` (`customer_id`,`listing_id`),
  ADD KEY `listing_id` (`listing_id`);

--
-- Indexes for table `dealers`
--
ALTER TABLE `dealers`
  ADD PRIMARY KEY (`dealer_id`),
  ADD UNIQUE KEY `unique_user_dealer` (`user_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`favorite_id`),
  ADD UNIQUE KEY `unique_customer_favorite` (`customer_id`,`listing_id`),
  ADD KEY `listing_id` (`listing_id`);

--
-- Indexes for table `food_categories`
--
ALTER TABLE `food_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `food_images`
--
ALTER TABLE `food_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `listing_id` (`listing_id`);

--
-- Indexes for table `food_listings`
--
ALTER TABLE `food_listings`
  ADD PRIMARY KEY (`listing_id`),
  ADD KEY `dealer_id` (`dealer_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `subcategory_id` (`subcategory_id`);

--
-- Indexes for table `food_subcategories`
--
ALTER TABLE `food_subcategories`
  ADD PRIMARY KEY (`subcategory_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `dealer_id` (`dealer_id`),
  ADD KEY `listing_id` (`listing_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `unique_customer_listing_review` (`customer_id`,`listing_id`),
  ADD KEY `listing_id` (`listing_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `table_bookings`
--
ALTER TABLE `table_bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `dealer_id` (`dealer_id`),
  ADD KEY `dish_id` (`dish_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_settings`
--
ALTER TABLE `admin_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dealers`
--
ALTER TABLE `dealers`
  MODIFY `dealer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `favorite_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `food_categories`
--
ALTER TABLE `food_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `food_images`
--
ALTER TABLE `food_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `food_listings`
--
ALTER TABLE `food_listings`
  MODIFY `listing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `food_subcategories`
--
ALTER TABLE `food_subcategories`
  MODIFY `subcategory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `table_bookings`
--
ALTER TABLE `table_bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `food_listings` (`listing_id`) ON DELETE CASCADE;

--
-- Constraints for table `dealers`
--
ALTER TABLE `dealers`
  ADD CONSTRAINT `dealers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `food_listings` (`listing_id`) ON DELETE CASCADE;

--
-- Constraints for table `food_images`
--
ALTER TABLE `food_images`
  ADD CONSTRAINT `food_images_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `food_listings` (`listing_id`) ON DELETE CASCADE;

--
-- Constraints for table `food_listings`
--
ALTER TABLE `food_listings`
  ADD CONSTRAINT `food_listings_ibfk_1` FOREIGN KEY (`dealer_id`) REFERENCES `dealers` (`dealer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `food_listings_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `food_categories` (`category_id`),
  ADD CONSTRAINT `food_listings_ibfk_3` FOREIGN KEY (`subcategory_id`) REFERENCES `food_subcategories` (`subcategory_id`);

--
-- Constraints for table `food_subcategories`
--
ALTER TABLE `food_subcategories`
  ADD CONSTRAINT `food_subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `food_categories` (`category_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`dealer_id`) REFERENCES `dealers` (`dealer_id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`listing_id`) REFERENCES `food_listings` (`listing_id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `food_listings` (`listing_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `table_bookings`
--
ALTER TABLE `table_bookings`
  ADD CONSTRAINT `table_bookings_ibfk_1` FOREIGN KEY (`dealer_id`) REFERENCES `dealers` (`dealer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `table_bookings_ibfk_2` FOREIGN KEY (`dish_id`) REFERENCES `food_listings` (`listing_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

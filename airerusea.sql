-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 13, 2025 at 10:07 AM
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
-- Database: `airerusea`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `CategoryID` int(11) NOT NULL,
  `CategoryName` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`CategoryID`, `CategoryName`) VALUES
(1, 'Photography'),
(2, 'Agriculture'),
(3, 'Surveillance'),
(4, 'Delivery'),
(5, 'Entertainment');

-- --------------------------------------------------------

--
-- Table structure for table `drones`
--

CREATE TABLE `drones` (
  `DroneID` int(11) NOT NULL,
  `Brand` varchar(255) DEFAULT NULL,
  `Model` varchar(255) DEFAULT NULL,
  `CategoryID` int(11) DEFAULT NULL,
  `WingTypeID` int(11) DEFAULT NULL,
  `Size` varchar(100) DEFAULT NULL,
  `PricePerDay` decimal(10,2) DEFAULT NULL,
  `QuantityAvailable` int(11) DEFAULT NULL,
  `PayloadCapacityID` int(11) DEFAULT NULL,
  `PowerSourceID` int(11) DEFAULT NULL,
  `MotorTypeID` int(11) DEFAULT NULL,
  `UsageCase` text DEFAULT NULL,
  `ReleaseDate` date DEFAULT NULL,
  `ImageURL` varchar(255) DEFAULT NULL,
  `Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drones`
--

INSERT INTO `drones` (`DroneID`, `Brand`, `Model`, `CategoryID`, `WingTypeID`, `Size`, `PricePerDay`, `QuantityAvailable`, `PayloadCapacityID`, `PowerSourceID`, `MotorTypeID`, `UsageCase`, `ReleaseDate`, `ImageURL`, `Description`) VALUES
(1, 'DJI', 'Phantom 4', 1, 3, 'Medium', 150.00, 10, 2, 1, 1, 'Aerial photography, surveying', '2016-03-01', 'phantom4.jpg', 'High-quality drone for aerial photography and surveying.'),
(2, 'DJI', 'Mavic Air 2', 1, 3, 'Small', 200.00, 8, 2, 1, 1, 'Aerial photography, travel', '2020-04-27', 'mavicair2.jpg', 'Compact drone with advanced camera capabilities.'),
(3, 'Autel Robotics', 'EVO 2', 1, 3, 'Medium', 250.00, 5, 3, 1, 1, 'Aerial photography, inspection', '2020-01-01', 'evo2.jpg', 'Professional-grade drone for inspection and filming.'),
(4, 'Parrot', 'Anafi', 1, 3, 'Small', 180.00, 15, 2, 1, 1, 'Aerial photography, travel', '2018-07-10', 'anafi.jpg', 'Lightweight drone perfect for travel and outdoor activities.'),
(5, 'Skydio', '2', 1, 3, 'Medium', 350.00, 3, 3, 1, 1, 'Autonomous flight, aerial photography', '2020-12-10', 'skydio2.jpg', 'Highly autonomous drone with exceptional obstacle avoidance.');

-- --------------------------------------------------------

--
-- Table structure for table `motortype`
--

CREATE TABLE `motortype` (
  `MotorTypeID` int(11) NOT NULL,
  `MotorTypeName` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `motortype`
--

INSERT INTO `motortype` (`MotorTypeID`, `MotorTypeName`) VALUES
(1, 'Brushless'),
(2, 'Brushed'),
(3, 'Electric Ducted Fan');

-- --------------------------------------------------------

--
-- Table structure for table `payloadcapacity`
--

CREATE TABLE `payloadcapacity` (
  `PayloadCapacityID` int(11) NOT NULL,
  `Capacity` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payloadcapacity`
--

INSERT INTO `payloadcapacity` (`PayloadCapacityID`, `Capacity`) VALUES
(1, '0-2 kg'),
(2, '2-5 kg'),
(3, '5-10 kg'),
(4, '10+ kg');

-- --------------------------------------------------------

--
-- Table structure for table `paymentmethods`
--

CREATE TABLE `paymentmethods` (
  `PaymentMethodID` int(11) NOT NULL,
  `Name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `paymentmethods`
--

INSERT INTO `paymentmethods` (`PaymentMethodID`, `Name`) VALUES
(1, 'Credit Card'),
(2, 'PayPal'),
(3, 'Bank Transfer'),
(4, 'Cash on Delivery');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `PaymentID` int(11) NOT NULL,
  `UserID` int(11) DEFAULT NULL,
  `RentalID` int(11) DEFAULT NULL,
  `PaymentMethodID` int(11) DEFAULT NULL,
  `PaymentDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `AmountPaid` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`PaymentID`, `UserID`, `RentalID`, `PaymentMethodID`, `PaymentDate`, `AmountPaid`) VALUES
(1, 1, 3, 2, '2025-05-02 03:29:59', 600.00),
(2, 1, 4, 2, '2025-05-02 03:30:02', 600.00),
(3, 1, 5, 1, '2025-05-02 03:45:53', 600.00),
(4, 1, 6, 1, '2025-05-02 03:45:56', 600.00),
(5, 1, 7, 1, '2025-05-02 03:46:21', 600.00),
(6, 1, 8, 1, '2025-05-02 03:46:26', 750.00),
(7, 1, 9, 2, '2025-05-02 05:47:40', 600.00),
(8, 1, 10, 2, '2025-05-02 05:53:41', 1050.00);

-- --------------------------------------------------------

--
-- Table structure for table `powersource`
--

CREATE TABLE `powersource` (
  `PowerSourceID` int(11) NOT NULL,
  `SourceType` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `powersource`
--

INSERT INTO `powersource` (`PowerSourceID`, `SourceType`) VALUES
(1, 'Battery'),
(2, 'Gasoline'),
(3, 'Hybrid');

-- --------------------------------------------------------

--
-- Table structure for table `rentals`
--

CREATE TABLE `rentals` (
  `RentalID` int(11) NOT NULL,
  `UserID` int(11) DEFAULT NULL,
  `DroneID` int(11) DEFAULT NULL,
  `RentStart` datetime DEFAULT NULL,
  `RentEnd` datetime DEFAULT NULL,
  `TotalCost` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rentals`
--

INSERT INTO `rentals` (`RentalID`, `UserID`, `DroneID`, `RentStart`, `RentEnd`, `TotalCost`) VALUES
(1, 1, 2, '2025-05-02 11:15:12', NULL, NULL),
(3, 1, 2, '2025-05-02 11:29:59', '2025-05-05 11:29:59', 600.00),
(4, 1, 2, '2025-05-02 11:30:02', '2025-05-05 11:30:02', 600.00),
(5, 1, 2, '2025-05-02 11:45:53', '2025-05-05 11:45:53', 600.00),
(6, 1, 2, '2025-05-02 11:45:56', '2025-05-05 11:45:56', 600.00),
(7, 1, 2, '2025-05-02 11:46:21', '2025-05-05 11:46:21', 600.00),
(8, 1, 3, '2025-05-02 11:46:26', '2025-05-05 11:46:26', 750.00),
(9, 1, 2, '2025-05-02 13:47:40', '2025-05-05 13:47:40', 600.00),
(10, 1, 5, '2025-05-02 13:53:41', '2025-05-05 13:53:41', 1050.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UserID` int(11) NOT NULL,
  `Name` varchar(100) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Password` varchar(255) DEFAULT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Address` text DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `Name`, `Email`, `Password`, `Phone`, `Address`, `is_admin`) VALUES
(1, 'Test User', 'test@example.com', 'password123', '1234567890', '123 Test Street', 0),
(3, 'Admin User', 'airerusea@gmail.com', 'password', '69420', 'Admin Street, City', 1);

-- --------------------------------------------------------

--
-- Table structure for table `wingtype`
--

CREATE TABLE `wingtype` (
  `WingTypeID` int(11) NOT NULL,
  `WingTypeName` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wingtype`
--

INSERT INTO `wingtype` (`WingTypeID`, `WingTypeName`) VALUES
(1, 'Fixed Wing'),
(2, 'Rotary Wing'),
(3, 'Hybrid Wing');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`CategoryID`);

--
-- Indexes for table `drones`
--
ALTER TABLE `drones`
  ADD PRIMARY KEY (`DroneID`),
  ADD KEY `CategoryID` (`CategoryID`),
  ADD KEY `WingTypeID` (`WingTypeID`),
  ADD KEY `PayloadCapacityID` (`PayloadCapacityID`),
  ADD KEY `PowerSourceID` (`PowerSourceID`),
  ADD KEY `MotorTypeID` (`MotorTypeID`);

--
-- Indexes for table `motortype`
--
ALTER TABLE `motortype`
  ADD PRIMARY KEY (`MotorTypeID`);

--
-- Indexes for table `payloadcapacity`
--
ALTER TABLE `payloadcapacity`
  ADD PRIMARY KEY (`PayloadCapacityID`);

--
-- Indexes for table `paymentmethods`
--
ALTER TABLE `paymentmethods`
  ADD PRIMARY KEY (`PaymentMethodID`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`PaymentID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `RentalID` (`RentalID`),
  ADD KEY `PaymentMethodID` (`PaymentMethodID`);

--
-- Indexes for table `powersource`
--
ALTER TABLE `powersource`
  ADD PRIMARY KEY (`PowerSourceID`);

--
-- Indexes for table `rentals`
--
ALTER TABLE `rentals`
  ADD PRIMARY KEY (`RentalID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `DroneID` (`DroneID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`);

--
-- Indexes for table `wingtype`
--
ALTER TABLE `wingtype`
  ADD PRIMARY KEY (`WingTypeID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `CategoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `drones`
--
ALTER TABLE `drones`
  MODIFY `DroneID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `motortype`
--
ALTER TABLE `motortype`
  MODIFY `MotorTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payloadcapacity`
--
ALTER TABLE `payloadcapacity`
  MODIFY `PayloadCapacityID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `paymentmethods`
--
ALTER TABLE `paymentmethods`
  MODIFY `PaymentMethodID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `PaymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `powersource`
--
ALTER TABLE `powersource`
  MODIFY `PowerSourceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rentals`
--
ALTER TABLE `rentals`
  MODIFY `RentalID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `wingtype`
--
ALTER TABLE `wingtype`
  MODIFY `WingTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `drones`
--
ALTER TABLE `drones`
  ADD CONSTRAINT `drones_ibfk_1` FOREIGN KEY (`CategoryID`) REFERENCES `categories` (`CategoryID`),
  ADD CONSTRAINT `drones_ibfk_2` FOREIGN KEY (`WingTypeID`) REFERENCES `wingtype` (`WingTypeID`),
  ADD CONSTRAINT `drones_ibfk_3` FOREIGN KEY (`PayloadCapacityID`) REFERENCES `payloadcapacity` (`PayloadCapacityID`),
  ADD CONSTRAINT `drones_ibfk_4` FOREIGN KEY (`PowerSourceID`) REFERENCES `powersource` (`PowerSourceID`),
  ADD CONSTRAINT `drones_ibfk_5` FOREIGN KEY (`MotorTypeID`) REFERENCES `motortype` (`MotorTypeID`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`RentalID`) REFERENCES `rentals` (`RentalID`),
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`PaymentMethodID`) REFERENCES `paymentmethods` (`PaymentMethodID`);

--
-- Constraints for table `rentals`
--
ALTER TABLE `rentals`
  ADD CONSTRAINT `rentals_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `rentals_ibfk_2` FOREIGN KEY (`DroneID`) REFERENCES `drones` (`DroneID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

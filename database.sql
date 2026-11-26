-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 25, 2026 at 06:27 PM
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
-- Database: `lektuvu_dalys`
--

CREATE DATABASE IF NOT EXISTS `lektuvu_dalys` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `lektuvu_dalys`;

-- Pakartotinai importuojant – senos lentelės ištrinamos (vaikinės pirmiau)
DROP TABLE IF EXISTS `uzsakymo_preke`;
DROP TABLE IF EXISTS `uzsakymas`;
DROP TABLE IF EXISTS `preke`;
DROP TABLE IF EXISTS `naudotojas`;

-- --------------------------------------------------------

--
-- Table structure for table `naudotojas`
--

CREATE TABLE `naudotojas` (
  `naudotojo_id` int(11) NOT NULL,
  `vardas` varchar(50) NOT NULL,
  `pavarde` varchar(50) NOT NULL,
  `pavadinimas` varchar(100) DEFAULT NULL,
  `pastas` varchar(100) NOT NULL,
  `slaptazodis` varchar(255) NOT NULL,
  `telefonas` varchar(20) DEFAULT NULL,
  `tipas` enum('Naudotojas','Vadybininkas','Tiekejas','Direktorius') NOT NULL DEFAULT 'Naudotojas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `naudotojas`
--

INSERT INTO `naudotojas` (`naudotojo_id`, `vardas`, `pavarde`, `pavadinimas`, `pastas`, `slaptazodis`, `telefonas`, `tipas`) VALUES
(3, 'Saulius', 'Vadybininkas', NULL, 'vadybininkas.saulius@lekt.lt', '$2y$10$0qKwzHNeBFIBCFgfeGd/nep3dA2TtF4LUqbqtzmLLrAg54cnDpWOq', NULL, 'Vadybininkas'),
(7, 'Milda', 'Tiekėja', 'Kauno gelžbetonis', 'info@kaunogelzbetonis.lt', 'kakamaka', '+3706000001', 'Tiekejas'),
(30, 'Algis', 'Direktorius', NULL, 'direktorius.algis@lekt.lt', '$2y$10$1Kk0ga.ipRSs/xnRlK5l/uFGlBqDX.4XIthZE8C3l1frYDlWs.Mki', NULL, 'Direktorius'),
(31, 'Vilius', 'Tiekėjęs', 'Susprogusių lėktuvų dalių šrotas', 'info@srotas.lt', '$2y$10$HNWRaJPON/uzchO3JWagj.Pr7LBW5gTcTt2Iebv7LfIJAmxv9DdCG', '+370676767', 'Tiekejas'),
(60, 'Karolis', 'Tiekėjęs', 'Ran Air', 'info@ran.com', '$2y$10$B0HeheTuFwiWjiyE6C9ZZeO1RxM2E.L8yG73Hx1yrcJAzLIUEl1yS', '+3706666666', 'Tiekejas'),
(67, 'Mysliukas', 'Naudotojas', NULL, 'mysliukas@gmail.com', '$2y$10$GO/F5GRdJ.OIVLeNTOeaQeM4rYgH88xex3Gg5BDTzQFvl8BUa1WfO', NULL, 'Naudotojas'),
(68, 'Jonas', 'Tiekėjas', 'Baltic Aero Parts', 'info@balticaero.lt', '$2y$10$uo6KYHmmKO8ZxnXhaNNnOuxPhd4ClxL03XYyrlQsw0EUmDZ.bA5HC', '+37060000000', 'Tiekejas'),
(69, 'Testas', 'Naudotojas', NULL, 'naudotojas@test.lt', '$2y$10$VMmdPotG7Q4KtwA87e/AqOsi5Z/NgPuml60ph7leGLTjSxFlkhGRy', NULL, 'Naudotojas'),
(70, 'Testas', 'Vadybininkas', NULL, 'vadybininkas@test.lt', '$2y$10$m4K0/n5Z.Q7AYZmDLehM5O0Hy7XJRNE1LPu8uVj.pB2m73.TJo76u', '+37060000010', 'Vadybininkas'),
(71, 'Testas', 'Tiekėjas', 'UAB Testinis tiekėjas', 'tiekejas@test.lt', '$2y$10$OlDzZzK.1MloUeJDCeYP5uyMlRxMsKKc1DYERSCUIB.Mwy0N6s8xm', '+37060000011', 'Tiekejas'),
(72, 'Testas', 'Direktorius', NULL, 'direktorius@test.lt', '$2y$10$LZR/7/gcx7OPn3R5ztk8ke5UGBDYjKKcQqLUB47KzNFyAu6Ju6olG', NULL, 'Direktorius');

-- --------------------------------------------------------

--
-- Table structure for table `preke`
--

CREATE TABLE `preke` (
  `prekes_id` int(11) NOT NULL,
  `tiekejo_id` int(11) NOT NULL,
  `pavadinimas` varchar(100) NOT NULL,
  `kiekis` int(11) NOT NULL,
  `kaina` decimal(10,2) NOT NULL,
  `gamintojas` varchar(100) DEFAULT NULL,
  `modelis` varchar(100) DEFAULT NULL,
  `pristatymo_laikas` int(10) UNSIGNED DEFAULT NULL COMMENT 'Pristatymo laikas dienomis'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `preke`
--

INSERT INTO `preke` (`prekes_id`, `tiekejo_id`, `pavadinimas`, `kiekis`, `kaina`, `gamintojas`, `modelis`, `pristatymo_laikas`) VALUES
(4, 7, 'Lėktuvo Boeing 767 gelžbetoninis rėmas', 12, 400000.00, 'Lenkijos pigiai gaminanti įmonė', 'Boeing767.123.ADWSC', 7),
(30, 31, 'Gelžbetonio Boeing 747 rėmas', 1, 180000.00, 'UAB Pigu.lt', 'Boeing 747', 7),
(31, 31, 'Gelžbetonio Boeing 747 rėmas', 3, 230000.23, 'Japanese Infra inc.', 'Boeing 747', 7),
(44, 60, 'Dešinysis priekinis stiklas', 1, 3000.00, 'Boston glass', 'Boeing 777', 7),
(49, 60, 'Test', 67, 67.00, NULL, NULL, 12);

-- --------------------------------------------------------

--
-- Table structure for table `uzsakymas`
--

CREATE TABLE `uzsakymas` (
  `uzsakymo_id` int(11) NOT NULL,
  `vadybininko_id` int(11) NOT NULL,
  `suma` decimal(10,2) DEFAULT NULL,
  `pateikimo_data` datetime DEFAULT current_timestamp(),
  `pristatymo_data` date DEFAULT NULL,
  `busena` enum('Pateiktas','Priimtas','Ivykdytas','Atsauktas') DEFAULT 'Pateiktas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uzsakymas`
--

INSERT INTO `uzsakymas` (`uzsakymo_id`, `vadybininko_id`, `suma`, `pateikimo_data`, `pristatymo_data`, `busena`) VALUES
(1, 3, 1200000.00, '2026-11-25 10:50:18', '2026-12-02', 'Pateiktas'),
(2, 3, 230000.23, '2026-11-25 14:01:34', '2026-12-02', 'Priimtas'),
(3, 3, 230000.23, '2026-11-25 14:07:15', '2026-12-02', 'Ivykdytas'),
(4, 3, 180000.00, '2026-11-25 15:55:52', '2026-12-02', 'Ivykdytas'),
(5, 3, 3000.00, '2026-11-25 15:55:52', '2026-12-02', 'Pateiktas'),
(6, 3, 410000.23, '2026-11-25 15:58:00', '2026-12-02', 'Priimtas'),
(7, 3, 2000000.00, '2026-11-25 18:52:50', '2026-12-04', 'Atsauktas');

-- --------------------------------------------------------

--
-- Table structure for table `uzsakymo_preke`
--

CREATE TABLE `uzsakymo_preke` (
  `eilutes_id` int(11) NOT NULL,
  `uzsakymo_id` int(11) NOT NULL,
  `prekes_id` int(11) NOT NULL,
  `kiekis` int(11) NOT NULL,
  `suma` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uzsakymo_preke`
--

INSERT INTO `uzsakymo_preke` (`eilutes_id`, `uzsakymo_id`, `prekes_id`, `kiekis`, `suma`) VALUES
(1, 1, 4, 3, 1200000.00),
(2, 2, 31, 1, 230000.23),
(3, 3, 31, 1, 230000.23),
(4, 4, 30, 1, 180000.00),
(5, 5, 44, 1, 3000.00),
(6, 6, 30, 1, 180000.00),
(7, 6, 31, 1, 230000.23),
(8, 7, 4, 5, 2000000.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `naudotojas`
--
ALTER TABLE `naudotojas`
  ADD PRIMARY KEY (`naudotojo_id`),
  ADD UNIQUE KEY `pastas` (`pastas`);

--
-- Indexes for table `preke`
--
ALTER TABLE `preke`
  ADD PRIMARY KEY (`prekes_id`),
  ADD KEY `tiekejo_id` (`tiekejo_id`);

--
-- Indexes for table `uzsakymas`
--
ALTER TABLE `uzsakymas`
  ADD PRIMARY KEY (`uzsakymo_id`),
  ADD KEY `vadybininko_id` (`vadybininko_id`);

--
-- Indexes for table `uzsakymo_preke`
--
ALTER TABLE `uzsakymo_preke`
  ADD PRIMARY KEY (`eilutes_id`),
  ADD KEY `uzsakymo_id` (`uzsakymo_id`),
  ADD KEY `prekes_id` (`prekes_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `naudotojas`
--
ALTER TABLE `naudotojas`
  MODIFY `naudotojo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `preke`
--
ALTER TABLE `preke`
  MODIFY `prekes_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `uzsakymas`
--
ALTER TABLE `uzsakymas`
  MODIFY `uzsakymo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `uzsakymo_preke`
--
ALTER TABLE `uzsakymo_preke`
  MODIFY `eilutes_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `preke`
--
ALTER TABLE `preke`
  ADD CONSTRAINT `preke_ibfk_1` FOREIGN KEY (`tiekejo_id`) REFERENCES `naudotojas` (`naudotojo_id`);

--
-- Constraints for table `uzsakymas`
--
ALTER TABLE `uzsakymas`
  ADD CONSTRAINT `uzsakymas_ibfk_1` FOREIGN KEY (`vadybininko_id`) REFERENCES `naudotojas` (`naudotojo_id`);

--
-- Constraints for table `uzsakymo_preke`
--
ALTER TABLE `uzsakymo_preke`
  ADD CONSTRAINT `uzsakymo_preke_ibfk_1` FOREIGN KEY (`uzsakymo_id`) REFERENCES `uzsakymas` (`uzsakymo_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `uzsakymo_preke_ibfk_2` FOREIGN KEY (`prekes_id`) REFERENCES `preke` (`prekes_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

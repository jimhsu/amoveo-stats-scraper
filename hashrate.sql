-- phpMyAdmin SQL Dump
-- version 4.5.4.1deb2ubuntu2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: May 12, 2018 at 12:41 AM
-- Server version: 10.1.30-MariaDB-1~xenial
-- PHP Version: 7.0.4-7ubuntu2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `amoveostats`
--

-- --------------------------------------------------------

--
-- Table structure for table `hashrate`
--

CREATE TABLE `hashrate` (
  `block` int(16) NOT NULL DEFAULT '0',
  `difficulty` decimal(12,3) NOT NULL DEFAULT '0.000',
  `nethash` int(16) NOT NULL DEFAULT '0',
  `blocktime` int(16) NOT NULL DEFAULT '60',
  `hashpredict` decimal(12,3) NOT NULL DEFAULT '0.000'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `hashrate`
--
ALTER TABLE `hashrate`
  ADD PRIMARY KEY (`block`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

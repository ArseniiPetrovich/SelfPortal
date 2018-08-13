-- phpMyAdmin SQL Dump
-- version 4.7.9
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Aug 12, 2018 at 05:31 PM
-- Server version: 5.7.21-0ubuntu0.16.04.1
-- PHP Version: 7.2.3-1+ubuntu16.04.1+deb.sury.org+1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `portal`
--
CREATE DATABASE IF NOT EXISTS `portal` DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;
USE `portal`;

-- --------------------------------------------------------

--
-- Table structure for table `actions`
--

CREATE TABLE `actions` (
  `id` int(11) NOT NULL,
  `resource` varchar(255) COLLATE utf8_bin NOT NULL,
  `action` varchar(255) COLLATE utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `actions`
--

INSERT INTO `actions` (`id`, `resource`, `action`) VALUES
(1, 'all', 'all'),
(44, 'domains', 'all'),
(32, 'domains', 'list'),
(29, 'keys', 'add'),
(46, 'keys', 'all'),
(30, 'keys', 'delete'),
(28, 'keys', 'list'),
(45, 'notifications', 'all'),
(31, 'notifications', 'list'),
(34, 'proxysites', 'add'),
(47, 'proxysites', 'all'),
(39, 'proxysites', 'check'),
(40, 'proxysites', 'count'),
(36, 'proxysites', 'delete'),
(38, 'proxysites', 'edit'),
(35, 'proxysites', 'get'),
(33, 'proxysites', 'list'),
(37, 'proxysites', 'switch'),
(21, 'snapshots', 'add'),
(48, 'snapshots', 'all'),
(23, 'snapshots', 'clear'),
(25, 'snapshots', 'count'),
(24, 'snapshots', 'extend'),
(26, 'snapshots', 'info'),
(20, 'snapshots', 'list'),
(27, 'snapshots', 'restore'),
(22, 'snapshots', 'terminate'),
(52, 'tasks', 'dbinfo'),
(49, 'vms', 'all'),
(17, 'vms', 'assignip'),
(50, 'vms', 'backupvm'),
(19, 'vms', 'clearvm'),
(3, 'vms', 'count'),
(2, 'vms', 'createserver'),
(51, 'vms', 'dbinfo'),
(18, 'vms', 'extend'),
(15, 'vms', 'flavor'),
(16, 'vms', 'flavordetails'),
(14, 'vms', 'imagedetails'),
(13, 'vms', 'images'),
(4, 'vms', 'info'),
(7, 'vms', 'list'),
(11, 'vms', 'rebootvm'),
(9, 'vms', 'startvm'),
(8, 'vms', 'stopvm'),
(10, 'vms', 'terminatevm'),
(12, 'vms', 'vnc');

-- --------------------------------------------------------

--
-- Table structure for table `ad_groups`
--

CREATE TABLE `ad_groups` (
  `id` int(11) NOT NULL,
  `ldap_dn` text COLLATE utf8_bin NOT NULL,
  `title` text COLLATE utf8_bin NOT NULL,
  `rights` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table `blacklist`
--

CREATE TABLE `blacklist` (
  `ip_id` int(6) UNSIGNED NOT NULL,
  `IP` int(8) UNSIGNED NOT NULL,
  `Mask` tinyint(2) NOT NULL DEFAULT '32'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `proc_quota` int(11) NOT NULL DEFAULT '0',
  `ram_quota` int(11) NOT NULL DEFAULT '0',
  `disk_quota` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Triggers `departments`
--
DELIMITER $$
CREATE TRIGGER `UPDATE_USERS_QUOTAS` AFTER UPDATE ON `departments` FOR EACH ROW UPDATE users SET users.proc_quota=NEW.proc_quota, users.ram_quota=NEW.ram_quota,users.disk_quota=NEW.disk_quota WHERE `users`.`department` = NEW.id and `users`.`inherit_quota`=1
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `domains`
--

CREATE TABLE `domains` (
  `domain_id` int(6) UNSIGNED NOT NULL,
  `domain` varchar(60) DEFAULT NULL,
  `shared` tinyint(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `internal_users`
--

CREATE TABLE `internal_users` (
  `user_id` int(6) UNSIGNED NOT NULL,
  `global_uid` int(11) NOT NULL,
  `passwd` text NOT NULL,
  `rights` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `ldap_users`
--

CREATE TABLE `ldap_users` (
  `user_id` int(6) UNSIGNED NOT NULL,
  `global_uid` int(11) NOT NULL,
  `SID` varchar(1000) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `actions` int(11) NOT NULL,
  `provider` int(11) DEFAULT NULL,
  `rights` int(11) NOT NULL,
  `bypass_resource_check` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `actions`, `provider`, `rights`, `bypass_resource_check`) VALUES
(1, 1, NULL, 3, 1),
(2, 45, NULL, 1, 1),
(3, 46, NULL, 1, 0),
(4, 47, NULL, 1, 0),
(5, 48, NULL, 1, 0),
(6, 49, NULL, 1, 0),
(7, 3, NULL, 1, 1),
(8, 7, NULL, 1, 1),
(9, 13, NULL, 1, 1),
(10, 14, NULL, 1, 1),
(11, 15, NULL, 1, 1),
(12, 16, NULL, 1, 1),
(13, 20, NULL, 1, 1),
(14, 31, NULL, 1, 1),
(17, 33, NULL, 1, 1),
(18, 40, NULL, 1, 1),
(19, 39, NULL, 1, 1),
(20, 25, NULL, 1, 1),
(21, 28, NULL, 1, 1),
(22, 29, NULL, 1, 1),
(23, 2, NULL, 1, 1),
(24, 18, NULL, 1, 0),
(25, 50, NULL, 1, 0),
(26, 34, NULL, 1, 1),
(27, 44, NULL, 1, 0),
(28, 32, NULL, 1, 1),
(29, 51, NULL, 1, 0),
(30, 52, NULL, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `providers`
--

CREATE TABLE `providers` (
  `id` int(11) NOT NULL,
  `title` text COLLATE utf8_bin NOT NULL,
  `enabled` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `providers`
--

INSERT INTO `providers` (`id`, `title`, `enabled`) VALUES
(1, 'OpenStack', 1),
(2, 'vSphere', 1);

-- --------------------------------------------------------

--
-- Table structure for table `proxysites`
--

CREATE TABLE `proxysites` (
  `id` int(6) UNSIGNED NOT NULL,
  `site_name` varchar(60) NOT NULL,
  `rhost` varchar(32) NOT NULL,
  `rport` varchar(5) NOT NULL,
  `user_id` int(6) NOT NULL,
  `domain_id` int(6) UNSIGNED NOT NULL,
  `exp_date` date DEFAULT NULL,
  `status` varchar(16) DEFAULT 'HTTPS',
  `cleared` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `public_keys`
--

CREATE TABLE `public_keys` (
  `key_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` text NOT NULL,
  `public_key` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `rights`
--

CREATE TABLE `rights` (
  `id` int(11) NOT NULL,
  `title` text COLLATE utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `rights`
--

INSERT INTO `rights` (`id`, `title`) VALUES
(0, 'DISABLED'),
(1, 'Users'),
(2, 'RM'),
(3, 'Admins');

-- --------------------------------------------------------

--
-- Table structure for table `snapshots`
--

CREATE TABLE `snapshots` (
  `snapshot_id` varchar(255) COLLATE utf8_bin NOT NULL,
  `vm_id` varchar(255) COLLATE utf8_bin NOT NULL,
  `exp_date` date DEFAULT NULL,
  `provider` int(11) NOT NULL,
  `status` varchar(255) COLLATE utf8_bin NOT NULL,
  `cleared` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` text NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` text NOT NULL,
  `exp_date` date NOT NULL,
  `provider` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `comment` text,
  `cleared` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` text COLLATE utf8_bin NOT NULL,
  `email` text COLLATE utf8_bin NOT NULL,
  `department` int(11) NOT NULL,
  `user_type` int(11) NOT NULL,
  `inherit_quota` tinyint(1) NOT NULL,
  `proc_quota` int(11) DEFAULT NULL,
  `ram_quota` int(11) DEFAULT NULL,
  `disk_quota` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table `user_types`
--

CREATE TABLE `user_types` (
  `id` int(11) NOT NULL,
  `title` text COLLATE utf8_bin NOT NULL,
  `table_name` text COLLATE utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `user_types`
--

INSERT INTO `user_types` (`id`, `title`, `table_name`) VALUES
(1, 'Internal', 'internal_users'),
(2, 'LDAP', 'ldap_users');

-- --------------------------------------------------------

--
-- Table structure for table `vms`
--

CREATE TABLE `vms` (
  `id` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `title` text NOT NULL,
  `exp_date` date DEFAULT NULL,
  `provider` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `cleared` tinyint(1) NOT NULL DEFAULT '0',
  `comment` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `vms_statuses`
--

CREATE TABLE `vms_statuses` (
  `id` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8_bin NOT NULL,
  `display_title` text COLLATE utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table `vms_statuses`
--

INSERT INTO `vms_statuses` (`id`, `title`, `display_title`) VALUES
(1, 'ENABLED', '<span class=\"label label-success\">ACTIVE</span>'),
(2, 'DISABLED', '<span class=\"label label-warning\">SHUTOFF</span>'),
(3, 'TERMINATED', '<span class=\"label label-danger\">TERMINATED</span>'),
(4, 'FAILURE', '<span class=\"label label-danger\">FAILURE</span>'),
(5, 'RESIZING', '<span class=\"label label-warning\">MAINTENANCE</span>'),
(6, 'MIGRATING', '<span class=\"label label-warning\">MAINTENANCE</span>'),
(7, 'BUILDING', '<span class=\"label label-default\">BUILDING</span>'),
(8, 'REBUILDING', '<span class=\"label label-warning\">RECOVERING</span>'),
(9, 'ACTIVE', '<span class=\"label label-success\">ACTIVE</span>'),
(10, 'poweredOff', '<span class=\"label label-warning\">SHUTOFF</span>'),
(11, 'poweredOn', '<span class=\"label label-success\">ACTIVE</span>'),
(12, 'SHUTOFF', '<span class=\"label label-warning\">SHUTOFF</span>');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `actions`
--
ALTER TABLE `actions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `resource` (`resource`,`action`);

--
-- Indexes for table `ad_groups`
--
ALTER TABLE `ad_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rights` (`rights`);

--
-- Indexes for table `blacklist`
--
ALTER TABLE `blacklist`
  ADD PRIMARY KEY (`ip_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `title` (`title`);

--
-- Indexes for table `domains`
--
ALTER TABLE `domains`
  ADD PRIMARY KEY (`domain_id`),
  ADD UNIQUE KEY `domain` (`domain`);

--
-- Indexes for table `internal_users`
--
ALTER TABLE `internal_users`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `rights` (`rights`),
  ADD KEY `global_uid` (`global_uid`);

--
-- Indexes for table `ldap_users`
--
ALTER TABLE `ldap_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `SID` (`SID`),
  ADD KEY `global_uid` (`global_uid`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `actions` (`actions`,`provider`,`rights`),
  ADD KEY `rights` (`rights`),
  ADD KEY `provider` (`provider`);

--
-- Indexes for table `providers`
--
ALTER TABLE `providers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `proxysites`
--
ALTER TABLE `proxysites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `site_name` (`site_name`,`domain_id`) USING BTREE,
  ADD KEY `user_id` (`user_id`),
  ADD KEY `domain_id` (`domain_id`);

--
-- Indexes for table `public_keys`
--
ALTER TABLE `public_keys`
  ADD PRIMARY KEY (`key_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `rights`
--
ALTER TABLE `rights`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `snapshots`
--
ALTER TABLE `snapshots`
  ADD PRIMARY KEY (`snapshot_id`),
  ADD KEY `provider` (`provider`),
  ADD KEY `vm_id` (`vm_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`(256)),
  ADD KEY `provider` (`provider`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `user_type` (`user_type`),
  ADD KEY `department` (`department`);

--
-- Indexes for table `user_types`
--
ALTER TABLE `user_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vms`
--
ALTER TABLE `vms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider` (`provider`),
  ADD KEY `status` (`status`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `vms_statuses`
--
ALTER TABLE `vms_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `title` (`title`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `actions`
--
ALTER TABLE `actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `ad_groups`
--
ALTER TABLE `ad_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `blacklist`
--
ALTER TABLE `blacklist`
  MODIFY `ip_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `domains`
--
ALTER TABLE `domains`
  MODIFY `domain_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT for table `internal_users`
--
ALTER TABLE `internal_users`
  MODIFY `user_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ldap_users`
--
ALTER TABLE `ldap_users`
  MODIFY `user_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `providers`
--
ALTER TABLE `providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `proxysites`
--
ALTER TABLE `proxysites`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `public_keys`
--
ALTER TABLE `public_keys`
  MODIFY `key_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rights`
--
ALTER TABLE `rights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `user_types`
--
ALTER TABLE `user_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vms_statuses`
--
ALTER TABLE `vms_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ad_groups`
--
ALTER TABLE `ad_groups`
  ADD CONSTRAINT `ad_groups_ibfk_1` FOREIGN KEY (`rights`) REFERENCES `rights` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `internal_users`
--
ALTER TABLE `internal_users`
  ADD CONSTRAINT `internal_users_ibfk_1` FOREIGN KEY (`global_uid`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `internal_users_ibfk_2` FOREIGN KEY (`rights`) REFERENCES `rights` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `ldap_users`
--
ALTER TABLE `ldap_users`
  ADD CONSTRAINT `ldap_users_ibfk_1` FOREIGN KEY (`global_uid`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`provider`) REFERENCES `providers` (`id`),
  ADD CONSTRAINT `permissions_ibfk_2` FOREIGN KEY (`rights`) REFERENCES `rights` (`id`),
  ADD CONSTRAINT `permissions_ibfk_3` FOREIGN KEY (`actions`) REFERENCES `actions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `proxysites`
--
ALTER TABLE `proxysites`
  ADD CONSTRAINT `proxysites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `proxysites_ibfk_2` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`domain_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `public_keys`
--
ALTER TABLE `public_keys`
  ADD CONSTRAINT `public_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `snapshots`
--
ALTER TABLE `snapshots`
  ADD CONSTRAINT `snapshots_ibfk_1` FOREIGN KEY (`provider`) REFERENCES `providers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `snapshots_ibfk_2` FOREIGN KEY (`vm_id`) REFERENCES `vms` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`provider`) REFERENCES `providers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`status`) REFERENCES `vms_statuses` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department`) REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`user_type`) REFERENCES `user_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vms`
--
ALTER TABLE `vms`
  ADD CONSTRAINT `vms_ibfk_1` FOREIGN KEY (`provider`) REFERENCES `providers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `vms_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `vms_ibfk_4` FOREIGN KEY (`status`) REFERENCES `vms_statuses` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

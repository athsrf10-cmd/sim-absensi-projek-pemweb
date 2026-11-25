-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2026 at 02:24 PM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sim_absensi`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL,
  `karyawan_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `status` enum('Hadir','Izin','Sakit','Alpha','Cuti') DEFAULT 'Hadir',
  `keterangan` varchar(255) DEFAULT NULL,
  `pengajuan_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `absensi`
--

INSERT INTO `absensi` (`id`, `karyawan_id`, `tanggal`, `jam_masuk`, `jam_pulang`, `status`, `keterangan`, `pengajuan_id`) VALUES
(11, 5, '2026-11-25', '09:57:07', '09:57:20', 'Hadir', NULL, NULL),
(12, 1, '2026-11-25', '09:59:21', '09:59:29', 'Hadir', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `jabatan`
--

CREATE TABLE `jabatan` (
  `id` int(11) NOT NULL,
  `kode_jabatan` varchar(20) NOT NULL,
  `nama_jabatan` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jabatan`
--

INSERT INTO `jabatan` (`id`, `kode_jabatan`, `nama_jabatan`) VALUES
(1, 'JBT-001', 'Keuangan'),
(4, 'JBT-002', 'HRD'),
(5, 'JBT-003', 'IT'),
(6, 'JBT-004', 'Teknisi');

-- --------------------------------------------------------

--
-- Table structure for table `karyawan`
--

CREATE TABLE `karyawan` (
  `id` int(11) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jabatan_id` int(11) DEFAULT NULL,
  `no_hp` varchar(15) DEFAULT NULL,
  `alamat` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `karyawan`
--

INSERT INTO `karyawan` (`id`, `nip`, `nama`, `jabatan_id`, `no_hp`, `alamat`) VALUES
(1, '1', 'Athaa Syariif', 5, '08122334445', 'Barbasari, Sleman'),
(5, '2', 'Aziel', 5, '08123456789', 'JL Parangtritis, Bantul'),
(6, '3', 'Budi', 4, '08122222222', 'Catur Tunggal, Sleman'),
(7, '4', 'Lionel Messi', 4, '08123456789', 'Los Santos, Amerika Serikat');

-- --------------------------------------------------------

--
-- Table structure for table `pengajuan`
--

CREATE TABLE `pengajuan` (
  `id` int(11) NOT NULL,
  `karyawan_id` int(11) NOT NULL,
  `jenis` enum('Izin','Sakit','Cuti') NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `alasan` varchar(255) DEFAULT NULL,
  `status` enum('Menunggu','Disetujui','Ditolak') NOT NULL DEFAULT 'Menunggu',
  `catatan_admin` varchar(255) DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diproses_pada` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengajuan`
--

INSERT INTO `pengajuan` (`id`, `karyawan_id`, `jenis`, `tanggal_mulai`, `tanggal_selesai`, `alasan`, `status`, `catatan_admin`, `dibuat_pada`, `diproses_pada`) VALUES
(1, 5, 'Izin', '2026-11-25', '2026-11-25', 'keperluan keluarga', 'Menunggu', NULL, '2026-11-25 20:15:47', NULL),
(2, 5, 'Cuti', '2026-11-29', '2026-11-30', 'acara', 'Menunggu', NULL, '2026-11-25 20:17:01', NULL),
(3, 5, 'Cuti', '2026-11-26', '2026-11-26', 'acara', 'Menunggu', NULL, '2026-11-25 20:18:29', NULL),
(4, 5, 'Sakit', '2026-11-27', '2026-11-27', NULL, 'Menunggu', NULL, '2026-11-25 20:19:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','karyawan') NOT NULL,
  `karyawan_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `karyawan_id`) VALUES
(1, 'admin', '$2y$10$mq6QGg3M.BRBasvLc2XgKuWJ7shtgZV2w.9/stYRCUp6aFYgBp2A.', 'admin', NULL),
(5, 'athaa', '$2y$10$2A45hhYGZvMHrzxy5EZFQe0RfI62RNcm8h8t625KTrJAeV24q9L9S', 'karyawan', 1),
(9, 'aziel', '$2y$10$Lqp1jhr/hHEQP1/dT4ZKOOka5wJ9zXRziGv.GgoXgxwW3QAXu4DJ.', 'karyawan', 5),
(10, 'budi', '$2y$10$KUuYjuyZ1dtXNdbYD35Sq.W4WMIdZsPCrp9yVMhko0.EkLNaSdFJ2', 'karyawan', 6),
(11, 'messi', '$2y$10$Xliww2cZdfGbKB/gEPqjKO8.BVRtYizBZ7zuKwDhvb55PKO8PnSpG', 'karyawan', 7);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `karyawan_id` (`karyawan_id`,`tanggal`),
  ADD KEY `pengajuan_id` (`pengajuan_id`);

--
-- Indexes for table `jabatan`
--
ALTER TABLE `jabatan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_jabatan` (`kode_jabatan`);

--
-- Indexes for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nip` (`nip`),
  ADD KEY `jabatan_id` (`jabatan_id`);

--
-- Indexes for table `pengajuan`
--
ALTER TABLE `pengajuan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `karyawan_id` (`karyawan_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `karyawan_id` (`karyawan_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `jabatan`
--
ALTER TABLE `jabatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `karyawan`
--
ALTER TABLE `karyawan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pengajuan`
--
ALTER TABLE `pengajuan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`),
  ADD CONSTRAINT `absensi_ibfk_2` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD CONSTRAINT `karyawan_ibfk_1` FOREIGN KEY (`jabatan_id`) REFERENCES `jabatan` (`id`);

--
-- Constraints for table `pengajuan`
--
ALTER TABLE `pengajuan`
  ADD CONSTRAINT `pengajuan_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

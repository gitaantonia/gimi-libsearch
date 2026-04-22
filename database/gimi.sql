-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 22, 2026 at 07:45 PM
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
-- Database: `gimi`
--

-- --------------------------------------------------------

--
-- Table structure for table `anggota`
--

CREATE TABLE `anggota` (
  `id_anggota` int(11) NOT NULL,
  `id_pengguna` varchar(36) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `jurusan` varchar(100) DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `tanggal_daftar` date NOT NULL DEFAULT curdate(),
  `status` enum('aktif','nonaktif','terverifikasi') NOT NULL DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `anggota`
--

INSERT INTO `anggota` (`id_anggota`, `id_pengguna`, `nama`, `nim`, `email`, `tgl_lahir`, `jurusan`, `no_telepon`, `alamat`, `tanggal_daftar`, `status`) VALUES
(1, 'bcf38f2f-3b15-11f1-a975-d0395778e6bc', 'Gita Antonia Sipayung', '12345', '', '2026-04-22', 'adsf', '1234', 'advb xz', '2026-04-22', 'aktif');

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `id_booking` varchar(36) NOT NULL DEFAULT uuid(),
  `id_fasilitas` varchar(36) NOT NULL,
  `waktu_mulai` datetime NOT NULL,
  `waktu_selesai` datetime NOT NULL,
  `kode_akses` varchar(20) DEFAULT NULL,
  `status` enum('pending','dikonfirmasi','selesai','dibatalkan') NOT NULL DEFAULT 'pending',
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id_booking` int(11) NOT NULL,
  `id_anggota` int(11) DEFAULT NULL,
  `id_fasilitas` varchar(36) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `status_booking` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `buku`
--

CREATE TABLE `buku` (
  `id_buku` varchar(36) NOT NULL DEFAULT uuid(),
  `barcode` varchar(50) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `pengarang` varchar(255) NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `tahun_terbit` int(11) DEFAULT NULL,
  `status` enum('tersedia','dipinjam','rusak','hilang') NOT NULL DEFAULT 'tersedia',
  `stok` int(11) DEFAULT NULL,
  `cover_url` varchar(255) DEFAULT NULL,
  `foto_pengarang` varchar(255) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `edisi` varchar(50) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buku`
--

INSERT INTO `buku` (`id_buku`, `barcode`, `judul`, `pengarang`, `kategori`, `tahun_terbit`, `status`, `stok`, `cover_url`, `foto_pengarang`, `isbn`, `edisi`, `deskripsi`) VALUES
('2303f878-372b-11f1-9978-d0395778e6bc', 'BK2026041378CFF7', 'Laut Bercerita', 'Gita', 'Fiksi', 2024, 'tersedia', 0, 'aset/covers/cover_69dcd2178bfb2.jpg', NULL, '314135676543', '2024', '\r\nLayout 2 kolom (cover kiri, form kanan)\r\nNavbar clean dark\r\nForm modern (rounded + focus effect)\r\nUpload cover interaktif\r\nResponsive (HP tetap rapi)\r\n'),
('f0e9dc6f-372b-11f1-9978-d0395778e6bc', 'BK20260413104249', 'Lalalalala', 'Gita', 'Fiksi', 2024, 'tersedia', 6, 'aset/covers/cover_69dcd37102b5a.jpeg', NULL, '314135670543', '2024', '🔥 Hasilnya:\r\nLayout 2 kolom (cover kiri, form kanan)\r\nNavbar clean dark\r\nForm modern (rounded + focus effect)\r\nUpload cover interaktif\r\nResponsive (HP tetap rapi)\r\n\r\nKalau kamu mau, aku bisa bantu:\r\n\r\nbikin versi lebih aesthetic (glassmorphism / neumorphism)\r\natau \r\nsamain style dengan halaman books kamu biar konsisten\r\n\r\nTinggal bilang aja 👍');

-- --------------------------------------------------------

--
-- Table structure for table `denda`
--

CREATE TABLE `denda` (
  `id_denda` varchar(36) NOT NULL DEFAULT uuid(),
  `id_peminjaman` varchar(36) NOT NULL,
  `jumlah_denda` int(11) NOT NULL DEFAULT 0,
  `jenis` enum('keterlambatan','kerusakan','kehilangan') NOT NULL,
  `status_bayar` enum('belum_bayar','lunas') NOT NULL DEFAULT 'belum_bayar',
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fasilitas`
--

CREATE TABLE `fasilitas` (
  `id` varchar(36) NOT NULL,
  `nama_fasilitas` varchar(100) NOT NULL,
  `kategori` enum('ruang_diskusi','meja_baca','ruang_komputer') NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `kapasitas` int(11) DEFAULT 0,
  `lokasi` enum('Lantai 1','Lantai 2','Lantai 3') DEFAULT 'Lantai 1',
  `status` enum('tersedia','dipesan','maintenance') DEFAULT 'tersedia',
  `gambar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fasilitas`
--

INSERT INTO `fasilitas` (`id`, `nama_fasilitas`, `kategori`, `deskripsi`, `kapasitas`, `lokasi`, `status`, `gambar`, `created_at`) VALUES
('', 'meja A', 'ruang_diskusi', 'adaeadzxcf', 0, 'Lantai 1', 'tersedia', '1776501226_124240132_tugas3.png', '2026-04-18 08:33:46'),
('0a81b848b1a106e515cadee47f1ffaeb', '', '', 'adaeadzxcf', 0, '', '', '', '2026-04-18 09:40:10'),
('6bab420b17457cbb1e8feac1403fd87c', 'AAAAA', 'ruang_diskusi', 'A. TUJUAN\r\n● Praktikan mampu Memahami berbagai tipe Task dalam BPMN.\r\n● Praktikan mampu memahami konsep Event-driven Modeling.\r\n● Praktikan mampu mengimplementasikan Boundary Events (Timer,\r\nMessage, Error) dalam diagram BPMN.\r\n● Praktikan mampu menggunakan Artifacts (Data Object & Annotation)\r\nuntuk memperjelas informasi model.\r\n● Merancang model proses bisnis yang kompleks dan realistis dengan\r\nmenggabungkan berbagai elemen BPMN\r\n', 10, 'Lantai 1', 'tersedia', 'img_1776505619.png', '2026-04-18 09:46:59'),
('a20881b7be77a98d4de204e55a7bd4fe', 'AAAAA', 'ruang_komputer', 'asdfsa', 5, 'Lantai 1', 'maintenance', 'img_1776505435.png', '2026-04-18 09:43:55');

-- --------------------------------------------------------

--
-- Table structure for table `koleksi_digital`
--

CREATE TABLE `koleksi_digital` (
  `id_koleksi` varchar(36) NOT NULL DEFAULT uuid(),
  `id_buku` varchar(36) NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `format` varchar(20) NOT NULL DEFAULT 'PDF',
  `tanggal_upload` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laporan`
--

CREATE TABLE `laporan` (
  `id_laporan` varchar(36) NOT NULL,
  `id_anggota` int(11) DEFAULT NULL,
  `tipe_laporan` enum('Issue','Incident','Irregular Activity') NOT NULL,
  `tgl_kejadian` datetime NOT NULL,
  `terkait_item` varchar(255) DEFAULT NULL,
  `deskripsi` text NOT NULL,
  `bukti_file` varchar(255) DEFAULT NULL,
  `dibuat_pada` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id_peminjaman` varchar(36) NOT NULL DEFAULT uuid(),
  `id_anggota` int(11) DEFAULT NULL,
  `id_buku` varchar(36) NOT NULL,
  `tgl_pinjam` date NOT NULL DEFAULT curdate(),
  `tgl_jatuh_tempo` date NOT NULL,
  `tgl_kembali` date DEFAULT NULL,
  `status` enum('dipinjam','pending','dikembalikan','terlambat','hilang','ditolak') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `peminjaman`
--

INSERT INTO `peminjaman` (`id_peminjaman`, `id_anggota`, `id_buku`, `tgl_pinjam`, `tgl_jatuh_tempo`, `tgl_kembali`, `status`) VALUES
('bfc364ac-3e6d-11f1-b427-d0395778e6bc', 1, 'f0e9dc6f-372b-11f1-9978-d0395778e6bc', '2026-04-22', '2026-04-29', NULL, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `pengguna`
--

CREATE TABLE `pengguna` (
  `id_pengguna` varchar(36) NOT NULL DEFAULT uuid(),
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('anggota','pustakawan','staf_it','kepala') NOT NULL DEFAULT 'anggota',
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengguna`
--

INSERT INTO `pengguna` (`id_pengguna`, `nama`, `email`, `password_hash`, `role`, `aktif`, `dibuat_pada`) VALUES
('a54f4696-341c-11f1-9897-d0395778e6bc', 'Gita Antonia Sipayung', 'gitasipayung04@gmail.com', '$2y$10$EIlW6S4N/.odxN5Ms6Js1.kcGTzKJvewV09noVxUZ7dQqusBdU1b.', 'anggota', 0, '2026-04-09 21:01:46'),
('bcf38f2f-3b15-11f1-a975-d0395778e6bc', 'Gita Antonia Sipayung', 'gita@gmail.com', '$2y$10$s5gVKfw5r4Q5j8B7MOso.ugCkMneor7KJMTwz4BMvIXCSq01hQA5i', 'anggota', 0, '2026-04-18 17:59:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `anggota`
--
ALTER TABLE `anggota`
  ADD PRIMARY KEY (`id_anggota`),
  ADD UNIQUE KEY `id_pengguna` (`id_pengguna`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nim` (`nim`);

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`id_booking`),
  ADD KEY `id_fasilitas` (`id_fasilitas`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id_booking`),
  ADD KEY `id_fasilitas` (`id_fasilitas`),
  ADD KEY `fk_bookings_anggota_baru` (`id_anggota`);

--
-- Indexes for table `buku`
--
ALTER TABLE `buku`
  ADD PRIMARY KEY (`id_buku`),
  ADD UNIQUE KEY `barcode` (`barcode`);

--
-- Indexes for table `denda`
--
ALTER TABLE `denda`
  ADD PRIMARY KEY (`id_denda`),
  ADD KEY `id_peminjaman` (`id_peminjaman`);

--
-- Indexes for table `fasilitas`
--
ALTER TABLE `fasilitas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `koleksi_digital`
--
ALTER TABLE `koleksi_digital`
  ADD PRIMARY KEY (`id_koleksi`),
  ADD UNIQUE KEY `id_buku` (`id_buku`);

--
-- Indexes for table `laporan`
--
ALTER TABLE `laporan`
  ADD PRIMARY KEY (`id_laporan`),
  ADD KEY `fk_laporan_anggota_baru` (`id_anggota`);

--
-- Indexes for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id_peminjaman`),
  ADD KEY `id_buku` (`id_buku`),
  ADD KEY `fk_peminjaman_anggota_baru` (`id_anggota`);

--
-- Indexes for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id_pengguna`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anggota`
--
ALTER TABLE `anggota`
  MODIFY `id_anggota` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id_booking` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `anggota`
--
ALTER TABLE `anggota`
  ADD CONSTRAINT `anggota_ibfk_1` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`);

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`id_fasilitas`) REFERENCES `fasilitas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bookings_anggota_baru` FOREIGN KEY (`id_anggota`) REFERENCES `anggota` (`id_anggota`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `denda`
--
ALTER TABLE `denda`
  ADD CONSTRAINT `denda_ibfk_1` FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman` (`id_peminjaman`);

--
-- Constraints for table `koleksi_digital`
--
ALTER TABLE `koleksi_digital`
  ADD CONSTRAINT `koleksi_digital_ibfk_1` FOREIGN KEY (`id_buku`) REFERENCES `buku` (`id_buku`);

--
-- Constraints for table `laporan`
--
ALTER TABLE `laporan`
  ADD CONSTRAINT `fk_laporan_anggota_baru` FOREIGN KEY (`id_anggota`) REFERENCES `anggota` (`id_anggota`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `fk_peminjaman_anggota_baru` FOREIGN KEY (`id_anggota`) REFERENCES `anggota` (`id_anggota`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`id_buku`) REFERENCES `buku` (`id_buku`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

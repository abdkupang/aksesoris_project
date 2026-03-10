-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 05, 2026 at 11:17 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_penjualan_aksesoris`
--

-- --------------------------------------------------------

--
-- Table structure for table `bukti_transfer`
--

CREATE TABLE `bukti_transfer` (
  `id_bukti_transfer` int UNSIGNED NOT NULL,
  `id_pesanan` int UNSIGNED NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `status_verifikasi` enum('menunggu','diterima','ditolak') NOT NULL DEFAULT 'menunggu',
  `catatan` text,
  `diunggah_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diverifikasi_pada` datetime DEFAULT NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bukti_transfer`
--

INSERT INTO `bukti_transfer` (`id_bukti_transfer`, `id_pesanan`, `nama_file`, `status_verifikasi`, `catatan`, `diunggah_pada`, `diverifikasi_pada`) VALUES
(1, 1, 'bukti_1_1765358556_60daeb15.jpg', 'diterima', '', '2025-12-10 17:22:36', '2025-12-10 17:26:44'),
(2, 2, 'bukti_2_1766125548_03796f18.png', 'menunggu', '', '2025-12-19 14:25:48', NULL),
(3, 3, 'bukti_3_1766127374_446b2a53.jpg', 'menunggu', '', '2025-12-19 14:56:14', NULL),
(4, 4, 'bukti_4_1766848314_877d9c14.jpg', 'diterima', '', '2025-12-27 23:11:54', '2025-12-27 23:12:58');

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id_kategori` int UNSIGNED NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `deskripsi` text,
  `dibuat_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id_kategori`, `nama_kategori`, `deskripsi`, `dibuat_pada`, `diperbarui_pada`) VALUES
(1, 'Gelang', '', '2025-12-10 16:15:25', '2025-12-10 16:15:25'),
(2, 'celana', 'fhasion', '2025-12-19 11:17:19', '2025-12-19 11:17:19'),
(3, 'Kaca Mata', '', '2025-12-19 13:44:30', '2025-12-19 13:44:30'),
(4, 'rok korea', 'desain terbaru', '2025-12-19 14:15:11', '2025-12-19 14:15:11'),
(5, 'kaos', 'gaya santai', '2025-12-19 14:27:27', '2025-12-19 14:27:27'),
(6, 'anting korea', 'korea style', '2025-12-19 14:30:07', '2025-12-19 14:30:07'),
(7, 'kalung', 'klaung style', '2025-12-19 14:31:17', '2025-12-19 14:31:17');

-- --------------------------------------------------------

--
-- Table structure for table `keranjang`
--

CREATE TABLE `keranjang` (
  `id_keranjang` int UNSIGNED NOT NULL,
  `id_pengguna` int UNSIGNED NOT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `keranjang_item` (
  `id_keranjang_item` int UNSIGNED NOT NULL,
  `id_keranjang` int UNSIGNED NOT NULL,
  `id_produk` int UNSIGNED NOT NULL,
  `jumlah` int NOT NULL DEFAULT '1',
  `harga_saat_ini` decimal(12,2) NOT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `komentar_pelanggan`
--

CREATE TABLE `komentar_pelanggan` (
  `id_komentar` int UNSIGNED NOT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `isi_komentar` text NOT NULL,
  `balasan_penjual` varchar(255) NOT NULL,
  `dijawab_pada` datetime NOT NULL,
  `rating` tinyint UNSIGNED DEFAULT NULL,
  `status_tampil` enum('ya','tidak') NOT NULL DEFAULT 'ya',
  `dibuat_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id_log` int UNSIGNED NOT NULL,
  `id_pengguna` int UNSIGNED DEFAULT NULL,
  `tipe_aktivitas` varchar(50) NOT NULL,
  `deskripsi` text,
  `dibuat_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengguna`
--

CREATE TABLE `pengguna` (
  `id_pengguna` int UNSIGNED NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `kata_sandi` varchar(255) NOT NULL,
  `role` enum('penjual','pembeli') NOT NULL DEFAULT 'pembeli',
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat` text,
  `dibuat_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengguna`
--

INSERT INTO `pengguna` (`id_pengguna`, `nama_lengkap`, `email`, `kata_sandi`, `role`, `no_hp`, `alamat`, `dibuat_pada`, `diperbarui_pada`) VALUES
(1, 'thesa', 'thesa@gmail.com', '$2y$10$N/.RWKxnhm32b5ElKa4Ifu9E84Hsa06ZSAvu9CbjPlv9jKDMGa4w.', 'penjual', '082145365693', 'gowa', '2025-12-04 18:33:09', '2025-12-04 18:39:33'),
(2, 'tea', 'tea@gmail.com', '$2y$10$7upZBtyYxCjhAEPysFdBkO2X1T4RRvucdc9z/st1otcPDLuHK2o76', 'pembeli', '09214567', 'makassar', '2025-12-04 18:39:12', '2025-12-04 18:39:12');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id_pesanan` int UNSIGNED NOT NULL,
  `kode_pesanan` varchar(50) NOT NULL,
  `id_pengguna` int UNSIGNED NOT NULL,
  `total_harga` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status_pesanan` enum('menunggu_pembayaran','menunggu_konfirmasi','diproses','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'menunggu_pembayaran',
  `metode_pembayaran` varchar(50) NOT NULL DEFAULT 'transfer_bank',
  `nama_penerima` varchar(100) NOT NULL,
  `no_hp_penerima` varchar(20) DEFAULT NULL,
  `alamat_pengiriman` text NOT NULL,
  `catatan` text,
  `dibuat_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `pesanan_item` (
  `id_pesanan_item` int UNSIGNED NOT NULL,
  `id_pesanan` int UNSIGNED NOT NULL,
  `id_produk` int UNSIGNED NOT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `harga` decimal(12,2) NOT NULL,
  `jumlah` int NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `produk` (
  `id_produk` int UNSIGNED NOT NULL,
  `id_kategori` int UNSIGNED DEFAULT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `deskripsi` text,
  `gambar_produk` varchar(255) DEFAULT NULL,
  `harga` decimal(12,2) NOT NULL,
  `stok` int NOT NULL DEFAULT '0',
  `status_produk` enum('draft','aktif','nonaktif') NOT NULL DEFAULT 'draft',
  `dibuat_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diperbarui_pada` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id_produk`, `id_kategori`, `nama_produk`, `deskripsi`, `gambar_produk`, `harga`, `stok`, `status_produk`, `dibuat_pada`, `diperbarui_pada`) VALUES
(2, 1, 'Gelang Besi Titanium', '', 'produk_2_1765357130_3c8e0f8b.webp', '35000.00', 399, 'aktif', '2025-12-10 16:20:01', '2025-12-19 14:12:49'),
(3, 3, 'Kaca mata hitam', '', 'produk_3_1766123136_2526eb8b.jpg', '3500000.00', 3, 'aktif', '2025-12-19 13:45:28', '2025-12-19 14:25:11'),
(4, 2, 'celana keren', 'berkualitas', 'produk_4_1766123490_c27d6dde.jpg', '290000.00', 49, 'aktif', '2025-12-19 13:51:15', '2025-12-19 13:51:30'),
(5, 4, 'rok pendek', 'rok korea style', 'produk_5_1766125093_1e9bf662.jpg', '89000.00', 12, 'aktif', '2025-12-19 14:16:24', '2025-12-19 14:18:13'),
(6, 5, 'kaos keren', 'gaya santai', 'produk_6_1766125709_97208bb4.jpg', '105000.00', 24, 'aktif', '2025-12-19 14:28:19', '2025-12-19 14:28:29'),
(7, 2, 'celana tisu', 'celana kantor', 'produk_7_1766125763_52d56c2e.jpg', '200000.00', 12, 'aktif', '2025-12-19 14:29:12', '2025-12-19 14:29:23'),
(8, 6, 'anting cewe', 'anting korea', 'produk_8_1766125844_a14fed16.jpg', '20000.00', 23, 'aktif', '2025-12-19 14:30:36', '2025-12-19 14:30:44'),
(9, 7, 'kalung salib', 'kalung style salib', 'produk_9_1766125918_57e8f49e.jpg', '35000.00', 29, 'aktif', '2025-12-19 14:31:46', '2025-12-19 14:55:52'),
(10, 4, 'rok style korea', 'style terbaru', 'produk_10_1766848116_0d5fada3.jpg', '190000.00', 10, 'aktif', '2025-12-27 23:08:23', '2025-12-27 23:11:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bukti_transfer`
--
ALTER TABLE `bukti_transfer`
  ADD PRIMARY KEY (`id_bukti_transfer`),
  ADD KEY `fk_bukti_transfer_pesanan` (`id_pesanan`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indexes for table `keranjang`
--
ALTER TABLE `keranjang`
  ADD PRIMARY KEY (`id_keranjang`),
  ADD KEY `fk_keranjang_pengguna` (`id_pengguna`);

--
-- Indexes for table `keranjang_item`
--
ALTER TABLE `keranjang_item`
  ADD PRIMARY KEY (`id_keranjang_item`),
  ADD KEY `fk_keranjang_item_keranjang` (`id_keranjang`),
  ADD KEY `fk_keranjang_item_produk` (`id_produk`);

--
-- Indexes for table `komentar_pelanggan`
--
ALTER TABLE `komentar_pelanggan`
  ADD PRIMARY KEY (`id_komentar`);

--
-- Indexes for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `fk_log_pengguna` (`id_pengguna`);

--
-- Indexes for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id_pengguna`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_pengguna_role` (`role`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id_pesanan`),
  ADD UNIQUE KEY `kode_pesanan` (`kode_pesanan`),
  ADD KEY `idx_pesanan_status` (`status_pesanan`),
  ADD KEY `idx_pesanan_pengguna` (`id_pengguna`);

--
-- Indexes for table `pesanan_item`
--
ALTER TABLE `pesanan_item`
  ADD PRIMARY KEY (`id_pesanan_item`),
  ADD KEY `fk_pesanan_item_pesanan` (`id_pesanan`),
  ADD KEY `fk_pesanan_item_produk` (`id_produk`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id_produk`),
  ADD KEY `fk_produk_kategori` (`id_kategori`),
  ADD KEY `idx_produk_status` (`status_produk`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bukti_transfer`
--
ALTER TABLE `bukti_transfer`
  MODIFY `id_bukti_transfer` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id_kategori` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `keranjang`
--
ALTER TABLE `keranjang`
  MODIFY `id_keranjang` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `keranjang_item`
--
ALTER TABLE `keranjang_item`
  MODIFY `id_keranjang_item` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `komentar_pelanggan`
--
ALTER TABLE `komentar_pelanggan`
  MODIFY `id_komentar` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id_log` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `id_pengguna` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id_pesanan` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pesanan_item`
--
ALTER TABLE `pesanan_item`
  MODIFY `id_pesanan_item` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id_produk` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bukti_transfer`
--
ALTER TABLE `bukti_transfer`
  ADD CONSTRAINT `fk_bukti_transfer_pesanan` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `keranjang`
--
ALTER TABLE `keranjang`
  ADD CONSTRAINT `fk_keranjang_pengguna` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `keranjang_item`
--
ALTER TABLE `keranjang_item`
  ADD CONSTRAINT `fk_keranjang_item_keranjang` FOREIGN KEY (`id_keranjang`) REFERENCES `keranjang` (`id_keranjang`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_keranjang_item_produk` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD CONSTRAINT `fk_log_pengguna` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `fk_pesanan_pengguna` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `pesanan_item`
--
ALTER TABLE `pesanan_item`
  ADD CONSTRAINT `fk_pesanan_item_pesanan` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pesanan_item_produk` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id_kategori`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;



ALTER TABLE pengguna
ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER kata_sandi,
ADD COLUMN email_verified_at DATETIME NULL AFTER is_verified;


CREATE TABLE email_tokens (
    id_token INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pengguna INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    jenis ENUM('aktivasi','reset_password') NOT NULL,
    expired_at DATETIME NOT NULL,
    digunakan TINYINT(1) NOT NULL DEFAULT 0,
    dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_token_pengguna
        FOREIGN KEY (id_pengguna)
        REFERENCES pengguna(id_pengguna)
        ON DELETE CASCADE,
    UNIQUE (token)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 02, 2026 at 05:52 AM
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
-- Database: `ticketing_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `aset_ipsrs`
--

CREATE TABLE `aset_ipsrs` (
  `id` int UNSIGNED NOT NULL,
  `no_inventaris` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nomor inventaris unik, contoh: INV-IPSRS-2025-0001',
  `jenis_aset` enum('Medis','Non-Medis') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Non-Medis' COMMENT 'Jenis peralatan',
  `nama_aset` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama / deskripsi aset',
  `kategori` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kategori sesuai jenis (mis: Ventilator, Pompa, HVAC)',
  `merek` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_aset` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Model / tipe perangkat',
  `serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_aset_rs` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor registrasi aset internal rumah sakit',
  `kondisi` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Baik',
  `status_pakai` enum('Terpakai','Tidak Terpakai','Dipinjam') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Terpakai' COMMENT 'Status penggunaan aset',
  `bagian_id` int UNSIGNED DEFAULT NULL COMMENT 'FK → bagian.id',
  `lokasi` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama bagian/instalasi',
  `pj_user_id` int UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `penanggung_jawab` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama PJ / teknisi',
  `tanggal_beli` date DEFAULT NULL,
  `harga_beli` bigint UNSIGNED DEFAULT NULL COMMENT 'Harga perolehan dalam Rupiah',
  `sumber_dana` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'APBN, APBD, BLUD, Hibah, dll',
  `no_bast` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor Berita Acara Serah Terima',
  `garansi_sampai` date DEFAULT NULL,
  `tgl_kalibrasi_terakhir` date DEFAULT NULL,
  `tgl_kalibrasi_berikutnya` date DEFAULT NULL,
  `no_sertifikat_kalibrasi` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tgl_service_terakhir` date DEFAULT NULL,
  `tgl_service_berikutnya` date DEFAULT NULL,
  `vendor_service` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama vendor atau teknisi service',
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Aset IPSRS — Medis & Non-Medis';

--
-- Dumping data for table `aset_ipsrs`
--

INSERT INTO `aset_ipsrs` (`id`, `no_inventaris`, `jenis_aset`, `nama_aset`, `kategori`, `merek`, `model_aset`, `serial_number`, `no_aset_rs`, `kondisi`, `status_pakai`, `bagian_id`, `lokasi`, `pj_user_id`, `penanggung_jawab`, `tanggal_beli`, `harga_beli`, `sumber_dana`, `no_bast`, `garansi_sampai`, `tgl_kalibrasi_terakhir`, `tgl_kalibrasi_berikutnya`, `no_sertifikat_kalibrasi`, `tgl_service_terakhir`, `tgl_service_berikutnya`, `vendor_service`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'INV-IPSRS-2026-0001', 'Non-Medis', 'Pompa Air', 'Peralatan Listrik', 'Philips', 'A11', '009992888989811', 'RS-MED-01', 'Baik', 'Terpakai', 5, 'Operasional', 9, 'Giano', '2026-03-04', 7500000, 'Lainnya', 'BA/01/0001/2026', '2026-03-04', '2026-03-04', '2026-03-11', '1100029990901', '2026-03-04', '2026-03-11', 'Internal', '-', 8, '2026-03-04 15:07:26', NULL),
(2, 'INV-IPSRS-2024-0001', 'Medis', 'Ventilator ICU', 'Ventilator', 'Drager', 'Evita V800', 'DRG-2024-00123', 'RS-MED-001', 'Baik', 'Terpakai', NULL, 'Ruang ICU Lt. 2', NULL, 'Teknisi IPSRS', '2022-03-15', 385000000, 'BLUD', 'BAST/2022/03/001', '2025-03-15', '2024-01-10', '2025-01-10', 'KAL/2024/001', '2024-06-01', '2025-06-01', 'PT Drager Indonesia', 'Ventilator dewasa kapasitas penuh, termasuk modul humidifier', 1, '2026-03-04 15:09:21', NULL),
(3, 'INV-IPSRS-2024-0002', 'Medis', 'USG Color Doppler', 'USG / Imaging', 'GE Healthcare', 'LOGIQ E10', 'GE-LOG-E10-4892', 'RS-MED-002', 'Baik', 'Terpakai', NULL, 'Poli Kandungan', NULL, 'Teknisi IPSRS', '2023-07-20', 520000000, 'APBD', 'BAST/2023/07/005', '2026-07-20', '2024-07-15', '2025-07-15', 'KAL/2024/002', '2024-07-15', '2025-07-15', 'PT GE Healthcare Indonesia', 'USG 4D dengan probe konveks dan linear, untuk kandungan & umum', 1, '2026-03-04 15:09:21', NULL),
(4, 'INV-IPSRS-2024-0003', 'Medis', 'Defibrilator AED', 'Defibrilator / AED', 'Philips', 'HeartStart MRx', 'PHL-MRX-2023-881', 'RS-MED-003', 'Baik', 'Terpakai', NULL, 'IGD / UGD', NULL, 'Teknisi IPSRS', '2023-01-10', 145000000, 'BLUD', 'BAST/2023/01/002', '2026-01-10', '2024-03-01', '2025-03-01', 'KAL/2024/003', '2024-03-01', '2025-03-01', 'PT Philips Indonesia', 'AED dengan kemampuan monitoring 12 lead dan pacu jantung eksternal', 1, '2026-03-04 15:09:21', '2026-03-06 13:47:32'),
(5, 'INV-IPSRS-2024-0004', 'Medis', 'Infus Pump Syringe', 'Infus Pump / Syringe Pump', 'Terumo', 'TE-SS700', 'TRM-SS700-2022-44', 'RS-MED-004', 'Dalam Perbaikan', 'Tidak Terpakai', NULL, 'Ruang Perawatan Anak', NULL, 'Teknisi IPSRS', '2022-09-05', 28500000, 'BLUD', 'BAST/2022/09/003', '2025-09-05', '2023-09-10', '2024-09-10', 'KAL/2023/004', '2024-09-10', '2025-09-10', 'PT Terumo Indonesia', 'Syringe pump presisi tinggi, sedang dalam perbaikan sensor tekanan', 1, '2026-03-04 15:09:21', NULL),
(6, 'INV-IPSRS-2024-0005', 'Medis', 'Inkubator Bayi', 'Inkubator / Infant Warmer', 'Draeger', 'Isolette C2', 'DRG-ISO-C2-2023-77', 'RS-MED-005', 'Baik', 'Terpakai', NULL, 'Ruang Perinatologi', NULL, 'Teknisi IPSRS', '2023-11-12', 195000000, 'APBD', 'BAST/2023/11/004', '2026-11-12', '2024-05-20', '2025-05-20', 'KAL/2024/005', '2024-05-20', '2025-05-20', 'PT Draeger Medical Indonesia', 'Inkubator neonatus dengan kontrol suhu & kelembaban otomatis', 1, '2026-03-04 15:09:21', NULL),
(7, 'INV-IPSRS-2024-0006', 'Non-Medis', 'Generator Set (Genset)', 'Generator / Panel Listrik', 'Caterpillar', 'DE400E0', 'CAT-DE400-2021-005', 'RS-NM-001', 'Baik', 'Terpakai', NULL, 'Ruang Genset Basement', NULL, 'Teknisi IPSRS', '2021-04-01', 875000000, 'APBN', 'BAST/2021/04/001', '2026-04-01', NULL, NULL, NULL, '2024-10-01', '2025-04-01', 'PT Trakindo Utama', 'Genset 400 KVA cadangan utama RS, servis berkala 6 bulan sekali', 1, '2026-03-04 15:09:21', NULL),
(8, 'INV-IPSRS-2024-0007', 'Non-Medis', 'AC Central Chiller', 'Peralatan HVAC / AC', 'Daikin', 'EWAQ016BAVP', 'DKN-CHILLER-2022-11', 'RS-NM-002', 'Baik', 'Terpakai', NULL, 'Ruang Mekanikal Atap Lt. 4', NULL, 'Teknisi IPSRS', '2022-06-15', 650000000, 'BLUD', 'BAST/2022/06/002', '2027-06-15', NULL, NULL, NULL, '2024-08-15', '2025-02-15', 'PT Daikin Airconditioning Indonesia', 'Chiller 160 TR untuk sistem AC central gedung utama RS', 1, '2026-03-04 15:09:21', '2026-03-04 15:23:32'),
(9, 'INV-IPSRS-2024-0008', 'Non-Medis', 'Pompa Air Sentrifugal', 'Pompa / Kompresor', 'Grundfos', 'CM10-3', 'GRF-CM10-2023-33', 'RS-NM-003', 'Rusak', 'Tidak Terpakai', NULL, 'Ruang Pompa Basement', NULL, 'Teknisi IPSRS', '2023-02-20', 18500000, 'BLUD', 'BAST/2023/02/003', '2026-02-20', NULL, NULL, NULL, '2023-08-20', '2024-08-20', 'PT Grundfos Pumps Indonesia', 'Pompa distribusi air bersih lantai 3-4, bearing rusak menunggu suku cadang', 1, '2026-03-04 15:09:21', NULL),
(10, 'INV-IPSRS-2024-0009', 'Non-Medis', 'Ambulans Transport', 'Kendaraan / Ambulans', 'Toyota', 'HiAce Commuter 2.8', 'MHFH3FH40P0-12345', 'RS-NM-004', 'Baik', 'Terpakai', NULL, 'Garasi Kendaraan RS', NULL, 'Teknisi IPSRS', '2023-05-17', 425000000, 'APBD', 'BAST/2023/05/004', '2026-05-17', NULL, NULL, NULL, '2024-11-01', '2025-05-01', 'Auto2000 Authorized Service', 'Ambulans transport pasien rujukan, kapasitas 1 tandu + 2 pendamping', 1, '2026-03-04 15:09:21', '2026-03-06 13:46:46'),
(11, 'INV-IPSRS-2024-0010', 'Non-Medis', 'Sistem CCTV 32 Channel', 'Peralatan Keamanan / CCTV', 'Hikvision', 'DS-7732NI-I4', 'HKV-NVR32-2023-09', 'RS-NM-005', 'Baik', 'Terpakai', NULL, 'Ruang Security / Control Room', NULL, 'Teknisi IPSRS', '2023-08-10', 95000000, 'BLUD', 'BAST/2023/08/005', '2026-08-10', NULL, NULL, NULL, '2024-08-10', '2025-08-10', 'PT Hikvision Indonesia', 'NVR 32 channel dengan 28 unit IP camera resolusi 4MP, storage 16TB', 1, '2026-03-04 15:09:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `aset_it`
--

CREATE TABLE `aset_it` (
  `id` int UNSIGNED NOT NULL,
  `no_inventaris` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nomor inventaris unik, contoh: INV-IT-2025-0001',
  `nama_aset` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama / deskripsi aset',
  `kategori` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Laptop, Printer, Switch, dll',
  `merek` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_aset` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Model / tipe perangkat',
  `serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kondisi` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Baik',
  `status_pakai` enum('Terpakai','Tidak Terpakai','Dipinjam') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Terpakai' COMMENT 'Status penggunaan aset',
  `bagian_id` int UNSIGNED DEFAULT NULL COMMENT 'FK → bagian.id',
  `lokasi` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama bagian (fallback display)',
  `pj_user_id` int UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `penanggung_jawab` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama PJ (fallback display)',
  `tanggal_beli` date DEFAULT NULL,
  `harga_beli` bigint UNSIGNED DEFAULT NULL COMMENT 'Harga dalam Rupiah',
  `garansi_sampai` date DEFAULT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventaris / Aset IT';

--
-- Dumping data for table `aset_it`
--

INSERT INTO `aset_it` (`id`, `no_inventaris`, `nama_aset`, `kategori`, `merek`, `model_aset`, `serial_number`, `kondisi`, `status_pakai`, `bagian_id`, `lokasi`, `pj_user_id`, `penanggung_jawab`, `tanggal_beli`, `harga_beli`, `garansi_sampai`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'INV-IT-2025-0001', 'Laptop Dell Latitude', 'Laptop', 'Dell', 'Latitude 5520', 'DLLAT5520-0001', 'Baik', 'Terpakai', 1, 'IT / Helpdesk', 2, '(sesuai user)', '2023-01-15', 14500000, '2026-01-15', 'Laptop staf IT helpdesk', NULL, '2026-02-26 21:14:11', '2026-02-26 22:25:28'),
(2, 'INV-IT-2025-0002', 'Printer HP LaserJet', 'Printer', 'HP', 'LaserJet Pro M404dn', 'HPLJ404-0002', 'Baik', 'Terpakai', 3, 'Administrasi', 4, '(sesuai user)', '2022-06-20', 4800000, '2025-06-20', NULL, NULL, '2026-02-26 21:14:11', NULL),
(3, 'INV-IT-2025-0003', 'Switch Cisco Catalyst', 'Switch', 'Cisco', 'Catalyst 2960-X', 'CSC2960X-0003', 'Baik', 'Terpakai', 1, 'Server Room', 2, '(sesuai user)', '2021-03-10', 22000000, '2024-03-10', 'Core switch lantai 1', NULL, '2026-02-26 21:14:11', NULL),
(4, 'INV-IT-2025-0004', 'UPS APC Smart-UPS', 'UPS', 'APC', 'Smart-UPS 1500', 'APC1500-0004', 'Baik', 'Terpakai', 1, 'IT', 11, 'budi', '2020-09-05', 9500000, NULL, 'Battery perlu diganti', NULL, '2026-02-26 21:14:11', '2026-02-26 21:17:11'),
(5, 'INV-IT-2025-0005', 'Monitor LG 24 inch', 'Monitor', 'LG', '24MK430H', 'LG24MK-0005', 'Baik', 'Terpakai', 5, 'Keuangan', 6, '(sesuai user)', '2023-07-01', 1950000, '2026-07-01', NULL, NULL, '2026-02-26 21:14:11', '2026-02-28 08:48:11'),
(6, 'INV-IT-2026-0006', 'CPU Build UP', 'Komputer', 'Build UP', 'Build UP', '123.123.123.123', 'Baik', 'Tidak Terpakai', 10, 'Gudang IT', 9, 'Giano', '2026-02-26', 7800000, '2029-02-26', 'Ram 8GB , Core i7', 8, '2026-02-26 21:15:28', '2026-03-04 08:00:41');

-- --------------------------------------------------------

--
-- Table structure for table `atk_barang`
--

CREATE TABLE `atk_barang` (
  `id` int UNSIGNED NOT NULL,
  `kode` varchar(40) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `kategori_id` int UNSIGNED DEFAULT NULL,
  `satuan` varchar(40) NOT NULL,
  `stok` int UNSIGNED NOT NULL DEFAULT '0',
  `stok_minimum` int UNSIGNED NOT NULL DEFAULT '0',
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `atk_barang`
--

INSERT INTO `atk_barang` (`id`, `kode`, `nama`, `kategori_id`, `satuan`, `stok`, `stok_minimum`, `aktif`, `created_at`, `updated_at`) VALUES
(1, 'ATK-6D90FE', 'test', 1, 'test', 1, 1, 0, '2026-10-21 14:49:35', '2026-10-21 15:46:49'),
(2, 'ATK-98001E', 'qqq', 1, 'qq', 6, 0, 1, '2026-10-21 15:53:31', '2026-10-21 16:01:47');

-- --------------------------------------------------------

--
-- Table structure for table `atk_kategori`
--

CREATE TABLE `atk_kategori` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'fa-tags',
  `keterangan` varchar(255) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sla_jam` int UNSIGNED NOT NULL DEFAULT '24',
  `sla_respon_jam` int UNSIGNED NOT NULL DEFAULT '4'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `atk_kategori`
--

INSERT INTO `atk_kategori` (`id`, `nama`, `icon`, `keterangan`, `aktif`, `created_at`, `sla_jam`, `sla_respon_jam`) VALUES
(1, 'test', 'fa-clipboard', 'test', 1, '2026-10-21 14:50:07', 24, 3);

-- --------------------------------------------------------

--
-- Table structure for table `atk_mutasi`
--

CREATE TABLE `atk_mutasi` (
  `id` int UNSIGNED NOT NULL,
  `barang_id` int UNSIGNED NOT NULL,
  `jenis` enum('masuk','keluar') NOT NULL,
  `jumlah` int UNSIGNED NOT NULL,
  `catatan` varchar(255) NOT NULL,
  `supplier_id` int UNSIGNED DEFAULT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `atk_mutasi`
--

INSERT INTO `atk_mutasi` (`id`, `barang_id`, `jenis`, `jumlah`, `catatan`, `supplier_id`, `user_id`, `created_at`) VALUES
(1, 1, 'masuk', 1, 'Stok awal', NULL, 17, '2026-10-21 14:49:35'),
(2, 2, 'masuk', 3, 'Stok awal', NULL, 17, '2026-10-21 15:53:31'),
(3, 2, 'masuk', 3, '33', 1, 17, '2026-10-21 16:01:47');

-- --------------------------------------------------------

--
-- Table structure for table `atk_opname`
--

CREATE TABLE `atk_opname` (
  `id` int UNSIGNED NOT NULL,
  `nomor` varchar(40) NOT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atk_opname_detail`
--

CREATE TABLE `atk_opname_detail` (
  `id` int UNSIGNED NOT NULL,
  `opname_id` int UNSIGNED NOT NULL,
  `barang_id` int UNSIGNED NOT NULL,
  `stok_sistem` int UNSIGNED NOT NULL,
  `stok_fisik` int UNSIGNED NOT NULL,
  `selisih` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atk_pengajuan`
--

CREATE TABLE `atk_pengajuan` (
  `id` int UNSIGNED NOT NULL,
  `nomor` varchar(40) NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `pemohon_nama` varchar(100) DEFAULT NULL,
  `pemohon_telepon` varchar(30) DEFAULT NULL,
  `pemohon_divisi` varchar(100) DEFAULT NULL,
  `atk_kategori_id` int UNSIGNED DEFAULT NULL,
  `barang_id` int UNSIGNED DEFAULT NULL,
  `nama_barang` varchar(150) NOT NULL,
  `satuan` varchar(40) NOT NULL,
  `jumlah` int UNSIGNED NOT NULL,
  `alasan` text NOT NULL,
  `status` enum('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  `catatan_approval` text,
  `approved_by` int UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atk_permintaan`
--

CREATE TABLE `atk_permintaan` (
  `id` int UNSIGNED NOT NULL,
  `nomor` varchar(40) NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `pemohon_nama` varchar(100) DEFAULT NULL,
  `pemohon_telepon` varchar(30) DEFAULT NULL,
  `pemohon_divisi` varchar(100) DEFAULT NULL,
  `barang_id` int UNSIGNED NOT NULL,
  `jumlah` int UNSIGNED NOT NULL,
  `keperluan` text NOT NULL,
  `status` enum('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  `catatan_approval` text,
  `approved_by` int UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `atk_permintaan`
--

INSERT INTO `atk_permintaan` (`id`, `nomor`, `user_id`, `barang_id`, `jumlah`, `keperluan`, `status`, `catatan_approval`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 'REQ-20261102-817528', 17, 2, 3, 'wkhqhwq', 'menunggu', NULL, NULL, NULL, '2026-11-02 12:47:59');

-- --------------------------------------------------------

--
-- Table structure for table `atk_supplier`
--

CREATE TABLE `atk_supplier` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(150) NOT NULL,
  `kontak` varchar(100) DEFAULT NULL,
  `telepon` varchar(40) DEFAULT NULL,
  `alamat` text,
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `atk_supplier`
--

INSERT INTO `atk_supplier` (`id`, `nama`, `kontak`, `telepon`, `alamat`, `aktif`, `created_at`) VALUES
(1, 'test', 'test', 'test', 'test', 1, '2026-10-21 16:01:30');

-- --------------------------------------------------------

--
-- Table structure for table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `id` int NOT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama file backup .sql',
  `filesize` bigint NOT NULL DEFAULT '0' COMMENT 'Ukuran file dalam bytes',
  `type` enum('manual','auto') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual' COMMENT 'Jenis backup',
  `status` enum('success','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success' COMMENT 'Status backup',
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Catatan tambahan / pesan error',
  `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'System' COMMENT 'Nama user yang memicu backup',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu backup dibuat'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Riwayat backup database FixSmart Helpdesk';

--
-- Dumping data for table `backup_logs`
--

INSERT INTO `backup_logs` (`id`, `filename`, `filesize`, `type`, `status`, `keterangan`, `created_by`, `created_at`) VALUES
(1, 'backup_manual_20260307_195154.sql', 80152, 'manual', 'success', NULL, 'M Wira', '2026-03-07 19:51:54'),
(2, 'backup_manual_20260307_195905.sql', 80514, 'manual', 'success', NULL, 'M Wira', '2026-03-07 19:59:05'),
(4, 'backup_manual_20261016_205818.sql', 103191, 'manual', 'success', NULL, 'Admin Utama', '2026-10-16 20:58:18');

-- --------------------------------------------------------

--
-- Table structure for table `bagian`
--

CREATE TABLE `bagian` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kode` varchar(20) DEFAULT NULL COMMENT 'Kode singkat, misal: IT, FIN, HRD',
  `deskripsi` text,
  `lokasi` varchar(150) DEFAULT NULL COMMENT 'Lantai / Gedung / Ruangan utama',
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `urutan` int NOT NULL DEFAULT '0' COMMENT 'Urutan tampil di dropdown',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bagian`
--

INSERT INTO `bagian` (`id`, `nama`, `kode`, `deskripsi`, `lokasi`, `status`, `urutan`, `created_at`) VALUES
(1, 'IT', 'IT', 'Divisi Teknologi Informasi', 'LT 1 Ruang IT', 'aktif', 1, '2026-02-25 20:31:32'),
(3, 'HRD', 'HRD', 'Human Resource Development', 'LT 2 Kantor Medis', 'aktif', 2, '2026-02-25 20:31:32'),
(4, 'Marketing', 'MKT', 'Divisi Pemasaran & Penjualan', 'LT 1 Ruang Marketing', 'aktif', 3, '2026-02-25 20:31:32'),
(12, 'Kasir', 'KSR', 'Divisi Transaksi Pembayaran Ranap & Rajal', 'LT 1 Kasir', 'aktif', 4, '2026-10-16 09:32:44'),
(13, 'Front Office', 'FO', 'Divisi Pelayanan Rajal & Ranap', 'LT 1 Front Office', 'aktif', 5, '2026-10-16 09:41:43'),
(14, 'Nurse Station Poli', 'NS', 'Divisi Perawat Poli', 'LT 1 Nurse Station', 'aktif', 6, '2026-10-16 09:42:02'),
(15, 'Apotek', 'APT', 'Divisi Peracikan dan Penjualan Obat', 'LT 1 Apotek', 'aktif', 7, '2026-10-16 09:46:15'),
(16, 'Security', 'SCT', 'Divisi Keamanan', 'Depan', 'aktif', 8, '2026-10-16 09:46:58'),
(17, 'VK', 'VK', 'Divisi Verlos Kamer Kamar Bersalin', 'LT 1 VK', 'aktif', 9, '2026-10-16 09:48:43'),
(18, 'OK', 'OK', 'Divisi Operatie Kamer', 'LT 1 OK', 'aktif', 10, '2026-10-16 09:49:32'),
(19, 'Laboratorium', 'LAB', 'Divisi Laboratorium', 'LT 1 Ruang Lab', 'aktif', 11, '2026-10-16 09:50:32'),
(20, 'Radiologi', 'RAD', 'Divisi Radiologi', 'LT 1 Ruang Radiologi', 'aktif', 12, '2026-10-16 09:51:26'),
(21, 'Fisioterapi', 'FSO', 'Divisi Fisioterapi', 'LT 1 Ruang Fisio', 'aktif', 13, '2026-10-16 09:56:24'),
(22, 'Gizi', 'GZI', 'Divisi Gizi', 'LT 1 Ruang Gizi', 'aktif', 14, '2026-10-16 18:14:39'),
(23, 'Rekam Medis', 'RM', 'Divisi Rekam Medis', 'LT 1 Ruang Rekam Medis', 'aktif', 15, '2026-10-16 18:15:17'),
(24, 'Kesling', 'KLG', 'Divisi Kesehatan Lingkungan', 'LT 2 Ruang Kesling', 'aktif', 16, '2026-10-16 18:16:27'),
(25, 'Sekretariat', 'SKR', 'Divisi Sekretariatan', 'LT 2 Ruang Sekretariat', 'aktif', 17, '2026-10-16 18:17:04'),
(26, 'Management', 'MGT', 'Divisi Management', 'LT 2 Kantor Medis', 'aktif', 18, '2026-10-16 18:18:08'),
(27, 'Wakil Direktur', 'WADIR', 'Divisi Wakil Direktur', 'LT 2 Kantor Medis', 'aktif', 19, '2026-10-16 18:18:28'),
(28, 'Keuangan', 'KEU', 'Divisi Keuangan', 'LT 2 Kantor Keuangan', 'aktif', 20, '2026-10-16 18:19:13'),
(29, 'Gudang Farmasi', 'GDF', 'Divisi Gudang Farmasi', 'LT 2 Ruang Gudang Farmasi', 'aktif', 21, '2026-10-16 18:19:42'),
(30, 'Perina', 'PRN', 'Divisi Perina', 'LT 2 Ruang Perina', 'aktif', 22, '2026-10-16 18:20:05'),
(31, 'Poli Obgyn', 'POG', 'Divisi Nurse Station Poli Obgyn', 'LT 2 Nurse Station', 'aktif', 23, '2026-10-16 18:21:27'),
(32, 'NS Ranap', 'PRNP', 'Divisi Nurse Station Ruang Inap', 'LT 3 Nurse Station', 'aktif', 24, '2026-10-16 18:22:12'),
(33, 'ATEM', 'ATM', 'Divisi Ahli Teknik Elektromedik', 'LT 1 Ruang IT', 'aktif', 25, '2026-10-16 21:29:09'),
(34, 'IPSRS', 'IPSRS', 'Divisi Instalasi Pemeliharaan Sarana Rumah Sakit', 'Gedung RS', 'aktif', 26, '2026-10-16 21:30:13');

-- --------------------------------------------------------

--
-- Table structure for table `berita_acara`
--

CREATE TABLE `berita_acara` (
  `id` int NOT NULL,
  `tiket_id` int NOT NULL,
  `nomor_ba` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_ba` date NOT NULL,
  `jenis_tindak` enum('pembelian_baru','perbaikan_eksternal','penghapusan_aset','penggantian_suku_cadang','lainnya') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'lainnya',
  `uraian_masalah` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `kesimpulan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tindak_lanjut` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `nilai_estimasi` bigint DEFAULT NULL,
  `dibuat_oleh` int NOT NULL,
  `diketahui_nama` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diketahui_jabatan` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mengetahui_nama` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mengetahui_jabatan` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catatan_tambahan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `berita_acara`
--

INSERT INTO `berita_acara` (`id`, `tiket_id`, `nomor_ba`, `tanggal_ba`, `jenis_tindak`, `uraian_masalah`, `kesimpulan`, `tindak_lanjut`, `nilai_estimasi`, `dibuat_oleh`, `diketahui_nama`, `diketahui_jabatan`, `mengetahui_nama`, `mengetahui_jabatan`, `catatan_tambahan`, `created_at`, `updated_at`) VALUES
(1, 26, 'BA-IT-2026-0001', '2026-03-07', 'pembelian_baru', 'Tolong di cek, terkendala tidak bisa nyala', 'Tidak bisa di perbaiki, ada part yang harus di ganti baru (kipas prosessor)', 'Beli baru saja yang lama di buang gpp', 550000, 12, 'Andi, S. Kom', 'Kanit IT', '-', '-', '-', '2026-03-07 08:58:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `berkas_karyawan`
--

CREATE TABLE `berkas_karyawan` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `jenis_berkas_id` int UNSIGNED DEFAULT NULL,
  `kategori_id` int UNSIGNED DEFAULT NULL,
  `nama_file` varchar(255) NOT NULL,
  `nama_asli` varchar(255) NOT NULL,
  `ukuran` int UNSIGNED DEFAULT '0',
  `mime_type` varchar(100) DEFAULT NULL,
  `keterangan` text,
  `tgl_dokumen` date DEFAULT NULL,
  `tgl_exp` date DEFAULT NULL,
  `status_verif` enum('pending','terverifikasi','ditolak') DEFAULT 'pending',
  `catatan_verif` text,
  `verified_by` int UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `uploaded_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `deskripsi` text,
  `icon` varchar(50) DEFAULT 'fa-tag',
  `sla_jam` int NOT NULL DEFAULT '24' COMMENT 'Target penyelesaian dalam jam',
  `sla_respon_jam` int NOT NULL DEFAULT '4' COMMENT 'Target respon pertama dalam jam',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id`, `nama`, `deskripsi`, `icon`, `sla_jam`, `sla_respon_jam`, `created_at`) VALUES
(1, 'Hardware', 'Masalah perangkat keras (PC, laptop, monitor, dll)', 'fa-desktop', 24, 4, '2026-02-25 20:31:32'),
(2, 'Software', 'Instalasi aplikasi, error sistem, OS', 'fa-laptop-code', 8, 2, '2026-02-25 20:31:32'),
(3, 'Jaringan', 'Koneksi internet, WiFi, LAN, VPN', 'fa-network-wired', 4, 1, '2026-02-25 20:31:32'),
(4, 'Email & Akun', 'Reset password, akses email, akun sistem', 'fa-envelope', 4, 1, '2026-02-25 20:31:32'),
(5, 'Printer', 'Printer, scanner, perangkat cetak', 'fa-print', 24, 4, '2026-02-25 20:31:32'),
(6, 'CCTV', 'Kamera CCTV, DVR/NVR, sistem keamanan', 'fa-video', 48, 8, '2026-02-25 20:31:32'),
(7, 'Server', 'Server down, backup, database', 'fa-server', 2, 1, '2026-02-25 20:31:32'),
(8, 'Lainnya', 'Masalah IT lainnya', 'fa-question-circle', 48, 8, '2026-02-25 20:31:32');

-- --------------------------------------------------------

--
-- Table structure for table `kategori_atem`
--

CREATE TABLE `kategori_atem` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fa-heart-pulse',
  `sla_jam` int NOT NULL DEFAULT '24',
  `sla_respon_jam` int NOT NULL DEFAULT '4',
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kategori_atem`
--

INSERT INTO `kategori_atem` (`id`, `nama`, `deskripsi`, `icon`, `sla_jam`, `sla_respon_jam`, `aktif`, `created_at`) VALUES
(3, 'Touchscreen / Monitor', 'Layar sentuh dan monitor anjungan', 'fa-hand-pointer', 48, 8, 1, '2026-10-16 21:12:21'),
(9, 'Peralatan Diagnostik', 'USG, EKG, monitor pasien', 'fa-stethoscope', 8, 2, 1, '2026-10-16 22:43:31'),
(10, 'Ventilator / Respirator', 'Masalah pada ventilator & CPAP', 'fa-lungs', 4, 1, 1, '2026-10-16 22:43:31'),
(11, 'Infus Pump / Syringe', 'Infus pump dan syringe pump', 'fa-syringe', 4, 1, 1, '2026-10-16 22:43:31'),
(12, 'Sterilisasi / Autoclave', 'Peralatan sterilisasi alat medis', 'fa-flask-vial', 8, 2, 1, '2026-10-16 22:43:31'),
(13, 'Peralatan Bedah', 'Alat dan instrumen bedah', 'fa-bandage', 12, 3, 1, '2026-10-16 22:43:31'),
(14, 'Peralatan Laboratorium', 'Alat lab klinik dan PA', 'fa-flask-vial', 12, 3, 1, '2026-10-16 22:43:31'),
(15, 'Radiologi / Imaging', 'Rontgen, CT-scan, MRI', 'fa-radiation', 24, 4, 1, '2026-10-16 22:43:31'),
(16, 'Peralatan Gigi', 'Dental unit dan aksesoris', 'fa-tooth', 24, 4, 1, '2026-10-16 22:43:31'),
(17, 'Peralatan Mata', 'Slit lamp, tonometer, dll', 'fa-eye', 24, 4, 1, '2026-10-16 22:43:31'),
(18, 'Inkubator / Infant', 'Inkubator bayi, infant warmer', 'fa-baby', 4, 1, 1, '2026-10-16 22:43:31'),
(19, 'Lainnya (Medis)', 'Peralatan medis lainnya', 'fa-kit-medical', 24, 4, 1, '2026-10-16 22:43:31');

-- --------------------------------------------------------

--
-- Table structure for table `kategori_ipsrs`
--

CREATE TABLE `kategori_ipsrs` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fa-toolbox',
  `jenis` enum('Medis','Non-Medis') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Non-Medis',
  `sla_jam` int NOT NULL DEFAULT '24',
  `sla_respon_jam` int NOT NULL DEFAULT '4',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kategori_ipsrs`
--

INSERT INTO `kategori_ipsrs` (`id`, `nama`, `deskripsi`, `icon`, `jenis`, `sla_jam`, `sla_respon_jam`, `created_at`) VALUES
(12, 'Instalasi Listrik', 'Panel, kabel, MCB, dll', 'fa-bolt', 'Non-Medis', 8, 2, '2026-03-04 08:55:49'),
(13, 'Generator / UPS', 'Genset, ATS, UPS', 'fa-plug-circle-bolt', 'Non-Medis', 4, 1, '2026-03-04 08:55:49'),
(14, 'HVAC / AC', 'AC, chiller, exhaust fan', 'fa-wind', 'Non-Medis', 12, 3, '2026-03-04 08:55:49'),
(15, 'Sanitasi / Plumbing', 'Pipa, pompa air, WC, wastafel', 'fa-droplet', 'Non-Medis', 12, 3, '2026-03-04 08:55:49'),
(16, 'Pompa / Kompresor', 'Pompa air, kompresor udara', 'fa-gear', 'Non-Medis', 8, 2, '2026-03-04 08:55:49'),
(19, 'Kebersihan', 'Alat kebersihan & pest control', 'fa-broom', 'Non-Medis', 48, 12, '2026-03-04 08:55:49'),
(20, 'Kendaraan / Ambulans', 'Perawatan kendaraan operasional', 'fa-car', 'Non-Medis', 24, 4, '2026-03-04 08:55:49'),
(22, 'Alat Angkat / Angkut', 'Brankar, kursi roda, lift barang', 'fa-dolly', 'Non-Medis', 12, 3, '2026-03-04 08:55:49'),
(23, 'Lainnya (Non-Medis)', 'Sarana & prasarana lainnya', 'fa-toolbox', 'Non-Medis', 48, 12, '2026-03-04 08:55:49');

-- --------------------------------------------------------

--
-- Table structure for table `komentar`
--

CREATE TABLE `komentar` (
  `id` int UNSIGNED NOT NULL,
  `tiket_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `isi` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `komentar_atem`
--

CREATE TABLE `komentar_atem` (
  `id` int UNSIGNED NOT NULL,
  `tiket_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `isi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `legacy_user_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `komentar_ipsrs`
--

CREATE TABLE `komentar_ipsrs` (
  `id` int UNSIGNED NOT NULL,
  `tiket_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `isi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `komentar_ipsrs`
--

INSERT INTO `komentar_ipsrs` (`id`, `tiket_id`, `user_id`, `isi`, `created_at`) VALUES
(1, 10, 10, 'Mohon segera ya mas', '2026-03-07 10:59:43');

-- --------------------------------------------------------

--
-- Table structure for table `koneksi_log`
--

CREATE TABLE `koneksi_log` (
  `id` bigint UNSIGNED NOT NULL,
  `monitor_id` int UNSIGNED NOT NULL,
  `status` enum('online','offline','timeout') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ping_ms` float DEFAULT NULL COMMENT 'Latensi dalam milidetik',
  `http_code` smallint DEFAULT NULL COMMENT 'HTTP status code jika tipe URL',
  `pesan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pesan error / info tambahan',
  `cek_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log hasil pengecekan koneksi per host';

--
-- Dumping data for table `koneksi_log`
--

INSERT INTO `koneksi_log` (`id`, `monitor_id`, `status`, `ping_ms`, `http_code`, `pesan`, `cek_at`) VALUES
(1, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:32:40'),
(2, 2, 'online', 22.12, NULL, 'OK', '2026-02-27 13:32:40'),
(6, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:33:09'),
(7, 2, 'online', 16.95, NULL, 'OK', '2026-02-27 13:33:09'),
(10, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:33:16'),
(11, 2, 'online', 19.89, NULL, 'OK', '2026-02-27 13:33:16'),
(13, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:33:25'),
(14, 2, 'online', 18.59, NULL, 'OK', '2026-02-27 13:33:25'),
(15, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:36:41'),
(16, 2, 'online', 16.26, NULL, 'OK', '2026-02-27 13:36:41'),
(17, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:36:47'),
(18, 2, 'online', 19.93, NULL, 'OK', '2026-02-27 13:36:47'),
(19, 6, 'offline', 4129.43, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:36:51'),
(20, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:36:58'),
(21, 2, 'online', 18.42, NULL, 'OK', '2026-02-27 13:36:58'),
(22, 6, 'timeout', NULL, 0, 'Timeout setelah 5s', '2026-02-27 13:37:03'),
(23, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:38:22'),
(24, 2, 'online', 17.09, NULL, 'OK', '2026-02-27 13:38:22'),
(25, 6, 'offline', 4373, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:38:26'),
(26, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:39:18'),
(27, 2, 'online', 19.91, NULL, 'OK', '2026-02-27 13:39:18'),
(28, 6, 'offline', 4126.77, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:39:22'),
(29, 7, 'offline', 146.17, 404, 'HTTP 404 Error', '2026-02-27 13:39:22'),
(30, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:40:34'),
(31, 2, 'online', 21.64, NULL, 'OK', '2026-02-27 13:40:34'),
(32, 6, 'offline', 4172.1, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:40:38'),
(33, 7, 'offline', 180.96, 404, 'HTTP 404 Error', '2026-02-27 13:40:38'),
(34, 8, 'online', 176.12, 200, 'HTTP 200 OK', '2026-02-27 13:40:38'),
(35, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:42:14'),
(36, 2, 'online', 19.33, NULL, 'OK', '2026-02-27 13:42:14'),
(37, 6, 'online', 318.2, 200, 'HTTP 200 OK', '2026-02-27 13:42:15'),
(38, 7, 'offline', 163.38, 404, 'HTTP 404 Error', '2026-02-27 13:42:15'),
(39, 8, 'online', 234.3, 200, 'HTTP 200 OK', '2026-02-27 13:42:15'),
(40, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:43:38'),
(41, 2, 'online', 23.92, NULL, 'OK', '2026-02-27 13:43:39'),
(42, 6, 'online', 298.09, 200, 'HTTP 200 OK', '2026-02-27 13:43:39'),
(43, 7, 'offline', 152.49, 404, 'HTTP 404 Error', '2026-02-27 13:43:39'),
(44, 8, 'online', 219.92, 200, 'HTTP 200 OK', '2026-02-27 13:43:39'),
(45, 9, 'offline', 193.47, 0, 'Failed to connect to apijkn.bpjs-kesehatan.go.id port 80: Connection refused', '2026-02-27 13:43:39'),
(46, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:45:24'),
(47, 2, 'online', 17.74, NULL, 'OK', '2026-02-27 13:45:24'),
(48, 6, 'offline', 4124.53, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:45:28'),
(49, 7, 'offline', 168.72, 404, 'HTTP 404 Error', '2026-02-27 13:45:28'),
(50, 8, 'online', 188.94, 200, 'HTTP 200 OK', '2026-02-27 13:45:29'),
(51, 9, 'offline', 186.37, 0, 'Failed to connect to apijkn.bpjs-kesehatan.go.id port 80: Connection refused', '2026-02-27 13:45:29'),
(52, 6, 'timeout', NULL, 0, 'Timeout setelah 5s', '2026-02-27 13:45:39'),
(53, 6, 'online', 3724.8, 200, 'HTTP 200', '2026-02-27 13:46:29'),
(54, 7, 'offline', 3724.9, 404, 'HTTP 404', '2026-02-27 13:46:29'),
(55, 8, 'online', 3724.8, 200, 'HTTP 200', '2026-02-27 13:46:29'),
(56, 9, 'online', 3724.8, 301, 'HTTP 301', '2026-02-27 13:46:29'),
(57, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 13:46:29'),
(58, 2, 'online', 17.5, NULL, 'TCP OK', '2026-02-27 13:46:29'),
(59, 6, 'online', 3837.8, 200, 'HTTP 200', '2026-02-27 13:47:01'),
(60, 7, 'offline', 3837.8, 404, 'HTTP 404', '2026-02-27 13:47:01'),
(61, 8, 'online', 3837.8, 200, 'HTTP 200', '2026-02-27 13:47:01'),
(62, 9, 'online', 3837.8, 301, 'HTTP 301', '2026-02-27 13:47:01'),
(63, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 13:47:01'),
(64, 2, 'online', 18.6, NULL, 'TCP OK', '2026-02-27 13:47:01'),
(65, 6, 'offline', 4119.4, 0, 'HTTP 0', '2026-02-27 13:47:11'),
(66, 7, 'offline', 4119.4, 404, 'HTTP 404', '2026-02-27 13:47:11'),
(67, 8, 'online', 4119.4, 200, 'HTTP 200', '2026-02-27 13:47:11'),
(68, 9, 'online', 4119.4, 301, 'HTTP 301', '2026-02-27 13:47:11'),
(69, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 13:47:11'),
(70, 2, 'online', 17.3, NULL, 'TCP OK', '2026-02-27 13:47:11'),
(71, 6, 'online', 2351.6, 200, 'HTTP 200', '2026-02-27 13:48:07'),
(72, 7, 'offline', 2351.6, 404, 'HTTP 404', '2026-02-27 13:48:07'),
(73, 8, 'online', 2351.5, 200, 'HTTP 200', '2026-02-27 13:48:07'),
(74, 9, 'online', 2351.5, 301, 'HTTP 301', '2026-02-27 13:48:07'),
(75, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 13:48:07'),
(76, 2, 'online', 17.3, NULL, 'TCP OK', '2026-02-27 13:48:07'),
(77, 6, 'online', 3774.8, 200, 'HTTP 200', '2026-02-27 13:49:51'),
(78, 7, 'offline', 3774.8, 404, 'HTTP 404', '2026-02-27 13:49:51'),
(79, 8, 'online', 3774.8, 200, 'HTTP 200', '2026-02-27 13:49:51'),
(80, 9, 'online', 3774.8, 301, 'HTTP 301', '2026-02-27 13:49:51'),
(81, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 13:49:51'),
(82, 2, 'online', 17.7, NULL, 'TCP OK', '2026-02-27 13:49:51'),
(83, 6, 'online', 2530.4, 200, 'HTTP 200', '2026-02-27 19:36:30'),
(84, 7, 'offline', 2530.4, 404, 'HTTP 404', '2026-02-27 19:36:30'),
(85, 8, 'online', 2530.3, 200, 'HTTP 200', '2026-02-27 19:36:30'),
(86, 9, 'online', 2530.3, 301, 'HTTP 301', '2026-02-27 19:36:30'),
(87, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 19:36:30'),
(88, 2, 'online', 21.4, NULL, 'TCP OK', '2026-02-27 19:36:30'),
(89, 6, 'offline', 4174.7, 0, 'HTTP 0', '2026-02-27 20:43:32'),
(90, 7, 'offline', 4174.5, 404, 'HTTP 404', '2026-02-27 20:43:32'),
(91, 8, 'online', 4174.5, 200, 'HTTP 200', '2026-02-27 20:43:32'),
(92, 9, 'online', 4174.5, 301, 'HTTP 301', '2026-02-27 20:43:32'),
(93, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 20:43:32'),
(94, 2, 'online', 16.7, NULL, 'TCP OK', '2026-02-27 20:43:32'),
(95, 6, 'online', 295.4, 200, 'HTTP 200', '2026-02-28 08:15:26'),
(96, 7, 'offline', 152.1, 404, 'HTTP 404', '2026-02-28 08:15:31'),
(97, 7, 'offline', 116, 404, 'HTTP 404', '2026-02-28 08:15:33'),
(98, 7, 'offline', 4109.7, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-28 08:15:37'),
(99, 2, 'online', 15, NULL, 'TCP OK', '2026-02-28 08:15:38'),
(100, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-28 08:15:42'),
(101, 6, 'offline', 4112.6, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-28 08:15:47'),
(102, 6, 'online', 599.7, 200, 'HTTP 200', '2026-03-02 08:08:16'),
(103, 7, 'offline', 599.7, 404, 'HTTP 404', '2026-03-02 08:08:16'),
(104, 8, 'online', 599.7, 200, 'HTTP 200', '2026-03-02 08:08:16'),
(105, 9, 'online', 599.6, 301, 'HTTP 301', '2026-03-02 08:08:16'),
(106, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-02 08:08:16'),
(107, 2, 'online', 15.2, NULL, 'TCP OK', '2026-03-02 08:08:16'),
(108, 6, 'offline', 5005.9, 0, 'name lookup timed out', '2026-03-03 21:57:36'),
(109, 2, 'timeout', NULL, NULL, 'Timeout', '2026-03-03 21:57:45'),
(110, 6, 'offline', 15003.3, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(111, 7, 'offline', 15003.3, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(112, 8, 'offline', 15003.3, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(113, 9, 'offline', 15003.2, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(114, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-03 21:58:06'),
(115, 2, 'timeout', NULL, NULL, 'Timeout', '2026-03-03 21:58:06'),
(116, 6, 'online', 353.9, 200, 'HTTP 200', '2026-03-04 08:49:14'),
(117, 7, 'offline', 353.9, 404, 'HTTP 404', '2026-03-04 08:49:14'),
(118, 8, 'online', 353.8, 200, 'HTTP 200', '2026-03-04 08:49:14'),
(119, 9, 'online', 353.8, 301, 'HTTP 301', '2026-03-04 08:49:14'),
(120, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-04 08:49:14'),
(121, 2, 'online', 14.8, NULL, 'TCP OK', '2026-03-04 08:49:14'),
(122, 6, 'offline', 4249.1, 0, 'HTTP 0', '2026-03-04 15:15:57'),
(123, 7, 'offline', 4249.1, 404, 'HTTP 404', '2026-03-04 15:15:57'),
(124, 8, 'online', 4249.1, 200, 'HTTP 200', '2026-03-04 15:15:57'),
(125, 9, 'online', 4249.1, 301, 'HTTP 301', '2026-03-04 15:15:57'),
(126, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-04 15:15:57'),
(127, 2, 'online', 15.8, NULL, 'TCP OK', '2026-03-04 15:15:57'),
(128, 6, 'online', 320.4, 200, 'HTTP 200', '2026-03-05 14:07:57'),
(129, 7, 'offline', 320.4, 404, 'HTTP 404', '2026-03-05 14:07:57'),
(130, 8, 'online', 320.4, 200, 'HTTP 200', '2026-03-05 14:07:57'),
(131, 9, 'online', 320.4, 301, 'HTTP 301', '2026-03-05 14:07:57'),
(132, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-05 14:07:57'),
(133, 2, 'online', 14.2, NULL, 'TCP OK', '2026-03-05 14:07:57'),
(134, 6, 'offline', 4147.1, 0, 'HTTP 0', '2026-03-05 14:20:04'),
(135, 7, 'offline', 4147.1, 404, 'HTTP 404', '2026-03-05 14:20:04'),
(136, 8, 'online', 4147, 200, 'HTTP 200', '2026-03-05 14:20:04'),
(137, 9, 'online', 4147, 301, 'HTTP 301', '2026-03-05 14:20:04'),
(138, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-05 14:20:04'),
(139, 2, 'online', 14.7, NULL, 'TCP OK', '2026-03-05 14:20:04'),
(140, 6, 'online', 288.4, 200, 'HTTP 200', '2026-03-05 14:45:15'),
(141, 7, 'offline', 288.4, 404, 'HTTP 404', '2026-03-05 14:45:15'),
(142, 8, 'online', 288.4, 200, 'HTTP 200', '2026-03-05 14:45:15'),
(143, 9, 'online', 288.4, 301, 'HTTP 301', '2026-03-05 14:45:15'),
(144, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-05 14:45:15'),
(145, 2, 'online', 14.6, NULL, 'TCP OK', '2026-03-05 14:45:15'),
(146, 6, 'online', 322.9, 200, 'HTTP 200', '2026-03-05 15:18:10'),
(147, 7, 'offline', 322.9, 404, 'HTTP 404', '2026-03-05 15:18:10'),
(148, 8, 'online', 322.9, 200, 'HTTP 200', '2026-03-05 15:18:10'),
(149, 9, 'online', 322.9, 301, 'HTTP 301', '2026-03-05 15:18:10'),
(150, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-05 15:18:10'),
(151, 2, 'online', 14.8, NULL, 'TCP OK', '2026-03-05 15:18:10'),
(152, 6, 'online', 406.8, 200, 'HTTP 200', '2026-03-05 20:55:05'),
(153, 7, 'offline', 406.8, 404, 'HTTP 404', '2026-03-05 20:55:05'),
(154, 8, 'online', 406.8, 200, 'HTTP 200', '2026-03-05 20:55:05'),
(155, 9, 'online', 406.7, 301, 'HTTP 301', '2026-03-05 20:55:05'),
(156, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-05 20:55:05'),
(157, 2, 'online', 18.7, NULL, 'TCP OK', '2026-03-05 20:55:05'),
(158, 6, 'online', 2268.1, 200, 'HTTP 200', '2026-03-06 09:58:00'),
(159, 7, 'offline', 2268.1, 404, 'HTTP 404', '2026-03-06 09:58:00'),
(160, 8, 'online', 2268, 200, 'HTTP 200', '2026-03-06 09:58:00'),
(161, 9, 'online', 2268, 301, 'HTTP 301', '2026-03-06 09:58:00'),
(162, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-06 09:58:00'),
(163, 2, 'online', 14.7, NULL, 'TCP OK', '2026-03-06 09:58:00'),
(164, 8, 'online', 330.9, 200, 'HTTP 200', '2026-03-07 10:29:20'),
(165, 7, 'offline', 200.8, 404, 'HTTP 404', '2026-03-07 10:29:27'),
(166, 6, 'online', 3263.5, 200, 'HTTP 200', '2026-03-07 10:29:58'),
(167, 9, 'timeout', NULL, 0, 'Timeout', '2026-10-13 19:44:12'),
(168, 6, 'online', 453.6, 302, 'HTTP 302 · TTFB 453.6ms', '2026-10-16 22:48:51'),
(169, 7, 'offline', 379.3, 404, 'HTTP 404', '2026-10-16 22:48:51'),
(170, 8, 'online', 563.1, 200, 'HTTP 200 · TTFB 563.1ms', '2026-10-16 22:48:51'),
(171, 9, 'online', 307.5, 301, 'HTTP 301 · TTFB 307.5ms', '2026-10-16 22:48:51'),
(172, 1, 'timeout', NULL, NULL, 'Timeout', '2026-10-16 22:48:51'),
(173, 2, 'online', 47.4, NULL, 'TCP OK · 47.4ms', '2026-10-16 22:48:51');

-- --------------------------------------------------------

--
-- Table structure for table `koneksi_monitor`
--

CREATE TABLE `koneksi_monitor` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label tampilan, cth: Server SIMRS',
  `host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'IP atau URL, cth: 192.168.1.1 / https://simrs.rspermata.com',
  `tipe` enum('ip','url') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'url' COMMENT 'ip = ping ICMP, url = HTTP request',
  `kategori` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Umum' COMMENT 'Pengelompokan: Server, Printer, Internet, dsb.',
  `port` smallint UNSIGNED DEFAULT NULL COMMENT 'Port opsional untuk tipe IP',
  `timeout_detik` tinyint UNSIGNED NOT NULL DEFAULT '5' COMMENT 'Batas waktu cek dalam detik',
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daftar host / URL yang dipantau via cek_koneksi.php';

--
-- Dumping data for table `koneksi_monitor`
--

INSERT INTO `koneksi_monitor` (`id`, `nama`, `host`, `tipe`, `kategori`, `port`, `timeout_detik`, `aktif`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Google DNS', '8.8.8.8', 'ip', 'Internet', NULL, 3, 1, NULL, '2026-02-27 13:30:22', NULL),
(2, 'Cloudflare DNS', '1.1.1.1', 'ip', 'Internet', NULL, 3, 1, NULL, '2026-02-27 13:30:22', NULL),
(6, 'Aplicare BPJS', 'https://new-api.bpjs-kesehatan.go.id/aplicaresws', 'url', 'BPJS', NULL, 5, 1, 5, '2026-02-27 13:36:41', '2026-02-27 13:38:16'),
(7, 'I-Care', 'https://apijkn.bpjs-kesehatan.go.id/wsihs/api/rs', 'url', 'BPJS', NULL, 5, 1, 5, '2026-02-27 13:39:12', NULL),
(8, 'Finger BPJS', 'https://fp.bpjs-kesehatan.go.id/finger-rest', 'url', 'BPJS', NULL, 5, 1, 5, '2026-02-27 13:40:28', NULL),
(9, 'Vclaim', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest', 'url', 'BPJS', NULL, 5, 1, 8, '2026-02-27 13:43:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int UNSIGNED NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `blocked_until` datetime DEFAULT NULL,
  `last_attempt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_log`
--

CREATE TABLE `login_log` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `username_input` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('berhasil','gagal','terkunci') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `device_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_new_ip` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_log`
--

INSERT INTO `login_log` (`id`, `user_id`, `username_input`, `status`, `ip_address`, `user_agent`, `device_type`, `browser`, `os`, `keterangan`, `is_new_ip`, `created_at`) VALUES
(1, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 1, '2026-03-05 15:07:58'),
(2, NULL, 'giano', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-05 15:12:50'),
(3, NULL, 'giano', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-05 15:12:55'),
(4, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-05 15:13:09'),
(5, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-05 15:13:20'),
(6, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-05 19:47:42'),
(7, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-05 20:54:32'),
(8, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 08:05:05'),
(9, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 1, '2026-03-06 08:07:12'),
(10, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-06 08:41:18'),
(11, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 08:41:38'),
(12, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 13:18:14'),
(13, 12, 'danu', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 1, '2026-03-06 13:18:31'),
(14, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 13:19:35'),
(15, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-06 13:22:44'),
(16, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 19:04:27'),
(17, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 19:15:19'),
(18, 10, 'qiana', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 1, '2026-03-06 21:44:49'),
(19, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 22:32:26'),
(20, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 07:52:08'),
(21, 10, 'qiana', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 08:40:40'),
(22, 9, 'giano@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 1, '2026-03-07 08:41:56'),
(23, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 08:42:20'),
(24, 12, 'danu', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 08:42:52'),
(25, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 09:17:55'),
(26, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 10:19:26'),
(27, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 10:27:32'),
(28, 10, 'qiana', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 10:42:52'),
(29, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-07 10:51:43'),
(30, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 10:51:54'),
(31, 10, 'qiana', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 10:58:02'),
(32, NULL, 'giano', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-07 11:00:11'),
(33, 9, 'giano', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 11:00:19'),
(34, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 11:02:01'),
(35, 10, 'qiana', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Password salah', 0, '2026-03-07 11:02:40'),
(36, 10, 'qiana', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 11:02:48'),
(37, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 11:05:27'),
(38, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 19:31:26'),
(39, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', 'Captcha salah', 0, '2026-10-10 11:29:18'),
(40, NULL, 'test@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', 'Captcha salah', 0, '2026-10-10 11:30:42'),
(41, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 1, '2026-10-10 11:31:07'),
(42, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 0, '2026-10-10 12:08:06'),
(43, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 0, '2026-10-10 13:30:02'),
(44, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 0, '2026-10-10 13:56:48'),
(45, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 0, '2026-10-10 20:04:21'),
(46, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 0, '2026-10-11 12:20:46'),
(47, 13, 'test@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', 'Password salah', 0, '2026-10-12 09:09:58'),
(48, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 0, '2026-10-12 09:10:18'),
(49, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 0, '2026-10-12 09:16:33'),
(50, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 0, '2026-10-12 09:18:45'),
(51, NULL, 'test@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', 'Captcha salah', 0, '2026-10-12 09:29:47'),
(52, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 0, '2026-10-12 09:29:59'),
(53, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 0, '2026-10-13 10:44:03'),
(54, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'Desktop', 'Chrome 154', 'Windows 10/11', NULL, 0, '2026-10-13 13:26:59'),
(55, NULL, 'test@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Captcha salah', 0, '2026-10-13 19:26:29'),
(56, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-13 19:26:44'),
(57, NULL, 'test@gmail.comm', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Email tidak ditemukan', 0, '2026-10-13 19:29:35'),
(58, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-13 19:29:49'),
(59, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Linux; Android 16; Pixel 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36', 'Mobile', 'Chrome 155', 'Android 16', NULL, 0, '2026-10-14 14:07:24'),
(60, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-14 15:40:20'),
(61, NULL, 'test@gmail.comm', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Email tidak ditemukan', 0, '2026-10-15 07:47:00'),
(62, NULL, 'test@gmail.comm', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Email tidak ditemukan', 0, '2026-10-15 07:47:13'),
(63, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-15 07:47:29'),
(64, 13, 'test@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-15 08:12:57'),
(65, NULL, 'test@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Captcha salah', 0, '2026-10-15 15:58:15'),
(66, 14, 'admin@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 1, '2026-10-16 08:26:56'),
(67, 14, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 08:56:54'),
(68, 16, 'marketingassalam', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 1, '2026-10-16 08:58:03'),
(69, 14, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 08:59:31'),
(70, 14, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 09:31:04'),
(71, NULL, '21265586541964', 'gagal', '::1', 'Mozilla/5.0 (Linux; Android 16; Pixel 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Mobile Safari/537.36', 'Mobile', 'Chrome 155', 'Android 16', 'Captcha salah', 0, '2026-10-16 15:54:37'),
(72, 14, 'admin', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Password salah', 0, '2026-10-16 18:09:09'),
(73, 14, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 18:09:16'),
(74, 15, 'testtt', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 1, '2026-10-16 18:34:26'),
(75, 14, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 18:34:42'),
(76, NULL, 'admin0117', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Username tidak ditemukan', 0, '2026-10-16 18:36:01'),
(77, NULL, 'admin0117', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Username tidak ditemukan', 0, '2026-10-16 18:36:14'),
(78, NULL, 'admin0117', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Username tidak ditemukan', 0, '2026-10-16 18:36:25'),
(79, NULL, 'admin0117', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Username tidak ditemukan', 0, '2026-10-16 18:36:45'),
(80, NULL, 'admin0117', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Username tidak ditemukan', 0, '2026-10-16 18:36:53'),
(81, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 1, '2026-10-16 18:40:22'),
(82, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 18:41:13'),
(83, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 19:53:15'),
(84, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 20:56:07'),
(85, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 21:28:26'),
(86, 18, 'ipsrs_assalam', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 1, '2026-10-16 21:31:13'),
(87, 19, 'atem_assalam', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 1, '2026-10-16 21:31:54'),
(88, 18, 'ipsrs_assalam', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 21:32:28'),
(89, 18, 'ipsrs_assalam', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 21:33:33'),
(90, 19, 'atem_assalam', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 21:34:00'),
(91, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-16 21:34:40'),
(92, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.142.0 Chrome/150.0.7871.250 Electron/43.7.7 Safari/537.36', 'Desktop', 'Chrome 150', 'Windows 10/11', NULL, 0, '2026-10-16 22:55:41'),
(93, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-17 14:41:15'),
(94, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-17 14:46:46'),
(95, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-19 14:41:23'),
(96, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-19 14:44:40'),
(97, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-20 13:41:49'),
(98, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-20 21:43:10'),
(99, NULL, 'admin', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Username tidak ditemukan', 0, '2026-10-21 08:48:08'),
(100, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-21 08:48:20'),
(101, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-21 09:16:25'),
(102, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-21 12:29:18'),
(103, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-21 13:56:40'),
(104, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-21 16:10:00'),
(105, NULL, 'admin', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Username tidak ditemukan', 0, '2026-10-21 16:12:38'),
(106, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-21 16:12:44'),
(107, NULL, 'admin', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Username tidak ditemukan', 0, '2026-10-23 09:26:14'),
(108, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-23 09:26:25'),
(109, NULL, 'admin0117', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Captcha salah', 0, '2026-10-26 08:51:37'),
(110, NULL, 'admin', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Username tidak ditemukan', 0, '2026-10-26 08:51:47'),
(111, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-26 08:51:58'),
(112, NULL, 'itassalam', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Captcha salah', 0, '2026-10-26 08:53:54'),
(113, NULL, 'itassalam', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Username tidak ditemukan', 0, '2026-10-26 08:54:03'),
(114, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-26 08:54:16'),
(115, 20, 'it.assalam', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 1, '2026-10-26 08:55:11'),
(116, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-26 08:55:43'),
(117, 17, 'admin0117', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-26 12:31:37'),
(118, 17, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-26 12:39:40'),
(119, 22, 'manag_umum', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 1, '2026-10-26 12:59:20'),
(120, 17, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-26 12:59:53'),
(121, 17, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-26 13:06:37'),
(122, 17, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-27 10:36:26'),
(123, NULL, 'admin', 'gagal', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', 'Captcha salah', 0, '2026-10-27 10:44:53'),
(124, 17, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-27 10:45:01'),
(125, 17, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', 'Desktop', 'Chrome 155', 'Windows 10/11', NULL, 0, '2026-10-27 11:02:44'),
(126, 17, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', 'Desktop', 'Chrome 156', 'Windows 10/11', NULL, 0, '2026-10-28 10:05:52'),
(127, 17, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', 'Desktop', 'Chrome 156', 'Windows 10/11', NULL, 0, '2026-10-29 13:29:05'),
(128, 17, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', 'Desktop', 'Chrome 156', 'Windows 10/11', NULL, 0, '2026-11-02 08:01:33'),
(129, 17, 'admin', 'berhasil', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', 'Desktop', 'Chrome 156', 'Windows 10/11', NULL, 0, '2026-11-02 12:38:12');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_ipsrs`
--

CREATE TABLE `maintenance_ipsrs` (
  `id` int UNSIGNED NOT NULL,
  `no_maintenance` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format: MNT-IPSRS-YYYYMM-0001',
  `aset_id` int UNSIGNED DEFAULT NULL,
  `aset_nama` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Snapshot nama aset saat dicatat',
  `teknisi_id` int UNSIGNED DEFAULT NULL,
  `teknisi_nama` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Snapshot nama teknisi saat dicatat',
  `tgl_maintenance` date NOT NULL,
  `tgl_maintenance_berikut` date DEFAULT NULL COMMENT 'Otomatis +3 bulan dari tgl_maintenance',
  `jenis_maintenance` enum('Preventif','Korektif','Rutin Bulanan','Penggantian Part','Kalibrasi','Inspeksi','Servis Berkala','Lainnya') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Preventif',
  `kondisi_sebelum` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kondisi_sesudah` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temuan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Temuan / masalah saat maintenance',
  `tindakan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Tindakan yang dilakukan',
  `biaya` int UNSIGNED DEFAULT NULL COMMENT 'Biaya maintenance dalam Rupiah',
  `status` enum('Selesai','Dalam Proses','Ditunda','Dibatalkan') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Selesai',
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catatan maintenance aset IPSRS (medis & non-medis)';

--
-- Dumping data for table `maintenance_ipsrs`
--

INSERT INTO `maintenance_ipsrs` (`id`, `no_maintenance`, `aset_id`, `aset_nama`, `teknisi_id`, `teknisi_nama`, `tgl_maintenance`, `tgl_maintenance_berikut`, `jenis_maintenance`, `kondisi_sebelum`, `kondisi_sesudah`, `temuan`, `tindakan`, `biaya`, `status`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'MNT-IPSRS-202603-0001', 8, 'INV-IPSRS-2024-0007 – AC Central Chiller', NULL, '', '2026-03-04', '2026-06-04', 'Preventif', 'Baik', 'Baik', 'tes', 'tes', 170000, 'Selesai', 'tes', NULL, '2026-03-04 15:23:32', NULL),
(2, 'MNT-IPSRS-202603-0002', 10, 'INV-IPSRS-2024-0009 – Ambulans Transport', NULL, 'Giano', '2026-03-06', '2026-06-06', 'Lainnya', 'Baik', 'Baik', '-', '-', 500000, 'Selesai', '-', NULL, '2026-03-06 13:46:46', NULL),
(3, 'MNT-IPSRS-202603-0003', 4, 'INV-IPSRS-2024-0003 – Defibrilator AED', NULL, 'Giano', '2026-03-06', '2026-06-06', 'Rutin Bulanan', 'Baik', 'Baik', '-', '-', 430000, 'Selesai', '-', NULL, '2026-03-06 13:47:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_it`
--

CREATE TABLE `maintenance_it` (
  `id` int UNSIGNED NOT NULL,
  `no_maintenance` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format: MNT-YYYYMM-0001',
  `aset_id` int UNSIGNED DEFAULT NULL COMMENT 'FK → aset_it.id',
  `aset_nama` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama aset (no_inv – nama_aset)',
  `teknisi_id` int UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `teknisi_nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama teknisi',
  `tgl_maintenance` date NOT NULL COMMENT 'Tanggal maintenance dilakukan',
  `tgl_maintenance_berikut` date DEFAULT NULL COMMENT 'Pengingat: tgl_maintenance + 3 bulan',
  `jenis_maintenance` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Preventif, Korektif, Rutin, dll',
  `kondisi_sebelum` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kondisi_sesudah` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temuan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Temuan / masalah yang ditemukan',
  `tindakan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Tindakan yang dilakukan',
  `biaya` bigint UNSIGNED DEFAULT NULL COMMENT 'Biaya maintenance dalam Rupiah',
  `status` enum('Selesai','Dalam Proses','Ditunda','Dibatalkan') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Selesai',
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catatan Maintenance / Perawatan Aset IT';

--
-- Dumping data for table `maintenance_it`
--

INSERT INTO `maintenance_it` (`id`, `no_maintenance`, `aset_id`, `aset_nama`, `teknisi_id`, `teknisi_nama`, `tgl_maintenance`, `tgl_maintenance_berikut`, `jenis_maintenance`, `kondisi_sebelum`, `kondisi_sesudah`, `temuan`, `tindakan`, `biaya`, `status`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 'MNT-202602-0001', 6, 'INV-IT-2026-0006 – CPU Build UP', 11, 'budi', '2026-02-26', '2026-05-26', 'Lainnya', 'Baik', 'Baik', 'bagus semua', 'bagus semua', 0, 'Selesai', '-', 8, '2026-02-26 21:27:01', NULL),
(6, 'MNT-202602-0002', 1, 'INV-IT-2025-0001 – Laptop Dell Latitude', 9, 'Giano', '2026-02-26', '2026-05-26', 'Rutin Bulanan', 'Baik', 'Baik', 'Bagus', 'Bagus', 0, 'Selesai', 'ganti pasta', 8, '2026-02-26 22:25:28', NULL),
(7, 'MNT-202602-0003', 5, 'INV-IT-2025-0005 – Monitor LG 24 inch', 9, 'Giano', '2026-02-28', '2026-05-28', 'Rutin Bulanan', 'Baik', 'Baik', '-', '-', 75000, 'Selesai', 'beli pasta', 8, '2026-02-28 08:48:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `master_pokja`
--

CREATE TABLE `master_pokja` (
  `id` int UNSIGNED NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `deskripsi` text,
  `ketua` varchar(100) DEFAULT NULL,
  `ketua_id` int UNSIGNED DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `urutan` tinyint UNSIGNED DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mutasi_aset`
--

CREATE TABLE `mutasi_aset` (
  `id` int UNSIGNED NOT NULL,
  `no_mutasi` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `aset_id` int UNSIGNED NOT NULL,
  `tanggal_mutasi` date NOT NULL,
  `jenis` enum('pindah_lokasi','pindah_pic','keduanya') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'keduanya',
  `dari_bagian_id` int UNSIGNED DEFAULT NULL,
  `dari_bagian_nama` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dari_pic_id` int UNSIGNED DEFAULT NULL,
  `dari_pic_nama` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ke_bagian_id` int UNSIGNED DEFAULT NULL,
  `ke_bagian_nama` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ke_pic_id` int UNSIGNED DEFAULT NULL,
  `ke_pic_nama` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kondisi_sebelum` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kondisi_sesudah` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_pakai` varchar(30) COLLATE utf8mb4_general_ci DEFAULT 'Terpakai',
  `keterangan` text COLLATE utf8mb4_general_ci,
  `dibuat_oleh` int UNSIGNED DEFAULT NULL,
  `dibuat_nama` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_mutasi` enum('draft','selesai','batal') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'selesai',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `kode` varchar(30) NOT NULL,
  `nama` varchar(80) NOT NULL,
  `sistem` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`kode`, `nama`, `sistem`) VALUES
('admin', 'Admin', 1),
('keuangan', 'Keuangan', 0),
('teknisi', 'Teknisi IT', 0),
('teknisi_atem', 'Teknisi ATEM', 0),
('teknisi_ipsrs', 'Teknisi IPSRS', 0),
('umum', 'Manager Umum / Wadir Umum', 0),
('user', 'User', 1);

-- --------------------------------------------------------

--
-- Table structure for table `role_menu`
--

CREATE TABLE `role_menu` (
  `role_kode` varchar(30) NOT NULL,
  `menu_kode` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role_menu`
--

INSERT INTO `role_menu` (`role_kode`, `menu_kode`) VALUES
('keuangan', 'penggajian'),
('teknisi', 'aset_it'),
('teknisi', 'berita_acara'),
('teknisi', 'tiket_it'),
('teknisi_atem', 'aset_atem'),
('teknisi_atem', 'kategori_atem'),
('teknisi_atem', 'tiket_atem'),
('teknisi_ipsrs', 'aset_ipsrs'),
('teknisi_ipsrs', 'tiket_ipsrs'),
('umum', 'aset_atem'),
('umum', 'aset_ipsrs'),
('umum', 'aset_it'),
('umum', 'atk_approve'),
('umum', 'atk_kelola'),
('umum', 'berita_acara'),
('umum', 'tiket_atem'),
('umum', 'tiket_ipsrs'),
('umum', 'tiket_it');

-- --------------------------------------------------------

--
-- Table structure for table `sdm_karyawan`
--

CREATE TABLE `sdm_karyawan` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `nik_ktp` varchar(20) DEFAULT NULL,
  `nik_rs` varchar(50) DEFAULT NULL,
  `gelar_depan` varchar(50) DEFAULT NULL,
  `gelar_belakang` varchar(80) DEFAULT NULL,
  `jenis_kelamin` varchar(5) DEFAULT NULL,
  `golongan_darah` varchar(5) DEFAULT NULL,
  `agama` varchar(30) DEFAULT NULL,
  `status_pernikahan` varchar(30) DEFAULT NULL,
  `jumlah_anak` int DEFAULT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `kewarganegaraan` varchar(50) DEFAULT NULL,
  `suku` varchar(50) DEFAULT NULL,
  `pendidikan_terakhir` varchar(30) DEFAULT NULL,
  `jurusan` varchar(100) DEFAULT NULL,
  `universitas` varchar(150) DEFAULT NULL,
  `tahun_lulus` int DEFAULT NULL,
  `no_hp` varchar(30) DEFAULT NULL,
  `no_hp_darurat` varchar(30) DEFAULT NULL,
  `kontak_darurat` varchar(100) DEFAULT NULL,
  `hubungan_darurat` varchar(50) DEFAULT NULL,
  `alamat_ktp` text,
  `kelurahan_ktp` varchar(100) DEFAULT NULL,
  `kecamatan_ktp` varchar(100) DEFAULT NULL,
  `kota_ktp` varchar(100) DEFAULT NULL,
  `provinsi_ktp` varchar(100) DEFAULT NULL,
  `kode_pos_ktp` varchar(10) DEFAULT NULL,
  `alamat_domisili` text,
  `kota_domisili` varchar(100) DEFAULT NULL,
  `divisi` varchar(100) DEFAULT NULL,
  `unit_kerja` varchar(100) DEFAULT NULL,
  `jabatan_id` int DEFAULT NULL,
  `atasan_id` int DEFAULT NULL,
  `jenis_karyawan` varchar(30) DEFAULT NULL,
  `status_kepegawaian` varchar(30) DEFAULT NULL,
  `jenis_tenaga` varchar(100) DEFAULT NULL,
  `spesialisasi` varchar(100) DEFAULT NULL,
  `sub_spesialisasi` varchar(100) DEFAULT NULL,
  `tgl_masuk` date DEFAULT NULL,
  `tgl_kontrak_mulai` date DEFAULT NULL,
  `tgl_kontrak_selesai` date DEFAULT NULL,
  `tgl_pengangkatan` date DEFAULT NULL,
  `tgl_resign` date DEFAULT NULL,
  `alasan_resign` varchar(255) DEFAULT NULL,
  `no_bpjs_kes` varchar(30) DEFAULT NULL,
  `no_bpjs_tk` varchar(30) DEFAULT NULL,
  `no_npwp` varchar(30) DEFAULT NULL,
  `bank` varchar(50) DEFAULT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `atas_nama_rek` varchar(100) DEFAULT NULL,
  `no_str` varchar(60) DEFAULT NULL,
  `tgl_terbit_str` date DEFAULT NULL,
  `tgl_exp_str` date DEFAULT NULL,
  `no_sip` varchar(60) DEFAULT NULL,
  `tgl_terbit_sip` date DEFAULT NULL,
  `tgl_exp_sip` date DEFAULT NULL,
  `no_sik` varchar(60) DEFAULT NULL,
  `tgl_exp_sik` date DEFAULT NULL,
  `kompetensi` text,
  `catatan` text,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sdm_karyawan`
--

INSERT INTO `sdm_karyawan` (`id`, `user_id`, `nik_ktp`, `nik_rs`, `gelar_depan`, `gelar_belakang`, `jenis_kelamin`, `golongan_darah`, `agama`, `status_pernikahan`, `jumlah_anak`, `tempat_lahir`, `tgl_lahir`, `kewarganegaraan`, `suku`, `pendidikan_terakhir`, `jurusan`, `universitas`, `tahun_lulus`, `no_hp`, `no_hp_darurat`, `kontak_darurat`, `hubungan_darurat`, `alamat_ktp`, `kelurahan_ktp`, `kecamatan_ktp`, `kota_ktp`, `provinsi_ktp`, `kode_pos_ktp`, `alamat_domisili`, `kota_domisili`, `divisi`, `unit_kerja`, `jabatan_id`, `atasan_id`, `jenis_karyawan`, `status_kepegawaian`, `jenis_tenaga`, `spesialisasi`, `sub_spesialisasi`, `tgl_masuk`, `tgl_kontrak_mulai`, `tgl_kontrak_selesai`, `tgl_pengangkatan`, `tgl_resign`, `alasan_resign`, `no_bpjs_kes`, `no_bpjs_tk`, `no_npwp`, `bank`, `no_rekening`, `atas_nama_rek`, `no_str`, `tgl_terbit_str`, `tgl_exp_str`, `no_sip`, `tgl_terbit_sip`, `tgl_exp_sip`, `no_sik`, `tgl_exp_sik`, `kompetensi`, `catatan`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 14, NULL, '-', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '-', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'IT', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 14, '2026-10-16 01:26:38', '2026-10-16 01:26:38'),
(2, 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'HRD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 15, '2026-10-16 01:56:36', '2026-10-16 01:56:36'),
(3, 16, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Marketing', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 16, '2026-10-16 01:57:41', '2026-10-16 01:57:41'),
(4, 17, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'IT', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 17, '2026-10-16 11:37:32', '2026-10-16 11:37:32'),
(5, 18, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'IPSRS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 18, '2026-10-16 14:31:05', '2026-10-16 14:31:05'),
(6, 19, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ATEM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 19, '2026-10-16 14:31:44', '2026-10-16 14:31:44');

-- --------------------------------------------------------

--
-- Table structure for table `server_room_log`
--

CREATE TABLE `server_room_log` (
  `id` int UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `waktu` time NOT NULL,
  `petugas` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama petugas / teknisi',
  `suhu_in` decimal(5,2) DEFAULT NULL COMMENT 'Suhu dalam ruangan (°C)',
  `suhu_out` decimal(5,2) DEFAULT NULL COMMENT 'Suhu luar ruangan (°C)',
  `kelembaban` decimal(5,2) DEFAULT NULL COMMENT 'Kelembaban udara (%RH)',
  `tegangan_pln` decimal(7,2) DEFAULT NULL COMMENT 'Tegangan masuk PLN (Volt)',
  `tegangan_ups` decimal(7,2) DEFAULT NULL COMMENT 'Tegangan output UPS (Volt)',
  `beban_ups` decimal(5,2) DEFAULT NULL COMMENT 'Beban UPS (%)',
  `baterai_ups` decimal(5,2) DEFAULT NULL COMMENT 'Kapasitas baterai UPS (%)',
  `kondisi_ac1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Normal' COMMENT 'Kondisi AC unit 1',
  `kondisi_ac2` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Normal' COMMENT 'Kondisi AC unit 2',
  `kondisi_listrik` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Normal' COMMENT 'Kondisi instalasi listrik',
  `kondisi_kebersihan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Bersih' COMMENT 'Kebersihan ruangan',
  `kondisi_pintu` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Terkunci' COMMENT 'Kondisi pintu akses',
  `kondisi_cctv` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Normal' COMMENT 'Kondisi CCTV',
  `ada_alarm` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 jika alarm berbunyi',
  `ada_banjir` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 jika ada deteksi air/banjir',
  `ada_asap` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 jika ada deteksi asap/kebakaran',
  `catatan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Catatan / temuan petugas',
  `status_overall` enum('Normal','Perhatian','Kritis') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Normal',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log pemantauan ruangan server';

--
-- Dumping data for table `server_room_log`
--

INSERT INTO `server_room_log` (`id`, `tanggal`, `waktu`, `petugas`, `suhu_in`, `suhu_out`, `kelembaban`, `tegangan_pln`, `tegangan_ups`, `beban_ups`, `baterai_ups`, `kondisi_ac1`, `kondisi_ac2`, `kondisi_listrik`, `kondisi_kebersihan`, `kondisi_pintu`, `kondisi_cctv`, `ada_alarm`, `ada_banjir`, `ada_asap`, `catatan`, `status_overall`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2026-02-27', '20:20:00', 'M Wira', '22.50', '30.50', '55.00', '220.00', '220.00', '3.00', '76.00', 'Normal', 'Normal', 'Normal', 'Bersih', 'Rusak', 'Normal', 1, 1, 1, 'bagus', 'Normal', 8, '2026-02-27 20:20:54', '2026-02-27 21:10:06'),
(2, '2026-02-28', '08:11:00', 'M Wira', '22.50', '30.00', '67.00', '210.00', '210.00', NULL, NULL, 'Normal', 'Normal', 'Normal', 'Bersih', 'Terkunci', 'Normal', 1, 1, 1, 'selesai', 'Normal', 8, '2026-02-28 08:12:11', NULL),
(3, '2026-03-02', '08:07:00', 'M Wira', '28.00', '39.00', '60.00', '220.00', '220.00', '89.00', '87.00', 'Normal', 'Normal', 'Normal', 'Bersih', 'Terkunci', 'Normal', 1, 1, 1, '-', 'Normal', 8, '2026-03-02 08:08:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key`, `value`, `updated_at`) VALUES
('backup_auto_enabled', '1', '2026-03-07 19:54:33'),
('backup_auto_retention', '12', '2026-03-07 19:42:50'),
('backup_auto_schedule', 'daily', '2026-03-07 19:42:50'),
('backup_auto_time', '19:58', '2026-03-07 19:56:24'),
('backup_compress', '1', '2026-03-07 19:54:23'),
('backup_notif_email', '0', '2026-03-07 19:42:50'),
('backup_notif_telegram', '1', '2026-03-07 19:54:23'),
('backup_storage_local', '1', '2026-03-07 19:42:50'),
('ipsrs_telegram_bot_token', '1', '2026-03-07 11:08:12'),
('ipsrs_telegram_chat_id', '1', '2026-03-07 11:08:17'),
('ipsrs_telegram_enabled', '1', '2026-03-07 10:39:55'),
('ipsrs_telegram_notif_diproses', '1', '2026-03-07 10:57:37'),
('ipsrs_telegram_notif_ditolak', '1', '2026-03-07 10:57:37'),
('ipsrs_telegram_notif_komentar', '1', '2026-03-07 10:57:37'),
('ipsrs_telegram_notif_selesai', '1', '2026-03-07 10:57:37'),
('ipsrs_telegram_notif_tiket_baru', '1', '2026-03-07 10:57:37'),
('telegram_bot_token', '1', '2026-03-07 20:00:21'),
('telegram_chat_id', '1', '2026-03-07 20:00:29'),
('telegram_enabled', '1', '2026-03-07 10:40:03'),
('telegram_notif_diproses', '1', '2026-02-25 20:31:32'),
('telegram_notif_ditolak', '1', '2026-02-25 20:31:32'),
('telegram_notif_komentar', '1', '2026-02-25 20:33:42'),
('telegram_notif_selesai', '1', '2026-02-25 20:31:32'),
('telegram_notif_tiket_baru', '1', '2026-02-25 20:31:32');

-- --------------------------------------------------------

--
-- Table structure for table `tiket`
--

CREATE TABLE `tiket` (
  `id` int UNSIGNED NOT NULL,
  `nomor` varchar(20) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text NOT NULL,
  `kategori_id` int UNSIGNED DEFAULT NULL,
  `prioritas` enum('Tinggi','Sedang','Rendah') NOT NULL DEFAULT 'Sedang',
  `status` enum('menunggu','diproses','selesai','ditolak','tidak_bisa') NOT NULL DEFAULT 'menunggu',
  `user_id` int UNSIGNED DEFAULT NULL COMMENT 'Pemohon akun, kosong untuk laporan publik',
  `pemohon_nama` varchar(100) DEFAULT NULL,
  `pemohon_telepon` varchar(30) DEFAULT NULL,
  `pemohon_divisi` varchar(100) DEFAULT NULL,
  `teknisi_id` int UNSIGNED DEFAULT NULL COMMENT 'Teknisi yang handle',
  `lokasi` varchar(255) DEFAULT NULL,
  `aset_id` int DEFAULT NULL,
  `aset_nama_cache` varchar(200) DEFAULT NULL,
  `catatan_penolakan` text COMMENT 'Alasan jika ditolak/tidak bisa',
  `waktu_submit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `waktu_diproses` datetime DEFAULT NULL COMMENT 'Kapan IT mulai handle',
  `waktu_selesai` datetime DEFAULT NULL COMMENT 'Kapan diselesaikan/ditolak/tidak bisa',
  `durasi_respon_menit` int DEFAULT NULL COMMENT 'Menit dari submit ke diproses',
  `durasi_selesai_menit` int DEFAULT NULL COMMENT 'Menit dari submit ke selesai',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tiket`
--

INSERT INTO `tiket` (`id`, `nomor`, `judul`, `deskripsi`, `kategori_id`, `prioritas`, `status`, `user_id`, `teknisi_id`, `lokasi`, `aset_id`, `aset_nama_cache`, `catatan_penolakan`, `waktu_submit`, `waktu_diproses`, `waktu_selesai`, `durasi_respon_menit`, `durasi_selesai_menit`, `created_at`, `updated_at`) VALUES
(29, 'TKT-00001', 'test', 'test', 1, 'Sedang', 'ditolak', 17, 17, 'LT 1 Ruang IT', NULL, NULL, 'oke', '2026-10-23 09:28:40', '2026-10-29 13:32:45', '2026-10-29 13:33:06', 8884, 8884, '2026-10-23 09:28:40', '2026-10-29 13:33:06');

-- --------------------------------------------------------

--
-- Table structure for table `tiket_atem`
--

CREATE TABLE `tiket_atem` (
  `id` int UNSIGNED NOT NULL,
  `nomor` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori_id` int UNSIGNED DEFAULT NULL,
  `prioritas` enum('Rendah','Sedang','Tinggi') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Sedang',
  `status` enum('menunggu','diproses','selesai','ditolak','tidak_bisa') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `user_id` int UNSIGNED DEFAULT NULL,
  `pemohon_nama` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pemohon_telepon` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pemohon_divisi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `teknisi_id` int UNSIGNED DEFAULT NULL,
  `lokasi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aset_info` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `waktu_submit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `waktu_proses` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `durasi_respon_menit` int UNSIGNED DEFAULT NULL,
  `durasi_selesai_menit` int UNSIGNED DEFAULT NULL,
  `catatan_teknisi` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `source_ipsrs_id` int UNSIGNED DEFAULT NULL,
  `rating` tinyint(1) DEFAULT NULL,
  `legacy_user_id` int UNSIGNED DEFAULT NULL,
  `legacy_teknisi_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tiket_atem`
--

INSERT INTO `tiket_atem` (`id`, `nomor`, `judul`, `deskripsi`, `kategori_id`, `prioritas`, `status`, `user_id`, `teknisi_id`, `lokasi`, `aset_info`, `waktu_submit`, `waktu_proses`, `waktu_selesai`, `durasi_respon_menit`, `durasi_selesai_menit`, `catatan_teknisi`, `created_at`, `updated_at`, `source_ipsrs_id`, `rating`, `legacy_user_id`, `legacy_teknisi_id`) VALUES
(2, 'TKT-IPSRS-2026-0001', 'tes', 'tes', 17, 'Sedang', 'selesai', NULL, NULL, 'Lt.3, R.Marketing', NULL, '2026-03-04 09:12:26', '2026-03-04 13:48:14', '2026-03-04 13:48:14', 275, 275, NULL, '2026-03-04 09:12:26', '2026-03-04 13:48:14', 1, NULL, 10, 8),
(3, 'TKT-IPSRS-2026-0002', 'Bocor', 'bocor', 11, 'Sedang', 'selesai', NULL, NULL, 'Lt.1, R.Operasional', NULL, '2026-03-04 14:26:49', '2026-03-04 14:27:19', '2026-03-04 14:27:19', 0, 0, 'sudah selesai ya', '2026-03-04 14:26:49', '2026-03-04 14:27:19', 2, NULL, 10, 9),
(5, 'TKT-ATEM-2026-000005', 'test', 'test', 18, 'Tinggi', 'selesai', 17, 19, 'lt 1', 'atem1111', '2026-10-16 22:48:23', '2026-10-16 22:49:12', '2026-10-16 22:49:12', 0, 0, NULL, '2026-10-16 22:48:23', '2026-10-16 22:49:12', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tiket_atem_foto`
--

CREATE TABLE `tiket_atem_foto` (
  `id` int UNSIGNED NOT NULL,
  `tiket_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `legacy_user_id` int UNSIGNED DEFAULT NULL,
  `nama_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tiket_atem_log`
--

CREATE TABLE `tiket_atem_log` (
  `id` int UNSIGNED NOT NULL,
  `tiket_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `status_dari` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_ke` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `legacy_user_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tiket_atem_log`
--

INSERT INTO `tiket_atem_log` (`id`, `tiket_id`, `user_id`, `status_dari`, `status_ke`, `keterangan`, `created_at`, `legacy_user_id`) VALUES
(2, 2, NULL, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 09:12:26', 10),
(3, 2, NULL, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 13:48:14', 8),
(4, 3, NULL, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 14:26:49', 10),
(5, 3, NULL, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 14:27:19', 9),
(9, 5, 17, NULL, 'menunggu', 'Tiket dibuat oleh Admin Utama', '2026-10-16 22:48:23', NULL),
(10, 5, 17, 'menunggu', 'menunggu', 'Tiket ditugaskan kepada teknisi #19', '2026-10-16 22:48:35', NULL),
(11, 5, 17, 'menunggu', 'selesai', 'Status tiket diperbarui.', '2026-10-16 22:49:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tiket_foto`
--

CREATE TABLE `tiket_foto` (
  `id` int UNSIGNED NOT NULL,
  `tiket_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `pemohon_nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pemohon_telepon` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tiket_foto`
--

INSERT INTO `tiket_foto` (`id`, `tiket_id`, `user_id`, `nama_file`, `path`, `created_at`) VALUES
(1, 25, 8, 'IT KERJA 1.jpeg', 'uploads/tiket_foto/tiket_25_1772554477_0.jpeg', '2026-03-03 23:14:37');

-- --------------------------------------------------------

--
-- Table structure for table `tiket_ipsrs`
--

CREATE TABLE `tiket_ipsrs` (
  `id` int UNSIGNED NOT NULL,
  `nomor` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `judul` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori_id` int UNSIGNED DEFAULT NULL,
  `jenis_tiket` enum('Medis','Non-Medis') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Non-Medis',
  `prioritas` enum('Rendah','Sedang','Tinggi') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Sedang',
  `status` enum('menunggu','diproses','selesai','ditolak','tidak_bisa') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `user_id` int UNSIGNED DEFAULT NULL,
  `pemohon_nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pemohon_telepon` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pemohon_divisi` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `teknisi_id` int UNSIGNED DEFAULT NULL,
  `lokasi` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aset_id` int UNSIGNED DEFAULT NULL,
  `waktu_submit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `waktu_proses` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `durasi_respon_menit` int UNSIGNED DEFAULT NULL,
  `durasi_selesai_menit` int UNSIGNED DEFAULT NULL,
  `catatan_teknisi` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rating` tinyint(1) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tiket_ipsrs`
--

INSERT INTO `tiket_ipsrs` (`id`, `nomor`, `judul`, `deskripsi`, `kategori_id`, `jenis_tiket`, `prioritas`, `status`, `user_id`, `teknisi_id`, `lokasi`, `aset_id`, `waktu_submit`, `waktu_proses`, `waktu_selesai`, `durasi_respon_menit`, `durasi_selesai_menit`, `catatan_teknisi`, `rating`, `created_at`, `updated_at`) VALUES
(3, 'TKT-IPSRS-2026-0003', 'Tidak bisa nyala', 'Tidak bisa nyala automatis\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0006 | Generator Set (Genset) | Caterpillar | DE400E0', 13, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', 7, '2026-03-04 20:58:17', '2026-03-04 20:59:25', '2026-03-04 20:59:25', 1, 1, 'sudah selesai', NULL, '2026-03-04 20:58:17', '2026-03-04 20:59:25'),
(4, 'TKT-IPSRS-2026-0004', 'listrik mati hidup', 'Listrik tidak stabil, mati hidup\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0006 | Generator Set (Genset) | Caterpillar | DE400E0', 12, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', 7, '2026-03-04 21:11:17', '2026-03-04 21:11:41', '2026-03-04 21:11:41', 0, 0, 'sudah selesai', NULL, '2026-03-04 21:11:17', '2026-03-04 21:11:41'),
(5, 'TKT-IPSRS-2026-0005', 'ambulan bocor halus , tapi keliling', 'ambulan bocor\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0009 | Ambulans Transport | Toyota | HiAce Commuter 2.8', 22, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', 10, '2026-03-04 21:20:11', '2026-03-04 21:21:24', '2026-03-04 21:21:24', 1, 1, 'sudah selesai', NULL, '2026-03-04 21:20:11', '2026-03-04 21:21:24'),
(6, 'TKT-IPSRS-2026-0006', 'sapu patah', 'sapu mbak cs patah , tolong di perbaiki', 23, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', NULL, '2026-03-04 21:26:56', '2026-03-04 21:27:14', '2026-03-04 21:27:24', 0, 0, 'sudah', NULL, '2026-03-04 21:26:56', '2026-03-04 21:27:24'),
(7, 'TKT-IPSRS-2026-0007', 'Ambulan', 'ambulan bocor\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0009 | Ambulans Transport | Toyota | HiAce Commuter 2.8', 22, 'Non-Medis', 'Sedang', 'diproses', 10, 9, 'Lt.1, R.Operasional', 10, '2026-03-04 21:30:26', '2026-03-04 21:30:48', NULL, 0, NULL, NULL, NULL, '2026-03-04 21:30:26', '2026-03-04 21:30:48'),
(8, 'TKT-IPSRS-2026-0008', 'ambulan', 'ambulan bocor\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0009 | Ambulans Transport | Toyota | HiAce Commuter 2.8', 22, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', 10, '2026-03-04 21:34:20', '2026-03-04 21:34:53', '2026-03-04 21:34:53', 0, 0, 'sudah ya', NULL, '2026-03-04 21:34:20', '2026-03-04 21:34:53'),
(9, 'TKT-IPSRS-2026-0009', 'Ambulan', 'ban nya bocor', 22, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', NULL, '2026-03-04 21:57:36', '2026-03-04 21:58:23', '2026-03-04 21:58:23', 0, 0, 'sudah ya', NULL, '2026-03-04 21:57:36', '2026-03-04 21:58:23'),
(10, 'TKT-IPSRS-2026-0010', 'Listrik nya redup', 'Listrik nya tidak stabil\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0006 | Generator Set (Genset) | Caterpillar | DE400E0', 12, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.4, R.Direksi', 7, '2026-03-07 10:58:48', '2026-03-07 11:00:26', '2026-03-07 11:00:50', 1, 2, 'sudah ya, harap di periksa lagi', NULL, '2026-03-07 10:58:48', '2026-03-07 11:00:50');

-- --------------------------------------------------------

--
-- Table structure for table `tiket_ipsrs_foto`
--

CREATE TABLE `tiket_ipsrs_foto` (
  `id` int UNSIGNED NOT NULL,
  `tiket_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `nama_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tiket_ipsrs_log`
--

CREATE TABLE `tiket_ipsrs_log` (
  `id` int UNSIGNED NOT NULL,
  `tiket_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `status_dari` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_ke` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tiket_ipsrs_log`
--

INSERT INTO `tiket_ipsrs_log` (`id`, `tiket_id`, `user_id`, `status_dari`, `status_ke`, `keterangan`, `created_at`) VALUES
(5, 3, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 20:58:17'),
(6, 3, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 20:59:25'),
(7, 4, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:11:17'),
(8, 4, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 21:11:41'),
(9, 5, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:20:11'),
(10, 5, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 21:21:24'),
(11, 6, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:26:56'),
(12, 6, 9, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh Giano', '2026-03-04 21:27:14'),
(13, 6, 9, 'diproses', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 21:27:24'),
(14, 7, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:30:26'),
(15, 7, 9, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh Giano', '2026-03-04 21:30:48'),
(16, 8, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:34:20'),
(17, 8, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 21:34:53'),
(18, 9, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:57:37'),
(19, 9, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 21:58:23'),
(20, 10, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-07 10:58:48'),
(21, 10, 10, 'menunggu', 'menunggu', 'Komentar: Mohon segera ya mas', '2026-03-07 10:59:43'),
(22, 10, 9, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh Giano', '2026-03-07 11:00:26'),
(23, 10, 9, 'diproses', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-07 11:00:50');

-- --------------------------------------------------------

--
-- Table structure for table `tiket_log`
--

CREATE TABLE `tiket_log` (
  `id` int UNSIGNED NOT NULL,
  `tiket_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `status_dari` varchar(20) DEFAULT NULL,
  `status_ke` varchar(20) DEFAULT NULL,
  `keterangan` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tiket_log`
--

INSERT INTO `tiket_log` (`id`, `tiket_id`, `user_id`, `status_dari`, `status_ke`, `keterangan`, `created_at`) VALUES
(72, 29, 17, NULL, 'menunggu', 'Tiket dibuat oleh Admin Utama', '2026-10-23 09:28:40'),
(73, 29, 17, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh Admin Utama', '2026-10-29 13:32:45'),
(74, 29, 17, 'diproses', 'ditolak', 'Tiket ditolak. Alasan: oke', '2026-10-29 13:33:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','teknisi','teknisi_ipsrs','teknisi_atem','admin','management_umum','wadir_umum','keuangan','akreditasi') NOT NULL DEFAULT 'user',
  `divisi` varchar(100) DEFAULT NULL COMMENT 'Nama bagian/divisi',
  `pokja_id` int UNSIGNED DEFAULT NULL,
  `is_akreditasi` tinyint(1) NOT NULL DEFAULT '0',
  `no_hp` varchar(20) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `email`, `password`, `role`, `divisi`, `pokja_id`, `is_akreditasi`, `no_hp`, `status`, `created_at`) VALUES
(17, 'Admin Utama', 'admin', NULL, '$2y$10$FrY0aCvOS22dEGcBpGsmROSvxi3NGxHGAhKXcCZDcF.6O1qs0cezC', 'admin', 'IT', NULL, 0, NULL, 'aktif', '2026-10-16 18:37:32'),
(18, 'IPSRS Assalam', 'ipsrs_assalam', NULL, '$2y$10$te1SCqA9vb9Xxpo9QSsdM.P2wPN3xW3UM6N4UbH6s2RJYkHeECvze', 'teknisi_ipsrs', 'IPSRS', NULL, 0, NULL, 'aktif', '2026-10-16 21:31:05'),
(19, 'ATEM Assalam', 'atem_assalam', NULL, '$2y$10$FDF4.3zJ8noqlrHQ0ORMR.ICr5O6zNlJ1QdQPePY2MPlh7cjef21i', 'teknisi_atem', 'ATEM', NULL, 0, NULL, 'aktif', '2026-10-16 21:31:44'),
(20, 'IT Assalam', 'it.assalam', NULL, '$2y$10$auapy7klRtqRBT3v72lRUeFCmcqeUPc7F93P0wEWZglnMyCNQm31G', 'teknisi', NULL, NULL, 0, NULL, 'aktif', '2026-10-26 08:53:19'),
(22, 'Management Umum', 'manag_umum', NULL, '$2y$10$laWgjWnxl2mrh0BBhCEI5OEq2I4A033GKYaFiL/C8hQBnnSZRbT2G', 'management_umum', NULL, NULL, 0, NULL, 'aktif', '2026-10-26 12:59:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `aset_ipsrs`
--
ALTER TABLE `aset_ipsrs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_no_inventaris` (`no_inventaris`),
  ADD KEY `idx_jenis_aset` (`jenis_aset`),
  ADD KEY `idx_kondisi` (`kondisi`),
  ADD KEY `idx_status_pakai` (`status_pakai`),
  ADD KEY `idx_bagian_id` (`bagian_id`),
  ADD KEY `idx_pj_user_id` (`pj_user_id`),
  ADD KEY `idx_kal_berikutnya` (`tgl_kalibrasi_berikutnya`),
  ADD KEY `idx_svc_berikutnya` (`tgl_service_berikutnya`);

--
-- Indexes for table `aset_it`
--
ALTER TABLE `aset_it`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_inventaris` (`no_inventaris`),
  ADD KEY `idx_kondisi` (`kondisi`),
  ADD KEY `idx_kategori` (`kategori`),
  ADD KEY `idx_bagian_id` (`bagian_id`),
  ADD KEY `idx_pj_user_id` (`pj_user_id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `atk_barang`
--
ALTER TABLE `atk_barang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `atk_kategori`
--
ALTER TABLE `atk_kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indexes for table `atk_mutasi`
--
ALTER TABLE `atk_mutasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `atk_opname`
--
ALTER TABLE `atk_opname`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor` (`nomor`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `atk_opname_detail`
--
ALTER TABLE `atk_opname_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `opname_id` (`opname_id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indexes for table `atk_pengajuan`
--
ALTER TABLE `atk_pengajuan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor` (`nomor`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `atk_permintaan`
--
ALTER TABLE `atk_permintaan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor` (`nomor`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `atk_supplier`
--
ALTER TABLE `atk_supplier`
  ADD PRIMARY KEY (`id`),
  ADD KEY `nama` (`nama`),
  ADD KEY `aktif` (`aktif`);

--
-- Indexes for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_filename` (`filename`(100));

--
-- Indexes for table `bagian`
--
ALTER TABLE `bagian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_nama` (`nama`);

--
-- Indexes for table `berita_acara`
--
ALTER TABLE `berita_acara`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tiket` (`tiket_id`);

--
-- Indexes for table `berkas_karyawan`
--
ALTER TABLE `berkas_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_jenis` (`jenis_berkas_id`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kategori_atem`
--
ALTER TABLE `kategori_atem`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_kategori_atem_nama` (`nama`),
  ADD KEY `idx_kategori_atem_aktif` (`aktif`);

--
-- Indexes for table `kategori_ipsrs`
--
ALTER TABLE `kategori_ipsrs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jenis` (`jenis`);

--
-- Indexes for table `komentar`
--
ALTER TABLE `komentar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tiket_id` (`tiket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `komentar_atem`
--
ALTER TABLE `komentar_atem`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_komentar_atem_tiket` (`tiket_id`),
  ADD KEY `idx_komentar_atem_user` (`user_id`);

--
-- Indexes for table `komentar_ipsrs`
--
ALTER TABLE `komentar_ipsrs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tiket` (`tiket_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `koneksi_log`
--
ALTER TABLE `koneksi_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_monitor` (`monitor_id`),
  ADD KEY `idx_cek_at` (`cek_at`);

--
-- Indexes for table `koneksi_monitor`
--
ALTER TABLE `koneksi_monitor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aktif` (`aktif`),
  ADD KEY `idx_tipe` (`tipe`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_ip` (`ip_address`);

--
-- Indexes for table `login_log`
--
ALTER TABLE `login_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `maintenance_ipsrs`
--
ALTER TABLE `maintenance_ipsrs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_no_maintenance` (`no_maintenance`),
  ADD KEY `idx_aset_id` (`aset_id`),
  ADD KEY `idx_teknisi_id` (`teknisi_id`),
  ADD KEY `idx_tgl_maintenance` (`tgl_maintenance`),
  ADD KEY `idx_tgl_maintenance_berikut` (`tgl_maintenance_berikut`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_jenis_maintenance` (`jenis_maintenance`),
  ADD KEY `fk_mnt_ipsrs_creator` (`created_by`);

--
-- Indexes for table `maintenance_it`
--
ALTER TABLE `maintenance_it`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_maintenance` (`no_maintenance`),
  ADD KEY `idx_aset_id` (`aset_id`),
  ADD KEY `idx_teknisi_id` (`teknisi_id`),
  ADD KEY `idx_tgl` (`tgl_maintenance`),
  ADD KEY `idx_tgl_berikut` (`tgl_maintenance_berikut`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `master_pokja`
--
ALTER TABLE `master_pokja`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `mutasi_aset`
--
ALTER TABLE `mutasi_aset`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_mutasi` (`no_mutasi`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`kode`);

--
-- Indexes for table `role_menu`
--
ALTER TABLE `role_menu`
  ADD PRIMARY KEY (`role_kode`,`menu_kode`);

--
-- Indexes for table `sdm_karyawan`
--
ALTER TABLE `sdm_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `server_room_log`
--
ALTER TABLE `server_room_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tanggal` (`tanggal`),
  ADD KEY `idx_status` (`status_overall`),
  ADD KEY `idx_tgl_waktu` (`tanggal`,`waktu`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `tiket`
--
ALTER TABLE `tiket`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor` (`nomor`),
  ADD KEY `kategori_id` (`kategori_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `teknisi_id` (`teknisi_id`);

--
-- Indexes for table `tiket_atem`
--
ALTER TABLE `tiket_atem`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tiket_atem_nomor` (`nomor`),
  ADD UNIQUE KEY `uq_tiket_atem_source_ipsrs` (`source_ipsrs_id`),
  ADD KEY `idx_tiket_atem_status` (`status`),
  ADD KEY `idx_tiket_atem_user` (`user_id`),
  ADD KEY `idx_tiket_atem_teknisi` (`teknisi_id`),
  ADD KEY `idx_tiket_atem_kategori` (`kategori_id`),
  ADD KEY `idx_tiket_atem_waktu` (`waktu_submit`);

--
-- Indexes for table `tiket_atem_foto`
--
ALTER TABLE `tiket_atem_foto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tiket_atem_foto_tiket` (`tiket_id`),
  ADD KEY `idx_tiket_atem_foto_user` (`user_id`);

--
-- Indexes for table `tiket_atem_log`
--
ALTER TABLE `tiket_atem_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tiket_atem_log_tiket` (`tiket_id`),
  ADD KEY `idx_tiket_atem_log_user` (`user_id`);

--
-- Indexes for table `tiket_foto`
--
ALTER TABLE `tiket_foto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tiket_id` (`tiket_id`);

--
-- Indexes for table `tiket_ipsrs`
--
ALTER TABLE `tiket_ipsrs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor` (`nomor`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_teknisi` (`teknisi_id`),
  ADD KEY `idx_kategori` (`kategori_id`),
  ADD KEY `idx_jenis` (`jenis_tiket`),
  ADD KEY `idx_aset` (`aset_id`),
  ADD KEY `idx_waktu` (`waktu_submit`);

--
-- Indexes for table `tiket_ipsrs_foto`
--
ALTER TABLE `tiket_ipsrs_foto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tiket` (`tiket_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `tiket_ipsrs_log`
--
ALTER TABLE `tiket_ipsrs_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tiket` (`tiket_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `tiket_log`
--
ALTER TABLE `tiket_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tiket_id` (`tiket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `aset_ipsrs`
--
ALTER TABLE `aset_ipsrs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `aset_it`
--
ALTER TABLE `aset_it`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `atk_barang`
--
ALTER TABLE `atk_barang`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `atk_kategori`
--
ALTER TABLE `atk_kategori`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `atk_mutasi`
--
ALTER TABLE `atk_mutasi`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `atk_opname`
--
ALTER TABLE `atk_opname`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atk_opname_detail`
--
ALTER TABLE `atk_opname_detail`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atk_pengajuan`
--
ALTER TABLE `atk_pengajuan`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atk_permintaan`
--
ALTER TABLE `atk_permintaan`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `atk_supplier`
--
ALTER TABLE `atk_supplier`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bagian`
--
ALTER TABLE `bagian`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `berita_acara`
--
ALTER TABLE `berita_acara`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `berkas_karyawan`
--
ALTER TABLE `berkas_karyawan`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `kategori_atem`
--
ALTER TABLE `kategori_atem`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `kategori_ipsrs`
--
ALTER TABLE `kategori_ipsrs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `komentar`
--
ALTER TABLE `komentar`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `komentar_atem`
--
ALTER TABLE `komentar_atem`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `komentar_ipsrs`
--
ALTER TABLE `komentar_ipsrs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `koneksi_log`
--
ALTER TABLE `koneksi_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=174;

--
-- AUTO_INCREMENT for table `koneksi_monitor`
--
ALTER TABLE `koneksi_monitor`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_log`
--
ALTER TABLE `login_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT for table `maintenance_ipsrs`
--
ALTER TABLE `maintenance_ipsrs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `maintenance_it`
--
ALTER TABLE `maintenance_it`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `master_pokja`
--
ALTER TABLE `master_pokja`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mutasi_aset`
--
ALTER TABLE `mutasi_aset`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sdm_karyawan`
--
ALTER TABLE `sdm_karyawan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `server_room_log`
--
ALTER TABLE `server_room_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tiket`
--
ALTER TABLE `tiket`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `tiket_atem`
--
ALTER TABLE `tiket_atem`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tiket_atem_foto`
--
ALTER TABLE `tiket_atem_foto`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tiket_atem_log`
--
ALTER TABLE `tiket_atem_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tiket_foto`
--
ALTER TABLE `tiket_foto`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tiket_ipsrs`
--
ALTER TABLE `tiket_ipsrs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tiket_ipsrs_foto`
--
ALTER TABLE `tiket_ipsrs_foto`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tiket_ipsrs_log`
--
ALTER TABLE `tiket_ipsrs_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `tiket_log`
--
ALTER TABLE `tiket_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `komentar`
--
ALTER TABLE `komentar`
  ADD CONSTRAINT `komentar_ibfk_1` FOREIGN KEY (`tiket_id`) REFERENCES `tiket` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `komentar_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `komentar_atem`
--
ALTER TABLE `komentar_atem`
  ADD CONSTRAINT `fk_komentar_atem_tiket` FOREIGN KEY (`tiket_id`) REFERENCES `tiket_atem` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_komentar_atem_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `koneksi_log`
--
ALTER TABLE `koneksi_log`
  ADD CONSTRAINT `fk_log_monitor` FOREIGN KEY (`monitor_id`) REFERENCES `koneksi_monitor` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_ipsrs`
--
ALTER TABLE `maintenance_ipsrs`
  ADD CONSTRAINT `fk_mnt_ipsrs_aset` FOREIGN KEY (`aset_id`) REFERENCES `aset_ipsrs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mnt_ipsrs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mnt_ipsrs_teknisi` FOREIGN KEY (`teknisi_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tiket`
--
ALTER TABLE `tiket`
  ADD CONSTRAINT `tiket_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tiket_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tiket_ibfk_3` FOREIGN KEY (`teknisi_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tiket_atem`
--
ALTER TABLE `tiket_atem`
  ADD CONSTRAINT `fk_tiket_atem_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_atem` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tiket_atem_teknisi` FOREIGN KEY (`teknisi_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tiket_atem_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tiket_atem_foto`
--
ALTER TABLE `tiket_atem_foto`
  ADD CONSTRAINT `fk_tiket_atem_foto_tiket` FOREIGN KEY (`tiket_id`) REFERENCES `tiket_atem` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tiket_atem_foto_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tiket_atem_log`
--
ALTER TABLE `tiket_atem_log`
  ADD CONSTRAINT `fk_tiket_atem_log_tiket` FOREIGN KEY (`tiket_id`) REFERENCES `tiket_atem` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tiket_atem_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tiket_log`
--
ALTER TABLE `tiket_log`
  ADD CONSTRAINT `tiket_log_ibfk_1` FOREIGN KEY (`tiket_id`) REFERENCES `tiket` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tiket_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 24 Nov 2026 pada 00.53
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_tokoku`
--

DELIMITER $$
--
-- Prosedur
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CariPelanggan` (IN `p_id` INT)   BEGIN
    SELECT *
    FROM tbl_pelanggan
    WHERE id_pelanggan = p_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CekTransaksi` (IN `p_id` INT)   BEGIN
    DECLARE jumlah INT;

    SELECT COUNT(*)
    INTO jumlah
    FROM tbl_transaksi
    WHERE id_pelanggan = p_id;

    IF jumlah > 0 THEN
        SELECT 'Pelanggan Memiliki Transaksi'
        AS keterangan;
    ELSE
        SELECT 'Belum Memiliki Transaksi'
        AS keterangan;
    END IF;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `HitungPelanggan` (OUT `jumlah` INT)   BEGIN
    SELECT COUNT(*)
    INTO jumlah
    FROM tbl_pelanggan;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `LaporanTransaksi` (IN `p_id_pelanggan` INT)   BEGIN
    SELECT
        p.id_pelanggan,
        p.nama_pelanggan,
        p.kota,
        t.id_transaksi,
        t.tanggal,
        t.total_belanja
    FROM tbl_pelanggan p
    INNER JOIN tbl_transaksi t
        ON p.id_pelanggan = t.id_pelanggan
    WHERE p.id_pelanggan = p_id_pelanggan;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_tambah_transaksi` (IN `p_id_pelanggan` INT, IN `p_id_produk` INT, IN `p_jumlah` INT)   BEGIN
    DECLARE v_harga DECIMAL(12,2);
    DECLARE v_nama_produk VARCHAR(100);
    DECLARE v_id_transaksi INT;
    DECLARE v_cek INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    
    SELECT COUNT(*) INTO v_cek
    FROM tbl_pelanggan
    WHERE id_pelanggan = p_id_pelanggan;

    IF v_cek = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Pelanggan tidak ditemukan';
    END IF;

    
    SELECT COUNT(*) INTO v_cek
    FROM tbl_produk
    WHERE id_produk = p_id_produk;

    IF v_cek = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Produk tidak ditemukan';
    END IF;

    
    IF p_jumlah <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Jumlah pembelian harus lebih dari 0';
    END IF;

    
    SELECT nama_produk, harga
    INTO v_nama_produk, v_harga
    FROM tbl_produk
    WHERE id_produk = p_id_produk;

    
    INSERT INTO tbl_transaksi (
        tanggal,
        id_pelanggan,
        total_belanja
    )
    VALUES (
        CURDATE(),
        p_id_pelanggan,
        v_harga * p_jumlah
    );

    SET v_id_transaksi = LAST_INSERT_ID();

    
    INSERT INTO tbl_detail_transaksi (
        id_transaksi,
        id_produk,
        nama_produk,
        jumlah,
        harga
    )
    VALUES (
        v_id_transaksi,
        p_id_produk,
        v_nama_produk,
        p_jumlah,
        v_harga
    );

    COMMIT;

    SELECT
        v_id_transaksi AS id_transaksi,
        v_nama_produk AS nama_produk,
        p_jumlah AS jumlah,
        v_harga AS harga_satuan,
        (v_harga * p_jumlah) AS total_belanja,
        'Transaksi berhasil ditambahkan' AS keterangan;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `TambahPelanggan` (IN `p_nama` VARCHAR(100), IN `p_kota` VARCHAR(50))   BEGIN
    INSERT INTO tbl_pelanggan
    (nama_pelanggan, kota)
    VALUES
    (p_nama, p_kota);
END$$

--
-- Fungsi
--
CREATE DEFINER=`root`@`localhost` FUNCTION `HitungDiskon` (`p_harga` DECIMAL(12,2), `p_diskon` DECIMAL(5,2)) RETURNS DECIMAL(12,2) DETERMINISTIC NO SQL BEGIN
    RETURN p_harga - (p_harga * p_diskon / 100);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `HitungPajak` (`p_harga` DECIMAL(12,2)) RETURNS DECIMAL(12,2) DETERMINISTIC NO SQL BEGIN
    RETURN p_harga * 0.11;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `HitungTotal` (`p_harga` DECIMAL(12,2), `p_jumlah` INT) RETURNS DECIMAL(12,2) DETERMINISTIC NO SQL BEGIN
    RETURN p_harga * p_jumlah;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `KategoriBelanja` (`p_total` DECIMAL(12,2)) RETURNS VARCHAR(30) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC NO SQL BEGIN
    RETURN CASE
        WHEN p_total >= 1000000 THEN 'Pelanggan Gold'
        WHEN p_total >= 500000 THEN 'Pelanggan Silver'
        ELSE 'Pelanggan Reguler'
    END;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `KonversiNilai` (`p_nilai` DECIMAL(5,2)) RETURNS VARCHAR(2) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC NO SQL BEGIN
    RETURN CASE
        WHEN p_nilai < 0 OR p_nilai > 100 THEN 'Tidak Valid'
        WHEN p_nilai >= 90 THEN 'A'
        WHEN p_nilai >= 80 THEN 'B'
        WHEN p_nilai >= 70 THEN 'C'
        WHEN p_nilai >= 60 THEN 'D'
        ELSE 'E'
    END;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_pelanggan`
--

CREATE TABLE `log_pelanggan` (
  `id_log` int(11) NOT NULL,
  `aktivitas` varchar(50) DEFAULT NULL,
  `id_pelanggan` int(11) DEFAULT NULL,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `log_pelanggan`
--

INSERT INTO `log_pelanggan` (`id_log`, `aktivitas`, `id_pelanggan`, `waktu`) VALUES
(1, 'INSERT PELANGGAN', 14, '2026-11-09 05:42:47'),
(2, 'INSERT PELANGGAN', 15, '2026-11-11 00:30:39'),
(3, 'INSERT PELANGGAN', 16, '2026-11-11 01:15:06'),
(4, 'INSERT PELANGGAN', 17, '2026-11-11 01:36:24');

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_perubahan_pelanggan`
--

CREATE TABLE `log_perubahan_pelanggan` (
  `id_log` int(11) NOT NULL,
  `id_pelanggan` int(11) DEFAULT NULL,
  `nama_lama` varchar(100) DEFAULT NULL,
  `nama_baru` varchar(100) DEFAULT NULL,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `log_perubahan_pelanggan`
--

INSERT INTO `log_perubahan_pelanggan` (`id_log`, `id_pelanggan`, `nama_lama`, `nama_baru`, `waktu`) VALUES
(1, 4, 'Dimas', 'Rina Wijaya', '2026-11-09 05:48:30'),
(2, 4, 'Rina Wijaya', 'Rina Wijaya', '2026-11-09 05:49:30');

-- --------------------------------------------------------

--
-- Struktur dari tabel `member`
--

CREATE TABLE `member` (
  `id_member` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `no_member` varchar(20) NOT NULL,
  `level` enum('reguler','silver','gold') DEFAULT 'reguler',
  `poin` int(11) DEFAULT 0,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `tgl_daftar` datetime DEFAULT current_timestamp(),
  `rfid_uid` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `member`
--

INSERT INTO `member` (`id_member`, `id_pelanggan`, `no_member`, `level`, `poin`, `status`, `tgl_daftar`, `rfid_uid`) VALUES
(1, 16, 'MBR-000001', 'reguler', 0, 'aktif', '2026-11-23 10:52:36', '0008442193'),
(2, 17, 'MBR-000002', 'reguler', 0, 'aktif', '2026-11-23 11:13:12', '0008493873');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_cabang`
--

CREATE TABLE `tbl_cabang` (
  `id_cabang` int(11) NOT NULL,
  `nama_cabang` varchar(100) DEFAULT NULL,
  `kota` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_detail_transaksi`
--

CREATE TABLE `tbl_detail_transaksi` (
  `id_detail` int(11) NOT NULL,
  `id_transaksi` int(11) DEFAULT NULL,
  `id_produk` int(11) DEFAULT NULL,
  `nama_produk` varchar(100) DEFAULT NULL,
  `jumlah` int(11) DEFAULT NULL,
  `harga` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_detail_transaksi`
--

INSERT INTO `tbl_detail_transaksi` (`id_detail`, `id_transaksi`, `id_produk`, `nama_produk`, `jumlah`, `harga`) VALUES
(1, 1, 8, 'Keyboard Mechanical', 1, 250000.00),
(2, 2, 13, 'Mouse Wireless', 2, 175000.00),
(3, 3, 2, 'Flashdisk 32GB', 2, 75000.00),
(4, 4, 11, 'Monitor LED', 1, 500000.00),
(5, 5, 7, 'Keyboard Gaming', 1, 200000.00),
(6, 6, 5, 'Headset', 1, 175000.00),
(7, 7, 18, 'SSD 256GB', 1, 425000.00),
(8, 8, 15, 'RAM 8GB', 1, 300000.00),
(9, 9, 12, 'Mouse Gaming', 1, 275000.00),
(10, 10, 14, 'Printer', 1, 600000.00),
(11, 11, 3, 'Flashdisk 64GB', 1, 125000.00),
(12, 12, 17, 'Speaker', 1, 225000.00),
(13, 13, 20, 'Webcam', 1, 400000.00),
(14, 14, 16, 'Router WiFi', 1, 325000.00),
(15, 15, 1, 'Cooling Pad', 1, 150000.00),
(16, 16, 4, 'Harddisk 1TB', 1, 550000.00),
(17, 17, 10, 'Mikrofon', 1, 275000.00),
(18, 18, 9, 'Laptop Stand', 1, 350000.00),
(19, 19, 19, 'UPS', 1, 450000.00),
(20, 20, 6, 'Kabel HDMI', 2, 100000.00),
(21, 22, 1, 'Cooling Pad', 2, 150000.00),
(22, 23, 20, 'Webcam', 10, 400000.00),
(23, 24, 20, 'Webcam', 1, 700000.00),
(24, 24, 21, 'Ram 16gb', 2, 1000000.00),
(25, 25, 10, 'Mikrofon', 1, 275000.00),
(26, 25, 11, 'Monitor LED', 1, 500000.00),
(27, 25, 12, 'Mouse Gaming', 1, 275000.00),
(28, 25, 13, 'Mouse Wireless', 1, 175000.00),
(29, 25, 14, 'Printer', 1, 600000.00),
(30, 25, 15, 'RAM 8GB', 1, 300000.00),
(31, 25, 16, 'Router WiFi', 1, 325000.00),
(32, 25, 17, 'Speaker', 1, 225000.00),
(33, 25, 18, 'SSD 256GB', 1, 425000.00),
(34, 25, 19, 'UPS', 1, 450000.00),
(35, 25, 20, 'Webcam', 1, 700000.00),
(36, 25, 21, 'Ram 16gb', 1, 1000000.00),
(37, 26, 12, 'Mouse Gaming', 1, 275000.00),
(38, 27, 10, 'Mikrofon', 1, 275000.00),
(39, 27, 11, 'Monitor LED', 1, 500000.00),
(40, 27, 13, 'Mouse Wireless', 1, 175000.00),
(41, 27, 21, 'Ram 16gb', 1, 1000000.00),
(42, 28, 11, 'Monitor LED', 1, 500000.00),
(43, 28, 12, 'Mouse Gaming', 1, 275000.00),
(44, 29, 17, 'Speaker', 29, 225000.00),
(45, 29, 21, 'Ram 16gb', 1, 1000000.00),
(46, 30, 12, 'Mouse Gaming', 1, 275000.00),
(47, 31, 12, 'Mouse Gaming', 1, 275000.00),
(48, 32, 12, 'Mouse Gaming', 1, 275000.00),
(49, 33, 12, 'Mouse Gaming', 1, 275000.00),
(50, 34, 20, 'Webcam', 1, 700000.00),
(51, 35, 12, 'Mouse Gaming', 1, 275000.00),
(52, 36, 10, 'Mikrofon', 1, 275000.00),
(53, 36, 11, 'Monitor LED', 1, 500000.00),
(54, 36, 12, 'Mouse Gaming', 1, 275000.00),
(55, 36, 13, 'Mouse Wireless', 1, 175000.00),
(56, 37, 12, 'Mouse Gaming', 1, 275000.00),
(57, 38, 11, 'Monitor LED', 1, 500000.00),
(58, 38, 13, 'Mouse Wireless', 1, 175000.00),
(59, 38, 20, 'Webcam', 1, 700000.00),
(60, 39, 11, 'Monitor LED', 1, 500000.00),
(61, 39, 13, 'Mouse Wireless', 1, 175000.00),
(62, 39, 15, 'RAM 8GB', 1, 300000.00),
(63, 39, 18, 'SSD 256GB', 1, 425000.00),
(64, 39, 20, 'Webcam', 1, 700000.00),
(65, 39, 21, 'Ram 16gb', 1, 1000000.00),
(66, 40, 10, 'Mikrofon', 1, 275000.00),
(67, 40, 11, 'Monitor LED', 1, 500000.00),
(68, 40, 14, 'Printer', 1, 600000.00),
(69, 40, 15, 'RAM 8GB', 1, 300000.00),
(70, 40, 16, 'Router WiFi', 1, 325000.00),
(71, 40, 19, 'USB', 1, 450000.00),
(72, 40, 20, 'Webcam', 1, 700000.00),
(73, 40, 21, 'Ram 16gb', 1, 1000000.00),
(74, 41, 10, 'Mikrofon', 1, 275000.00),
(75, 41, 14, 'Printer', 1, 600000.00),
(76, 41, 16, 'Router WiFi', 1, 325000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_kategori`
--

CREATE TABLE `tbl_kategori` (
  `id_kategori` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_kategori`
--

INSERT INTO `tbl_kategori` (`id_kategori`, `nama_kategori`) VALUES
(1, 'Penyimpanan'),
(2, 'Aksesoris Komputer'),
(3, 'Perangkat Input'),
(4, 'Audio dan Video'),
(5, 'Jaringan'),
(6, 'Perangkat Output'),
(7, 'Komponen Komputer');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_log`
--

CREATE TABLE `tbl_log` (
  `id_log` int(11) NOT NULL,
  `aktivitas` varchar(100) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_log`
--

INSERT INTO `tbl_log` (`id_log`, `aktivitas`, `keterangan`, `waktu`) VALUES
(1, 'UPDATE PRODUK', 'Mengubah produk: Webcam', '2026-11-09 06:18:50'),
(2, 'INSERT TRANSAKSI', 'Transaksi #23 oleh Rina Wijaya, produk Webcam, total Rp 4.000.000', '2026-11-09 06:19:35'),
(3, 'INSERT PRODUK', 'Menambahkan produk: Ram 16gb', '2026-11-10 23:53:03'),
(4, 'UPDATE PRODUK', 'Mengubah produk: Webcam', '2026-11-10 23:53:53'),
(5, 'DELETE TRANSAKSI', 'Menghapus transaksi #21 beserta detailnya', '2026-11-10 23:55:20'),
(6, 'INSERT TRANSAKSI', 'Transaksi #24 oleh Agus, Webcam x1, Ram 16gb x2, total Rp 2.700.000', '2026-11-11 00:28:24'),
(7, 'UPDATE PRODUK', 'Mengubah produk: UPS', '2026-11-11 00:29:22'),
(8, 'UPDATE PRODUK', 'Mengubah produk: SSD 256GB', '2026-11-11 00:29:39'),
(9, 'UPDATE PRODUK', 'Mengubah produk: Speaker', '2026-11-11 00:29:45'),
(10, 'UPDATE PRODUK', 'Mengubah produk: Router WiFi', '2026-11-11 00:29:50'),
(11, 'UPDATE PRODUK', 'Mengubah produk: RAM 8GB', '2026-11-11 00:29:54'),
(12, 'UPDATE PRODUK', 'Mengubah produk: Printer', '2026-11-11 00:29:59'),
(13, 'UPDATE PRODUK', 'Mengubah produk: Mouse Wireless', '2026-11-11 00:30:05'),
(14, 'UPDATE PRODUK', 'Mengubah produk: Mouse Gaming', '2026-11-11 00:30:10'),
(15, 'UPDATE PRODUK', 'Mengubah produk: Monitor LED', '2026-11-11 00:30:15'),
(16, 'UPDATE PRODUK', 'Mengubah produk: Mikrofon', '2026-11-11 00:30:23'),
(17, 'INSERT PELANGGAN', 'Menambahkan pelanggan: Neo', '2026-11-11 00:30:39'),
(18, 'INSERT TRANSAKSI', 'Transaksi #25 oleh Neo, Mikrofon x1, Monitor LED x1, Mouse Gaming x1, Mouse Wireless x1, Printer x1, RAM 8GB x1, Router WiFi x1, Speaker x1, SSD 256GB x1, UPS x1, Webcam x1, Ram 16gb x1, total Rp 5.250.000', '2026-11-11 00:30:56'),
(19, 'INSERT TRANSAKSI', 'Transaksi #26 oleh Neo, Mouse Gaming x1, total Rp 275.000', '2026-11-11 00:33:16'),
(20, 'INSERT TRANSAKSI', 'Transaksi #27 oleh Neo, Mikrofon x1, Monitor LED x1, Mouse Wireless x1, Ram 16gb x1, total Rp 1.950.000', '2026-11-11 00:36:51'),
(21, 'INSERT TRANSAKSI', 'Transaksi #28 oleh Neo, Monitor LED x1, Mouse Gaming x1, total Rp 775.000', '2026-11-11 01:03:58'),
(22, 'LOGIN', 'User admin (admin) masuk ke sistem', '2026-11-11 01:10:33'),
(23, 'LOGOUT', 'User admin keluar dari sistem', '2026-11-11 01:11:13'),
(24, 'LOGIN', 'User admin (admin) masuk ke sistem', '2026-11-11 01:11:53'),
(25, 'REGISTER', 'Pelanggan baru mendaftar: Danara (Amfidza neo danara)', '2026-11-11 01:15:06'),
(26, 'UPDATE ROLE', 'Admin admin mengubah role Danara: pelanggan menjadi petugas', '2026-11-11 01:15:25'),
(27, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-11 01:19:26'),
(28, 'INSERT TRANSAKSI', 'Transaksi #29 oleh Neo, Speaker x29, Ram 16gb x1, total Rp 7.525.000 (kasir: Danara)', '2026-11-11 01:20:31'),
(29, 'INSERT TRANSAKSI', 'Transaksi #30 oleh Amfidza neo danara, Mouse Gaming x1, total Rp 275.000 (kasir: Danara)', '2026-11-11 01:24:56'),
(30, 'INSERT TRANSAKSI', 'Transaksi #31 oleh Andi, Mouse Gaming x1, total Rp 275.000 (kasir: Danara)', '2026-11-11 01:25:29'),
(31, 'INSERT TRANSAKSI', 'Transaksi #32 oleh Neo, Mouse Gaming x1, total Rp 275.000 (kasir: admin)', '2026-11-11 01:26:22'),
(32, 'INSERT TRANSAKSI', 'Transaksi #33 oleh Neo, Mouse Gaming x1, total Rp 275.000 (kasir: admin)', '2026-11-11 01:26:35'),
(33, 'INSERT TRANSAKSI', 'Transaksi #34 oleh Amfidza neo danara, Webcam x1, total Rp 700.000 (kasir: admin)', '2026-11-11 01:27:04'),
(34, 'INSERT TRANSAKSI', 'Transaksi #35 oleh Agus, Mouse Gaming x1, total Rp 275.000', '2026-11-11 01:28:32'),
(35, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-11 01:35:47'),
(36, 'REGISTER', 'Pelanggan baru mendaftar: Amfidza (Amfidza)', '2026-11-11 01:36:24'),
(37, 'LOGIN', 'User Amfidza (pelanggan) masuk ke sistem', '2026-11-11 01:36:38'),
(38, 'INSERT TRANSAKSI', 'Transaksi #36 oleh Amfidza, Mikrofon x1, Monitor LED x1, Mouse Gaming x1, Mouse Wireless x1, total Rp 1.225.000 (oleh pelanggan Amfidza)', '2026-11-11 01:37:02'),
(39, 'UPDATE PRODUK', 'Mengubah produk: Ram 16gb', '2026-11-11 01:44:38'),
(40, 'UPDATE PRODUK', 'Mengubah produk: Ram 16gb', '2026-11-11 01:46:30'),
(41, 'UPDATE PRODUK', 'Mengubah produk: Mikrofon', '2026-11-11 01:46:40'),
(42, 'UPDATE PRODUK', 'Mengubah produk: Monitor LED', '2026-11-11 03:28:06'),
(43, 'UPDATE PRODUK', 'Mengubah produk: Webcam', '2026-11-11 03:28:36'),
(44, 'UPDATE PRODUK', 'Mengubah produk: USB', '2026-11-11 03:29:29'),
(45, 'UPDATE PRODUK', 'Mengubah produk: Mouse Gaming', '2026-11-11 03:30:19'),
(46, 'UPDATE PRODUK', 'Mengubah produk: SSD 256GB', '2026-11-11 03:31:19'),
(47, 'UPDATE PRODUK', 'Mengubah produk: Speaker', '2026-11-11 03:33:19'),
(48, 'UPDATE PRODUK', 'Mengubah produk: Router WiFi', '2026-11-11 03:33:47'),
(49, 'UPDATE PRODUK', 'Mengubah produk: RAM 8GB', '2026-11-11 03:34:30'),
(50, 'UPDATE PRODUK', 'Mengubah produk: Printer', '2026-11-11 03:34:59'),
(51, 'UPDATE PRODUK', 'Mengubah produk: Mouse Wireless', '2026-11-11 03:35:26'),
(52, 'UPDATE PRODUK', 'Mengubah produk: Laptop Stand', '2026-11-11 03:36:03'),
(53, 'UPDATE PRODUK', 'Mengubah produk: Keyboard Mechanical', '2026-11-11 03:36:43'),
(54, 'UPDATE PRODUK', 'Mengubah produk: Keyboard Gaming', '2026-11-11 03:37:06'),
(55, 'UPDATE PRODUK', 'Mengubah produk: Kabel HDMI', '2026-11-11 03:37:30'),
(56, 'UPDATE PRODUK', 'Mengubah produk: Headset', '2026-11-11 03:37:50'),
(57, 'UPDATE PRODUK', 'Mengubah produk: Harddisk 1TB', '2026-11-11 03:38:14'),
(58, 'UPDATE PRODUK', 'Mengubah produk: Flashdisk 64GB', '2026-11-11 03:38:39'),
(59, 'UPDATE PRODUK', 'Mengubah produk: Flashdisk 32GB', '2026-11-11 03:38:52'),
(60, 'UPDATE PRODUK', 'Mengubah produk: Cooling Pad', '2026-11-11 03:39:19'),
(61, 'LOGIN', 'User Amfidza (pelanggan) masuk ke sistem', '2026-11-11 05:55:26'),
(62, 'LOGOUT', 'User Amfidza keluar dari sistem', '2026-11-11 05:55:32'),
(63, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-11 05:55:45'),
(64, 'INSERT TRANSAKSI', 'Transaksi #37 oleh Agus, Mouse Gaming x1, total Rp 275.000 (oleh petugas Danara)', '2026-11-11 06:03:15'),
(65, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-11 06:32:58'),
(66, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-13 02:44:27'),
(67, 'INSERT TRANSAKSI', 'Transaksi #38 oleh Amfidza neo danara, Monitor LED x1, Mouse Wireless x1, Webcam x1, total Rp 1.375.000 (oleh petugas Danara)', '2026-11-13 02:44:58'),
(68, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-13 02:45:15'),
(69, 'LOGIN', 'User admin (admin) masuk ke sistem', '2026-11-13 02:45:47'),
(70, 'LOGIN', 'User Amfidza (pelanggan) masuk ke sistem', '2026-11-13 03:42:42'),
(71, 'INSERT TRANSAKSI', 'Transaksi #39 oleh Amfidza, Monitor LED x1, Mouse Wireless x1, RAM 8GB x1, SSD 256GB x1, Webcam x1, Ram 16gb x1, total Rp 3.100.000 (oleh pelanggan Amfidza)', '2026-11-13 03:59:21'),
(72, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-23 01:06:12'),
(73, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-23 01:09:44'),
(74, 'LOGIN', 'User Amfidza (pelanggan) masuk ke sistem', '2026-11-23 01:10:00'),
(75, 'LOGOUT', 'User Amfidza keluar dari sistem', '2026-11-23 02:37:49'),
(76, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-23 02:38:02'),
(77, 'INSERT TRANSAKSI', 'Transaksi #40 oleh Citra, Mikrofon x1, Monitor LED x1, Printer x1, RAM 8GB x1, Router WiFi x1, USB x1, Webcam x1, Ram 16gb x1, total Rp 4.150.000 (oleh petugas Danara)', '2026-11-23 02:51:07'),
(78, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-23 03:49:17'),
(79, 'LOGIN', 'User admin (admin) masuk ke sistem', '2026-11-23 03:49:27'),
(80, 'INSERT MEMBER', 'Mendaftarkan Amfidza neo danara sebagai member MBR-000001', '2026-11-23 03:52:36'),
(81, 'UPDATE MEMBER', 'Memasang kartu RFID ke member MBR-000001', '2026-11-23 03:52:43'),
(82, 'UPDATE MEMBER', 'Memasang kartu RFID ke member MBR-000001', '2026-11-23 03:52:53'),
(83, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-23 03:53:29'),
(84, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-23 03:58:02'),
(85, 'LOGIN RFID', 'User Danara (petugas) masuk dengan kartu RFID', '2026-11-23 04:09:06'),
(86, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-23 04:09:13'),
(87, 'LOGIN RFID', 'User Danara (petugas) masuk dengan kartu RFID', '2026-11-23 04:09:18'),
(88, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-23 04:09:26'),
(89, 'LOGIN RFID', 'User Danara (petugas) masuk dengan kartu RFID', '2026-11-23 04:09:30'),
(90, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-23 04:12:11'),
(91, 'LOGIN RFID', 'User Danara (petugas) masuk dengan kartu RFID', '2026-11-23 04:12:18'),
(92, 'INSERT MEMBER', 'Mendaftarkan Amfidza sebagai member MBR-000002', '2026-11-23 04:13:12'),
(93, 'UPDATE MEMBER', 'Memasang kartu RFID ke member MBR-000002', '2026-11-23 04:13:16'),
(94, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-23 04:14:09'),
(95, 'LOGIN RFID', 'User Amfidza (pelanggan) masuk dengan kartu RFID', '2026-11-23 04:14:15'),
(96, 'LOGOUT', 'User admin keluar dari sistem', '2026-11-23 04:29:09'),
(97, 'LOGIN RFID', 'User Danara (petugas) masuk dengan kartu RFID', '2026-11-23 04:29:45'),
(98, 'INSERT TRANSAKSI', 'Transaksi #41 oleh Amfidza, Mikrofon x1, Printer x1, Router WiFi x1, total Rp 1.020.000 (diskon member Rp 180.000) (oleh petugas Danara)', '2026-11-23 04:31:52'),
(99, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-23 04:43:31'),
(100, 'LOGIN', 'User admin (admin) masuk ke sistem', '2026-11-23 04:43:42'),
(101, 'UPDATE PRODUK', 'Mengubah produk: Ram 16gb', '2026-11-23 04:44:28'),
(102, 'UPDATE PRODUK', 'Mengubah produk: Webcam', '2026-11-23 04:45:11'),
(103, 'UPDATE PRODUK', 'Mengubah produk: USB', '2026-11-23 04:45:24'),
(104, 'UPDATE PRODUK', 'Mengubah produk: SSD 256GB', '2026-11-23 04:46:28'),
(105, 'UPDATE PRODUK', 'Mengubah produk: SSD 256GB', '2026-11-23 04:47:11'),
(106, 'UPDATE PRODUK', 'Mengubah produk: Mouse Gaming', '2026-11-23 04:48:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_pelanggan`
--

CREATE TABLE `tbl_pelanggan` (
  `id_pelanggan` int(11) NOT NULL,
  `nama_pelanggan` varchar(100) DEFAULT NULL,
  `kota` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_pelanggan`
--

INSERT INTO `tbl_pelanggan` (`id_pelanggan`, `nama_pelanggan`, `kota`) VALUES
(1, 'Andi', 'Ponorogo'),
(2, 'Budi', 'Madiun'),
(3, 'Citra', 'Surabaya'),
(4, 'Rina Wijaya', 'Ponorogo'),
(5, 'Eka', 'Magetan'),
(6, 'Fajar', 'Madiun'),
(7, 'Gita', 'Ngawi'),
(8, 'Hendra', 'Surabaya'),
(9, 'Indah', 'Ponorogo'),
(10, 'Joko', 'Magetan'),
(11, 'Agus', 'Ponorogo'),
(12, 'Dewi', 'Ponorogo'),
(13, 'Rudi', 'Madiun'),
(14, 'Rina', 'Ponorogo'),
(15, 'Neo', 'Ponorogo'),
(16, 'Amfidza neo danara', 'Ponorogo'),
(17, 'Amfidza', 'Ponorogo');

--
-- Trigger `tbl_pelanggan`
--
DELIMITER $$
CREATE TRIGGER `after_insert_pelanggan` AFTER INSERT ON `tbl_pelanggan` FOR EACH ROW BEGIN
    INSERT INTO log_pelanggan
    (aktivitas, id_pelanggan)
    VALUES
    ('INSERT PELANGGAN', NEW.id_pelanggan);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_update_pelanggan` AFTER UPDATE ON `tbl_pelanggan` FOR EACH ROW BEGIN
    INSERT INTO log_perubahan_pelanggan
    (id_pelanggan, nama_lama, nama_baru)
    VALUES
    (OLD.id_pelanggan, OLD.nama_pelanggan, NEW.nama_pelanggan);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_produk`
--

CREATE TABLE `tbl_produk` (
  `id_produk` int(11) NOT NULL,
  `kode_produk` varchar(20) NOT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `nama_produk` varchar(100) NOT NULL,
  `id_kategori` int(11) DEFAULT NULL,
  `harga` decimal(12,2) NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `gambar` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_produk`
--

INSERT INTO `tbl_produk` (`id_produk`, `kode_produk`, `barcode`, `nama_produk`, `id_kategori`, `harga`, `stok`, `gambar`) VALUES
(1, 'PRD001', NULL, 'Cooling Pad', 2, 150000.00, 0, '544eb97877e9bef7d4fe12f2c0f1bcb2.jpg'),
(2, 'PRD002', NULL, 'Flashdisk 32GB', 1, 75000.00, 0, '73eac311742e8921711a91ea8c80f9d7.jpg'),
(3, 'PRD003', NULL, 'Flashdisk 64GB', 1, 125000.00, 0, 'ddd477a5a3fad2644894efc4a126d93d.jpg'),
(4, 'PRD004', NULL, 'Harddisk 1TB', 1, 550000.00, 0, 'ef2ffa84753b123594fe989dcacc3994.jpg'),
(5, 'PRD005', NULL, 'Headset', 4, 175000.00, 0, '32a099b68062901a47b16fd2a1b41800.jpg'),
(6, 'PRD006', NULL, 'Kabel HDMI', 2, 100000.00, 0, '6f411bf89bee1990604c71651c7ae50b.jpg'),
(7, 'PRD007', NULL, 'Keyboard Gaming', 3, 200000.00, 0, 'b386059e26b362a7980b868f410a4713.jpg'),
(8, 'PRD008', NULL, 'Keyboard Mechanical', 3, 250000.00, 0, 'b99107e90bcd7ce2e2fce8e948110179.jpg'),
(9, 'PRD009', NULL, 'Laptop Stand', 2, 350000.00, 0, 'c26f9239f8040b7cdc7377d50d0f3cef.jpg'),
(10, 'PRD010', NULL, 'Mikrofon', 4, 275000.00, 25, '8c9c1e47bcffe5c11c63995128fc3fc1.jpg'),
(11, 'PRD011', NULL, 'Monitor LED', 6, 500000.00, 3, 'b36b2dc4ec7985e2a85eb5fa88a39b5f.jpg'),
(12, 'PRD012', '8935001859918', 'Mouse Gaming', 3, 275000.00, 320, '08412d76827ee093d6fe93af29ae9638.jpg'),
(13, 'PRD013', NULL, 'Mouse Wireless', 3, 175000.00, 25, '414658d2ac8a58171710b22b14f6b08a.jpg'),
(14, 'PRD014', NULL, 'Printer', 6, 600000.00, 17, 'c1640597a9e38277dfd9a9fe9c43540a.jpg'),
(15, 'PRD015', NULL, 'RAM 8GB', 7, 300000.00, 7, '9a23fd529df75116901e18a5f4734a15.jpg'),
(16, 'PRD016', NULL, 'Router WiFi', 5, 325000.00, 17, '8d215cc2203af1751b2e289b8eb021cc.jpg'),
(17, 'PRD017', NULL, 'Speaker', 4, 225000.00, 0, '1a3def833475d818a61f35c7f2369719.jpg'),
(18, 'PRD018', '8997240102187', 'SSD 256GB', 1, 425000.00, 28, 'd000d5153824e01e4fd0918231c74938.jpg'),
(19, 'PRD019', '8991389241646', 'USB', 1, 450000.00, 21, '1f4fcd484e734cbb63c2cc23e219aed3.jpg'),
(20, 'PRD020', '8996001600269', 'Webcam', 4, 700000.00, 5, '39aff4270b59abf2885c1b5de14a406a.jpg'),
(21, 'PRD021', '(90)MD123782000200043', 'Ram 16gb', 7, 1000000.00, 10, 'fd508092b18e63ab3822ae70a65e1c86.jpg');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_transaksi`
--

CREATE TABLE `tbl_transaksi` (
  `id_transaksi` int(11) NOT NULL,
  `tanggal` date DEFAULT NULL,
  `id_pelanggan` int(11) DEFAULT NULL,
  `total_belanja` decimal(12,2) DEFAULT NULL,
  `metode_bayar` varchar(10) NOT NULL DEFAULT 'tunai',
  `subtotal` decimal(12,2) DEFAULT NULL,
  `diskon_persen` decimal(5,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(12,2) NOT NULL DEFAULT 0.00,
  `id_member` int(11) DEFAULT NULL,
  `poin_didapat` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_transaksi`
--

INSERT INTO `tbl_transaksi` (`id_transaksi`, `tanggal`, `id_pelanggan`, `total_belanja`, `metode_bayar`, `subtotal`, `diskon_persen`, `diskon`, `id_member`, `poin_didapat`) VALUES
(1, '2026-10-01', 1, 250000.00, 'tunai', 250000.00, 0.00, 0.00, NULL, 0),
(2, '2026-10-02', 2, 350000.00, 'tunai', 350000.00, 0.00, 0.00, NULL, 0),
(3, '2026-10-03', 1, 150000.00, 'tunai', 150000.00, 0.00, 0.00, NULL, 0),
(4, '2026-10-04', 3, 500000.00, 'tunai', 500000.00, 0.00, 0.00, NULL, 0),
(5, '2026-10-05', 4, 200000.00, 'tunai', 200000.00, 0.00, 0.00, NULL, 0),
(6, '2026-10-06', 5, 175000.00, 'tunai', 175000.00, 0.00, 0.00, NULL, 0),
(7, '2026-10-07', 6, 425000.00, 'tunai', 425000.00, 0.00, 0.00, NULL, 0),
(8, '2026-10-08', 7, 300000.00, 'tunai', 300000.00, 0.00, 0.00, NULL, 0),
(9, '2026-10-09', 8, 275000.00, 'tunai', 275000.00, 0.00, 0.00, NULL, 0),
(10, '2026-10-10', 9, 600000.00, 'tunai', 600000.00, 0.00, 0.00, NULL, 0),
(11, '2026-10-11', 10, 125000.00, 'tunai', 125000.00, 0.00, 0.00, NULL, 0),
(12, '2026-10-12', 2, 225000.00, 'tunai', 225000.00, 0.00, 0.00, NULL, 0),
(13, '2026-10-13', 3, 400000.00, 'tunai', 400000.00, 0.00, 0.00, NULL, 0),
(14, '2026-10-14', 4, 325000.00, 'tunai', 325000.00, 0.00, 0.00, NULL, 0),
(15, '2026-10-15', 5, 150000.00, 'tunai', 150000.00, 0.00, 0.00, NULL, 0),
(16, '2026-10-16', 6, 550000.00, 'tunai', 550000.00, 0.00, 0.00, NULL, 0),
(17, '2026-10-17', 7, 275000.00, 'tunai', 275000.00, 0.00, 0.00, NULL, 0),
(18, '2026-10-18', 8, 350000.00, 'tunai', 350000.00, 0.00, 0.00, NULL, 0),
(19, '2026-10-19', 9, 450000.00, 'tunai', 450000.00, 0.00, 0.00, NULL, 0),
(20, '2026-10-20', 10, 200000.00, 'tunai', 200000.00, 0.00, 0.00, NULL, 0),
(22, '2026-11-09', 1, 300000.00, 'tunai', 300000.00, 0.00, 0.00, NULL, 0),
(23, '2026-11-09', 4, 4000000.00, 'tunai', 4000000.00, 0.00, 0.00, NULL, 0),
(24, '2026-11-11', 11, 2700000.00, 'tunai', 2700000.00, 0.00, 0.00, NULL, 0),
(25, '2026-11-11', 15, 5250000.00, 'tunai', 5250000.00, 0.00, 0.00, NULL, 0),
(26, '2026-11-11', 15, 275000.00, 'tunai', 275000.00, 0.00, 0.00, NULL, 0),
(27, '2026-11-11', 15, 1950000.00, 'tunai', 1950000.00, 0.00, 0.00, NULL, 0),
(28, '2026-11-11', 15, 775000.00, 'tunai', 775000.00, 0.00, 0.00, NULL, 0),
(29, '2026-11-11', 15, 7525000.00, 'tunai', 7525000.00, 0.00, 0.00, NULL, 0),
(30, '2026-11-11', 16, 275000.00, 'tunai', 275000.00, 0.00, 0.00, NULL, 0),
(31, '2026-11-11', 1, 275000.00, 'tunai', 275000.00, 0.00, 0.00, NULL, 0),
(32, '2026-11-11', 15, 275000.00, 'tunai', 275000.00, 0.00, 0.00, NULL, 0),
(33, '2026-11-11', 15, 275000.00, 'tunai', 275000.00, 0.00, 0.00, NULL, 0),
(34, '2026-11-11', 16, 700000.00, 'tunai', 700000.00, 0.00, 0.00, NULL, 0),
(35, '2026-11-11', 11, 275000.00, 'tunai', 275000.00, 0.00, 0.00, NULL, 0),
(36, '2026-11-11', 17, 1225000.00, 'tunai', 1225000.00, 0.00, 0.00, NULL, 0),
(37, '2026-11-11', 11, 275000.00, 'tunai', 275000.00, 0.00, 0.00, NULL, 0),
(38, '2026-11-13', 16, 1375000.00, 'tunai', 1375000.00, 0.00, 0.00, NULL, 0),
(39, '2026-11-13', 17, 3100000.00, 'tunai', 3100000.00, 0.00, 0.00, NULL, 0),
(40, '2026-11-23', 3, 4150000.00, 'tunai', 4150000.00, 0.00, 0.00, NULL, 0),
(41, '2026-11-23', 17, 1020000.00, 'tunai', 1200000.00, 15.00, 180000.00, 2, 0);

--
-- Trigger `tbl_transaksi`
--
DELIMITER $$
CREATE TRIGGER `before_insert_transaksi` BEFORE INSERT ON `tbl_transaksi` FOR EACH ROW BEGIN
    IF NEW.total_belanja < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Total belanja tidak boleh negatif';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_user`
--

CREATE TABLE `tbl_user` (
  `id_user` int(11) NOT NULL,
  `username` varchar(30) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','petugas','pelanggan') NOT NULL DEFAULT 'pelanggan',
  `id_pelanggan` int(11) DEFAULT NULL,
  `dibuat` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_user`
--

INSERT INTO `tbl_user` (`id_user`, `username`, `password_hash`, `nama_lengkap`, `role`, `id_pelanggan`, `dibuat`) VALUES
(1, 'admin', '$2y$10$rQP/yBsdJSs/N5GQGlYDs.Cb8SMo9xyZjRl2ozcH8ceq2..tqy8HO', 'Administrator', 'admin', NULL, '2026-11-11 01:07:21'),
(2, 'Danara', '$2y$10$Z9KXhqK.Mgtbk/GuHO4RGudUzn8wyu1EdWn1JlPs63W/O0WEeXJ72', 'Amfidza neo danara', 'petugas', 16, '2026-11-11 01:15:06'),
(3, 'Amfidza', '$2y$10$7oYqaEjMiOBaxkV2CmXbUOmHnVH9hIB.BA9yqiSjh7ijJ3.lDAlWq', 'Amfidza', 'pelanggan', 17, '2026-11-11 01:36:24');

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_laporan_penjualan`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_laporan_penjualan` (
`id_transaksi` int(11)
,`tanggal` date
,`nama_pelanggan` varchar(100)
,`kode_produk` varchar(20)
,`nama_produk` varchar(100)
,`nama_kategori` varchar(100)
,`jumlah` int(11)
,`harga` decimal(12,2)
,`subtotal` decimal(22,2)
,`total_belanja` decimal(12,2)
,`diskon_persen` decimal(5,2)
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_pelanggan`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_pelanggan` (
`id_pelanggan` int(11)
,`nama_pelanggan` varchar(100)
,`kota` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_total_belanja`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_total_belanja` (
`id_pelanggan` int(11)
,`nama_pelanggan` varchar(100)
,`total_transaksi` bigint(21)
,`total_belanja` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `view_transaksi_pelanggan`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `view_transaksi_pelanggan` (
`id_transaksi` int(11)
,`tanggal` date
,`nama_pelanggan` varchar(100)
,`kota` varchar(50)
,`total_belanja` decimal(12,2)
);

-- --------------------------------------------------------

--
-- Struktur untuk view `view_laporan_penjualan`
--
DROP TABLE IF EXISTS `view_laporan_penjualan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_laporan_penjualan`  AS SELECT `t`.`id_transaksi` AS `id_transaksi`, `t`.`tanggal` AS `tanggal`, `p`.`nama_pelanggan` AS `nama_pelanggan`, `pr`.`kode_produk` AS `kode_produk`, `pr`.`nama_produk` AS `nama_produk`, `k`.`nama_kategori` AS `nama_kategori`, `d`.`jumlah` AS `jumlah`, `d`.`harga` AS `harga`, `d`.`jumlah`* `d`.`harga` AS `subtotal`, `t`.`total_belanja` AS `total_belanja`, `t`.`diskon_persen` AS `diskon_persen` FROM ((((`tbl_transaksi` `t` join `tbl_pelanggan` `p` on(`t`.`id_pelanggan` = `p`.`id_pelanggan`)) join `tbl_detail_transaksi` `d` on(`t`.`id_transaksi` = `d`.`id_transaksi`)) join `tbl_produk` `pr` on(`d`.`id_produk` = `pr`.`id_produk`)) join `tbl_kategori` `k` on(`pr`.`id_kategori` = `k`.`id_kategori`)) ;

-- --------------------------------------------------------

--
-- Struktur untuk view `view_pelanggan`
--
DROP TABLE IF EXISTS `view_pelanggan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_pelanggan`  AS SELECT `tbl_pelanggan`.`id_pelanggan` AS `id_pelanggan`, `tbl_pelanggan`.`nama_pelanggan` AS `nama_pelanggan`, `tbl_pelanggan`.`kota` AS `kota` FROM `tbl_pelanggan` ;

-- --------------------------------------------------------

--
-- Struktur untuk view `view_total_belanja`
--
DROP TABLE IF EXISTS `view_total_belanja`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_total_belanja`  AS SELECT `p`.`id_pelanggan` AS `id_pelanggan`, `p`.`nama_pelanggan` AS `nama_pelanggan`, count(`t`.`id_transaksi`) AS `total_transaksi`, coalesce(sum(`t`.`total_belanja`),0) AS `total_belanja` FROM (`tbl_pelanggan` `p` left join `tbl_transaksi` `t` on(`p`.`id_pelanggan` = `t`.`id_pelanggan`)) GROUP BY `p`.`id_pelanggan`, `p`.`nama_pelanggan` ;

-- --------------------------------------------------------

--
-- Struktur untuk view `view_transaksi_pelanggan`
--
DROP TABLE IF EXISTS `view_transaksi_pelanggan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_transaksi_pelanggan`  AS SELECT `t`.`id_transaksi` AS `id_transaksi`, `t`.`tanggal` AS `tanggal`, `p`.`nama_pelanggan` AS `nama_pelanggan`, `p`.`kota` AS `kota`, `t`.`total_belanja` AS `total_belanja` FROM (`tbl_transaksi` `t` join `tbl_pelanggan` `p` on(`t`.`id_pelanggan` = `p`.`id_pelanggan`)) ;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `log_pelanggan`
--
ALTER TABLE `log_pelanggan`
  ADD PRIMARY KEY (`id_log`);

--
-- Indeks untuk tabel `log_perubahan_pelanggan`
--
ALTER TABLE `log_perubahan_pelanggan`
  ADD PRIMARY KEY (`id_log`);

--
-- Indeks untuk tabel `member`
--
ALTER TABLE `member`
  ADD PRIMARY KEY (`id_member`),
  ADD UNIQUE KEY `no_member` (`no_member`),
  ADD UNIQUE KEY `id_pelanggan` (`id_pelanggan`),
  ADD UNIQUE KEY `rfid_uid` (`rfid_uid`);

--
-- Indeks untuk tabel `tbl_cabang`
--
ALTER TABLE `tbl_cabang`
  ADD PRIMARY KEY (`id_cabang`);

--
-- Indeks untuk tabel `tbl_detail_transaksi`
--
ALTER TABLE `tbl_detail_transaksi`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_transaksi` (`id_transaksi`),
  ADD KEY `fk_detail_produk` (`id_produk`);

--
-- Indeks untuk tabel `tbl_kategori`
--
ALTER TABLE `tbl_kategori`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indeks untuk tabel `tbl_log`
--
ALTER TABLE `tbl_log`
  ADD PRIMARY KEY (`id_log`);

--
-- Indeks untuk tabel `tbl_pelanggan`
--
ALTER TABLE `tbl_pelanggan`
  ADD PRIMARY KEY (`id_pelanggan`);

--
-- Indeks untuk tabel `tbl_produk`
--
ALTER TABLE `tbl_produk`
  ADD PRIMARY KEY (`id_produk`),
  ADD UNIQUE KEY `kode_produk` (`kode_produk`),
  ADD UNIQUE KEY `uq_produk_barcode` (`barcode`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indeks untuk tabel `tbl_transaksi`
--
ALTER TABLE `tbl_transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD KEY `id_pelanggan` (`id_pelanggan`),
  ADD KEY `fk_transaksi_member` (`id_member`);

--
-- Indeks untuk tabel `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `id_pelanggan` (`id_pelanggan`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `log_pelanggan`
--
ALTER TABLE `log_pelanggan`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `log_perubahan_pelanggan`
--
ALTER TABLE `log_perubahan_pelanggan`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `member`
--
ALTER TABLE `member`
  MODIFY `id_member` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `tbl_cabang`
--
ALTER TABLE `tbl_cabang`
  MODIFY `id_cabang` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tbl_detail_transaksi`
--
ALTER TABLE `tbl_detail_transaksi`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT untuk tabel `tbl_kategori`
--
ALTER TABLE `tbl_kategori`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `tbl_log`
--
ALTER TABLE `tbl_log`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT untuk tabel `tbl_pelanggan`
--
ALTER TABLE `tbl_pelanggan`
  MODIFY `id_pelanggan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `tbl_produk`
--
ALTER TABLE `tbl_produk`
  MODIFY `id_produk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `tbl_transaksi`
--
ALTER TABLE `tbl_transaksi`
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT untuk tabel `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `member`
--
ALTER TABLE `member`
  ADD CONSTRAINT `fk_member_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `tbl_pelanggan` (`id_pelanggan`);

--
-- Ketidakleluasaan untuk tabel `tbl_detail_transaksi`
--
ALTER TABLE `tbl_detail_transaksi`
  ADD CONSTRAINT `fk_detail_produk` FOREIGN KEY (`id_produk`) REFERENCES `tbl_produk` (`id_produk`),
  ADD CONSTRAINT `tbl_detail_transaksi_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `tbl_transaksi` (`id_transaksi`);

--
-- Ketidakleluasaan untuk tabel `tbl_produk`
--
ALTER TABLE `tbl_produk`
  ADD CONSTRAINT `tbl_produk_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `tbl_kategori` (`id_kategori`);

--
-- Ketidakleluasaan untuk tabel `tbl_transaksi`
--
ALTER TABLE `tbl_transaksi`
  ADD CONSTRAINT `fk_transaksi_member` FOREIGN KEY (`id_member`) REFERENCES `member` (`id_member`),
  ADD CONSTRAINT `tbl_transaksi_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `tbl_pelanggan` (`id_pelanggan`);

--
-- Ketidakleluasaan untuk tabel `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD CONSTRAINT `fk_user_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `tbl_pelanggan` (`id_pelanggan`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

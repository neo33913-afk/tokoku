-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 26 Nov 2026 pada 07.26
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
(76, 41, 16, 'Router WiFi', 1, 325000.00),
(77, 42, 11, 'Monitor LED', 1, 500000.00),
(78, 42, 12, 'Mouse Gaming', 1, 275000.00),
(79, 43, 11, 'Monitor LED', 1, 500000.00),
(80, 43, 12, 'Mouse Gaming', 1, 275000.00),
(81, 43, 16, 'Router WiFi', 1, 325000.00),
(82, 43, 21, 'Ram 16gb', 1, 1000000.00),
(83, 44, 10, 'Mikrofon', 1, 275000.00),
(84, 44, 11, 'Monitor LED', 1, 500000.00),
(85, 45, 10, 'Mikrofon', 1, 275000.00),
(86, 46, 10, 'Mikrofon', 1, 275000.00),
(87, 46, 12, 'Mouse Gaming', 1, 275000.00),
(88, 46, 13, 'Mouse Wireless', 1, 175000.00),
(89, 47, 12, 'Mouse Gaming', 1, 275000.00),
(90, 48, 20, 'Webcam', 1, 700000.00),
(92, 50, 12, 'Mouse Gaming', 1, 275000.00),
(93, 51, 12, 'Mouse Gaming', 1, 275000.00),
(94, 52, 12, 'Mouse Gaming', 1, 275000.00),
(95, 53, 12, 'Mouse Gaming', 1, 275000.00),
(96, 53, 13, 'Mouse Wireless', 1, 175000.00),
(97, 53, 14, 'Printer', 1, 600000.00),
(98, 54, 13, 'Mouse Wireless', 1, 175000.00),
(99, 55, 14, 'Printer', 1, 600000.00),
(100, 56, 12, 'Mouse Gaming', 1, 275000.00),
(101, 57, 12, 'Mouse Gaming', 1, 275000.00),
(102, 58, 12, 'Mouse Gaming', 1, 275000.00),
(103, 59, 12, 'Mouse Gaming', 1, 275000.00),
(104, 59, 21, 'Ram 16gb', 1, 1000000.00),
(105, 60, 12, 'Mouse Gaming', 1, 275000.00),
(106, 61, 12, 'Mouse Gaming', 1, 275000.00),
(107, 61, 13, 'Mouse Wireless', 1, 175000.00),
(108, 61, 18, 'SSD 256GB', 1, 425000.00),
(109, 61, 21, 'Ram 16gb', 1, 1000000.00),
(110, 62, 12, 'Mouse Gaming', 1, 275000.00),
(111, 62, 18, 'SSD 256GB', 1, 425000.00),
(112, 62, 19, 'USB', 1, 450000.00),
(113, 62, 20, 'Webcam', 1, 700000.00),
(114, 62, 21, 'Ram 16gb', 1, 1000000.00);

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
(106, 'UPDATE PRODUK', 'Mengubah produk: Mouse Gaming', '2026-11-23 04:48:04'),
(107, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-25 00:49:42'),
(108, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-25 01:06:21'),
(109, 'LOGIN', 'User admin (admin) masuk ke sistem', '2026-11-25 01:06:29'),
(110, 'LOGIN', 'User admin (admin) masuk ke sistem', '2026-11-25 01:13:39'),
(111, 'LOGOUT', 'User admin keluar dari sistem', '2026-11-25 02:33:45'),
(112, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-25 02:35:17'),
(113, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-25 02:35:20'),
(114, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-25 02:39:12'),
(115, 'INSERT TRANSAKSI', 'Transaksi #42 oleh Amfidza, Monitor LED x1, Mouse Gaming x1, total Rp 658.750 (diskon member Rp 116.250) (oleh petugas Danara)', '2026-11-25 02:39:28'),
(116, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-25 02:42:10'),
(117, 'LOGIN', 'User admin (admin) masuk ke sistem', '2026-11-25 02:42:17'),
(118, 'LOGOUT', 'User admin keluar dari sistem', '2026-11-25 03:36:55'),
(119, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-25 03:37:06'),
(120, 'INSERT TRANSAKSI', 'Transaksi #43 oleh Amfidza, Monitor LED x1, Mouse Gaming x1, Router WiFi x1, Ram 16gb x1, total Rp 1.785.000 (diskon member Rp 315.000) (oleh petugas Danara)', '2026-11-25 03:39:28'),
(121, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-25 03:40:46'),
(122, 'LOGIN', 'User admin (admin) masuk ke sistem', '2026-11-25 03:40:54'),
(123, 'LOGOUT', 'User admin keluar dari sistem', '2026-11-25 03:41:02'),
(124, 'LOGIN', 'User Danara (petugas) masuk ke sistem', '2026-11-25 03:41:11'),
(125, 'LOGOUT', 'User Danara keluar dari sistem', '2026-11-25 03:43:59'),
(126, 'LOGIN', 'User Amfidza (pelanggan) masuk ke sistem', '2026-11-25 03:44:24'),
(127, 'INSERT TRANSAKSI', 'Transaksi #44 oleh Amfidza, Mikrofon x1, Monitor LED x1, total Rp 658.750 (diskon member Rp 116.250) (oleh pelanggan Amfidza)', '2026-11-25 03:44:34'),
(128, 'LOGIN RFID', 'User Amfidza (pelanggan) masuk dengan kartu RFID', '2026-11-25 04:01:58'),
(129, 'LOGOUT', 'User Amfidza keluar dari sistem', '2026-11-25 04:02:06'),
(130, 'LOGIN RFID', 'User Amfidza (pelanggan) masuk dengan kartu RFID', '2026-11-25 04:02:16'),
(131, 'LOGOUT', 'User Amfidza keluar dari sistem', '2026-11-25 04:49:52'),
(132, 'LOGIN RFID', 'User Amfidza (pelanggan) masuk dengan kartu RFID', '2026-11-25 04:50:42'),
(133, 'LOGOUT', 'User Amfidza keluar dari sistem', '2026-11-25 04:50:49'),
(134, 'LOGIN RFID', 'User Danara (petugas) masuk dengan kartu RFID', '2026-11-25 04:50:56'),
(135, 'LOGIN', 'User Amfidza (pelanggan) masuk ke sistem', '2026-11-25 05:53:02'),
(136, 'INSERT TRANSAKSI', 'Transaksi #45 oleh Amfidza, Mikrofon x1, total Rp 233.750 (diskon member Rp 41.250) (oleh pelanggan Amfidza)', '2026-11-25 06:25:58'),
(137, 'INSERT TRANSAKSI', 'Transaksi #46 oleh Amfidza, Mikrofon x1, Mouse Gaming x1, Mouse Wireless x1, total Rp 616.250 (diskon member Rp 108.750) (oleh pelanggan Amfidza)', '2026-11-25 06:40:10'),
(138, 'LOGOUT', 'User Amfidza keluar dari sistem', '2026-11-25 06:41:10'),
(139, 'LOGIN', 'User admin (admin) masuk ke sistem', '2026-11-25 06:41:22'),
(140, 'LOGIN', 'User Amfidza (pelanggan) masuk ke sistem', '2026-11-26 01:18:54'),
(141, 'INSERT TRANSAKSI', 'Transaksi #47 oleh Amfidza, Mouse Gaming x1, total Rp 233.750 (diskon member Rp 41.250) (oleh pelanggan Amfidza)', '2026-11-26 01:19:00'),
(142, 'INSERT TRANSAKSI', 'Transaksi #48 oleh Amfidza, Webcam x1, total Rp 595.000 (diskon member Rp 105.000) (oleh pelanggan Amfidza)', '2026-11-26 03:18:48'),
(143, 'INSERT TRANSAKSI', 'Transaksi #50 oleh Amfidza, Mouse Gaming x1, total Rp 233.750 (diskon member Rp 41.250) (oleh pelanggan Amfidza)', '2026-11-26 03:27:07'),
(144, 'INSERT TRANSAKSI', 'Transaksi #51 oleh Amfidza, Mouse Gaming x1, total Rp 233.750 (diskon member Rp 41.250) (oleh pelanggan Amfidza)', '2026-11-26 03:34:56'),
(145, 'PEMBAYARAN QRIS', 'Transaksi #51 lunas via QRIS Duitku (terdeteksi otomatis)', '2026-11-26 03:35:27'),
(146, 'INSERT TRANSAKSI', 'Transaksi #52 oleh Amfidza, Mouse Gaming x1, total Rp 233.750 (diskon member Rp 41.250) (oleh pelanggan Amfidza)', '2026-11-26 03:38:15'),
(147, 'PEMBAYARAN QRIS', 'Transaksi #52 lunas via QRIS Duitku (terdeteksi otomatis)', '2026-11-26 03:38:35'),
(148, 'INSERT TRANSAKSI', 'Transaksi #53 oleh Amfidza, Mouse Gaming x1, Mouse Wireless x1, Printer x1, total Rp 892.500 (diskon member Rp 157.500) (oleh pelanggan Amfidza)', '2026-11-26 03:40:26'),
(149, 'PEMBAYARAN QRIS', 'Transaksi #50 dibatalkan (QRIS Duitku kedaluwarsa/gagal), stok dikembalikan', '2026-11-26 03:44:29'),
(150, 'LOGIN', 'User Amfidza (pelanggan) masuk ke sistem', '2026-11-26 03:45:02'),
(151, 'INSERT TRANSAKSI', 'Transaksi #54 oleh Amfidza, Mouse Wireless x1, total Rp 148.750 (diskon member Rp 26.250) (oleh pelanggan Amfidza)', '2026-11-26 03:45:16'),
(152, 'INSERT TRANSAKSI', 'Transaksi #55 oleh Amfidza, Printer x1, total Rp 510.000 (diskon member Rp 90.000) (oleh pelanggan Amfidza)', '2026-11-26 03:50:59'),
(153, 'INSERT TRANSAKSI', 'Transaksi #56 oleh Amfidza, Mouse Gaming x1, total Rp 233.750 (diskon member Rp 41.250) (oleh pelanggan Amfidza)', '2026-11-26 03:53:17'),
(154, 'INSERT TRANSAKSI', 'Transaksi #57 oleh Amfidza, Mouse Gaming x1, total Rp 233.750 (diskon member Rp 41.250) (oleh pelanggan Amfidza)', '2026-11-26 03:58:29'),
(155, 'PEMBAYARAN QRIS', 'Transaksi #57 lunas via QRIS Midtrans (terdeteksi otomatis)', '2026-11-26 04:01:46'),
(156, 'INSERT TRANSAKSI', 'Transaksi #58 oleh Amfidza, Mouse Gaming x1, total Rp 233.750 (diskon member Rp 41.250) (oleh pelanggan Amfidza)', '2026-11-26 04:02:23'),
(157, 'PEMBAYARAN QRIS', 'Transaksi #58 lunas via QRIS Midtrans (terdeteksi otomatis)', '2026-11-26 04:02:44'),
(158, 'INSERT TRANSAKSI', 'Transaksi #59 oleh Amfidza, Mouse Gaming x1, Ram 16gb x1, total Rp 1.083.750 (diskon member Rp 191.250) (oleh pelanggan Amfidza)', '2026-11-26 04:44:08'),
(159, 'PEMBAYARAN VA', 'Transaksi #59 lunas via VA Duitku (terdeteksi otomatis)', '2026-11-26 04:46:49'),
(160, 'INSERT TRANSAKSI', 'Transaksi #60 oleh Amfidza, Mouse Gaming x1, total Rp 233.750 (diskon member Rp 41.250) (oleh pelanggan Amfidza)', '2026-11-26 05:59:28'),
(161, 'PEMBAYARAN VA', 'Transaksi #60 lunas via VA Duitku (terdeteksi otomatis)', '2026-11-26 05:59:49'),
(162, 'LOGOUT', 'User Amfidza keluar dari sistem', '2026-11-26 06:16:03'),
(163, 'LOGIN', 'User Amfidza (pelanggan) masuk ke sistem', '2026-11-26 06:16:31'),
(164, 'INSERT TRANSAKSI', 'Transaksi #61 oleh Amfidza, Mouse Gaming x1, Mouse Wireless x1, SSD 256GB x1, Ram 16gb x1, total Rp 1.593.750 (diskon member Rp 281.250) (oleh pelanggan Amfidza)', '2026-11-26 06:21:53'),
(165, 'INSERT TRANSAKSI', 'Transaksi #62 oleh Amfidza, Mouse Gaming x1, SSD 256GB x1, USB x1, Webcam x1, Ram 16gb x1, total Rp 2.422.500 (diskon member Rp 427.500) (oleh pelanggan Amfidza)', '2026-11-26 06:23:44'),
(166, 'PEMBAYARAN QRIS', 'Transaksi #62 lunas via QRIS Duitku (terdeteksi otomatis)', '2026-11-26 06:23:57');

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
(10, 'PRD010', NULL, 'Mikrofon', 4, 275000.00, 22, '8c9c1e47bcffe5c11c63995128fc3fc1.jpg'),
(11, 'PRD011', NULL, 'Monitor LED', 6, 500000.00, 0, 'b36b2dc4ec7985e2a85eb5fa88a39b5f.jpg'),
(12, 'PRD012', '8935001859918', 'Mouse Gaming', 3, 275000.00, 306, '08412d76827ee093d6fe93af29ae9638.jpg'),
(13, 'PRD013', NULL, 'Mouse Wireless', 3, 175000.00, 21, '414658d2ac8a58171710b22b14f6b08a.jpg'),
(14, 'PRD014', NULL, 'Printer', 6, 600000.00, 15, 'c1640597a9e38277dfd9a9fe9c43540a.jpg'),
(15, 'PRD015', NULL, 'RAM 8GB', 7, 300000.00, 7, '9a23fd529df75116901e18a5f4734a15.jpg'),
(16, 'PRD016', NULL, 'Router WiFi', 5, 325000.00, 16, '8d215cc2203af1751b2e289b8eb021cc.jpg'),
(17, 'PRD017', NULL, 'Speaker', 4, 225000.00, 0, '1a3def833475d818a61f35c7f2369719.jpg'),
(18, 'PRD018', '8997240102187', 'SSD 256GB', 1, 425000.00, 26, 'd000d5153824e01e4fd0918231c74938.jpg'),
(19, 'PRD019', '8991389241646', 'USB', 1, 450000.00, 20, '1f4fcd484e734cbb63c2cc23e219aed3.jpg'),
(20, 'PRD020', '8996001600269', 'Webcam', 4, 700000.00, 3, '39aff4270b59abf2885c1b5de14a406a.jpg'),
(21, 'PRD021', '(90)MD123782000200043', 'Ram 16gb', 7, 1000000.00, 6, 'fd508092b18e63ab3822ae70a65e1c86.jpg');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_transaksi`
--

CREATE TABLE `tbl_transaksi` (
  `id_transaksi` int(11) NOT NULL,
  `tanggal` date DEFAULT NULL,
  `id_pelanggan` int(11) DEFAULT NULL,
  `total_belanja` decimal(12,2) DEFAULT NULL,
  `metode_bayar` varchar(10) DEFAULT 'tunai',
  `gateway` varchar(20) DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT NULL,
  `diskon_persen` decimal(5,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(12,2) NOT NULL DEFAULT 0.00,
  `id_member` int(11) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `poin_didapat` int(11) DEFAULT 0,
  `status_bayar` enum('lunas','menunggu','batal') NOT NULL DEFAULT 'lunas',
  `order_id_gateway` varchar(64) DEFAULT NULL,
  `referensi_gateway` varchar(500) DEFAULT NULL,
  `qr_data` text DEFAULT NULL,
  `kadaluarsa` datetime DEFAULT NULL,
  `bank_va` varchar(20) DEFAULT NULL,
  `no_va` varchar(64) DEFAULT NULL,
  `biller_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_transaksi`
--

INSERT INTO `tbl_transaksi` (`id_transaksi`, `tanggal`, `id_pelanggan`, `total_belanja`, `metode_bayar`, `gateway`, `subtotal`, `diskon_persen`, `diskon`, `id_member`, `id_user`, `poin_didapat`, `status_bayar`, `order_id_gateway`, `referensi_gateway`, `qr_data`, `kadaluarsa`, `bank_va`, `no_va`, `biller_code`) VALUES
(1, '2026-10-01', 1, 250000.00, 'tunai', NULL, 250000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, '2026-10-02', 2, 350000.00, 'tunai', NULL, 350000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, '2026-10-03', 1, 150000.00, 'tunai', NULL, 150000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, '2026-10-04', 3, 500000.00, 'tunai', NULL, 500000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, '2026-10-05', 4, 200000.00, 'tunai', NULL, 200000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, '2026-10-06', 5, 175000.00, 'tunai', NULL, 175000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, '2026-10-07', 6, 425000.00, 'tunai', NULL, 425000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, '2026-10-08', 7, 300000.00, 'tunai', NULL, 300000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, '2026-10-09', 8, 275000.00, 'tunai', NULL, 275000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, '2026-10-10', 9, 600000.00, 'tunai', NULL, 600000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, '2026-10-11', 10, 125000.00, 'tunai', NULL, 125000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, '2026-10-12', 2, 225000.00, 'tunai', NULL, 225000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(13, '2026-10-13', 3, 400000.00, 'tunai', NULL, 400000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, '2026-10-14', 4, 325000.00, 'tunai', NULL, 325000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, '2026-10-15', 5, 150000.00, 'tunai', NULL, 150000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, '2026-10-16', 6, 550000.00, 'tunai', NULL, 550000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, '2026-10-17', 7, 275000.00, 'tunai', NULL, 275000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, '2026-10-18', 8, 350000.00, 'tunai', NULL, 350000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, '2026-10-19', 9, 450000.00, 'tunai', NULL, 450000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, '2026-10-20', 10, 200000.00, 'tunai', NULL, 200000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, '2026-11-09', 1, 300000.00, 'tunai', NULL, 300000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, '2026-11-09', 4, 4000000.00, 'tunai', NULL, 4000000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, '2026-11-11', 11, 2700000.00, 'tunai', NULL, 2700000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, '2026-11-11', 15, 5250000.00, 'tunai', NULL, 5250000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(26, '2026-11-11', 15, 275000.00, 'tunai', NULL, 275000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, '2026-11-11', 15, 1950000.00, 'tunai', NULL, 1950000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, '2026-11-11', 15, 775000.00, 'tunai', NULL, 775000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, '2026-11-11', 15, 7525000.00, 'tunai', NULL, 7525000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, '2026-11-11', 16, 275000.00, 'tunai', NULL, 275000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, '2026-11-11', 1, 275000.00, 'tunai', NULL, 275000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(32, '2026-11-11', 15, 275000.00, 'tunai', NULL, 275000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(33, '2026-11-11', 15, 275000.00, 'tunai', NULL, 275000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(34, '2026-11-11', 16, 700000.00, 'tunai', NULL, 700000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(35, '2026-11-11', 11, 275000.00, 'tunai', NULL, 275000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(36, '2026-11-11', 17, 1225000.00, 'tunai', NULL, 1225000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, '2026-11-11', 11, 275000.00, 'tunai', NULL, 275000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, '2026-11-13', 16, 1375000.00, 'tunai', NULL, 1375000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(39, '2026-11-13', 17, 3100000.00, 'tunai', NULL, 3100000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(40, '2026-11-23', 3, 4150000.00, 'tunai', NULL, 4150000.00, 0.00, 0.00, NULL, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, '2026-11-23', 17, 1020000.00, 'tunai', NULL, 1200000.00, 15.00, 180000.00, 2, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(42, '2026-11-25', 17, 658750.00, 'qris', NULL, 775000.00, 15.00, 116250.00, 2, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, '2026-11-25', 17, 1785000.00, 'qris', NULL, 2100000.00, 15.00, 315000.00, 2, 2, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(44, '2026-11-25', 17, 658750.00, 'tunai', NULL, 775000.00, 15.00, 116250.00, 2, NULL, 0, 'lunas', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(45, '2026-11-25', 17, 233750.00, 'qris', 'midtrans', 275000.00, 15.00, 41250.00, 2, NULL, 0, 'menunggu', 'TKU-45-1795587958', NULL, '00020101021226620014COM.GO-JEK.WWW011993600914354990656850210M5499065680303UKE51440014ID.CO.QRIS.WWW0215AID4272322522860303UKE52048911530336054062337505802ID5906tokoku6008PASURUAN61056716162395028A1202611250625588TTMR6NMHHID0703A016304EB89', '2026-11-25 13:40:58', NULL, NULL, NULL),
(46, '2026-11-25', 17, 616250.00, 'qris', 'midtrans', 725000.00, 15.00, 108750.00, 2, NULL, 0, 'menunggu', 'TKU-46-1795588810', NULL, '00020101021226620014COM.GO-JEK.WWW011993600914354990656850210M5499065680303UKE51440014ID.CO.QRIS.WWW0215AID4272322522860303UKE52048911530336054066162505802ID5906tokoku6008PASURUAN61056716162395028A120261125064011IcR4nTy31MID0703A016304C1C6', '2026-11-25 13:55:10', NULL, NULL, NULL),
(47, '2026-11-26', 17, 233750.00, 'qris', 'midtrans', 275000.00, 15.00, 41250.00, 2, NULL, 0, 'menunggu', 'TKU-47-1795655940', NULL, '00020101021226620014COM.GO-JEK.WWW011993600914354990656850210M5499065680303UKE51440014ID.CO.QRIS.WWW0215AID4272322522860303UKE52048911530336054062337505802ID5906tokoku6008PASURUAN61056716162395028A120261126011901eTnlSiEluZID0703A0163042DD2', '2026-11-26 08:34:00', NULL, NULL, NULL),
(48, '2026-11-26', 17, 595000.00, 'qris', 'midtrans', 700000.00, 15.00, 105000.00, 2, NULL, 0, 'menunggu', 'TKU-48-1795663127', NULL, '00020101021226620014COM.GO-JEK.WWW011993600914354990656850210M5499065680303UKE51440014ID.CO.QRIS.WWW0215AID4272322522860303UKE52048911530336054065950005802ID5906tokoku6008PASURUAN61056716162395028A120261126031849zvb6S5C5AvID0703A016304624E', '2026-11-26 10:33:48', NULL, NULL, NULL),
(50, '2026-11-26', 17, 233750.00, 'qris', 'duitku', 275000.00, 15.00, 41250.00, 2, NULL, 0, 'batal', 'TKU-50-1795663621', NULL, '00020101021226610016ID.CO.SHOPEE.WWW01189360091800202688450208202688450303UKE5204798853033605409233750.005802ID5906Duitku6015KAB. BANGKA BAR610533315622205181367283285842940016304DC9F', '2026-11-26 10:42:07', NULL, NULL, NULL),
(51, '2026-11-26', 17, 233750.00, 'qris', 'duitku', 275000.00, 15.00, 41250.00, 2, NULL, 0, 'lunas', 'TKU-51-1795664094', 'DS38593261SV6I7VEGFQSOUM', '00020101021226610016ID.CO.SHOPEE.WWW01189360091800202688450208202688450303UKE5204798853033605409233750.005802ID5906Duitku6015KAB. BANGKA BAR610533315622205181563867558917178296304C1B9', '2026-11-26 10:49:56', NULL, NULL, NULL),
(52, '2026-11-26', 17, 233750.00, 'qris', 'duitku', 275000.00, 15.00, 41250.00, 2, NULL, 0, 'lunas', 'TKU-52-1795664293', 'DS3859326TX5WYNTQJE1X5MB', '00020101021226610016ID.CO.SHOPEE.WWW01189360091800202688450208202688450303UKE5204798853033605409233750.005802ID5906Duitku6015KAB. BANGKA BAR6105333156222051816383682399578050663044067', '2026-11-26 10:53:15', NULL, NULL, NULL),
(53, '2026-11-26', 17, 892500.00, 'qris', 'midtrans', 1050000.00, 15.00, 157500.00, 2, NULL, 0, 'menunggu', 'TKU-53-1795664426', NULL, '00020101021226620014COM.GO-JEK.WWW011993600914354990656850210M5499065680303UKE51440014ID.CO.QRIS.WWW0215AID4272322522860303UKE52048911530336054068925005802ID5906tokoku6008PASURUAN61056716162395028A1202611260340281BIx1pDwmJID0703A0163044188', '2026-11-26 10:55:26', NULL, NULL, NULL),
(54, '2026-11-26', 17, 148750.00, 'qris', 'midtrans', 175000.00, 15.00, 26250.00, 2, NULL, 0, 'menunggu', 'TKU-54-1795664713', NULL, '00020101021226620014COM.GO-JEK.WWW011993600914354990656850210M5499065680303UKE51440014ID.CO.QRIS.WWW0215AID4272322522860303UKE52048911530336054061487505802ID5906tokoku6008PASURUAN61056716162395028A120261126034518ICOJXZRtMqID0703A0163042896', '2026-11-26 11:00:16', NULL, NULL, NULL),
(55, '2026-11-26', 17, 510000.00, 'qris', 'midtrans', 600000.00, 15.00, 90000.00, 2, NULL, 0, 'menunggu', 'TKU-55-1795665059', 'https://api.sandbox.midtrans.com/v2/qris/f4fa7b1a-610c-4540-a0ae', '00020101021226620014COM.GO-JEK.WWW011993600914354990656850210M5499065680303UKE51440014ID.CO.QRIS.WWW0215AID4272322522860303UKE52048911530336054065100005802ID5906tokoku6008PASURUAN61056716162395028A120261126035101t04yAXeYwwID0703A0163048659', '2026-11-26 11:05:59', NULL, NULL, NULL),
(56, '2026-11-26', 17, 233750.00, 'qris', 'midtrans', 275000.00, 15.00, 41250.00, 2, NULL, 0, 'menunggu', 'TKU-56-1795665196', 'https://merchants-app.sbx.midtrans.com/v4/qris/gopay/A1202611260', '00020101021226620014COM.GO-JEK.WWW011993600914354990656850210M5499065680303UKE51440014ID.CO.QRIS.WWW0215AID4272322522860303UKE52048911530336054062337505802ID5906tokoku6008PASURUAN61056716162395028A120261126035319mWYoiPqtwFID0703A016304798D', '2026-11-26 11:08:17', NULL, NULL, NULL),
(57, '2026-11-26', 17, 233750.00, 'qris', 'midtrans', 275000.00, 15.00, 41250.00, 2, NULL, 0, 'lunas', 'TKU-57-1795665509', 'https://merchants-app.sbx.midtrans.com/v4/qris/gopay/A120261126035831Zr9Ek6VOayID/qr-code', '00020101021226620014COM.GO-JEK.WWW011993600914354990656850210M5499065680303UKE51440014ID.CO.QRIS.WWW0215AID4272322522860303UKE52048911530336054062337505802ID5906tokoku6008PASURUAN61056716162395028A120261126035831Zr9Ek6VOayID0703A016304688C', '2026-11-26 11:13:29', NULL, NULL, NULL),
(58, '2026-11-26', 17, 233750.00, 'qris', 'midtrans', 275000.00, 15.00, 41250.00, 2, NULL, 0, 'lunas', 'TKU-58-1795665741', 'https://merchants-app.sbx.midtrans.com/v4/qris/gopay/A120261126040225QYNQr0pARlID/qr-code', '00020101021226620014COM.GO-JEK.WWW011993600914354990656850210M5499065680303UKE51440014ID.CO.QRIS.WWW0215AID4272322522860303UKE52048911530336054062337505802ID5906tokoku6008PASURUAN61056716162395028A120261126040225QYNQr0pARlID0703A016304AD57', '2026-11-26 11:17:23', NULL, NULL, NULL),
(59, '2026-11-26', 17, 1083750.00, 'va', 'duitku', 1275000.00, 15.00, 191250.00, 2, NULL, 0, 'lunas', 'TKU-59-1795668247', 'DS3859326L02OEAEVHIPQP0V', NULL, '2026-11-26 11:59:07', 'bca', '7007014007882330', NULL),
(60, '2026-11-26', 17, 233750.00, 'va', 'duitku', 275000.00, 15.00, 41250.00, 2, NULL, 0, 'lunas', 'TKU-60-1795672766', 'DS3859326RYLHQODZ282H0MA', NULL, '2026-11-26 13:14:26', 'bca', '7007014004195008', NULL),
(61, '2026-11-26', 17, 1593750.00, 'qris', 'midtrans', 1875000.00, 15.00, 281250.00, 2, NULL, 0, 'menunggu', 'TKU-61-1795674113', 'https://merchants-app.sbx.midtrans.com/v4/qris/gopay/A120261126062153L9fq9AIKAtID/qr-code', '00020101021226620014COM.GO-JEK.WWW011993600914354990656850210M5499065680303UKE51440014ID.CO.QRIS.WWW0215AID4272322522860303UKE520489115303360540715937505802ID5906tokoku6008PASURUAN61056716162395028A120261126062153L9fq9AIKAtID0703A0163049126', '2026-11-26 13:36:53', NULL, NULL, NULL),
(62, '2026-11-26', 17, 2422500.00, 'qris', 'duitku', 2850000.00, 15.00, 427500.00, 2, NULL, 0, 'lunas', 'TKU-62-1795674220', 'DS3859326J2FAFD8B6NTYM2S', '00020101021226610016ID.CO.SHOPEE.WWW01189360091800202688450208202688450303UKE52047988530336054102422500.005802ID5906Duitku6015KAB. BANGKA BAR610533315622205181572448473627300446304D41F', '2026-11-26 13:38:40', NULL, NULL, NULL);

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
  ADD KEY `fk_transaksi_member` (`id_member`),
  ADD KEY `idx_transaksi_user` (`id_user`),
  ADD KEY `idx_transaksi_status` (`status_bayar`);

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
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT untuk tabel `tbl_kategori`
--
ALTER TABLE `tbl_kategori`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `tbl_log`
--
ALTER TABLE `tbl_log`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

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
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

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

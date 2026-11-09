-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 09 Nov 2026 pada 07.24
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
(1, 'INSERT PELANGGAN', 14, '2026-11-09 05:42:47');

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
(22, 23, 20, 'Webcam', 10, 400000.00);

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
(2, 'INSERT TRANSAKSI', 'Transaksi #23 oleh Rina Wijaya, produk Webcam, total Rp 4.000.000', '2026-11-09 06:19:35');

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
(14, 'Rina', 'Ponorogo');

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
  `nama_produk` varchar(100) NOT NULL,
  `id_kategori` int(11) DEFAULT NULL,
  `harga` decimal(12,2) NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_produk`
--

INSERT INTO `tbl_produk` (`id_produk`, `kode_produk`, `nama_produk`, `id_kategori`, `harga`, `stok`) VALUES
(1, 'PRD001', 'Cooling Pad', 2, 150000.00, 0),
(2, 'PRD002', 'Flashdisk 32GB', 1, 75000.00, 0),
(3, 'PRD003', 'Flashdisk 64GB', 1, 125000.00, 0),
(4, 'PRD004', 'Harddisk 1TB', 1, 550000.00, 0),
(5, 'PRD005', 'Headset', 4, 175000.00, 0),
(6, 'PRD006', 'Kabel HDMI', 2, 100000.00, 0),
(7, 'PRD007', 'Keyboard Gaming', 3, 200000.00, 0),
(8, 'PRD008', 'Keyboard Mechanical', 3, 250000.00, 0),
(9, 'PRD009', 'Laptop Stand', 2, 350000.00, 0),
(10, 'PRD010', 'Mikrofon', 4, 275000.00, 0),
(11, 'PRD011', 'Monitor LED', 6, 500000.00, 0),
(12, 'PRD012', 'Mouse Gaming', 3, 275000.00, 0),
(13, 'PRD013', 'Mouse Wireless', 3, 175000.00, 0),
(14, 'PRD014', 'Printer', 6, 600000.00, 0),
(15, 'PRD015', 'RAM 8GB', 7, 300000.00, 0),
(16, 'PRD016', 'Router WiFi', 5, 325000.00, 0),
(17, 'PRD017', 'Speaker', 4, 225000.00, 0),
(18, 'PRD018', 'SSD 256GB', 1, 425000.00, 0),
(19, 'PRD019', 'UPS', 7, 450000.00, 0),
(20, 'PRD020', 'Webcam', 4, 400000.00, 11);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_transaksi`
--

CREATE TABLE `tbl_transaksi` (
  `id_transaksi` int(11) NOT NULL,
  `tanggal` date DEFAULT NULL,
  `id_pelanggan` int(11) DEFAULT NULL,
  `total_belanja` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_transaksi`
--

INSERT INTO `tbl_transaksi` (`id_transaksi`, `tanggal`, `id_pelanggan`, `total_belanja`) VALUES
(1, '2026-10-01', 1, 250000.00),
(2, '2026-10-02', 2, 350000.00),
(3, '2026-10-03', 1, 150000.00),
(4, '2026-10-04', 3, 500000.00),
(5, '2026-10-05', 4, 200000.00),
(6, '2026-10-06', 5, 175000.00),
(7, '2026-10-07', 6, 425000.00),
(8, '2026-10-08', 7, 300000.00),
(9, '2026-10-09', 8, 275000.00),
(10, '2026-10-10', 9, 600000.00),
(11, '2026-10-11', 10, 125000.00),
(12, '2026-10-12', 2, 225000.00),
(13, '2026-10-13', 3, 400000.00),
(14, '2026-10-14', 4, 325000.00),
(15, '2026-10-15', 5, 150000.00),
(16, '2026-10-16', 6, 550000.00),
(17, '2026-10-17', 7, 275000.00),
(18, '2026-10-18', 8, 350000.00),
(19, '2026-10-19', 9, 450000.00),
(20, '2026-10-20', 10, 200000.00),
(21, '2026-11-01', 1, 50000.00),
(22, '2026-11-09', 1, 300000.00),
(23, '2026-11-09', 4, 4000000.00);

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

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_laporan_penjualan`  AS SELECT `t`.`id_transaksi` AS `id_transaksi`, `t`.`tanggal` AS `tanggal`, `p`.`nama_pelanggan` AS `nama_pelanggan`, `pr`.`kode_produk` AS `kode_produk`, `pr`.`nama_produk` AS `nama_produk`, `k`.`nama_kategori` AS `nama_kategori`, `d`.`jumlah` AS `jumlah`, `d`.`harga` AS `harga`, `d`.`jumlah`* `d`.`harga` AS `subtotal`, `t`.`total_belanja` AS `total_belanja` FROM ((((`tbl_transaksi` `t` join `tbl_pelanggan` `p` on(`t`.`id_pelanggan` = `p`.`id_pelanggan`)) join `tbl_detail_transaksi` `d` on(`t`.`id_transaksi` = `d`.`id_transaksi`)) join `tbl_produk` `pr` on(`d`.`id_produk` = `pr`.`id_produk`)) join `tbl_kategori` `k` on(`pr`.`id_kategori` = `k`.`id_kategori`)) ;

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
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indeks untuk tabel `tbl_transaksi`
--
ALTER TABLE `tbl_transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD KEY `id_pelanggan` (`id_pelanggan`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `log_pelanggan`
--
ALTER TABLE `log_pelanggan`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `log_perubahan_pelanggan`
--
ALTER TABLE `log_perubahan_pelanggan`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `tbl_cabang`
--
ALTER TABLE `tbl_cabang`
  MODIFY `id_cabang` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tbl_detail_transaksi`
--
ALTER TABLE `tbl_detail_transaksi`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `tbl_kategori`
--
ALTER TABLE `tbl_kategori`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `tbl_log`
--
ALTER TABLE `tbl_log`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `tbl_pelanggan`
--
ALTER TABLE `tbl_pelanggan`
  MODIFY `id_pelanggan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `tbl_produk`
--
ALTER TABLE `tbl_produk`
  MODIFY `id_produk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT untuk tabel `tbl_transaksi`
--
ALTER TABLE `tbl_transaksi`
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

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
  ADD CONSTRAINT `tbl_transaksi_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `tbl_pelanggan` (`id_pelanggan`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

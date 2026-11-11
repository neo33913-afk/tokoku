<?php

// Ikon menu per halaman (Bootstrap Icons). Halaman baru yang belum terdaftar memakai ikon bawaan.
$ikonMenu = [
    'dashboard' => 'bi-grid-1x2',
    'petugas' => 'bi-grid-1x2',
    'pelanggan' => 'bi-people',
    'produk' => 'bi-box-seam',
    'transaksi' => 'bi-cash-coin',
    'laporan' => 'bi-bar-chart-line',
    'log' => 'bi-clock-history',
    'belanja' => 'bi-bag',
    'riwayat' => 'bi-receipt',
    'user' => 'bi-person-gear'
];

$inisial = mb_strtoupper(mb_substr(trim($user['nama_lengkap'] ?? ''), 0, 1));
$inisial = $inisial !== '' ? $inisial : '?';

// Tanggal hari ini dalam bahasa Indonesia
$namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$namaBulan = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

$tanggalHariIni = $namaHari[(int) date('w')] . ', '
    . date('j') . ' ' . $namaBulan[(int) date('n')] . ' ' . date('Y');

?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> | Tokoku</title>

    <!-- Tema dan status sidebar dipasang paling awal supaya tidak berkedip -->
    <script src="assets/js/tema.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-page="<?= e($page) ?>">

<div class="app-shell">

    <!-- ===== Sidebar: menyempit jadi ikon, melebar saat disorot ===== -->
    <aside class="sidebar" id="sidebar" aria-label="Menu utama">

        <a class="sidebar-brand" href="index.php">
            <span class="brand-icon">T</span>
            <span class="label">Tokoku</span>
        </a>

        <nav class="sidebar-nav">

            <?php foreach ($pages as $key => $label): ?>
                <a class="sidebar-link <?= $page === $key ? 'active' : '' ?>"
                   href="index.php?page=<?= e($key) ?>"
                   title="<?= e($label) ?>"
                   <?= $page === $key ? 'aria-current="page"' : '' ?>>
                    <i class="bi <?= e($ikonMenu[$key] ?? 'bi-circle') ?>"></i>
                    <span class="label"><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>

        </nav>

        <!-- Sematkan sidebar supaya selalu terbuka (hanya di layar lebar) -->
        <div class="sidebar-foot d-none d-lg-block">
            <button type="button"
                    class="sidebar-link"
                    data-sidebar-pin
                    aria-pressed="false">
                <i class="bi bi-pin-angle ikon-pin-off"></i>
                <i class="bi bi-pin-angle-fill ikon-pin-on"></i>
                <span class="label">
                    <span class="teks-pin-off">Sematkan menu</span>
                    <span class="teks-pin-on">Lepas sematan</span>
                </span>
            </button>
        </div>

    </aside>

    <!-- Latar gelap di belakang sidebar (layar kecil) -->
    <div class="sidebar-backdrop" data-sidebar-toggle></div>


    <div class="app-content">

        <!-- ===== Topbar ===== -->
        <header class="topbar">

            <button type="button"
                    class="btn-ikon d-lg-none"
                    data-sidebar-toggle
                    aria-controls="sidebar"
                    aria-expanded="false"
                    aria-label="Buka menu">
                <i class="bi bi-list fs-5"></i>
            </button>

            <span class="text-secondary small d-none d-lg-inline">
                <i class="bi bi-calendar3 me-1"></i><?= e($tanggalHariIni) ?>
            </span>

            <div class="ms-auto d-flex align-items-center gap-2">

                <button type="button"
                        class="btn-ikon"
                        data-tema-toggle
                        aria-label="Ganti tema"
                        title="Ganti tema">
                    <i class="bi bi-moon-stars ikon-gelap"></i>
                    <i class="bi bi-sun ikon-terang"></i>
                </button>

                <div class="dropdown">
                    <button type="button"
                            class="btn-user"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            aria-label="Menu akun">
                        <span class="avatar"><?= e($inisial) ?></span>
                        <span class="d-none d-sm-inline"><?= e($user['nama_lengkap']) ?></span>
                        <i class="bi bi-chevron-down small text-secondary"></i>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end">

                        <div class="px-3 py-2">
                            <div class="fw-semibold"><?= e($user['nama_lengkap']) ?></div>
                            <div class="small text-secondary mb-2">@<?= e($user['username']) ?></div>
                            <span class="badge-role"><?= e($user['role']) ?></span>
                        </div>

                        <hr class="dropdown-divider">

                        <form action="logout.php" method="POST" class="mb-0">
                            <input type="hidden" name="csrf_token"
                                   value="<?= e(csrf_token()) ?>">

                            <button class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Keluar
                            </button>
                        </form>

                    </div>
                </div>

            </div>

        </header>


        <main class="app-main">

            <?php if (!empty($_SESSION['flash'])): ?>

                <?php
                $tipeFlash = $_SESSION['flash']['type'];
                $ikonFlash = [
                    'success' => 'bi-check-circle-fill',
                    'danger' => 'bi-exclamation-triangle-fill',
                    'warning' => 'bi-exclamation-circle-fill'
                ][$tipeFlash] ?? 'bi-info-circle-fill';
                ?>

                <div class="alert alert-<?= e($tipeFlash) ?> alert-dismissible fade show d-flex align-items-center gap-2"
                     role="alert">
                    <i class="bi <?= e($ikonFlash) ?>"></i>
                    <div><?= e($_SESSION['flash']['message']) ?></div>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup">
                    </button>
                </div>

                <?php unset($_SESSION['flash']); ?>

            <?php endif; ?>

            <div class="page-header mb-4">
                <h3 class="fw-bold mb-1"><?= e($pageTitle) ?></h3>
                <p class="text-secondary mb-0">
                    Sistem Informasi Penjualan Toko
                </p>
            </div>
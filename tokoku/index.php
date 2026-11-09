<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pages = [
    'dashboard' => 'Dashboard',
    'pelanggan' => 'Pelanggan',
    'produk' => 'Produk',
    'transaksi' => 'Transaksi',
    'laporan' => 'Laporan Penjualan',
    'log' => 'Log Aktivitas'
];

$page = $_GET['page'] ?? 'dashboard';

if (!array_key_exists($page, $pages)) {
    $page = 'dashboard';
}

$pageTitle = $pages[$page];

require_once __DIR__ . '/includes/header.php';

require __DIR__ . '/pages/' . $page . '.php';

require_once __DIR__ . '/includes/footer.php';
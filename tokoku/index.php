<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Wajib login; role diperiksa ulang ke database di setiap permintaan
require_login($pdo);

$user = user_login();

// Menu hanya berisi halaman yang boleh dibuka role ini
$pages = array_intersect_key(
    semua_halaman(),
    array_flip(izin_halaman($user['role']))
);

if (!$pages) {
    keluar_user();
    redirect_ke('login.php');
}

$page = $_GET['page'] ?? '';

// Halaman tidak dikenal atau bukan hak role ini: kembali ke halaman awal role
if (!array_key_exists($page, $pages)) {
    $page = array_key_first($pages);
}

$pageTitle = $pages[$page];

require_once __DIR__ . '/includes/header.php';

require __DIR__ . '/pages/' . $page . '.php';

require_once __DIR__ . '/includes/footer.php';
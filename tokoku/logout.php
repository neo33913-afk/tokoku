<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Logout hanya lewat POST + token CSRF, supaya tidak bisa dipicu dari link/gambar pihak lain
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        verify_csrf();

        $user = user_login();

        if ($user) {
            log_activity($pdo, 'LOGOUT', "User {$user['username']} keluar dari sistem");
        }

        keluar_user();

        // Sesi lama sudah dihancurkan; mulai sesi baru untuk pesan
        session_start();
        set_flash('Anda sudah keluar.');

    } catch (Throwable $e) {
        error_log('[Tokoku] ' . $e->getMessage());
    }
}

redirect_ke('login.php');
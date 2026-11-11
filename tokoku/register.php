<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (user_login()) {
    redirect_ke(halaman_awal(user_role()));
}

$error = '';
$nilai = [
    'username' => '',
    'nama_lengkap' => '',
    'kota' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        verify_csrf();

        $nilai['username'] = trim($_POST['username'] ?? '');
        $nilai['nama_lengkap'] = trim($_POST['nama_lengkap'] ?? '');
        $nilai['kota'] = trim($_POST['kota'] ?? '');

        $password = $_POST['password'] ?? '';
        $konfirmasi = $_POST['konfirmasi'] ?? '';

        if (
            $nilai['username'] === '' ||
            $nilai['nama_lengkap'] === '' ||
            $nilai['kota'] === '' ||
            $password === ''
        ) {
            throw new RuntimeException('Semua kolom wajib diisi.');
        }

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $nilai['username'])) {
            throw new RuntimeException(
                'Username 3-30 karakter, hanya huruf, angka, dan garis bawah (_).'
            );
        }

        if (mb_strlen($nilai['nama_lengkap']) > 100 || mb_strlen($nilai['kota']) > 50) {
            throw new RuntimeException('Nama maksimal 100 karakter dan kota maksimal 50 karakter.');
        }

        if (strlen($password) < 6) {
            throw new RuntimeException('Password minimal 6 karakter.');
        }

        if ($password !== $konfirmasi) {
            throw new RuntimeException('Konfirmasi password tidak sama.');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_user WHERE username = ?");
        $stmt->execute([$nilai['username']]);

        if ($stmt->fetchColumn() > 0) {
            throw new RuntimeException('Username sudah dipakai. Pilih username lain.');
        }

        $pdo->beginTransaction();

        // Data pelanggan dibuat otomatis supaya langsung muncul di pencarian kasir
        $stmt = $pdo->prepare("
            INSERT INTO tbl_pelanggan (nama_pelanggan, kota)
            VALUES (?, ?)
        ");

        $stmt->execute([$nilai['nama_lengkap'], $nilai['kota']]);

        $idPelanggan = (int) $pdo->lastInsertId();

        // Role SELALU pelanggan untuk pendaftaran sendiri; hanya admin yang bisa mengubahnya
        $stmt = $pdo->prepare("
            INSERT INTO tbl_user
            (username, password_hash, nama_lengkap, role, id_pelanggan)
            VALUES (?, ?, ?, 'pelanggan', ?)
        ");

        $stmt->execute([
            $nilai['username'],
            password_hash($password, PASSWORD_DEFAULT),
            $nilai['nama_lengkap'],
            $idPelanggan
        ]);

        log_activity(
            $pdo,
            'REGISTER',
            "Pelanggan baru mendaftar: {$nilai['username']} ({$nilai['nama_lengkap']})"
        );

        $pdo->commit();

        set_flash('Pendaftaran berhasil. Silakan masuk.');

        redirect_ke('login.php');

    } catch (RuntimeException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[Tokoku] ' . $e->getMessage());

        // 23000 = username sama didaftarkan bersamaan oleh dua orang
        $error = $e->getCode() === '23000'
            ? 'Username sudah dipakai. Pilih username lain.'
            : 'Terjadi kesalahan pada database. Silakan coba lagi.';
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daftar | Tokoku</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">

            <div class="text-center mb-4">
                <span class="brand-icon">T</span>
                <h3 class="fw-bold mt-2 mb-0">Tokoku</h3>
                <p class="text-secondary mb-0">Daftar akun pelanggan</p>
            </div>

            <div class="content-card">
                <div class="content-card-header">
                    <h5 class="fw-bold mb-0">Daftar</h5>
                </div>

                <div class="content-card-body">

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 small">
                            <?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php" autocomplete="on">

                        <input type="hidden" name="csrf_token"
                               value="<?= e(csrf_token()) ?>">

                        <div class="mb-3">
                            <label class="form-label" for="nama_lengkap">Nama Lengkap</label>
                            <input type="text"
                                   id="nama_lengkap"
                                   name="nama_lengkap"
                                   class="form-control"
                                   maxlength="100"
                                   value="<?= e($nilai['nama_lengkap']) ?>"
                                   autocomplete="name"
                                   autofocus
                                   required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="kota">Kota</label>
                            <input type="text"
                                   id="kota"
                                   name="kota"
                                   class="form-control"
                                   maxlength="50"
                                   value="<?= e($nilai['kota']) ?>"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="username">Username</label>
                            <input type="text"
                                   id="username"
                                   name="username"
                                   class="form-control"
                                   maxlength="30"
                                   pattern="[A-Za-z0-9_]{3,30}"
                                   value="<?= e($nilai['username']) ?>"
                                   autocomplete="username"
                                   required>
                            <div class="form-text">3-30 karakter: huruf, angka, dan garis bawah.</div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="password">Password</label>
                                <input type="password"
                                       id="password"
                                       name="password"
                                       class="form-control"
                                       minlength="6"
                                       autocomplete="new-password"
                                       required>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label" for="konfirmasi">Ulangi Password</label>
                                <input type="password"
                                       id="konfirmasi"
                                       name="konfirmasi"
                                       class="form-control"
                                       minlength="6"
                                       autocomplete="new-password"
                                       required>
                            </div>
                        </div>

                        <button class="btn btn-dark w-100 py-2">Daftar</button>

                    </form>

                    <div class="text-center small text-secondary mt-3">
                        Sudah punya akun?
                        <a href="login.php">Masuk</a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</main>

</body>
</html>
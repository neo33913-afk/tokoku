<?php

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Sudah login: langsung ke halaman sesuai role
if (user_login()) {
    redirect_ke(halaman_awal(user_role()));
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        verify_csrf();

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            throw new RuntimeException('Username dan password wajib diisi.');
        }

        $stmt = $pdo->prepare("
            SELECT id_user, username, password_hash, nama_lengkap, role, id_pelanggan
            FROM tbl_user
            WHERE username = ?
        ");

        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            // Jeda singkat memperlambat tebak-tebakan password
            usleep(400000);

            throw new RuntimeException('Username atau password salah.');
        }

        // Perbarui hash jika algoritma bawaan PHP sudah berganti
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $stmt = $pdo->prepare("UPDATE tbl_user SET password_hash = ? WHERE id_user = ?");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $row['id_user']]);
        }

        masuk_user($row);

        log_activity(
            $pdo,
            'LOGIN',
            "User {$row['username']} ({$row['role']}) masuk ke sistem"
        );

        redirect_ke(halaman_awal($row['role']));

    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        error_log('[Tokoku] ' . $e->getMessage());
        $error = 'Terjadi kesalahan pada database. Silakan coba lagi.';
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Masuk | Tokoku</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">

            <div class="text-center mb-4">
                <span class="brand-icon">T</span>
                <h3 class="fw-bold mt-2 mb-0">Tokoku</h3>
                <p class="text-secondary mb-0">Sistem Informasi Penjualan Toko</p>
            </div>

            <div class="content-card">
                <div class="content-card-header">
                    <h5 class="fw-bold mb-0">Masuk</h5>
                </div>

                <div class="content-card-body">

                    <?php if ($flash): ?>
                        <div class="alert alert-<?= e($flash['type']) ?> py-2 small">
                            <?= e($flash['message']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 small">
                            <?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" autocomplete="on">

                        <input type="hidden" name="csrf_token"
                               value="<?= e(csrf_token()) ?>">

                        <div class="mb-3">
                            <label class="form-label" for="username">Username</label>
                            <input type="text"
                                   id="username"
                                   name="username"
                                   class="form-control"
                                   value="<?= e($username) ?>"
                                   autocomplete="username"
                                   autofocus
                                   required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-control"
                                   autocomplete="current-password"
                                   required>
                        </div>

                        <button class="btn btn-dark w-100 py-2">Masuk</button>

                    </form>

                    <div class="text-center small text-secondary mt-3">
                        Belum punya akun?
                        <a href="register.php">Daftar sebagai pelanggan</a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</main>

</body>
</html>
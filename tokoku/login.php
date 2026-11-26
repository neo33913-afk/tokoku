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
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Masuk | Tokoku</title>

    <script src="assets/js/tema.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="auth-wrap">

    <!-- Panel samping (hanya tampil di layar lebar) -->
    <aside class="auth-side">

        <a class="navbar-brand" href="login.php">
            <span class="brand-icon">T</span>
            <span>Tokoku</span>
        </a>

        <div>
            <h2 class="auth-judul">Kelola toko jadi lebih mudah dan rapi.</h2>

            <ul class="auth-fitur">
                <li>
                    <i class="bi bi-check2-circle"></i>
                    <span>Transaksi cepat dengan pencarian produk dan keranjang.</span>
                </li>
                <li>
                    <i class="bi bi-check2-circle"></i>
                    <span>Struk pembayaran otomatis siap cetak.</span>
                </li>
                <li>
                    <i class="bi bi-check2-circle"></i>
                    <span>Akses sesuai peran: admin, petugas, dan pelanggan.</span>
                </li>
            </ul>
        </div>

        <small class="opacity-75">&copy; <?= date('Y') ?> Tokoku</small>

    </aside>

    <!-- Form -->
    <main class="auth-main">

        <button type="button"
                class="btn-ikon auth-tema"
                data-tema-toggle
                aria-label="Ganti tema"
                title="Ganti tema">
            <i class="bi bi-moon-stars ikon-gelap"></i>
            <i class="bi bi-sun ikon-terang"></i>
        </button>

        <div class="auth-box">

            <!-- Logo (hanya tampil di layar kecil) -->
            <div class="d-lg-none text-center mb-4">
                <span class="brand-icon">T</span>
                <div class="fw-bold fs-4 mt-2">Tokoku</div>
            </div>

            <h1 class="auth-title mb-1">Selamat datang</h1>
            <p class="text-secondary mb-4">Masuk untuk melanjutkan ke Tokoku.</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> py-2 small">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 small d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" autocomplete="on">

                <input type="hidden" name="csrf_token"
                       value="<?= e(csrf_token()) ?>">

                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>

                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>

                        <input type="text"
                               id="username"
                               name="username"
                               class="form-control"
                               value="<?= e($username) ?>"
                               autocomplete="username"
                               autofocus
                               required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>

                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>

                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control"
                               autocomplete="current-password"
                               required>

                        <button type="button"
                                class="btn btn-outline-secondary"
                                data-toggle-password="#password"
                                aria-label="Tampilkan password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button class="btn btn-dark w-100 py-2">
                    Masuk
                    <i class="bi bi-arrow-right ms-1"></i>
                </button>

            </form>

            <div class="text-center small text-secondary mt-4">
                Belum punya akun?
                <a href="register.php" class="fw-semibold">Daftar sebagai pelanggan</a>
            </div>

        </div>
    </main>

</div>
<!-- Login RFID -->
<form id="form-rfid" action="login_rfid.php" method="POST" hidden>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="rfid_uid" id="rfid-uid-login">
</form>

<?php if (!empty($_SESSION['rfid_error'])): ?>
    <div class="alert alert-danger"
         style="position:fixed;top:1rem;left:50%;transform:translateX(-50%);z-index:1100;max-width:90%">
        <?= e($_SESSION['rfid_error']) ?>
    </div>
    <?php unset($_SESSION['rfid_error']); ?>
<?php endif; ?>

<style>
    .rfid-overlay {
        position: fixed;
        inset: 0;
        z-index: 1090;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, .6);
    }

    .rfid-overlay[hidden] {
        display: none;
    }

    .rfid-box {
        width: 100%;
        max-width: 380px;
        padding: 2rem 1.5rem;
        border-radius: 16px;
        text-align: center;
        background: var(--bs-body-bg, #fff);
        color: var(--bs-body-color, #212529);
        box-shadow: 0 20px 50px rgba(0, 0, 0, .3);
    }

    .rfid-ikon {
        font-size: 3.2rem;
        line-height: 1;
        margin-bottom: .75rem;
        color: #4f46e5;
        animation: rfid-denyut 1.4s ease-in-out infinite;
    }

    @keyframes rfid-denyut {
        50% {
            transform: scale(1.12);
            opacity: .7;
        }
    }

    /* Kolom penerima kode kartu: tetap bisa fokus, tapi tidak terlihat */
    .rfid-scan {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
    }
</style>

<script>
    (function () {

        var passInput = document.querySelector('input[type="password"]');
        var formLogin = passInput ? passInput.closest('form') : null;

        if (!formLogin) {
            return;
        }

        // Tombol pilihan di bawah form login
        var wrap = document.createElement('div');
        wrap.className = 'mt-3 text-center';
        wrap.innerHTML =
            '<div class="text-secondary small mb-2">atau</div>' +
            '<button type="button" id="btn-rfid" class="btn btn-outline-secondary w-100 py-2">' +
            '<i class="bi bi-credit-card-2-front me-2"></i>Masuk dengan Kartu RFID' +
            '</button>';

        formLogin.insertAdjacentElement('afterend', wrap);

        // Pop up scan kartu
        var ov = document.createElement('div');
        ov.className = 'rfid-overlay';
        ov.hidden = true;
        ov.innerHTML =
            '<div class="rfid-box">' +
            '<div class="rfid-ikon"><i class="bi bi-wifi"></i></div>' +
            '<h5 class="fw-bold mb-1">Tempelkan kartu</h5>' +
            '<p id="rfid-status" class="text-secondary mb-3">Dekatkan kartu member ke reader.</p>' +
            '<input type="password" id="rfid-scan" class="rfid-scan" autocomplete="off" tabindex="-1">' +
            '<button type="button" id="rfid-batal" class="btn btn-light border w-100">Batal</button>' +
            '</div>';

        document.body.appendChild(ov);

        var scan = ov.querySelector('#rfid-scan');
        var status = ov.querySelector('#rfid-status');

        function buka() {
            ov.hidden = false;
            scan.value = '';
            status.textContent = 'Dekatkan kartu member ke reader.';
            setTimeout(function () { scan.focus(); }, 50);
        }

        function tutup() {
            ov.hidden = true;
            scan.value = '';
        }

        document.getElementById('btn-rfid').addEventListener('click', buka);
        ov.querySelector('#rfid-batal').addEventListener('click', tutup);

        ov.addEventListener('click', function (ev) {
            if (ev.target === ov) {
                tutup();
            }
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !ov.hidden) {
                tutup();
            }
        });

        // Fokus dijaga supaya kode kartu selalu masuk ke kolom penerima
        scan.addEventListener('blur', function () {
            setTimeout(function () {
                if (!ov.hidden) {
                    scan.focus();
                }
            }, 50);
        });

        scan.addEventListener('input', function () {
            status.textContent = 'Membaca kartu...';
        });

        // Reader menekan Enter setelah mengetik kode kartu
        scan.addEventListener('keydown', function (ev) {

            if (ev.key !== 'Enter') {
                return;
            }

            ev.preventDefault();

            var kode = scan.value.trim();

            if (kode.length < 4) {
                status.textContent = 'Kartu tidak terbaca, coba lagi.';
                scan.value = '';
                return;
            }

            status.textContent = 'Memeriksa kartu...';

            document.getElementById('rfid-uid-login').value = kode;
            document.getElementById('form-rfid').submit();
        });

    })();
</script>
</body>
</html>
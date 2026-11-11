<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> | Tokoku</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container">

        <a class="navbar-brand fw-bold" href="index.php">
            <span class="brand-icon">T</span>
            Tokoku
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMenu">

            <ul class="navbar-nav ms-auto gap-lg-2">

                <?php foreach ($pages as $key => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $page === $key ? 'active' : '' ?>"
                           href="index.php?page=<?= e($key) ?>">
                            <?= e($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>

            </ul>

            <!-- Info user + keluar -->
            <div class="d-flex align-items-center gap-2 ms-lg-3 mt-2 mt-lg-0">

                <div class="text-lg-end lh-sm">
                    <div class="fw-semibold small"><?= e($user['nama_lengkap']) ?></div>
                    <span class="badge text-bg-secondary text-capitalize">
                        <?= e($user['role']) ?>
                    </span>
                </div>

                <form action="logout.php" method="POST" class="mb-0">
                    <input type="hidden" name="csrf_token"
                           value="<?= e(csrf_token()) ?>">

                    <button class="btn btn-sm btn-outline-dark">Keluar</button>
                </form>

            </div>

        </div>
    </div>
</nav>

<main class="container py-4">
    <?php if (!empty($_SESSION['flash'])): ?>

    <div class="alert alert-<?= e($_SESSION['flash']['type']) ?> alert-dismissible fade show">
        <?= e($_SESSION['flash']['message']) ?>

        <button type="button"
                class="btn-close"
                data-bs-dismiss="alert">
        </button>
    </div>

    <?php unset($_SESSION['flash']); ?>

<?php endif; ?>
    <div class="mb-4">
        <h3 class="fw-bold mb-1"><?= e($pageTitle) ?></h3>
        <p class="text-secondary mb-0">
            Sistem Informasi Penjualan Toko
        </p>
    </div>
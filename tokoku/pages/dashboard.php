<?php

$totalPelanggan = $pdo->query(
    "SELECT COUNT(*) FROM tbl_pelanggan"
)->fetchColumn();

$totalProduk = $pdo->query(
    "SELECT COUNT(*) FROM tbl_produk"
)->fetchColumn();

$totalTransaksi = $pdo->query(
    "SELECT COUNT(*) FROM tbl_transaksi"
)->fetchColumn();

$totalPenjualan = $pdo->query(
    "SELECT COALESCE(SUM(total_belanja), 0)
     FROM tbl_transaksi"
)->fetchColumn();

$transaksiTerbaru = $pdo->query("
    SELECT
        t.id_transaksi,
        t.tanggal,
        p.nama_pelanggan,
        t.total_belanja
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    ORDER BY t.id_transaksi DESC
    LIMIT 5
")->fetchAll();

?>

<div class="row g-3 mb-4">

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <div class="stat-label">Total Pelanggan</div>
            <div class="stat-value"><?= e($totalPelanggan) ?></div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <div class="stat-label">Total Produk</div>
            <div class="stat-value"><?= e($totalProduk) ?></div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <div class="stat-label">Total Transaksi</div>
            <div class="stat-value"><?= e($totalTransaksi) ?></div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <div class="stat-label">Total Penjualan</div>
            <div class="stat-value fs-4">
                <?= rupiah($totalPenjualan) ?>
            </div>
        </div>
    </div>

</div>

<div class="content-card">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0">Transaksi Terbaru</h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">

            <thead>
                <tr>
                    <th>ID Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Total Belanja</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($transaksiTerbaru as $row): ?>
                    <tr>
                        <td>#<?= e($row['id_transaksi']) ?></td>
                        <td><?= e($row['tanggal']) ?></td>
                        <td><?= e($row['nama_pelanggan'] ?? '-') ?></td>
                        <td class="fw-semibold">
                            <?= rupiah($row['total_belanja']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$transaksiTerbaru): ?>
                    <tr>
                        <td colspan="4" class="text-center text-secondary">
                            Belum ada transaksi.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>

        </table>
    </div>
</div>
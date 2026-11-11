<?php

$data = $pdo->query("
    SELECT *
    FROM view_laporan_penjualan
    ORDER BY id_transaksi DESC
")->fetchAll();

?>

<div class="content-card">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0">Laporan Detail Penjualan</h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Produk</th>
                    <th>Kategori</th>
                    <th>Jumlah</th>
                    <th>Subtotal</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td>#<?= e($row['id_transaksi']) ?></td>
                        <td><?= e($row['tanggal']) ?></td>
                        <td><?= e($row['nama_pelanggan']) ?></td>
                        <td><?= e($row['nama_produk']) ?></td>
                        <td><?= e($row['nama_kategori']) ?></td>
                        <td><?= e($row['jumlah']) ?></td>
                        <td class="fw-semibold">
                            <?= rupiah($row['subtotal']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$data): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary">
                            Data laporan belum tersedia.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>

        </table>
    </div>
</div>
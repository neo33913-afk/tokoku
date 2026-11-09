<?php

$pelanggan = $pdo->query("
    SELECT id_pelanggan, nama_pelanggan
    FROM tbl_pelanggan
    ORDER BY nama_pelanggan
")->fetchAll();

$produk = $pdo->query("
    SELECT id_produk, kode_produk, nama_produk, harga
    FROM tbl_produk
    ORDER BY nama_produk
")->fetchAll();

$data = $pdo->query("
    SELECT
        t.id_transaksi,
        t.tanggal,
        p.nama_pelanggan,
        t.total_belanja,
        COUNT(d.id_detail) AS jumlah_item
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN tbl_detail_transaksi d
        ON t.id_transaksi = d.id_transaksi
    GROUP BY
        t.id_transaksi,
        t.tanggal,
        p.nama_pelanggan,
        t.total_belanja
    ORDER BY t.id_transaksi DESC
")->fetchAll();

?>

<div class="content-card mb-4">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0">Buat Transaksi Baru</h5>
    </div>

    <div class="content-card-body">

        <form action="actions.php" method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= e(csrf_token()) ?>">

            <input type="hidden" name="action"
                   value="transaksi_simpan">

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Pelanggan</label>

                    <select name="id_pelanggan"
                            class="form-select"
                            required>

                        <option value="">Pilih Pelanggan</option>

                        <?php foreach ($pelanggan as $row): ?>

                            <option value="<?= e($row['id_pelanggan']) ?>">
                                <?= e($row['nama_pelanggan']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Produk</label>

                    <select name="id_produk"
                            class="form-select"
                            required>

                        <option value="">Pilih Produk</option>

                        <?php foreach ($produk as $row): ?>

                            <option value="<?= e($row['id_produk']) ?>">
                                <?= e($row['nama_produk']) ?>
                                - <?= rupiah($row['harga']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Tanggal Transaksi</label>

                    <input type="date"
                           name="tanggal"
                           class="form-control"
                           value="<?= date('Y-m-d') ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Jumlah Pembelian</label>

                    <input type="number"
                           name="jumlah"
                           class="form-control"
                           min="1"
                           value="1"
                           required>
                </div>

            </div>

            <div class="mt-3">
                <button class="btn btn-dark">
                    Simpan Transaksi
                </button>
            </div>

        </form>

        <p class="text-secondary small mt-3 mb-0">
            Total belanja dihitung otomatis berdasarkan harga produk dan jumlah pembelian.
        </p>

    </div>
</div>


<div class="content-card">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0">Riwayat Transaksi</h5>
    </div>

    <div class="table-responsive">

        <table class="table table-hover mb-0">

            <thead>
                <tr>
                    <th>ID Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Jumlah Item</th>
                    <th>Total Belanja</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($data as $row): ?>

                    <tr>

                        <td>#<?= e($row['id_transaksi']) ?></td>

                        <td><?= e($row['tanggal']) ?></td>

                        <td><?= e($row['nama_pelanggan'] ?? '-') ?></td>

                        <td><?= e($row['jumlah_item']) ?></td>

                        <td class="fw-semibold">
                            <?= rupiah($row['total_belanja']) ?>
                        </td>

                        <td>

                            <form action="actions.php"
                                  method="POST"
                                  onsubmit="return confirm('Yakin ingin menghapus transaksi ini beserta detailnya?')">

                                <input type="hidden" name="csrf_token"
                                       value="<?= e(csrf_token()) ?>">

                                <input type="hidden" name="action"
                                       value="transaksi_hapus">

                                <input type="hidden" name="id_transaksi"
                                       value="<?= e($row['id_transaksi']) ?>">

                                <button class="btn btn-sm btn-outline-danger">
                                    Hapus
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$data): ?>
                    <tr>
                        <td colspan="6" class="text-center text-secondary">
                            Belum ada transaksi.
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>

        </table>

    </div>
</div>
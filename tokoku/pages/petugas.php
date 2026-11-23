<?php

// Dashboard petugas: ringkasan hari ini + kelola transaksi hari ini
$hariIni = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS jumlah, COALESCE(SUM(total_belanja), 0) AS total
    FROM tbl_transaksi
    WHERE tanggal = ?
");

$stmt->execute([$hariIni]);
$ringkas = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(d.jumlah), 0)
    FROM tbl_detail_transaksi d
    INNER JOIN tbl_transaksi t
        ON d.id_transaksi = t.id_transaksi
    WHERE t.tanggal = ?
");

$stmt->execute([$hariIni]);
$barangTerjual = $stmt->fetchColumn();

$stokHabis = $pdo->query(
    "SELECT COUNT(*) FROM tbl_produk WHERE stok = 0"
)->fetchColumn();

$stokMenipis = $pdo->query(
    "SELECT COUNT(*) FROM tbl_produk WHERE stok BETWEEN 1 AND 5"
)->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        t.id_transaksi,
        p.nama_pelanggan,
        t.total_belanja
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    WHERE t.tanggal = ?
    ORDER BY t.id_transaksi DESC
    LIMIT 20
");

$stmt->execute([$hariIni]);
$data = $stmt->fetchAll();

// Rincian item untuk transaksi yang tampil
$rincian = [];

if ($data) {
    $ids = array_column($data, 'id_transaksi');
    $placeholder = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT id_transaksi, nama_produk, jumlah
        FROM tbl_detail_transaksi
        WHERE id_transaksi IN ($placeholder)
        ORDER BY id_detail
    ");

    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $d) {
        $rincian[$d['id_transaksi']][] = $d;
    }
}

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-secondary">
        Ringkasan hari ini, <?= e(date('d/m/Y')) ?>
    </div>

    <a href="index.php?page=transaksi" class="btn btn-dark">
        + Transaksi Baru
    </a>
</div>

<div class="row g-3 mb-4">

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <div class="stat-label">Transaksi Hari Ini</div>
            <div class="stat-value"><?= e($ringkas['jumlah']) ?></div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <div class="stat-label">Penjualan Hari Ini</div>
            <div class="stat-value fs-4"><?= rupiah($ringkas['total']) ?></div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <div class="stat-label">Barang Terjual</div>
            <div class="stat-value"><?= e($barangTerjual) ?> <small class="fs-6">pcs</small></div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card p-3">
            <div class="stat-label">Stok Habis / Menipis (&le; 5)</div>
            <div class="stat-value">
                <?= e($stokHabis) ?> <small class="fs-6">/ <?= e($stokMenipis) ?></small>
            </div>
        </div>
    </div>

</div>

<div class="content-card">

    <div class="content-card-header d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0">Transaksi Hari Ini</h5>

        <a href="index.php?page=transaksi" class="small">
            Lihat semua riwayat
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Pelanggan</th>
                    <th>Rincian</th>
                    <th>Total</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td>#<?= e($row['id_transaksi']) ?></td>
                        <td><?= e($row['nama_pelanggan'] ?? '-') ?></td>

                        <td>
                            <?php foreach ($rincian[$row['id_transaksi']] ?? [] as $item): ?>
                                <div class="small">
                                    <?= e($item['nama_produk']) ?>
                                    <span class="text-secondary">
                                        &times; <?= e($item['jumlah']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </td>

                        <td class="fw-semibold">
                            <?= rupiah($row['total_belanja']) ?>
                        </td>

                        <td>
                            <div class="d-flex gap-2">

                                <a href="struk.php?id=<?= e($row['id_transaksi']) ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-dark">
                                    Struk
                                </a>

                                <form action="actions.php"
                                      method="POST"
                                      onsubmit="return confirm('Yakin ingin membatalkan transaksi ini? Stok produk akan dikembalikan.')">

                                    <input type="hidden" name="csrf_token"
                                           value="<?= e(csrf_token()) ?>">

                                    <input type="hidden" name="action"
                                           value="transaksi_hapus">

                                    <input type="hidden" name="id_transaksi"
                                           value="<?= e($row['id_transaksi']) ?>">

                                    <button class="btn btn-sm btn-outline-danger">
                                        Batalkan
                                    </button>

                                </form>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$data): ?>
                    <tr>
                        <td colspan="5" class="text-center text-secondary">
                            Belum ada transaksi hari ini.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>

        </table>
    </div>
</div>
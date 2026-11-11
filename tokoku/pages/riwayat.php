<?php

// Halaman role pelanggan: hanya transaksi milik akunnya sendiri
$idPelanggan = $user['id_pelanggan'] ?? null;

$data = [];
$rincian = [];

if ($idPelanggan) {

    $stmt = $pdo->prepare("
        SELECT id_transaksi, tanggal, total_belanja
        FROM tbl_transaksi
        WHERE id_pelanggan = ?
        ORDER BY id_transaksi DESC
    ");

    $stmt->execute([$idPelanggan]);
    $data = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT d.id_transaksi, d.nama_produk, d.jumlah, d.harga
        FROM tbl_detail_transaksi d
        INNER JOIN tbl_transaksi t
            ON d.id_transaksi = t.id_transaksi
        WHERE t.id_pelanggan = ?
        ORDER BY d.id_detail
    ");

    $stmt->execute([$idPelanggan]);

    foreach ($stmt->fetchAll() as $d) {
        $rincian[$d['id_transaksi']][] = $d;
    }
}

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-secondary">
        <?= count($data) ?> transaksi
    </div>

    <a href="index.php?page=belanja" class="btn btn-dark btn-sm">
        Belanja Lagi
    </a>
</div>

<?php if (!$data): ?>

    <div class="card">
        <div class="card-body text-center text-secondary py-5">
            <?= $idPelanggan
                ? 'Belum ada transaksi. Yuk mulai belanja!'
                : 'Akun ini belum terhubung ke data pelanggan.' ?>
        </div>
    </div>

<?php endif; ?>

<div class="row g-3">

    <?php foreach ($data as $row): ?>

        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100" style="border-radius: 16px;">

                <div class="card-body d-flex flex-column">

                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold">#<?= e($row['id_transaksi']) ?></div>
                            <div class="small text-secondary">
                                <?= e(date('d/m/Y', strtotime($row['tanggal']))) ?>
                            </div>
                        </div>

                        <a href="struk.php?id=<?= e($row['id_transaksi']) ?>"
                           target="_blank"
                           class="btn btn-sm btn-outline-dark">
                            Struk
                        </a>
                    </div>

                    <div class="border-top pt-2 mb-2">
                        <?php foreach ($rincian[$row['id_transaksi']] ?? [] as $item): ?>
                            <div class="d-flex justify-content-between gap-2 small py-1">
                                <span>
                                    <?= e($item['nama_produk']) ?>
                                    <span class="text-secondary">&times; <?= e($item['jumlah']) ?></span>
                                </span>

                                <span class="text-nowrap">
                                    <?= rupiah($item['harga'] * $item['jumlah']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-auto">
                        <span class="text-secondary">Total</span>
                        <span class="fw-bold fs-5">
                            <?= rupiah($row['total_belanja']) ?>
                        </span>
                    </div>

                </div>
            </div>
        </div>

    <?php endforeach; ?>

</div>
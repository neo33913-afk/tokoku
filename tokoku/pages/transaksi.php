<?php

$pelanggan = $pdo->query("
    SELECT id_pelanggan, nama_pelanggan, kota
    FROM tbl_pelanggan
    ORDER BY nama_pelanggan
")->fetchAll();

// Data pelanggan untuk JavaScript (pencarian pelanggan)
$pelangganJson = json_encode(
    array_map(function ($p) {
        return [
            'id_pelanggan' => (int) $p['id_pelanggan'],
            'nama_pelanggan' => $p['nama_pelanggan'],
            'kota' => $p['kota']
        ];
    }, $pelanggan),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);

$produk = $pdo->query("
    SELECT
        p.id_produk,
        p.kode_produk,
        p.nama_produk,
        p.harga,
        p.stok,
        k.nama_kategori
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k
        ON p.id_kategori = k.id_kategori
    ORDER BY p.nama_produk
")->fetchAll();

// Data produk untuk JavaScript (pencarian dan keranjang)
$produkJson = json_encode(
    array_map(function ($p) {
        return [
            'id_produk' => (int) $p['id_produk'],
            'kode_produk' => $p['kode_produk'],
            'nama_produk' => $p['nama_produk'],
            'nama_kategori' => $p['nama_kategori'],
            'harga' => (float) $p['harga'],
            'stok' => (int) $p['stok']
        ];
    }, $produk),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);

$data = $pdo->query("
    SELECT
        t.id_transaksi,
        t.tanggal,
        p.nama_pelanggan,
        t.total_belanja
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    ORDER BY t.id_transaksi DESC
")->fetchAll();

// Rincian item per transaksi, dikelompokkan berdasarkan id_transaksi
$rincian = [];

$semuaDetail = $pdo->query("
    SELECT id_transaksi, nama_produk, jumlah, harga
    FROM tbl_detail_transaksi
    ORDER BY id_detail
")->fetchAll();

foreach ($semuaDetail as $d) {
    $rincian[$d['id_transaksi']][] = $d;
}

?>

<style>
    .produk-scroll {
        max-height: 62vh;
        overflow-y: auto;
        padding: .25rem;
    }

    .produk-card {
        background: #fff;
        cursor: pointer;
        transition: border-color .15s, box-shadow .15s;
    }

    .produk-card:hover:not(:disabled) {
        border-color: #212529;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
    }

    .produk-card:disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    .keranjang-list {
        max-height: 36vh;
        overflow-y: auto;
    }

    .kontrol-jumlah {
        width: 118px;
    }

    @media (min-width: 992px) {
        .keranjang-sticky {
            position: sticky;
            top: 5rem;
        }
    }
</style>

<script id="data-produk" type="application/json"><?= $produkJson ?></script>
<script id="data-pelanggan" type="application/json"><?= $pelangganJson ?></script>

<div class="row g-3 mb-4">

    <!-- ===================== KIRI: PILIH PRODUK ===================== -->
    <div class="col-lg-7">

        <div class="content-card">

            <div class="content-card-header">
                <h5 class="fw-bold mb-0">Pilih Produk</h5>
            </div>

            <div class="content-card-body">

                <input type="search"
                       id="cari-produk"
                       class="form-control form-control-lg mb-2"
                       placeholder="Cari nama, kode, atau kategori produk..."
                       autocomplete="off"
                       autofocus>

                <div class="small text-secondary mb-3">
                    <span id="info-pencarian"></span>
                    &middot; Tekan <kbd>Enter</kbd> untuk menambahkan hasil pertama (atau kode produk yang persis sama) ke keranjang.
                </div>

                <div class="produk-scroll">
                    <div id="daftar-produk" class="row g-2"></div>
                </div>

            </div>
        </div>

    </div>


    <!-- ===================== KANAN: KERANJANG ===================== -->
    <div class="col-lg-5">

        <form action="actions.php" method="POST" id="form-transaksi">

            <input type="hidden" name="csrf_token"
                   value="<?= e(csrf_token()) ?>">

            <input type="hidden" name="action"
                   value="transaksi_simpan">

            <!-- Input id_produk[] dan jumlah[] diisi oleh script.js -->
            <div id="input-tersembunyi"></div>

            <div class="content-card keranjang-sticky">

                <div class="content-card-header d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        Keranjang
                        <span class="badge text-bg-dark ms-1" id="jumlah-item">0</span>
                    </h5>

                    <button type="button"
                            id="kosongkan-keranjang"
                            class="btn btn-sm btn-outline-secondary"
                            hidden>
                        Kosongkan
                    </button>
                </div>

                <div class="content-card-body">

                    <div id="pesan-keranjang"
                         class="alert alert-warning py-2 small"
                         hidden></div>

                    <div id="keranjang-kosong"
                         class="text-center text-secondary py-4">
                        Keranjang masih kosong.<br>
                        <small>Klik produk di sebelah kiri untuk menambahkan.</small>
                    </div>

                    <div id="daftar-keranjang" class="keranjang-list"></div>

                    <div class="row g-2 mt-3">

                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0" for="cari-pelanggan">Pelanggan</label>

                                <?php if (user_role() === 'admin'): ?>
                                    <a href="index.php?page=pelanggan"
                                       target="_blank"
                                       class="small">
                                        + Pelanggan baru
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div id="pelanggan-box">

                                <!-- Diisi otomatis saat pelanggan dipilih -->
                                <input type="hidden"
                                       name="id_pelanggan"
                                       id="id-pelanggan"
                                       value="">

                                <input type="search"
                                       id="cari-pelanggan"
                                       class="form-control"
                                       placeholder="Cari nama atau kota pelanggan..."
                                       autocomplete="off">

                                <!-- Daftar hasil pencarian pelanggan -->
                                <div id="hasil-pelanggan"
                                     class="list-group mt-1"
                                     hidden></div>

                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label mb-1">Tanggal Transaksi</label>

                            <input type="date"
                                   name="tanggal"
                                   class="form-control"
                                   value="<?= date('Y-m-d') ?>"
                                   required>
                        </div>

                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top mt-3 pt-3">
                        <span class="text-secondary">Total</span>
                        <span class="fw-bold fs-4" id="total-keranjang">Rp 0</span>
                    </div>

                    <button type="submit"
                            id="tombol-bayar"
                            class="btn btn-dark w-100 mt-3 py-2"
                            disabled>
                        Bayar &amp; Cetak Struk
                    </button>

                </div>
            </div>

        </form>

    </div>

</div>


<!-- ===================== RIWAYAT ===================== -->
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
                    <th>Rincian</th>
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

                        <td>
                            <?php foreach ($rincian[$row['id_transaksi']] ?? [] as $item): ?>
                                <div class="small">
                                    <?= e($item['nama_produk']) ?>
                                    <span class="text-secondary">
                                        &times; <?= e($item['jumlah']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>

                            <?php if (empty($rincian[$row['id_transaksi']])): ?>
                                <span class="text-secondary small">-</span>
                            <?php endif; ?>
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

                                <?php
                                // Admin: semua transaksi. Petugas: hanya transaksi hari ini.
                                $bolehHapus = user_role() === 'admin'
                                    || (user_role() === 'petugas' && $row['tanggal'] === date('Y-m-d'));
                                ?>
                                <?php if ($bolehHapus): ?>
                                <form action="actions.php"
                                      method="POST"
                                      onsubmit="return confirm('Yakin ingin menghapus transaksi ini? Stok produk akan dikembalikan.')">

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
                                <?php endif; ?>

                            </div>

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
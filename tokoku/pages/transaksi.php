<?php

$pelanggan = $pdo->query("
    SELECT p.id_pelanggan, p.nama_pelanggan, p.kota,
           EXISTS(SELECT 1 FROM member m
                  WHERE m.id_pelanggan = p.id_pelanggan AND m.status = 'aktif') AS is_member
    FROM tbl_pelanggan p
    ORDER BY p.nama_pelanggan
")->fetchAll();

$pelangganJson = json_encode(
    array_map(function ($p) {
        return [
            'id_pelanggan' => (int) $p['id_pelanggan'],
            'nama_pelanggan' => $p['nama_pelanggan'],
            'kota' => $p['kota'],
            'member' => (bool) $p['is_member']
        ];
    }, $pelanggan),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);

$memberRfid = $pdo->query("
    SELECT m.rfid_uid, m.no_member, p.id_pelanggan, p.nama_pelanggan
    FROM member m
    JOIN tbl_pelanggan p ON p.id_pelanggan = m.id_pelanggan
    WHERE m.status = 'aktif' AND m.rfid_uid IS NOT NULL AND m.rfid_uid <> ''
")->fetchAll();

$memberJson = json_encode(
    array_map(function ($m) {
        return [
            'uid' => strtoupper($m['rfid_uid']),
            'no_member' => $m['no_member'],
            'id_pelanggan' => (int) $m['id_pelanggan'],
            'nama_pelanggan' => $m['nama_pelanggan']
        ];
    }, $memberRfid),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);

$produk = $pdo->query("
    SELECT
        p.id_produk,
        p.kode_produk,
        p.barcode,
        p.nama_produk,
        p.harga,
        p.stok,
        k.nama_kategori
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k
        ON p.id_kategori = k.id_kategori
    ORDER BY p.nama_produk
")->fetchAll();

$produkJson = json_encode(
    array_map(function ($p) {
        return [
            'id_produk' => (int) $p['id_produk'],
            'kode_produk' => $p['kode_produk'],
            'barcode' => $p['barcode'],
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
        t.total_belanja,
        t.diskon_persen,
        t.metode_bayar,
        t.status_bayar
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON t.id_pelanggan = p.id_pelanggan
    ORDER BY t.id_transaksi DESC
")->fetchAll();

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
    .d-flex[hidden] { display: none !important; }
    .produk-scroll { max-height: 62vh; overflow-y: auto; padding: .25rem; }
    .produk-card { background: #fff; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
    .produk-card:hover:not(:disabled) { border-color: #212529; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .produk-card:disabled { opacity: .5; cursor: not-allowed; }
    .keranjang-list { max-height: 36vh; overflow-y: auto; }
    .kontrol-jumlah { width: 118px; }
    @media (min-width: 992px) { .keranjang-sticky { position: sticky; top: 5rem; } }
</style>

<script id="data-produk" type="application/json"><?= $produkJson ?></script>
<script id="data-pelanggan" type="application/json"><?= $pelangganJson ?></script>
<script id="data-member" type="application/json"><?= $memberJson ?></script>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="content-card">
            <div class="content-card-header">
                <h5 class="fw-bold mb-0">Pilih Produk</h5>
            </div>
            <div class="content-card-body">
                <input type="search" id="cari-produk" class="form-control form-control-lg mb-2" placeholder="Cari nama, kode, atau kategori produk..." autocomplete="off" autofocus>
                <div class="small text-secondary mb-3">
                    <span id="info-pencarian"></span>
                    · Tekan <kbd>Enter</kbd> untuk menambahkan hasil pertama ke keranjang.
                    <br>
                    <i class="bi bi-upc-scan"></i> Scan barcode produk dengan scanner untuk langsung menambahkannya.
                </div>
                <div class="produk-scroll">
                    <div id="daftar-produk" class="row g-2"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <form action="actions.php" method="POST" id="form-transaksi" data-diskon="<?= (int) DISKON_MEMBER_PERSEN ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="transaksi_simpan">
            <div id="input-tersembunyi"></div>

            <div class="content-card keranjang-sticky">
                <div class="content-card-header d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        Keranjang <span class="badge text-bg-dark ms-1" id="jumlah-item">0</span>
                    </h5>
                    <button type="button" id="kosongkan-keranjang" class="btn btn-sm btn-outline-secondary" hidden>Kosongkan</button>
                </div>

                <div class="content-card-body">
                    <div id="pesan-keranjang" class="alert alert-warning py-2 small" hidden></div>
                    <div id="keranjang-kosong" class="text-center text-secondary py-4">
                        Keranjang masih kosong.<br><small>Klik produk di sebelah kiri untuk menambahkan.</small>
                    </div>
                    <div id="daftar-keranjang" class="keranjang-list"></div>

                    <div class="row g-2 mt-3">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0" for="cari-pelanggan">Pelanggan</label>
                                <?php if (user_role() === 'admin'): ?>
                                    <a href="index.php?page=pelanggan" target="_blank" class="small">+ Pelanggan baru</a>
                                <?php endif; ?>
                            </div>
                            <div id="pelanggan-box">
                                <input type="hidden" name="id_pelanggan" id="id-pelanggan" value="">
                                <input type="search" id="cari-pelanggan" class="form-control" placeholder="Cari nama atau kota pelanggan..." autocomplete="off">
                                <div id="hasil-pelanggan" class="list-group mt-1" hidden></div>
                                <div id="info-member" class="alert alert-success py-2 small mt-2 mb-0 d-flex justify-content-between align-items-center" hidden>
                                    <span><i class="bi bi-person-vcard me-1"></i><span id="info-member-teks"></span></span>
                                    <button type="button" class="btn-close btn-sm" id="lepas-member" aria-label="Lepas member"></button>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-broadcast"></i> Tempelkan kartu member pada reader untuk mengisi pelanggan otomatis.
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">Tanggal Transaksi</label>
                            <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="border-top mt-3 pt-3">
                        <div class="d-flex justify-content-between small text-secondary">
                            <span>Subtotal</span><span id="subtotal-keranjang">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between small text-success" id="baris-diskon" hidden>
                            <span id="label-diskon">Diskon Member</span><span id="diskon-keranjang">- Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="text-secondary">Total</span>
                            <span class="fw-bold fs-4" id="total-keranjang">Rp 0</span>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label mb-1">Metode Pembayaran</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="metode_bayar" id="metode-tunai" value="tunai" checked>
                            <label class="btn btn-outline-dark" for="metode-tunai">Tunai</label>
                            <input type="radio" class="btn-check" name="metode_bayar" id="metode-qris" value="qris">
                            <label class="btn btn-outline-dark" for="metode-qris">QRIS</label>
                        </div>
                    </div>

                    <div id="area-qris" class="mt-3 text-center" data-qris="<?= e(QRIS_STATIS) ?>" hidden>
                        <div class="small text-secondary mb-2">Pelanggan scan QRIS di bawah, lalu ketik nominal yang sama di aplikasi</div>
                        <img id="qris-img" src="" alt="QRIS" class="img-fluid border rounded" style="max-width: 240px;">
                        <div class="mt-2 fw-bold fs-4" id="qris-nominal">Rp 0</div>
                        <div class="form-check d-inline-block text-start mt-2">
                            <input class="form-check-input" type="checkbox" id="konfirmasi-qris">
                            <label class="form-check-label small" for="konfirmasi-qris">Pembayaran sudah diterima (cek di GoPay Merchant)</label>
                        </div>
                    </div>

                    <button type="submit" id="tombol-bayar" class="btn btn-dark w-100 mt-3 py-2" disabled>
                        Bayar &amp; Cetak Struk
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="content-card">
    <div class="content-card-header"><h5 class="fw-bold mb-0">Riwayat Transaksi</h5></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID Transaksi</th><th>Tanggal</th><th>Pelanggan</th><th>Rincian</th><th>Total Belanja</th><th>Metode</th><th>Aksi</th>
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
                                <div class="small"><?= e($item['nama_produk']) ?> <span class="text-secondary">&times; <?= e($item['jumlah']) ?></span></div>
                            <?php endforeach; ?>
                            <?php if (empty($rincian[$row['id_transaksi']])): ?><span class="text-secondary small">-</span><?php endif; ?>
                        </td>
                        <td class="fw-semibold">
                            <?= rupiah($row['total_belanja']) ?>
                            <?php if ((float) $row['diskon_persen'] > 0): ?>
                                <div class="small text-success fw-normal">Diskon member <?= (float) $row['diskon_persen'] ?>%</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e(strtoupper($row['metode_bayar'] ?? 'TUNAI')) ?>
                            <?php if (($row['status_bayar'] ?? 'lunas') === 'menunggu'): ?>
                                <div><span class="badge text-bg-warning">Menunggu pembayaran</span></div>
                            <?php elseif (($row['status_bayar'] ?? 'lunas') === 'batal'): ?>
                                <div><span class="badge text-bg-secondary">Dibatalkan</span></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <?php if (($row['status_bayar'] ?? 'lunas') === 'lunas'): ?>
                                <a href="struk.php?id=<?= e($row['id_transaksi']) ?>" target="_blank" class="btn btn-sm btn-outline-dark">Struk</a>
                                <?php endif; ?>
                                <?php
                                $bolehHapus = user_role() === 'admin' || (user_role() === 'petugas' && $row['tanggal'] === date('Y-m-d'));
                                ?>
                                <?php if ($bolehHapus): ?>
                                <form action="actions.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus transaksi ini? Stok produk akan dikembalikan.')">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="transaksi_hapus">
                                    <input type="hidden" name="id_transaksi" value="<?= e($row['id_transaksi']) ?>">
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data): ?>
                    <tr><td colspan="7" class="text-center text-secondary">Belum ada transaksi.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var dataEl = document.getElementById('data-member');
    var daftar = [];
    try { daftar = JSON.parse(dataEl.textContent) || []; } catch (err) { daftar = []; }

    var inputId = document.getElementById('id-pelanggan');
    var inputCari = document.getElementById('cari-pelanggan');
    var hasil = document.getElementById('hasil-pelanggan');
    var info = document.getElementById('info-member');
    var infoTeks = document.getElementById('info-member-teks');
    var tombolLepas = document.getElementById('lepas-member');

    function cariMember(uid) {
        uid = (uid || '').trim().toUpperCase();
        for (var i = 0; i < daftar.length; i++) {
            if (daftar[i].uid === uid) return daftar[i];
        }
        return null;
    }

    function pilihMember(m) {
        inputId.value = m.id_pelanggan;
        inputCari.value = m.nama_pelanggan;
        if (hasil) hasil.hidden = true;
        infoTeks.textContent = 'Member: ' + m.nama_pelanggan + ' (' + m.no_member + ')';
        info.hidden = false;
        inputId.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function lepasMember() {
        inputId.value = '';
        inputCari.value = '';
        info.hidden = true;
        inputId.dispatchEvent(new Event('change', {bubbles: true}));
    }

    tombolLepas.addEventListener('click', lepasMember);
    inputCari.addEventListener('input', function () { info.hidden = true; });

    var buffer = '';
    var terakhir = 0;
    document.addEventListener('keydown', function (ev) {
        var sekarang = Date.now();
        if (sekarang - terakhir > 80) buffer = '';
        terakhir = sekarang;

        if (ev.key === 'Enter') {
            var m = buffer.length >= 4 ? cariMember(buffer) : null;
            if (m) {
                ev.preventDefault();
                ev.stopPropagation();
                var aktif = document.activeElement;
                if (aktif && aktif.tagName === 'INPUT' && aktif.value.toUpperCase().slice(-buffer.length) === buffer.toUpperCase()) {
                    aktif.value = aktif.value.slice(0, aktif.value.length - buffer.length);
                    aktif.dispatchEvent(new Event('input', {bubbles: true}));
                }
                pilihMember(m);
            }
            buffer = '';
            return;
        }
        if (ev.key && ev.key.length === 1) buffer += ev.key;
    }, true);
})();
</script>
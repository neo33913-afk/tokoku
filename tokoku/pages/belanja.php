<?php

// Halaman belanja untuk role pelanggan: katalog produk + keranjang
require_once __DIR__ . '/../includes/upload.php';
$idPelanggan = $user['id_pelanggan'] ?? null;
$bolehBeli = !empty($idPelanggan);

$namaDepan = explode(' ', trim($user['nama_lengkap']))[0];

$produk = $pdo->query("
    SELECT
        p.id_produk,
        p.nama_produk,
        p.harga,
        p.stok,
        p.gambar,
        k.nama_kategori
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k
        ON p.id_kategori = k.id_kategori
    ORDER BY (p.stok > 0) DESC, p.nama_produk
")->fetchAll();

$produkJson = json_encode(
    array_map(function ($p) {
        return [
            'id_produk' => (int) $p['id_produk'],
            'nama_produk' => $p['nama_produk'],
            'nama_kategori' => $p['nama_kategori'],
            'harga' => (float) $p['harga'],
            'stok' => (int) $p['stok'],
            'gambar' => url_gambar_produk($p['gambar'])
        ];
    }, $produk),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);

?>

<style>
    .bl-hero {
        background: linear-gradient(135deg, #212529 0%, #3d444b 100%);
        color: #fff;
        border-radius: 18px;
        padding: 1.25rem;
    }

    .bl-hero h4 {
        font-weight: 700;
        margin-bottom: .15rem;
    }

    .bl-search {
        border: 0;
        border-radius: 999px;
        padding: .75rem 1.2rem;
    }

    .bl-chips {
        display: flex;
        gap: .5rem;
        overflow-x: auto;
        padding: .25rem 0 .6rem;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }

    .bl-chips::-webkit-scrollbar {
        display: none;
    }

    .bl-chip {
        flex: 0 0 auto;
        white-space: nowrap;
        border: 1px solid #dee2e6;
        background: #fff;
        color: #212529;
        border-radius: 999px;
        padding: .4rem 1rem;
        font-size: .875rem;
        cursor: pointer;
    }

    .bl-chip.aktif {
        background: #212529;
        border-color: #212529;
        color: #fff;
    }

    .bl-card {
        display: flex;
        flex-direction: column;
        height: 100%;
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 16px;
        overflow: hidden;
        transition: box-shadow .15s, transform .15s;
    }

    .bl-card:hover {
        box-shadow: 0 8px 22px rgba(0, 0, 0, .09);
        transform: translateY(-2px);
    }

    .bl-card.bl-habis .bl-tile,
    .bl-card.bl-habis .bl-body > :not(.bl-aksi) {
        opacity: .5;
    }

    .bl-tile {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        aspect-ratio: 4 / 3;
        font-size: 2.2rem;
        font-weight: 700;
        letter-spacing: 1px;
    }

    .bl-img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .bl-thumb {
        position: relative;
        display: flex;
        flex: 0 0 52px;
        align-items: center;
        justify-content: center;
        width: 52px;
        height: 52px;
        border-radius: 10px;
        overflow: hidden;
        font-size: .9rem;
        font-weight: 700;
    }

    .bl-kat {
        position: absolute;
        left: .6rem;
        top: .6rem;
        max-width: calc(100% - 1.2rem);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        padding: .15rem .55rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, .85);
        color: #495057;
        font-size: .68rem;
        font-weight: 600;
        letter-spacing: 0;
    }

    .bl-body {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: .15rem;
        padding: .8rem;
    }

    .bl-nama {
        font-weight: 600;
        line-height: 1.25;
    }

    .bl-harga {
        font-weight: 700;
        font-size: 1.05rem;
    }

    .bl-stok {
        font-size: .78rem;
        color: #6c757d;
    }

    .bl-stok.sedikit {
        color: #c2570c;
        font-weight: 600;
    }

    .bl-aksi {
        margin-top: auto;
        padding-top: .6rem;
    }

    .bl-stepper .form-control {
        min-width: 0;
    }

    /* Bar keranjang melayang di bawah layar */
    .bl-bar {
        position: fixed;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 1030;
        padding: .75rem 0;
        background: #fff;
        border-top: 1px solid #dee2e6;
        box-shadow: 0 -6px 18px rgba(0, 0, 0, .08);
    }

    .bl-spacer {
        height: 5.5rem;
    }

    .bl-toast {
        position: fixed;
        left: 50%;
        bottom: 5.5rem;
        z-index: 1080;
        transform: translateX(-50%);
        max-width: calc(100% - 2rem);
        padding: .6rem 1rem;
        border-radius: 999px;
        background: #212529;
        color: #fff;
        font-size: .875rem;
        text-align: center;
    }

    .bl-item {
        padding: .85rem 0;
        border-bottom: 1px solid #f1f3f5;
    }

    @media (max-width: 575.98px) {
        .bl-hero {
            padding: 1rem;
            border-radius: 16px;
        }

        .bl-tile {
            font-size: 1.8rem;
        }

        .bl-body {
            padding: .65rem;
        }
    }
</style>

<script id="data-belanja" type="application/json"><?= $produkJson ?></script>

<div id="belanja"
     data-kunci="tokoku_belanja_<?= e($user['id_user']) ?>"
     data-boleh="<?= $bolehBeli ? '1' : '0' ?>">

    <?php if (!$bolehBeli): ?>
        <div class="alert alert-warning">
            Akun Anda belum terhubung ke data pelanggan, jadi belum bisa berbelanja.
            Hubungi admin toko.
        </div>
    <?php endif; ?>

    <!-- Sapaan + pencarian -->
    <div class="bl-hero mb-3">
        <h4>Halo, <?= e($namaDepan) ?>!</h4>
        <p class="mb-3 opacity-75">Mau belanja apa hari ini?</p>

        <input type="search"
               id="bl-cari"
               class="form-control bl-search"
               placeholder="Cari produk atau kategori..."
               autocomplete="off">
    </div>

    <!-- Kategori -->
    <div class="bl-chips" id="bl-kategori"></div>

    <div class="small text-secondary mb-2" id="bl-info"></div>

    <!-- Katalog -->
    <div class="row g-2 g-md-3" id="bl-grid"></div>

    <div class="text-center text-secondary py-5" id="bl-kosong" hidden>
        Produk tidak ditemukan.
    </div>

    <div class="bl-spacer"></div>

    <!-- Bar keranjang -->
    <div class="bl-bar" id="bl-bar" hidden>
        <div class="container d-flex align-items-center justify-content-between gap-3">

            <div class="lh-sm">
                <div class="small text-secondary"><span id="bl-bar-jumlah">0</span> barang</div>
                <div class="fw-bold fs-5" id="bl-bar-total">Rp 0</div>
            </div>

            <button type="button"
                    class="btn btn-dark px-4 py-2"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#bl-keranjang"
                    aria-controls="bl-keranjang">
                Lihat Keranjang
            </button>

        </div>
    </div>

    <div class="bl-toast" id="bl-toast" role="status" hidden></div>

    <!-- Keranjang (panel geser) -->
    <div class="offcanvas offcanvas-end"
         tabindex="-1"
         id="bl-keranjang"
         aria-labelledby="bl-keranjang-judul"
         style="width: min(420px, 100vw);">

        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-bold" id="bl-keranjang-judul">Keranjang Belanja</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>

        <form action="actions.php" method="POST" id="bl-form" class="d-flex flex-column flex-grow-1 overflow-hidden">

            <input type="hidden" name="csrf_token"
                   value="<?= e(csrf_token()) ?>">

            <input type="hidden" name="action"
                   value="belanja_simpan">

            <!-- id_produk[] dan jumlah[] diisi oleh belanja.js -->
            <div id="bl-input"></div>

            <div class="offcanvas-body py-0">

                <div class="text-center text-secondary py-5" id="bl-keranjang-kosong">
                    Keranjang masih kosong.<br>
                    <small>Pilih produk lalu tekan Tambah.</small>
                </div>

                <div id="bl-daftar"></div>

            </div>

            <div class="border-top p-3">

                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-secondary">Total</span>
                    <span class="fw-bold fs-4" id="bl-total">Rp 0</span>
                </div>

                <div class="small text-secondary mb-3">
                    Pesanan langsung tercatat sebagai transaksi atas nama Anda.
                </div>

                <button type="submit"
                        id="bl-bayar"
                        class="btn btn-dark w-100 py-2"
                        disabled>
                    Beli Sekarang
                </button>

            </div>

        </form>
    </div>

</div>
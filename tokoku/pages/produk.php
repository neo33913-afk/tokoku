<?php

require_once __DIR__ . '/../includes/upload.php';

$edit = null;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM tbl_produk
        WHERE id_produk = ?
    ");

    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$kategori = $pdo->query("
    SELECT *
    FROM tbl_kategori
    ORDER BY nama_kategori
")->fetchAll();

$data = $pdo->query("
    SELECT
        p.*,
        k.nama_kategori
    FROM tbl_produk p
    LEFT JOIN tbl_kategori k
        ON p.id_kategori = k.id_kategori
    ORDER BY p.id_produk DESC
")->fetchAll();

$urlGambarEdit = url_gambar_produk($edit['gambar'] ?? null);

?>

<div class="content-card mb-4">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0">
            <?= $edit ? 'Edit Produk' : 'Tambah Produk' ?>
        </h5>
    </div>

    <div class="content-card-body">

        <form action="actions.php" method="POST" enctype="multipart/form-data">

            <input type="hidden" name="csrf_token"
                   value="<?= e(csrf_token()) ?>">

            <input type="hidden" name="action"
                   value="produk_simpan">

            <input type="hidden" name="id_produk"
                   value="<?= e($edit['id_produk'] ?? '') ?>">

            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">Kode Produk</label>

                    <input type="text"
                           name="kode_produk"
                           class="form-control"
                           value="<?= e($edit['kode_produk'] ?? '') ?>"
                           required>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Nama Produk</label>

                    <input type="text"
                           name="nama_produk"
                           class="form-control"
                           value="<?= e($edit['nama_produk'] ?? '') ?>"
                           required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Kategori</label>

                    <select name="id_kategori" class="form-select">

                        <option value="">Pilih Kategori</option>

                        <?php foreach ($kategori as $kat): ?>

                            <option value="<?= e($kat['id_kategori']) ?>"
                                <?= ($edit['id_kategori'] ?? '') == $kat['id_kategori'] ? 'selected' : '' ?>>

                                <?= e($kat['nama_kategori']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Harga</label>

                    <input type="number"
                           name="harga"
                           class="form-control"
                           min="0"
                           step="0.01"
                           value="<?= e($edit['harga'] ?? '') ?>"
                           required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Stok</label>

                    <input type="number"
                           name="stok"
                           class="form-control"
                           min="0"
                           value="<?= e($edit['stok'] ?? '0') ?>"
                           required>
                </div>

                <div class="col-12">
                    <label class="form-label" for="input-gambar">
                        Gambar Produk
                        <span class="text-secondary small">
                            (opsional, JPG/PNG/WEBP, maksimal 2 MB)
                        </span>
                    </label>

                    <div class="d-flex align-items-center gap-3 flex-wrap">

                        <img id="preview-gambar"
                             src="<?= e($urlGambarEdit ?? '') ?>"
                             alt="Pratinjau gambar"
                             style="width: 90px; height: 90px; object-fit: cover; border-radius: 12px; border: 1px solid #dee2e6;"
                             <?= $urlGambarEdit ? '' : 'hidden' ?>>

                        <div class="flex-grow-1" style="min-width: 220px;">

                            <input type="file"
                                   id="input-gambar"
                                   name="gambar"
                                   class="form-control"
                                   accept="image/jpeg,image/png,image/webp">

                            <?php if ($urlGambarEdit): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="hapus_gambar"
                                           value="1"
                                           id="hapus-gambar">

                                    <label class="form-check-label small" for="hapus-gambar">
                                        Hapus gambar saat ini
                                    </label>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

            </div>

            <div class="mt-3 d-flex gap-2">

                <button class="btn btn-dark">
                    <?= $edit ? 'Simpan Perubahan' : 'Tambah Produk' ?>
                </button>

                <?php if ($edit): ?>
                    <a href="index.php?page=produk"
                       class="btn btn-light border">
                        Batal
                    </a>
                <?php endif; ?>

            </div>

        </form>

    </div>
</div>


<div class="content-card">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0">Daftar Produk</h5>
    </div>

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0">

            <thead>
                <tr>
                    <th style="width: 70px;">Gambar</th>
                    <th>Kode</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($data as $row): ?>

                    <?php $urlGambar = url_gambar_produk($row['gambar'] ?? null); ?>

                    <tr>

                        <td>
                            <?php if ($urlGambar): ?>
                                <img src="<?= e($urlGambar) ?>"
                                     alt="<?= e($row['nama_produk']) ?>"
                                     loading="lazy"
                                     style="width: 48px; height: 48px; object-fit: cover; border-radius: 10px; border: 1px solid #e9ecef;">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center text-secondary fw-bold"
                                     style="width: 48px; height: 48px; border-radius: 10px; background: #f1f3f5; font-size: .85rem;">
                                    <?= e(strtoupper(mb_substr($row['nama_produk'], 0, 2))) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge-soft">
                                <?= e($row['kode_produk']) ?>
                            </span>
                        </td>

                        <td class="fw-semibold">
                            <?= e($row['nama_produk']) ?>
                        </td>

                        <td>
                            <?= e($row['nama_kategori'] ?? '-') ?>
                        </td>

                        <td><?= rupiah($row['harga']) ?></td>

                        <td><?= e($row['stok']) ?></td>

                        <td>
                            <div class="d-flex gap-2">

                                <a href="index.php?page=produk&edit=<?= e($row['id_produk']) ?>"
                                   class="btn btn-sm btn-outline-dark">
                                    Edit
                                </a>

                                <form action="actions.php"
                                      method="POST"
                                      onsubmit="return confirm('Yakin ingin menghapus produk ini?')">

                                    <input type="hidden" name="csrf_token"
                                           value="<?= e(csrf_token()) ?>">

                                    <input type="hidden" name="action"
                                           value="produk_hapus">

                                    <input type="hidden" name="id_produk"
                                           value="<?= e($row['id_produk']) ?>">

                                    <button class="btn btn-sm btn-outline-danger">
                                        Hapus
                                    </button>

                                </form>

                            </div>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$data): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary">
                            Belum ada produk.
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>

        </table>

    </div>
</div>

<script>
    // Pratinjau gambar yang baru dipilih + batas ukuran 2 MB (server tetap memeriksa ulang)
    (function () {
        var input = document.getElementById('input-gambar');
        var preview = document.getElementById('preview-gambar');
        var hapus = document.getElementById('hapus-gambar');

        if (!input || !preview) {
            return;
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];

            if (!file) {
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran gambar maksimal 2 MB.');
                input.value = '';
                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.hidden = false;

            if (hapus) {
                hapus.checked = false;
            }
        });
    })();
</script>
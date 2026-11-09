<?php

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

?>

<div class="content-card mb-4">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0">
            <?= $edit ? 'Edit Produk' : 'Tambah Produk' ?>
        </h5>
    </div>

    <div class="content-card-body">

        <form action="actions.php" method="POST">

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

        <table class="table table-hover mb-0">

            <thead>
                <tr>
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

                    <tr>

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
                        <td colspan="6" class="text-center text-secondary">
                            Belum ada produk.
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>

        </table>

    </div>
</div>
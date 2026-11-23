<?php

$edit = null;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM tbl_pelanggan
        WHERE id_pelanggan = ?
    ");

    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$data = $pdo->query("
    SELECT *
    FROM tbl_pelanggan
    ORDER BY id_pelanggan DESC
")->fetchAll();

?>

<div class="content-card mb-4">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0">
            <?= $edit ? 'Edit Pelanggan' : 'Tambah Pelanggan' ?>
        </h5>
    </div>

    <div class="content-card-body">

        <form action="actions.php" method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= e(csrf_token()) ?>">

            <input type="hidden" name="action"
                   value="pelanggan_simpan">

            <input type="hidden" name="id_pelanggan"
                   value="<?= e($edit['id_pelanggan'] ?? '') ?>">

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Nama Pelanggan</label>

                    <input type="text"
                           name="nama_pelanggan"
                           class="form-control"
                           value="<?= e($edit['nama_pelanggan'] ?? '') ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Kota</label>

                    <input type="text"
                           name="kota"
                           class="form-control"
                           value="<?= e($edit['kota'] ?? '') ?>"
                           required>
                </div>

            </div>

            <div class="mt-3 d-flex gap-2">

                <button class="btn btn-dark">
                    <?= $edit ? 'Simpan Perubahan' : 'Tambah Pelanggan' ?>
                </button>

                <?php if ($edit): ?>
                    <a href="index.php?page=pelanggan"
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
        <h5 class="fw-bold mb-0">Daftar Pelanggan</h5>
    </div>

    <div class="table-responsive">

        <table class="table table-hover mb-0">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Pelanggan</th>
                    <th>Kota</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($data as $row): ?>

                    <tr>
                        <td><?= e($row['id_pelanggan']) ?></td>

                        <td class="fw-semibold">
                            <?= e($row['nama_pelanggan']) ?>
                        </td>

                        <td><?= e($row['kota']) ?></td>

                        <td>
                            <div class="d-flex gap-2">

                                <a href="index.php?page=pelanggan&edit=<?= e($row['id_pelanggan']) ?>"
                                   class="btn btn-sm btn-outline-dark">
                                    Edit
                                </a>

                                <form action="actions.php"
                                      method="POST"
                                      onsubmit="return confirm('Yakin ingin menghapus pelanggan ini?')">

                                    <input type="hidden" name="csrf_token"
                                           value="<?= e(csrf_token()) ?>">

                                    <input type="hidden" name="action"
                                           value="pelanggan_hapus">

                                    <input type="hidden" name="id_pelanggan"
                                           value="<?= e($row['id_pelanggan']) ?>">

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
                        <td colspan="4" class="text-center text-secondary">
                            Belum ada data pelanggan.
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>

        </table>

    </div>
</div>
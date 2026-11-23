<?php

// Halaman admin: mengubah role user (pelanggan, petugas, admin)
$data = $pdo->query("
    SELECT u.id_user, u.username, u.nama_lengkap, u.role, u.dibuat, p.kota
    FROM tbl_user u
    LEFT JOIN tbl_pelanggan p
        ON u.id_pelanggan = p.id_pelanggan
    ORDER BY FIELD(u.role, 'admin', 'petugas', 'pelanggan'), u.nama_lengkap
")->fetchAll();

$daftarRole = ['pelanggan', 'petugas', 'admin'];

?>

<div class="content-card">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0">Kelola User &amp; Role</h5>
    </div>

    <div class="content-card-body pb-0">
        <p class="small text-secondary mb-0">
            Pengguna yang mendaftar otomatis menjadi <strong>pelanggan</strong>.
            Ubah menjadi <strong>petugas</strong> agar bisa mengelola transaksi.
            Role akun Anda sendiri tidak bisa diubah di sini.
        </p>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">

            <thead>
                <tr>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Kota</th>
                    <th>Terdaftar</th>
                    <th>Role</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($data as $row): ?>

                    <?php $akunSendiri = (int) $row['id_user'] === (int) $user['id_user']; ?>

                    <tr>
                        <td class="fw-semibold"><?= e($row['username']) ?></td>
                        <td><?= e($row['nama_lengkap']) ?></td>
                        <td><?= e($row['kota'] ?? '-') ?></td>
                        <td><?= e(date('d/m/Y', strtotime($row['dibuat']))) ?></td>

                        <td>
                            <?php if ($akunSendiri): ?>

                                <span class="badge text-bg-dark text-capitalize">
                                    <?= e($row['role']) ?>
                                </span>
                                <span class="small text-secondary ms-1">(akun Anda)</span>

                            <?php else: ?>

                                <form action="actions.php"
                                      method="POST"
                                      class="d-flex gap-2"
                                      onsubmit="return confirm('Ubah role <?= e($row['username']) ?>?')">

                                    <input type="hidden" name="csrf_token"
                                           value="<?= e(csrf_token()) ?>">

                                    <input type="hidden" name="action"
                                           value="user_role_ubah">

                                    <input type="hidden" name="id_user"
                                           value="<?= e($row['id_user']) ?>">

                                    <select name="role"
                                            class="form-select form-select-sm"
                                            style="width: 140px">

                                        <?php foreach ($daftarRole as $r): ?>
                                            <option value="<?= e($r) ?>"
                                                <?= $row['role'] === $r ? 'selected' : '' ?>>
                                                <?= e(ucfirst($r)) ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>

                                    <button class="btn btn-sm btn-outline-dark">
                                        Simpan
                                    </button>

                                </form>

                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (!$data): ?>
                    <tr>
                        <td colspan="5" class="text-center text-secondary">
                            Belum ada user.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>

        </table>
    </div>
</div>
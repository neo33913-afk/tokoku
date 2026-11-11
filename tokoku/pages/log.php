<?php

$data = $pdo->query("
    SELECT id_log, aktivitas, keterangan, waktu
    FROM tbl_log
    ORDER BY id_log DESC
")->fetchAll();

?>

<div class="content-card">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0">Riwayat Aktivitas Database</h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">

            <thead>
                <tr>
                    <th>ID Log</th>
                    <th>Aktivitas</th>
                    <th>Keterangan</th>
                    <th>Waktu</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?= e($row['id_log']) ?></td>
                        <td>
                            <span class="badge-soft">
                                <?= e($row['aktivitas']) ?>
                            </span>
                        </td>
                        <td><?= e($row['keterangan']) ?></td>
                        <td><?= e($row['waktu']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$data): ?>
                    <tr>
                        <td colspan="4" class="text-center text-secondary">
                            Belum ada aktivitas yang tercatat.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>

        </table>
    </div>
</div>
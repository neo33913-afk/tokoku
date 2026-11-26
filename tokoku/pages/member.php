<?php

/**
 * Halaman Member (satu file).
 * Simpan sebagai: pages/member.php
 * Isi: daftarkan member, daftar member + pencarian, kartu member (pop up) + cetak,
 *      dan pemasangan kartu RFID ke member (scan kartu di pop up RFID).
 * Form diproses di file ini sendiri, jadi actions.php tidak perlu diubah.
 */

if (!function_exists('generate_no_member')) {
    function generate_no_member(PDO $pdo): string
    {
        $n = (int) $pdo->query("
            SELECT IFNULL(MAX(id_member), 0) + 1 FROM member
        ")->fetchColumn();

        return 'MBR-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}

$pesan = null; // [tipe, teks]

// =========================
// PROSES FORM
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi_member'] ?? '') !== '') {

    try {
        verify_csrf();

        if (!in_array(user_role(), ['admin', 'petugas'], true)) {
            throw new RuntimeException('Anda tidak punya akses untuk aksi ini.');
        }

        $aksi = $_POST['aksi_member'];

        if ($aksi === 'tambah') {

            $idPelanggan = (int) ($_POST['id_pelanggan'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT nama_pelanggan
                FROM tbl_pelanggan
                WHERE id_pelanggan = ?
            ");
            $stmt->execute([$idPelanggan]);
            $pel = $stmt->fetch();

            if (!$pel) {
                throw new RuntimeException('Pilih pelanggan terlebih dahulu.');
            }

            $stmt = $pdo->prepare("SELECT 1 FROM member WHERE id_pelanggan = ?");
            $stmt->execute([$idPelanggan]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException('Pelanggan ini sudah menjadi member.');
            }

            $noMember = generate_no_member($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO member (id_pelanggan, no_member)
                VALUES (?, ?)
            ");
            $stmt->execute([$idPelanggan, $noMember]);

            log_activity(
                $pdo,
                'INSERT MEMBER',
                "Mendaftarkan {$pel['nama_pelanggan']} sebagai member $noMember"
            );

            $pesan = ['success', "{$pel['nama_pelanggan']} terdaftar sebagai member ($noMember)."];

        } elseif ($aksi === 'status') {

            $idMember = (int) ($_POST['id_member'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT no_member, status
                FROM member
                WHERE id_member = ?
            ");
            $stmt->execute([$idMember]);
            $mb = $stmt->fetch();

            if (!$mb) {
                throw new RuntimeException('Member tidak ditemukan.');
            }

            $statusBaru = $mb['status'] === 'aktif' ? 'nonaktif' : 'aktif';

            $pdo->prepare("UPDATE member SET status = ? WHERE id_member = ?")
                ->execute([$statusBaru, $idMember]);

            log_activity(
                $pdo,
                'UPDATE MEMBER',
                "Member {$mb['no_member']} menjadi $statusBaru"
            );

            $pesan = ['success', "Member {$mb['no_member']} sekarang $statusBaru."];

        } elseif ($aksi === 'rfid') {

            $idMember = (int) ($_POST['id_member'] ?? 0);
            $uid = strtoupper(trim($_POST['rfid_uid'] ?? ''));

            // Kosong = melepas kartu dari member
            if ($uid !== '' && !preg_match('/^[A-Z0-9]{4,30}$/', $uid)) {
                throw new RuntimeException('Kode RFID tidak valid (4-30 huruf/angka).');
            }

            $stmt = $pdo->prepare("SELECT no_member FROM member WHERE id_member = ?");
            $stmt->execute([$idMember]);
            $mb = $stmt->fetch();

            if (!$mb) {
                throw new RuntimeException('Member tidak ditemukan.');
            }

            try {
                $pdo->prepare("UPDATE member SET rfid_uid = ? WHERE id_member = ?")
                    ->execute([$uid !== '' ? $uid : null, $idMember]);
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    throw new RuntimeException('Kartu RFID ini sudah dipakai member lain.');
                }

                throw $ex;
            }

            log_activity(
                $pdo,
                'UPDATE MEMBER',
                $uid !== ''
                    ? "Memasang kartu RFID ke member {$mb['no_member']}"
                    : "Melepas kartu RFID dari member {$mb['no_member']}"
            );

            $pesan = ['success', $uid !== ''
                ? "Kartu RFID dipasang ke member {$mb['no_member']}."
                : "Kartu RFID member {$mb['no_member']} dilepas."];

        } else {
            throw new RuntimeException('Aksi tidak dikenali.');
        }

    } catch (PDOException $ex) {

        error_log('[Tokoku] ' . $ex->getMessage());

        $pesan = ['danger', $ex->getCode() === '23000'
            ? 'Pelanggan ini sudah menjadi member, atau nomor member bentrok. Coba lagi.'
            : 'Terjadi kesalahan pada database. Silakan coba lagi.'];

    } catch (RuntimeException $ex) {

        $pesan = ['danger', $ex->getMessage()];
    }
}

// =========================
// DATA
// =========================
$belumMember = $pdo->query("
    SELECT id_pelanggan, nama_pelanggan, kota
    FROM tbl_pelanggan
    WHERE id_pelanggan NOT IN (SELECT id_pelanggan FROM member)
    ORDER BY nama_pelanggan
")->fetchAll();

$data = $pdo->query("
    SELECT m.id_member, m.no_member, m.rfid_uid, m.status, m.tgl_daftar,
           p.nama_pelanggan, p.kota
    FROM member m
    JOIN tbl_pelanggan p ON p.id_pelanggan = m.id_pelanggan
    ORDER BY m.id_member DESC
")->fetchAll();

?>

<style>
    .kartu-member {
        width: 85.6mm;
        max-width: 100%;
        height: 54mm;
        padding: 14px 16px;
        box-sizing: border-box;
        border-radius: 12px;
        color: #fff;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 6px 18px rgba(0, 0, 0, .25);
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .km-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
    }

    .km-label {
        font-size: 11px;
        background: rgba(255, 255, 255, .25);
        padding: 2px 8px;
        border-radius: 99px;
    }

    .km-nama {
        font-size: 18px;
        font-weight: 600;
        line-height: 1.2;
    }

    .km-no {
        font-family: monospace;
        letter-spacing: 2px;
        font-size: 15px;
    }

    .km-foot {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        font-size: 11px;
    }

    #km-qr {
        background: #fff;
        padding: 3px;
        border-radius: 4px;
        line-height: 0;
    }

    /* Saat cetak: hanya kartu yang tampil */
    @media print {
        body * {
            visibility: hidden;
        }

        #kartu-member,
        #kartu-member * {
            visibility: visible;
        }

        #kartu-member {
            position: fixed;
            top: 0;
            left: 0;
            box-shadow: none;
        }

        @page {
            margin: 10mm;
        }
    }
</style>


<?php if ($pesan): ?>
    <div class="alert alert-<?= e($pesan[0]) ?> alert-dismissible fade show" role="alert">
        <?= e($pesan[1]) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
<?php endif; ?>


<!-- ===================== DAFTARKAN MEMBER ===================== -->
<div class="content-card mb-4">

    <div class="content-card-header">
        <h5 class="fw-bold mb-0">Daftarkan Member</h5>
    </div>

    <div class="content-card-body">

        <form action="index.php?page=member" method="POST">

            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="aksi_member" value="tambah">

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Pelanggan</label>

                    <select name="id_pelanggan" class="form-select" required>
                        <option value="">-- Pilih pelanggan --</option>

                        <?php foreach ($belumMember as $p): ?>
                            <option value="<?= e($p['id_pelanggan']) ?>">
                                <?= e($p['nama_pelanggan']) ?> (<?= e($p['kota']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (!$belumMember): ?>
                        <div class="form-text">Semua pelanggan sudah menjadi member.</div>
                    <?php endif; ?>
                </div>
            </div>

            <button class="btn btn-dark mt-3" <?= $belumMember ? '' : 'disabled' ?>>
                Daftarkan Member
            </button>

        </form>

    </div>
</div>


<!-- ===================== DAFTAR MEMBER ===================== -->
<div class="content-card">

    <div class="content-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="fw-bold mb-0">Daftar Member</h5>

        <input type="search"
               id="cari-member"
               class="form-control form-control-sm"
               style="max-width: 260px"
               placeholder="Cari nama, kota, atau nomor..."
               autocomplete="off">
    </div>

    <div class="table-responsive">

        <table class="table table-hover mb-0">

            <thead>
                <tr>
                    <th>No. Member</th>
                    <th>Nama</th>
                    <th>Kota</th>
                    <th>Terdaftar</th>
                    <th>RFID</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($data as $row): ?>

                    <tr class="baris-member">

                        <td class="fw-semibold"><?= e($row['no_member']) ?></td>

                        <td><?= e($row['nama_pelanggan']) ?></td>

                        <td><?= e($row['kota']) ?></td>

                        <td><?= e(date('d/m/Y', strtotime($row['tgl_daftar']))) ?></td>

                        <td>
                            <?php if (!empty($row['rfid_uid'])): ?>
                                <code><?= e($row['rfid_uid']) ?></code>
                            <?php else: ?>
                                <span class="text-secondary small">Belum dipasang</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge text-bg-<?= $row['status'] === 'aktif' ? 'success' : 'secondary' ?>">
                                <?= e($row['status']) ?>
                            </span>
                        </td>

                        <td>
                            <div class="d-flex gap-2">

                                <button type="button"
                                        class="btn btn-sm btn-outline-dark"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modal-kartu"
                                        data-nama="<?= e($row['nama_pelanggan']) ?>"
                                        data-no="<?= e($row['no_member']) ?>"
                                        data-sejak="<?= e(date('d/m/Y', strtotime($row['tgl_daftar']))) ?>">
                                    Kartu
                                </button>

                                <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modal-rfid"
                                        data-id="<?= e($row['id_member']) ?>"
                                        data-nama="<?= e($row['nama_pelanggan']) ?>"
                                        data-uid="<?= e($row['rfid_uid'] ?? '') ?>">
                                    <i class="bi bi-broadcast"></i> RFID
                                </button>

                                <form action="index.php?page=member" method="POST">

                                    <input type="hidden" name="csrf_token"
                                           value="<?= e(csrf_token()) ?>">

                                    <input type="hidden" name="aksi_member" value="status">

                                    <input type="hidden" name="id_member"
                                           value="<?= e($row['id_member']) ?>">

                                    <button class="btn btn-sm btn-outline-secondary">
                                        <?= $row['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>

                                </form>

                            </div>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$data): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary">
                            Belum ada member.
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>

        </table>

    </div>
</div>


<!-- ===================== POP UP KARTU ===================== -->
<div class="modal fade" id="modal-kartu" tabindex="-1" aria-labelledby="judul-kartu" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="judul-kartu">Kartu Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body d-flex justify-content-center">

                <div class="kartu-member" id="kartu-member">

                    <div class="km-head">
                        <strong>TOKOKU</strong>
                        <span class="km-label">MEMBER</span>
                    </div>

                    <div class="km-nama" id="km-nama"></div>

                    <div class="km-no" id="km-no"></div>

                    <div class="km-foot">
                        <span>Sejak <span id="km-sejak"></span></span>
                        <div id="km-qr"></div>
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-dark" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Cetak Kartu
                </button>
            </div>

        </div>
    </div>
</div>


<!-- ===================== POP UP PASANG RFID ===================== -->
<div class="modal fade" id="modal-rfid" tabindex="-1" aria-labelledby="judul-rfid" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form action="index.php?page=member" method="POST" autocomplete="off">

                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="aksi_member" value="rfid">
                <input type="hidden" name="id_member" id="rfid-id-member" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="judul-rfid">Pasang Kartu RFID</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">

                    <p class="mb-2">
                        Member: <strong id="rfid-nama"></strong>
                    </p>

                    <label class="form-label" for="rfid-uid">Kode kartu RFID</label>

                    <input type="text"
                           name="rfid_uid"
                           id="rfid-uid"
                           class="form-control form-control-lg"
                           placeholder="Tempelkan kartu pada reader..."
                           maxlength="30">

                    <div class="form-text">
                        Klik kolom di atas, lalu tempelkan kartu pada reader. Kode terisi otomatis
                        dan form langsung tersimpan. Kosongkan kolom lalu simpan untuk melepas kartu.
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-dark">Simpan</button>
                </div>

            </form>

        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        // Cegah form terkirim ulang saat halaman di-refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Isi kartu saat pop up dibuka
        var modal = document.getElementById('modal-kartu');
        var qrBox = document.getElementById('km-qr');

        modal.addEventListener('show.bs.modal', function (ev) {

            var tombol = ev.relatedTarget;

            if (!tombol) {
                return;
            }

            var no = tombol.getAttribute('data-no') || '';

            document.getElementById('km-nama').textContent = tombol.getAttribute('data-nama') || '';
            document.getElementById('km-no').textContent = no;
            document.getElementById('km-sejak').textContent = tombol.getAttribute('data-sejak') || '';

            qrBox.innerHTML = '';

            if (window.QRCode) {
                new QRCode(qrBox, {text: no, width: 56, height: 56});
            }
        });

        // Pop up pasang RFID: isi data member, lalu fokus ke kolom supaya siap scan
        var modalRfid = document.getElementById('modal-rfid');
        var inputUid = document.getElementById('rfid-uid');

        modalRfid.addEventListener('show.bs.modal', function (ev) {
            var tombol = ev.relatedTarget;

            if (!tombol) {
                return;
            }

            document.getElementById('rfid-id-member').value = tombol.getAttribute('data-id') || '';
            document.getElementById('rfid-nama').textContent = tombol.getAttribute('data-nama') || '';
            inputUid.value = tombol.getAttribute('data-uid') || '';
        });

        modalRfid.addEventListener('shown.bs.modal', function () {
            inputUid.focus();
            inputUid.select();
        });

        // Pencarian di tabel member
        var cari = document.getElementById('cari-member');
        var baris = document.querySelectorAll('.baris-member');

        cari.addEventListener('input', function () {
            var kata = cari.value.trim().toLowerCase();

            baris.forEach(function (tr) {
                tr.hidden = kata !== '' && tr.textContent.toLowerCase().indexOf(kata) === -1;
            });
        });
    });
</script>
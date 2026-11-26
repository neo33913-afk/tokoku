<?php

/**
 * Upload gambar produk.
 * File disimpan di uploads/produk/ dengan nama acak (nama asli dari user tidak dipakai).
 */

const GAMBAR_MAKS_BYTE = 2097152; // 2 MB
const GAMBAR_POLA_NAMA = '/^[a-f0-9]{32}\.(jpg|png|webp)$/';

function folder_gambar_produk()
{
    $dir = __DIR__ . '/../uploads/produk';

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException(
            'Folder uploads/produk tidak bisa dibuat. Buat manual dan pastikan bisa ditulis.'
        );
    }

    // Lapisan pengaman: skrip PHP tidak pernah dijalankan dari folder upload
    $ht = $dir . '/.htaccess';

    if (!file_exists($ht)) {
        @file_put_contents(
            $ht,
            "<FilesMatch \"\\.(php|phtml|phar|php[0-9])\$\">\n    Require all denied\n</FilesMatch>\n"
        );
    }

    // Mencegah daftar isi folder terlihat
    if (!file_exists($dir . '/index.html')) {
        @file_put_contents($dir . '/index.html', '');
    }

    return $dir;
}

/**
 * Memeriksa file kiriman. Belum memindahkan apa pun.
 * Mengembalikan ['tmp' => ..., 'nama' => ...] atau melempar RuntimeException.
 */
function validasi_gambar_produk(array $file)
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Ukuran gambar terlalu besar (maksimal 2 MB).');
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Gambar gagal diunggah. Silakan coba lagi.');
    }

    $tmp = $file['tmp_name'] ?? '';

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Berkas gambar tidak valid.');
    }

    if (filesize($tmp) > GAMBAR_MAKS_BYTE) {
        throw new RuntimeException('Ukuran gambar terlalu besar (maksimal 2 MB).');
    }

    // Jenis file dibaca dari ISI file, bukan dari nama/ekstensi yang dikirim user
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);

    $ekstensi = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($ekstensi[$mime]) || @getimagesize($tmp) === false) {
        throw new RuntimeException('Format gambar harus JPG, PNG, atau WEBP.');
    }

    return [
        'tmp' => $tmp,
        'nama' => bin2hex(random_bytes(16)) . '.' . $ekstensi[$mime]
    ];
}

function pindahkan_gambar_produk(array $info)
{
    $tujuan = folder_gambar_produk() . '/' . $info['nama'];

    if (!move_uploaded_file($info['tmp'], $tujuan)) {
        throw new RuntimeException('Gambar gagal disimpan di server.');
    }

    @chmod($tujuan, 0644);
}

function hapus_gambar_produk($nama)
{
    if (!$nama || !preg_match(GAMBAR_POLA_NAMA, $nama)) {
        return;
    }

    $path = __DIR__ . '/../uploads/produk/' . $nama;

    if (is_file($path)) {
        @unlink($path);
    }
}

// Alamat gambar untuk <img>, atau null kalau produk belum punya gambar
function url_gambar_produk($nama)
{
    if (!$nama || !preg_match(GAMBAR_POLA_NAMA, $nama)) {
        return null;
    }

    return 'uploads/produk/' . $nama;
}
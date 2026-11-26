<?php

// Kunci Duitku: dashboard Duitku > Proyek > pilih proyek (sandbox) > Merchant Code dan API Key.
// Saat mengembangkan pakai proyek SANDBOX. API Key bersifat rahasia: jangan dibagikan atau di-upload ke GitHub.
const DUITKU_MERCHANT_CODE = 'DS38593';
const DUITKU_API_KEY = '5d2bf4f667c5c57540a9047513fbc991';

// false = sandbox (uji coba), true = produksi (uang sungguhan)
const DUITKU_PRODUKSI = false;

// Kode metode pembayaran QRIS yang aktif di proyek Duitku lu.
// Lihat daftar kode di dokumentasi Duitku (bagian metode pembayaran) dan pastikan channel QRIS-nya aktif di proyek.
const DUITKU_METODE = 'SP';

// Batas waktu pelanggan membayar (menit)
const DUITKU_MENIT_KADALUARSA = 15;

// Duitku mewajibkan email pembeli; dipakai email bawaan ini karena data pelanggan tidak menyimpan email
const DUITKU_EMAIL = 'pelanggan@tokoku.id';

// Alamat yang dikirim ke Duitku. Callback hanya bisa dipanggil Duitku bila alamatnya bisa dijangkau
// dari internet (hosting atau ngrok). Di localhost pembayaran tetap terdeteksi lewat pengecekan berkala.
// Kalau Duitku menolak alamat localhost, ganti dengan alamat publik.
const DUITKU_CALLBACK_URL = 'http://localhost/tokoku/duitku_callback.php';
const DUITKU_RETURN_URL = 'http://localhost/tokoku/index.php?page=riwayat';
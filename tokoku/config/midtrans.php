<?php

// Kunci Midtrans: dashboard Midtrans > Settings > Access Keys.
// Saat mengembangkan pakai kunci SANDBOX. Server Key bersifat rahasia: jangan dibagikan atau di-upload ke GitHub.
const MIDTRANS_SERVER_KEY = 'SB-Mid-server-NkdxxRMiRM-cTO9M~70OzKcI';

// false = sandbox (uji coba), true = produksi (uang sungguhan)
const MIDTRANS_PRODUKSI = false;

// Batas waktu pelanggan membayar QRIS
const MIDTRANS_MENIT_KADALUARSA = 15;
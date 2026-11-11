// Halaman Transaksi (tampilan kasir): pencarian produk + keranjang
document.addEventListener('DOMContentLoaded', function () {

    const dataEl = document.getElementById('data-produk');
    const daftarProdukEl = document.getElementById('daftar-produk');

    // Bukan halaman transaksi, tidak perlu melakukan apa-apa
    if (!dataEl || !daftarProdukEl) {
        return;
    }

    const KUNCI = 'tokoku_keranjang';

    const inputCari = document.getElementById('cari-produk');
    const infoCari = document.getElementById('info-pencarian');
    const daftarKeranjangEl = document.getElementById('daftar-keranjang');
    const kosongEl = document.getElementById('keranjang-kosong');
    const inputTersembunyi = document.getElementById('input-tersembunyi');
    const totalEl = document.getElementById('total-keranjang');
    const jumlahEl = document.getElementById('jumlah-item');
    const tombolBayar = document.getElementById('tombol-bayar');
    const tombolKosongkan = document.getElementById('kosongkan-keranjang');
    const pesanEl = document.getElementById('pesan-keranjang');
    const form = document.getElementById('form-transaksi');

    const semuaProduk = JSON.parse(dataEl.textContent);
    const peta = {};

    semuaProduk.forEach(function (p) {
        peta[p.id_produk] = p;
    });

    // Keranjang berupa daftar [{id, jumlah}] sesuai urutan produk ditambahkan
    let keranjang = [];
    let timerPesan = null;


    // ---------- Fungsi bantu ----------

    function formatRupiah(angka) {
        return 'Rp ' + Math.round(angka).toLocaleString('id-ID');
    }

    // Membuat elemen dengan textContent (aman dari HTML injection)
    function buat(tag, kelas, teks) {
        const node = document.createElement(tag);

        if (kelas) {
            node.className = kelas;
        }

        if (teks !== undefined) {
            node.textContent = teks;
        }

        return node;
    }

    function tampilPesan(teks) {
        pesanEl.textContent = teks;
        pesanEl.hidden = false;

        clearTimeout(timerPesan);
        timerPesan = setTimeout(function () {
            pesanEl.hidden = true;
        }, 3000);
    }

    function cariItem(id) {
        return keranjang.find(function (item) {
            return item.id === id;
        });
    }


    // ---------- Penyimpanan keranjang di browser ----------

    function simpan() {
        try {
            localStorage.setItem(KUNCI, JSON.stringify(keranjang));
        } catch (e) {
            // Abaikan: keranjang tetap jalan, hanya tidak tersimpan
        }
    }

    function muat() {
        try {
            const tersimpan = JSON.parse(localStorage.getItem(KUNCI) || '[]');

            if (!Array.isArray(tersimpan)) {
                return;
            }

            tersimpan.forEach(function (item) {
                const p = peta[item.id];
                const jumlah = parseInt(item.jumlah, 10);

                // Buang produk yang sudah tidak ada / stok habis, sesuaikan jumlah dengan stok terbaru
                if (p && p.stok > 0 && jumlah > 0 && !cariItem(p.id_produk)) {
                    keranjang.push({
                        id: p.id_produk,
                        jumlah: Math.min(jumlah, p.stok)
                    });
                }
            });
        } catch (e) {
            keranjang = [];
        }
    }


    // ---------- Daftar produk + pencarian ----------

    const kartu = {};

    semuaProduk.forEach(function (p) {
        const kolom = buat('div', 'col-sm-6 col-xl-4');
        const tombol = buat('button', 'produk-card card w-100 h-100 text-start p-2');
        tombol.type = 'button';

        const atas = buat('div', 'd-flex justify-content-between align-items-start gap-2');
        atas.appendChild(buat('span', 'badge-soft', p.kode_produk));

        const lencana = buat('span', 'badge text-bg-dark d-none');
        atas.appendChild(lencana);

        tombol.appendChild(atas);
        tombol.appendChild(buat('div', 'fw-semibold mt-1', p.nama_produk));
        tombol.appendChild(buat('div', 'small text-secondary', p.nama_kategori || 'Tanpa kategori'));

        const bawah = buat('div', 'd-flex justify-content-between align-items-center mt-1');
        bawah.appendChild(buat('span', 'fw-semibold', formatRupiah(p.harga)));
        bawah.appendChild(buat(
            'span',
            'small ' + (p.stok > 0 ? 'text-secondary' : 'text-danger'),
            p.stok > 0 ? 'Stok ' + p.stok : 'Stok habis'
        ));
        tombol.appendChild(bawah);

        if (p.stok <= 0) {
            tombol.disabled = true;
        }

        tombol.addEventListener('click', function () {
            tambah(p.id_produk);
        });

        kolom.appendChild(tombol);
        daftarProdukEl.appendChild(kolom);

        kartu[p.id_produk] = { kolom: kolom, lencana: lencana };
    });

    // Semua kata yang diketik harus ada di nama, kode, atau kategori
    function cocok(p, kata) {
        if (!kata) {
            return true;
        }

        const teks = (
            p.nama_produk + ' ' + p.kode_produk + ' ' + (p.nama_kategori || '')
        ).toLowerCase();

        return kata.toLowerCase().split(/\s+/).every(function (k) {
            return teks.indexOf(k) !== -1;
        });
    }

    function filterProduk() {
        const kata = inputCari.value.trim();
        let ditemukan = 0;

        semuaProduk.forEach(function (p) {
            const tampil = cocok(p, kata);
            kartu[p.id_produk].kolom.hidden = !tampil;

            if (tampil) {
                ditemukan++;
            }
        });

        infoCari.textContent = ditemukan === 0
            ? 'Produk tidak ditemukan.'
            : ditemukan + ' produk';
    }

    inputCari.addEventListener('input', filterProduk);

    // Enter: tambahkan produk dengan kode yang persis sama, atau hasil pertama yang stoknya ada
    inputCari.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();

        const kata = inputCari.value.trim().toLowerCase();

        if (!kata) {
            return;
        }

        let target = semuaProduk.find(function (p) {
            return p.kode_produk.toLowerCase() === kata && p.stok > 0;
        });

        if (!target) {
            target = semuaProduk.find(function (p) {
                return p.stok > 0 && cocok(p, kata);
            });
        }

        if (!target) {
            tampilPesan('Tidak ada produk dengan stok tersedia untuk pencarian itu.');
            return;
        }

        tambah(target.id_produk);
        inputCari.value = '';
        filterProduk();
    });


    // ---------- Aksi keranjang ----------

    function tambah(id) {
        const item = cariItem(id);
        const jumlahBaru = (item ? item.jumlah : 0) + 1;

        setJumlah(id, jumlahBaru);

        // Gulir ke bawah supaya item terbaru terlihat
        daftarKeranjangEl.scrollTop = daftarKeranjangEl.scrollHeight;
    }

    function setJumlah(id, nilai) {
        const p = peta[id];

        if (!p) {
            return;
        }

        const jumlah = parseInt(nilai, 10);

        // Input kosong / bukan angka: kembalikan tampilan seperti semula
        if (isNaN(jumlah)) {
            renderKeranjang();
            return;
        }

        let item = cariItem(id);

        if (jumlah <= 0) {
            keranjang = keranjang.filter(function (i) {
                return i.id !== id;
            });
        } else {
            let final = jumlah;

            if (jumlah > p.stok) {
                final = p.stok;
                tampilPesan('Stok ' + p.nama_produk + ' hanya ' + p.stok + '.');
            }

            if (item) {
                item.jumlah = final;
            } else {
                keranjang.push({ id: id, jumlah: final });
            }
        }

        simpan();
        renderKeranjang();
    }

    function hapusItem(id) {
        keranjang = keranjang.filter(function (i) {
            return i.id !== id;
        });

        simpan();
        renderKeranjang();
    }


    // ---------- Tampilan keranjang ----------

    function renderKeranjang() {
        daftarKeranjangEl.replaceChildren();
        inputTersembunyi.replaceChildren();

        let total = 0;
        let jumlahPcs = 0;

        keranjang.forEach(function (item) {
            const p = peta[item.id];
            const subtotal = p.harga * item.jumlah;

            total += subtotal;
            jumlahPcs += item.jumlah;

            // Baris tampilan
            const baris = buat('div', 'border-bottom py-2');

            const atas = buat('div', 'd-flex justify-content-between gap-2');
            const info = buat('div');
            info.appendChild(buat('div', 'fw-semibold', p.nama_produk));
            info.appendChild(buat('div', 'small text-secondary', formatRupiah(p.harga)));

            const tombolHapus = buat('button', 'btn btn-sm btn-outline-danger align-self-start', '\u00D7');
            tombolHapus.type = 'button';
            tombolHapus.title = 'Hapus dari keranjang';
            tombolHapus.addEventListener('click', function () {
                hapusItem(item.id);
            });

            atas.appendChild(info);
            atas.appendChild(tombolHapus);

            const bawah = buat('div', 'd-flex justify-content-between align-items-center mt-1');

            const kontrol = buat('div', 'input-group input-group-sm kontrol-jumlah');

            const kurang = buat('button', 'btn btn-outline-dark', '\u2212');
            kurang.type = 'button';
            kurang.addEventListener('click', function () {
                setJumlah(item.id, item.jumlah - 1);
            });

            const inputJumlah = buat('input', 'form-control text-center');
            inputJumlah.type = 'number';
            inputJumlah.min = 1;
            inputJumlah.max = p.stok;
            inputJumlah.value = item.jumlah;
            inputJumlah.addEventListener('change', function () {
                setJumlah(item.id, inputJumlah.value);
            });

            const tambahBtn = buat('button', 'btn btn-outline-dark', '+');
            tambahBtn.type = 'button';
            tambahBtn.addEventListener('click', function () {
                setJumlah(item.id, item.jumlah + 1);
            });

            kontrol.appendChild(kurang);
            kontrol.appendChild(inputJumlah);
            kontrol.appendChild(tambahBtn);

            bawah.appendChild(kontrol);
            bawah.appendChild(buat('span', 'fw-semibold', formatRupiah(subtotal)));

            baris.appendChild(atas);
            baris.appendChild(bawah);
            daftarKeranjangEl.appendChild(baris);

            // Input tersembunyi yang dikirim ke actions.php
            const idInput = buat('input');
            idInput.type = 'hidden';
            idInput.name = 'id_produk[]';
            idInput.value = item.id;

            const jumlahInput = buat('input');
            jumlahInput.type = 'hidden';
            jumlahInput.name = 'jumlah[]';
            jumlahInput.value = item.jumlah;

            inputTersembunyi.appendChild(idInput);
            inputTersembunyi.appendChild(jumlahInput);
        });

        kosongEl.hidden = keranjang.length > 0;
        tombolKosongkan.hidden = keranjang.length === 0;
        tombolBayar.disabled = keranjang.length === 0;
        tombolBayar.textContent = 'Bayar & Cetak Struk';

        totalEl.textContent = formatRupiah(total);
        jumlahEl.textContent = jumlahPcs;

        // Lencana "xN" pada kartu produk yang sudah ada di keranjang
        semuaProduk.forEach(function (p) {
            const lencana = kartu[p.id_produk].lencana;
            const item = cariItem(p.id_produk);

            if (item) {
                lencana.textContent = '\u00D7' + item.jumlah;
                lencana.classList.remove('d-none');
            } else {
                lencana.classList.add('d-none');
            }
        });
    }


    // ---------- Tombol dan form ----------

    tombolKosongkan.addEventListener('click', function () {
        if (confirm('Kosongkan semua isi keranjang?')) {
            keranjang = [];
            simpan();
            renderKeranjang();
        }
    });

    form.addEventListener('submit', function (event) {
        if (keranjang.length === 0) {
            event.preventDefault();
            tampilPesan('Keranjang masih kosong.');
            return;
        }

        if (!idPelangganEl.value) {
            event.preventDefault();
            inputPelanggan.classList.add('is-invalid');
            inputPelanggan.focus();
            tampilPesan('Pilih pelanggan terlebih dahulu.');
            return;
        }

        // Cegah klik ganda; keranjang dikosongkan oleh halaman struk setelah berhasil
        tombolBayar.disabled = true;
        tombolBayar.textContent = 'Memproses...';
    });

    // Saat kembali ke halaman ini lewat tombol Back, tampilkan keadaan terbaru
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            renderKeranjang();
        }
    });


    // ---------- Pencarian pelanggan ----------

    const KUNCI_PELANGGAN = 'tokoku_pelanggan';
    const MAKS_HASIL = 8;

    const dataPelangganEl = document.getElementById('data-pelanggan');
    const kotakPelanggan = document.getElementById('pelanggan-box');
    const inputPelanggan = document.getElementById('cari-pelanggan');
    const idPelangganEl = document.getElementById('id-pelanggan');
    const hasilPelangganEl = document.getElementById('hasil-pelanggan');

    const semuaPelanggan = JSON.parse(dataPelangganEl.textContent);

    let hasilSaatIni = [];
    let indeksAktif = -1;

    function labelPelanggan(p) {
        return p.nama_pelanggan + (p.kota ? ' \u2014 ' + p.kota : '');
    }

    function simpanPelanggan(id) {
        try {
            if (id) {
                localStorage.setItem(KUNCI_PELANGGAN, String(id));
            } else {
                localStorage.removeItem(KUNCI_PELANGGAN);
            }
        } catch (e) {
            // Abaikan: pilihan tetap jalan, hanya tidak tersimpan
        }
    }

    function tutupHasil() {
        hasilPelangganEl.hidden = true;
        indeksAktif = -1;
    }

    function pilihPelanggan(p) {
        idPelangganEl.value = p.id_pelanggan;
        inputPelanggan.value = labelPelanggan(p);
        inputPelanggan.classList.remove('is-invalid');
        inputPelanggan.classList.add('is-valid');

        simpanPelanggan(p.id_pelanggan);
        tutupHasil();
    }

    function hapusPilihanPelanggan() {
        idPelangganEl.value = '';
        inputPelanggan.classList.remove('is-valid');
        simpanPelanggan(null);
    }

    function renderHasilPelanggan() {
        const kata = inputPelanggan.value.trim().toLowerCase().split(/\s+/).filter(Boolean);

        const cocokSemua = semuaPelanggan.filter(function (p) {
            const teks = (p.nama_pelanggan + ' ' + (p.kota || '')).toLowerCase();

            return kata.every(function (k) {
                return teks.indexOf(k) !== -1;
            });
        });

        hasilSaatIni = cocokSemua.slice(0, MAKS_HASIL);
        hasilPelangganEl.replaceChildren();

        if (hasilSaatIni.length === 0) {
            hasilPelangganEl.appendChild(
                buat('div', 'list-group-item small text-secondary', 'Pelanggan tidak ditemukan.')
            );
        }

        hasilSaatIni.forEach(function (p, i) {
            const tombol = buat(
                'button',
                'list-group-item list-group-item-action py-2' + (i === indeksAktif ? ' active' : '')
            );
            tombol.type = 'button';

            tombol.appendChild(buat('div', 'fw-semibold', p.nama_pelanggan));
            tombol.appendChild(buat('small', 'opacity-75', p.kota || '-'));

            // mousedown (bukan click) supaya terjadi sebelum kolom pencarian kehilangan fokus
            tombol.addEventListener('mousedown', function (event) {
                event.preventDefault();
                pilihPelanggan(p);
            });

            hasilPelangganEl.appendChild(tombol);
        });

        if (cocokSemua.length > MAKS_HASIL) {
            hasilPelangganEl.appendChild(buat(
                'div',
                'list-group-item small text-secondary',
                'Menampilkan ' + MAKS_HASIL + ' dari ' + cocokSemua.length + ' pelanggan. Ketik lebih spesifik.'
            ));
        }

        hasilPelangganEl.hidden = false;
    }

    inputPelanggan.addEventListener('input', function () {
        // Mengetik ulang berarti pilihan sebelumnya dibatalkan
        hapusPilihanPelanggan();
        indeksAktif = -1;
        renderHasilPelanggan();
    });

    inputPelanggan.addEventListener('focus', function () {
        if (idPelangganEl.value) {
            // Sudah ada pelanggan terpilih: blok teksnya supaya mudah diganti
            inputPelanggan.select();
        } else {
            renderHasilPelanggan();
        }
    });

    inputPelanggan.addEventListener('keydown', function (event) {

        if (event.key === 'Enter') {
            // Cegah Enter mengirim form sebelum waktunya
            event.preventDefault();

            if (!hasilPelangganEl.hidden && hasilSaatIni.length > 0) {
                pilihPelanggan(hasilSaatIni[indeksAktif >= 0 ? indeksAktif : 0]);
            }

            return;
        }

        if (event.key === 'Escape') {
            tutupHasil();
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (hasilPelangganEl.hidden) {
                renderHasilPelanggan();
            }

            if (hasilSaatIni.length === 0) {
                return;
            }

            if (event.key === 'ArrowDown') {
                indeksAktif = Math.min(indeksAktif + 1, hasilSaatIni.length - 1);
            } else {
                indeksAktif = Math.max(indeksAktif - 1, 0);
            }

            renderHasilPelanggan();
        }
    });

    // Klik di luar kotak pelanggan menutup daftar hasil
    document.addEventListener('click', function (event) {
        if (!kotakPelanggan.contains(event.target)) {
            tutupHasil();
        }
    });

    function muatPelanggan() {
        try {
            const id = parseInt(localStorage.getItem(KUNCI_PELANGGAN), 10);
            const p = semuaPelanggan.find(function (x) {
                return x.id_pelanggan === id;
            });

            if (p) {
                pilihPelanggan(p);
            }
        } catch (e) {
            // Abaikan
        }
    }


    // ---------- Mulai ----------

    muat();
    muatPelanggan();
    filterProduk();
    renderKeranjang();
    inputCari.focus();
});
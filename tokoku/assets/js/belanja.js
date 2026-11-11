// Halaman Belanja (role pelanggan): katalog, filter kategori, pencarian, keranjang
(function () {
    const root = document.getElementById('belanja');
    const dataEl = document.getElementById('data-belanja');

    // Bukan halaman belanja
    if (!root || !dataEl) {
        return;
    }

    const KUNCI = root.dataset.kunci || 'tokoku_belanja';
    const BOLEH = root.dataset.boleh === '1';

    // Setelah pembelian berhasil server mengarahkan ke ...&struk=ID: kosongkan keranjang.
    // Dijalankan langsung (sebelum struk-popup.js menghapus parameter dari alamat).
    try {
        if (new URLSearchParams(window.location.search).get('struk')) {
            localStorage.removeItem(KUNCI);
        }
    } catch (e) {
        // Abaikan
    }

    const inputCari = document.getElementById('bl-cari');
    const kategoriEl = document.getElementById('bl-kategori');
    const infoEl = document.getElementById('bl-info');
    const gridEl = document.getElementById('bl-grid');
    const kosongEl = document.getElementById('bl-kosong');
    const barEl = document.getElementById('bl-bar');
    const barJumlahEl = document.getElementById('bl-bar-jumlah');
    const barTotalEl = document.getElementById('bl-bar-total');
    const toastEl = document.getElementById('bl-toast');
    const daftarEl = document.getElementById('bl-daftar');
    const keranjangKosongEl = document.getElementById('bl-keranjang-kosong');
    const totalEl = document.getElementById('bl-total');
    const inputEl = document.getElementById('bl-input');
    const tombolBayar = document.getElementById('bl-bayar');
    const form = document.getElementById('bl-form');

    const semuaProduk = JSON.parse(dataEl.textContent);
    const peta = {};

    semuaProduk.forEach(function (p) {
        peta[p.id_produk] = p;
    });

    let keranjang = [];      // [{id, jumlah}]
    let kategoriAktif = '';  // '' = semua
    let timerToast = null;

    // Warna lembut untuk tiap kategori: [latar, huruf]
    const PALET = [
        ['#e8f1ff', '#1c4fa0'],
        ['#e6f7ef', '#17794a'],
        ['#fff4e0', '#a6600a'],
        ['#fde8ee', '#a3294f'],
        ['#efe9ff', '#5a36b0'],
        ['#e3f6f8', '#0f6f7e'],
        ['#f3f4e1', '#6a6b15'],
        ['#f1ece8', '#6b4a35']
    ];


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

    function warnaKategori(nama) {
        let hash = 0;
        const teks = nama || '';

        for (let i = 0; i < teks.length; i++) {
            hash = (hash * 31 + teks.charCodeAt(i)) % 997;
        }

        return PALET[hash % PALET.length];
    }

    function inisial(nama) {
        const kata = nama.trim().split(/\s+/);

        if (kata.length === 1) {
            return kata[0].slice(0, 2).toUpperCase();
        }

        return (kata[0][0] + kata[1][0]).toUpperCase();
    }

    // Gambar produk; kalau gagal dimuat, yang tampil tetap inisial berwarna
    function buatGambar(p) {
        if (!p.gambar) {
            return null;
        }

        const img = buat('img', 'bl-img');
        img.src = p.gambar;
        img.alt = p.nama_produk;
        img.loading = 'lazy';
        img.addEventListener('error', function () {
            img.remove();
        });

        return img;
    }

    // Kotak kecil (gambar atau inisial) untuk daftar keranjang
    function buatThumb(p) {
        const warna = warnaKategori(p.nama_kategori);
        const kotak = buat('div', 'bl-thumb', inisial(p.nama_produk));
        kotak.style.background = warna[0];
        kotak.style.color = warna[1];

        const gambar = buatGambar(p);

        if (gambar) {
            kotak.appendChild(gambar);
        }

        return kotak;
    }

    function tampilPesan(teks) {
        toastEl.textContent = teks;
        toastEl.hidden = false;

        clearTimeout(timerToast);
        timerToast = setTimeout(function () {
            toastEl.hidden = true;
        }, 2500);
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

                // Buang produk yang sudah tidak ada / habis; sesuaikan jumlah dengan stok terbaru
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


    // ---------- Aksi keranjang ----------

    function setJumlah(id, nilai) {
        const p = peta[id];

        if (!p || !BOLEH) {
            return;
        }

        const jumlah = parseInt(nilai, 10);

        if (isNaN(jumlah)) {
            render();
            return;
        }

        const item = cariItem(id);

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
        render();
    }

    function tambah(id) {
        const item = cariItem(id);

        setJumlah(id, (item ? item.jumlah : 0) + 1);
    }


    // ---------- Katalog ----------

    const kartu = {};

    semuaProduk.forEach(function (p) {
        const kolom = buat('div', 'col-6 col-md-4 col-xl-3');
        const card = buat('div', 'bl-card' + (p.stok <= 0 ? ' bl-habis' : ''));

        const warna = warnaKategori(p.nama_kategori);
        const tile = buat('div', 'bl-tile');
        tile.style.background = warna[0];
        tile.style.color = warna[1];
        tile.appendChild(buat('span', '', inisial(p.nama_produk)));

        const gambar = buatGambar(p);

        if (gambar) {
            tile.appendChild(gambar);
        }

        if (p.nama_kategori) {
            tile.appendChild(buat('span', 'bl-kat', p.nama_kategori));
        }

        const body = buat('div', 'bl-body');
        body.appendChild(buat('div', 'bl-nama', p.nama_produk));
        body.appendChild(buat('div', 'bl-harga', formatRupiah(p.harga)));

        if (p.stok <= 0) {
            body.appendChild(buat('div', 'bl-stok', 'Stok habis'));
        } else if (p.stok <= 5) {
            body.appendChild(buat('div', 'bl-stok sedikit', 'Sisa ' + p.stok));
        } else {
            body.appendChild(buat('div', 'bl-stok', 'Stok ' + p.stok));
        }

        const aksi = buat('div', 'bl-aksi');
        body.appendChild(aksi);

        card.appendChild(tile);
        card.appendChild(body);
        kolom.appendChild(card);
        gridEl.appendChild(kolom);

        kartu[p.id_produk] = { kolom: kolom, aksi: aksi };
    });

    // Tombol "Tambah" atau pengatur jumlah di tiap kartu
    function renderAksi(p) {
        const aksi = kartu[p.id_produk].aksi;
        aksi.replaceChildren();

        if (p.stok <= 0 || !BOLEH) {
            const tombol = buat(
                'button',
                'btn btn-light border w-100 btn-sm',
                p.stok <= 0 ? 'Stok habis' : 'Tidak tersedia'
            );
            tombol.type = 'button';
            tombol.disabled = true;
            aksi.appendChild(tombol);
            return;
        }

        const item = cariItem(p.id_produk);

        if (!item) {
            const tombol = buat('button', 'btn btn-dark btn-sm w-100', '+ Tambah');
            tombol.type = 'button';
            tombol.addEventListener('click', function () {
                tambah(p.id_produk);
            });
            aksi.appendChild(tombol);
            return;
        }

        aksi.appendChild(buatPengatur(p, item));
    }

    // Pengatur jumlah (- 1 +), dipakai di kartu dan di keranjang
    function buatPengatur(p, item) {
        const grup = buat('div', 'input-group input-group-sm bl-stepper');

        const kurang = buat('button', 'btn btn-outline-dark', '−');
        kurang.type = 'button';
        kurang.setAttribute('aria-label', 'Kurangi');
        kurang.addEventListener('click', function () {
            setJumlah(p.id_produk, item.jumlah - 1);
        });

        const input = buat('input', 'form-control text-center');
        input.type = 'number';
        input.min = 1;
        input.max = p.stok;
        input.value = item.jumlah;
        input.setAttribute('aria-label', 'Jumlah');
        input.addEventListener('change', function () {
            setJumlah(p.id_produk, input.value);
        });

        const plus = buat('button', 'btn btn-outline-dark', '+');
        plus.type = 'button';
        plus.setAttribute('aria-label', 'Tambah');
        plus.addEventListener('click', function () {
            setJumlah(p.id_produk, item.jumlah + 1);
        });

        grup.appendChild(kurang);
        grup.appendChild(input);
        grup.appendChild(plus);

        return grup;
    }


    // ---------- Kategori + pencarian ----------

    const daftarKategori = [];

    semuaProduk.forEach(function (p) {
        const nama = p.nama_kategori || 'Lainnya';

        if (daftarKategori.indexOf(nama) === -1) {
            daftarKategori.push(nama);
        }
    });

    daftarKategori.sort();

    const chips = [];

    function buatChip(label, nilai) {
        const chip = buat('button', 'bl-chip', label);
        chip.type = 'button';
        chip.dataset.nilai = nilai;

        chip.addEventListener('click', function () {
            kategoriAktif = nilai;
            tandaiChip();
            filter();
        });

        chips.push(chip);
        kategoriEl.appendChild(chip);
    }

    function tandaiChip() {
        chips.forEach(function (chip) {
            chip.classList.toggle('aktif', chip.dataset.nilai === kategoriAktif);
        });
    }

    buatChip('Semua', '');

    daftarKategori.forEach(function (nama) {
        buatChip(nama, nama);
    });

    // Semua kata yang diketik harus ada di nama atau kategori
    function cocok(p, kata) {
        const kategori = p.nama_kategori || 'Lainnya';

        if (kategoriAktif && kategori !== kategoriAktif) {
            return false;
        }

        if (!kata) {
            return true;
        }

        const teks = (p.nama_produk + ' ' + kategori).toLowerCase();

        return kata.toLowerCase().split(/\s+/).every(function (k) {
            return teks.indexOf(k) !== -1;
        });
    }

    function filter() {
        const kata = inputCari.value.trim();
        let ditemukan = 0;

        semuaProduk.forEach(function (p) {
            const tampil = cocok(p, kata);
            kartu[p.id_produk].kolom.hidden = !tampil;

            if (tampil) {
                ditemukan++;
            }
        });

        kosongEl.hidden = ditemukan > 0;
        infoEl.textContent = ditemukan > 0 ? ditemukan + ' produk' : '';
    }

    inputCari.addEventListener('input', filter);


    // ---------- Tampilan keranjang ----------

    function render() {
        daftarEl.replaceChildren();
        inputEl.replaceChildren();

        let total = 0;
        let jumlahPcs = 0;

        keranjang.forEach(function (item) {
            const p = peta[item.id];
            const subtotal = p.harga * item.jumlah;

            total += subtotal;
            jumlahPcs += item.jumlah;

            const baris = buat('div', 'bl-item');

            const atas = buat('div', 'd-flex justify-content-between align-items-start gap-2');
            const kiri = buat('div', 'd-flex align-items-center gap-2');
            const info = buat('div');
            info.appendChild(buat('div', 'fw-semibold', p.nama_produk));
            info.appendChild(buat('div', 'small text-secondary', formatRupiah(p.harga)));

            kiri.appendChild(buatThumb(p));
            kiri.appendChild(info);

            const hapus = buat('button', 'btn btn-sm btn-outline-danger', '×');
            hapus.type = 'button';
            hapus.title = 'Hapus dari keranjang';
            hapus.addEventListener('click', function () {
                setJumlah(item.id, 0);
            });

            atas.appendChild(kiri);
            atas.appendChild(hapus);

            const bawah = buat('div', 'd-flex justify-content-between align-items-center mt-2');

            const pengatur = buatPengatur(p, item);
            pengatur.style.width = '120px';

            bawah.appendChild(pengatur);
            bawah.appendChild(buat('span', 'fw-semibold', formatRupiah(subtotal)));

            baris.appendChild(atas);
            baris.appendChild(bawah);
            daftarEl.appendChild(baris);

            // Input tersembunyi yang dikirim ke actions.php
            const idInput = buat('input');
            idInput.type = 'hidden';
            idInput.name = 'id_produk[]';
            idInput.value = item.id;

            const jumlahInput = buat('input');
            jumlahInput.type = 'hidden';
            jumlahInput.name = 'jumlah[]';
            jumlahInput.value = item.jumlah;

            inputEl.appendChild(idInput);
            inputEl.appendChild(jumlahInput);
        });

        keranjangKosongEl.hidden = keranjang.length > 0;
        tombolBayar.disabled = keranjang.length === 0 || !BOLEH;
        tombolBayar.textContent = 'Beli Sekarang';

        totalEl.textContent = formatRupiah(total);

        barEl.hidden = keranjang.length === 0;
        barJumlahEl.textContent = jumlahPcs;
        barTotalEl.textContent = formatRupiah(total);

        semuaProduk.forEach(renderAksi);
    }

    form.addEventListener('submit', function (event) {
        if (!BOLEH || keranjang.length === 0) {
            event.preventDefault();
            return;
        }

        // Cegah klik ganda; keranjang dikosongkan setelah pembelian berhasil
        tombolBayar.disabled = true;
        tombolBayar.textContent = 'Memproses...';
    });

    // Kembali lewat tombol Back: tampilkan keadaan terbaru
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            render();
        }
    });


    // ---------- Mulai ----------

    muat();
    tandaiChip();
    filter();
    render();
})();
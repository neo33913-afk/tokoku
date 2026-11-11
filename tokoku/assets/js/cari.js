/*
 * Pencarian tabel (di sisi browser, langsung saat mengetik).
 * Kotak pencarian dibuat otomatis di header kartu tabel pada halaman
 * yang terdaftar di KONFIG, tanpa perlu mengubah file halamannya.
 *
 * Halaman dikenali dari <body data-page="...">.
 */
document.addEventListener('DOMContentLoaded', function () {

    var KONFIG = {
        produk: 'Cari nama, kode, atau kategori...',
        pelanggan: 'Cari nama atau kota...',
        user: 'Cari nama, username, atau role...'
    };

    var halaman = document.body.getAttribute('data-page');
    var placeholder = KONFIG[halaman];
    var main = document.querySelector('main');

    if (!placeholder || !main) {
        return;
    }

    // Huruf kecil, tanpa aksen, spasi dirapikan
    function normal(teks) {
        return String(teks)
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    // Teks sebuah sel untuk dicari. Tombol dan menu pilihan tidak ikut dihitung,
    // supaya mencari "edit" atau "admin" tidak cocok ke semua baris.
    function teksSel(td) {
        var klon = td.cloneNode(true);
        var asli = td.querySelectorAll('select');

        klon.querySelectorAll('select').forEach(function (select, i) {
            var terpilih = asli[i].options[asli[i].selectedIndex];
            select.replaceWith(document.createTextNode(terpilih ? ' ' + terpilih.text + ' ' : ''));
        });

        klon.querySelectorAll('button, .btn, option, input, textarea, script, style, template')
            .forEach(function (el) {
                el.remove();
            });

        return klon.textContent;
    }

    var semuaInput = [];

    main.querySelectorAll('table').forEach(function (tabel) {

        var tbody = tabel.querySelector('tbody');

        if (!tbody) {
            return;
        }

        // Kolom "Aksi" tidak ikut dicari
        var kolomAksi = -1;

        tabel.querySelectorAll('thead th').forEach(function (th, i) {
            if (normal(th.textContent) === 'aksi') {
                kolomAksi = i;
            }
        });

        var jumlahKolom = tabel.querySelectorAll('thead th').length || 1;
        var indeks = [];
        var barisPesan = [];

        Array.prototype.forEach.call(tbody.rows, function (tr) {
            var barisPesanBawaan = tr.cells.length === 1 && tr.cells[0].hasAttribute('colspan');

            if (barisPesanBawaan) {
                barisPesan.push(tr);
                return;
            }

            var teks = '';

            Array.prototype.forEach.call(tr.cells, function (td, i) {
                if (i !== kolomAksi) {
                    teks += ' ' + teksSel(td);
                }
            });

            indeks.push({ tr: tr, teks: normal(teks) });
        });


        // ----- Kotak pencarian -----

        var info = document.createElement('small');
        info.className = 'text-secondary text-nowrap';

        var grup = document.createElement('div');
        grup.className = 'input-group input-group-sm cari-box';
        grup.innerHTML =
            '<span class="input-group-text"><i class="bi bi-search"></i></span>' +
            '<input type="search" class="form-control" autocomplete="off">';

        var input = grup.querySelector('input');
        input.placeholder = placeholder;
        input.setAttribute('aria-label', placeholder);

        var pembungkus = document.createElement('div');
        pembungkus.className = 'cari-wrap d-flex align-items-center gap-2';
        pembungkus.appendChild(info);
        pembungkus.appendChild(grup);

        var kartu = tabel.closest('.content-card');
        var header = kartu ? kartu.querySelector('.content-card-header') : null;

        if (header) {
            header.classList.add('d-flex', 'flex-wrap', 'align-items-center', 'justify-content-between', 'gap-2');
            header.appendChild(pembungkus);
        } else {
            var tempat = tabel.closest('.table-responsive') || tabel;
            pembungkus.classList.add('mb-3');
            tempat.parentNode.insertBefore(pembungkus, tempat);
        }


        // ----- Baris "tidak ditemukan" -----

        var barisKosong = document.createElement('tr');
        var selKosong = document.createElement('td');

        barisKosong.hidden = true;
        selKosong.colSpan = jumlahKolom;
        selKosong.className = 'text-center text-secondary py-4';
        barisKosong.appendChild(selKosong);
        tbody.appendChild(barisKosong);


        // ----- Penyaringan -----

        function saring() {
            var kata = normal(input.value).split(' ').filter(Boolean);
            var ditemukan = 0;

            indeks.forEach(function (item) {
                var cocok = kata.every(function (k) {
                    return item.teks.indexOf(k) !== -1;
                });

                item.tr.hidden = !cocok;

                if (cocok) {
                    ditemukan++;
                }
            });

            // Pesan bawaan halaman ("Belum ada data") disembunyikan saat mencari
            barisPesan.forEach(function (tr) {
                tr.hidden = kata.length > 0;
            });

            if (kata.length > 0 && ditemukan === 0) {
                selKosong.textContent = 'Tidak ada hasil untuk \u201C' + input.value.trim() + '\u201D.';
                barisKosong.hidden = false;
            } else {
                barisKosong.hidden = true;
            }

            info.textContent = kata.length > 0
                ? ditemukan + ' dari ' + indeks.length
                : '';
        }

        input.addEventListener('input', saring);

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && input.value !== '') {
                input.value = '';
                saring();
            }
        });

        semuaInput.push(input);
    });


    // Tekan "/" untuk langsung fokus ke kotak pencarian
    document.addEventListener('keydown', function (event) {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        var aktif = document.activeElement;
        var sedangMengetik = aktif && /^(INPUT|TEXTAREA|SELECT)$/.test(aktif.tagName);

        if (sedangMengetik || semuaInput.length === 0) {
            return;
        }

        event.preventDefault();
        semuaInput[0].focus();
    });
});
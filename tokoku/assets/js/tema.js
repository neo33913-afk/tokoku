/*
 * Tema terang/gelap, sidebar, dan tombol "lihat password".
 * Dipanggil di <head> (sebelum isi halaman tampil) supaya tema dan status
 * sidebar langsung benar dan layar tidak berkedip.
 */
(function () {

    var KUNCI_TEMA = 'tokoku_tema';
    var KUNCI_SIDEBAR = 'tokoku_sidebar';
    var html = document.documentElement;


    // ---------- Tema ----------

    function temaTersimpan() {
        try {
            var t = localStorage.getItem(KUNCI_TEMA);
            return (t === 'dark' || t === 'light') ? t : null;
        } catch (e) {
            return null;
        }
    }

    // Kunjungan pertama mengikuti pengaturan perangkat (terang/gelap)
    function temaAwal() {
        var t = temaTersimpan();

        if (t) {
            return t;
        }

        var gelap = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

        return gelap ? 'dark' : 'light';
    }

    function terapkanTema(tema) {
        html.setAttribute('data-bs-theme', tema);

        document.querySelectorAll('[data-tema-toggle]').forEach(function (tombol) {
            tombol.setAttribute('aria-pressed', tema === 'dark' ? 'true' : 'false');
            tombol.setAttribute(
                'title',
                tema === 'dark' ? 'Ganti ke tema terang' : 'Ganti ke tema gelap'
            );
        });
    }

    terapkanTema(temaAwal());


    // ---------- Sidebar: status "disematkan" dipulihkan sebelum halaman tampil ----------

    function sidebarDisematkan() {
        try {
            return localStorage.getItem(KUNCI_SIDEBAR) === 'pinned';
        } catch (e) {
            return false;
        }
    }

    if (sidebarDisematkan()) {
        html.classList.add('sidebar-pinned');
    }

    function aturSidebarTerbuka(buka) {
        if (!document.body) {
            return;
        }

        document.body.classList.toggle('sidebar-open', buka);

        document.querySelectorAll('[data-sidebar-toggle][aria-expanded]').forEach(function (tombol) {
            tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        terapkanTema(html.getAttribute('data-bs-theme') || 'light');

        document.querySelectorAll('[data-sidebar-pin]').forEach(function (tombol) {
            tombol.setAttribute(
                'aria-pressed',
                html.classList.contains('sidebar-pinned') ? 'true' : 'false'
            );
        });
    });


    // ---------- Klik ----------

    document.addEventListener('click', function (event) {

        // Ganti tema
        var tombolTema = event.target.closest('[data-tema-toggle]');

        if (tombolTema) {
            var baru = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';

            try {
                localStorage.setItem(KUNCI_TEMA, baru);
            } catch (e) {
                // Abaikan: tema tetap berganti, hanya tidak diingat
            }

            terapkanTema(baru);
            return;
        }

        // Sematkan / lepas sematan sidebar (layar lebar)
        var tombolPin = event.target.closest('[data-sidebar-pin]');

        if (tombolPin) {
            var disematkan = html.classList.toggle('sidebar-pinned');

            try {
                if (disematkan) {
                    localStorage.setItem(KUNCI_SIDEBAR, 'pinned');
                } else {
                    localStorage.removeItem(KUNCI_SIDEBAR);
                }
            } catch (e) {
                // Abaikan
            }

            tombolPin.setAttribute('aria-pressed', disematkan ? 'true' : 'false');
            return;
        }

        // Buka / tutup sidebar (layar kecil): tombol menu dan latar gelap
        var tombolMenu = event.target.closest('[data-sidebar-toggle]');

        if (tombolMenu) {
            aturSidebarTerbuka(!document.body.classList.contains('sidebar-open'));
            return;
        }

        // Memilih menu di layar kecil: sidebar menutup sendiri
        if (event.target.closest('.sidebar a')) {
            aturSidebarTerbuka(false);
            return;
        }

        // Lihat / sembunyikan password
        var tombolLihat = event.target.closest('[data-toggle-password]');

        if (tombolLihat) {
            var input = document.querySelector(tombolLihat.getAttribute('data-toggle-password'));

            if (!input) {
                return;
            }

            var tampil = input.type === 'password';
            input.type = tampil ? 'text' : 'password';

            var ikon = tombolLihat.querySelector('i');

            if (ikon) {
                ikon.className = tampil ? 'bi bi-eye-slash' : 'bi bi-eye';
            }

            tombolLihat.setAttribute(
                'aria-label',
                tampil ? 'Sembunyikan password' : 'Tampilkan password'
            );
        }
    });


    // ---------- Keyboard dan ukuran layar ----------

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            aturSidebarTerbuka(false);
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) {
            aturSidebarTerbuka(false);
        }
    });

})();
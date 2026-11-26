</main>

        <footer class="app-footer">
            <div class="app-footer-inner">
                <span>&copy; <?= date('Y') ?> Tokoku</span>
                <span>Sistem Informasi Penjualan Toko</span>
            </div>
        </footer>

    </div><!-- /.app-content -->

</div><!-- /.app-shell -->


<!-- Pop up struk (dipakai semua halaman yang punya link struk) -->
<div class="modal fade" id="modal-struk" tabindex="-1" aria-labelledby="judul-struk" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="judul-struk">Struk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-0 bg-light">
                <iframe id="frame-struk"
                        title="Struk"
                        class="w-100 border-0 d-block"
                        style="height: 320px;"></iframe>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-dark" id="cetak-struk">
                    <i class="bi bi-printer me-1"></i>Cetak Struk
                </button>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/script.js"></script>
<script src="assets/js/belanja.js"></script>
<script src="assets/js/cari.js"></script>


</body>
</html>
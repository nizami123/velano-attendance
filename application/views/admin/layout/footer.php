    </div><!-- /.at-content -->
</div><!-- /.at-main -->
</div><!-- /.at-layout -->

<!-- jQuery (needed by DataTables) -->
<script src="<?= base_url('assets/vendor/jquery-3.7.1.min.js') ?>"></script>
<!-- Bootstrap 5 JS bundle (includes Popper, needed for dropdown/offcanvas) -->
<script src="<?= base_url('assets/vendor/bootstrap.bundle.min.js') ?>"></script>
<!-- DataTables -->
<script src="<?= base_url('assets/vendor/jquery.dataTables.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/dataTables.bootstrap5.min.js') ?>"></script>
<!-- Leaflet -->
<script src="<?= base_url('assets/vendor/leaflet.js') ?>"></script>
<!-- Chart.js (dashboard + laporan charts) -->
<script src="<?= base_url('assets/vendor/chart.umd.min.js') ?>"></script>

<script>window.ADMIN_BASE_URL = <?= json_encode(base_url()) ?>;
window.ATTENDANCE_PHOTO_BASE_URL = <?= json_encode(attendance_photo_base_url()) ?>;
window.REQUEST_ATTACHMENT_BASE_URL = <?= json_encode(request_attachment_base_url()) ?>;</script>
<script src="<?= base_url('assets/admin/js/admin.js') ?>"></script>

<?php if (isset($extraScript)): ?>
    <?= $extraScript ?>
<?php endif; ?>

</body>
</html>

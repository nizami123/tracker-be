    </div><!-- /.at-content -->
</div><!-- /.at-main -->
</div><!-- /.at-layout -->

<!-- jQuery (needed by DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 JS bundle (includes Popper, needed for dropdown/offcanvas) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<!-- Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Chart.js (dashboard + laporan charts) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>window.ADMIN_BASE_URL = <?= json_encode(base_url()) ?>;</script>
<script src="<?= base_url('assets/admin/js/admin.js') ?>"></script>

<?php if (isset($extraScript)): ?>
    <?= $extraScript ?>
<?php endif; ?>

</body>
</html>

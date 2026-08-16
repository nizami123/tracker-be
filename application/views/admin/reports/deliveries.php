<div class="mb-3">
    <h4 class="fw-bold mb-0">Laporan Pengiriman</h4>
    <p class="text-gray small mb-0">Default periode: bulan berjalan.</p>
</div>

<div class="alert alert-warning small py-2">
    <i class="bi bi-info-circle-fill me-1"></i>
    Status <strong>Dibatalkan</strong> selalu 0 — sistem belum memiliki status pembatalan pengiriman di database
    (nilai asli yang ada hanya <code>IN_PROGRESS</code>, <code>ARRIVED</code>, <code>COMPLETED</code>).
</div>

<div class="at-card mb-3">
    <form id="filterForm" method="get" action="<?= site_url('admin/reports/deliveries') ?>" class="row g-3 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Tanggal</label>
            <input type="date" class="form-control" name="date_from" value="<?= html_escape($filters['date_from']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Sampai Tanggal</label>
            <input type="date" class="form-control" name="date_to" value="<?= html_escape($filters['date_to']) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Driver</label>
            <select class="form-select" name="driver_id">
                <option value="">Semua</option>
                <?php foreach ($drivers as $d): ?><option value="<?= $d['id'] ?>" <?= $filters['driver_id'] == $d['id'] ? 'selected' : '' ?>><?= html_escape($d['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Kantor Tujuan</label>
            <select class="form-select" name="destination_office_id">
                <option value="">Semua</option>
                <?php foreach ($offices as $o): ?><option value="<?= $o['id'] ?>" <?= $filters['destination_office_id'] == $o['id'] ? 'selected' : '' ?>><?= html_escape($o['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Status</label>
            <select class="form-select" name="status">
                <option value="">Semua</option>
                <option value="IN_PROGRESS" <?= $filters['status'] == 'IN_PROGRESS' ? 'selected' : '' ?>>Dalam Perjalanan</option>
                <option value="ARRIVED" <?= $filters['status'] == 'ARRIVED' ? 'selected' : '' ?>>Sampai Tujuan</option>
                <option value="COMPLETED" <?= $filters['status'] == 'COMPLETED' ? 'selected' : '' ?>>Selesai</option>
            </select>
        </div>
        <div class="col-12 d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-at-primary"><i class="bi bi-search me-1"></i>Tampilkan</button>
            <a href="<?= site_url('admin/reports/deliveries') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            <a href="#" id="btnExportExcel" class="btn btn-outline-at-primary ms-auto"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
            <a href="#" id="btnExportPdf" target="_blank" class="btn btn-outline-at-primary"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
        </div>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="at-stat-card"><div class="icon-box bg-icon-blue"><i class="bi bi-truck-front-fill"></i></div><div><div class="stat-value"><?= $summary['total'] ?></div><div class="stat-label">Jumlah Pengiriman</div></div></div></div>
    <div class="col-6 col-md-3"><div class="at-stat-card"><div class="icon-box bg-icon-blue"><i class="bi bi-arrow-right-circle-fill"></i></div><div><div class="stat-value"><?= $summary['IN_PROGRESS'] + $summary['ARRIVED'] ?></div><div class="stat-label">Aktif</div></div></div></div>
    <div class="col-6 col-md-3"><div class="at-stat-card"><div class="icon-box bg-icon-green"><i class="bi bi-check-circle-fill"></i></div><div><div class="stat-value"><?= $summary['COMPLETED'] ?></div><div class="stat-label">Selesai</div></div></div></div>
    <div class="col-6 col-md-3"><div class="at-stat-card"><div class="icon-box bg-icon-red"><i class="bi bi-x-circle-fill"></i></div><div><div class="stat-value"><?= $summary['CANCELLED'] ?></div><div class="stat-label">Dibatalkan</div></div></div></div>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100">
            <thead><tr><th>Tanggal</th><th>Driver</th><th>Kendaraan</th><th>Tujuan</th><th>Mulai</th><th>Selesai</th><th>Status</th></tr></thead>
            <tbody>
                <?php $statusMap = array('IN_PROGRESS' => ['Dalam Perjalanan', 'badge-at-blue'], 'ARRIVED' => ['Sampai Tujuan', 'badge-at-orange'], 'COMPLETED' => ['Selesai', 'badge-at-green']); ?>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-gray py-4">Tidak ada data pada periode ini.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): [$label, $cls] = $statusMap[$r['status']] ?? [$r['status'], 'badge-at-gray']; ?>
                    <tr>
                        <td><?= html_escape(substr($r['created_at'], 0, 10)) ?></td>
                        <td><?= html_escape($r['driver_name']) ?></td>
                        <td><?= html_escape($r['brand'] . ' ' . $r['vehicle_type']) ?></td>
                        <td><?= html_escape($r['destination_office_name'] ?: '-') ?></td>
                        <td><?= $r['pickup_time'] ? html_escape(substr($r['pickup_time'], 11, 8)) : '-' ?></td>
                        <td><?= $r['arrival_time'] ? html_escape(substr($r['arrival_time'], 11, 8)) : '-' ?></td>
                        <td><span class="badge-at <?= $cls ?>"><?= $label ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function buildQuery() { return new URLSearchParams(new FormData(document.getElementById('filterForm'))).toString(); }
    document.getElementById('btnExportExcel').addEventListener('click', function (e) {
        e.preventDefault(); window.location.href = ADMIN_BASE_URL + 'admin/reports/deliveries_export_excel?' + buildQuery();
    });
    document.getElementById('btnExportPdf').addEventListener('click', function (e) {
        e.preventDefault(); window.open(ADMIN_BASE_URL + 'admin/reports/deliveries_export_pdf?' + buildQuery(), '_blank');
    });
});
</script>

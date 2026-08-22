<div class="mb-3">
    <h4 class="fw-bold mb-0">Laporan Pengajuan</h4>
    <p class="text-gray small mb-0">Default periode: bulan berjalan.</p>
</div>

<div class="at-card mb-3">
    <form id="filterForm" method="get" action="<?= site_url('admin/reports/requests') ?>" class="row g-3 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Tanggal</label>
            <input type="date" class="form-control" name="date_from" value="<?= html_escape($filters['date_from']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Sampai Tanggal</label>
            <input type="date" class="form-control" name="date_to" value="<?= html_escape($filters['date_to']) ?>">
        </div>
        <?php if ($isSuperAdmin): ?>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Kantor</label>
            <select class="form-select" name="office_id">
                <option value="">Semua</option>
                <?php foreach ($offices as $o): ?><option value="<?= $o['id'] ?>" <?= $filters['office_id'] == $o['id'] ? 'selected' : '' ?>><?= html_escape($o['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Karyawan</label>
            <select class="form-select" name="employee_id">
                <option value="">Semua</option>
                <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $filters['employee_id'] == $e['id'] ? 'selected' : '' ?>><?= html_escape($e['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Jenis</label>
            <select class="form-select" name="type">
                <option value="">Semua</option>
                <option value="LATE" <?= $filters['type'] == 'LATE' ? 'selected' : '' ?>>Terlambat</option>
                <option value="CHECK_IN" <?= $filters['type'] == 'CHECK_IN' ? 'selected' : '' ?>>Masuk</option>
                <option value="OUTSIDE_OFFICE" <?= $filters['type'] == 'OUTSIDE_OFFICE' ? 'selected' : '' ?>>Absen Luar Kantor</option>
                <option value="CHECK_OUT" <?= $filters['type'] == 'CHECK_OUT' ? 'selected' : '' ?>>Pulang</option>
                <option value="LEAVE" <?= $filters['type'] == 'LEAVE' ? 'selected' : '' ?>>Cuti/Izin</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Status</label>
            <select class="form-select" name="status">
                <option value="">Semua</option>
                <option value="PENDING" <?= $filters['status'] == 'PENDING' ? 'selected' : '' ?>>Menunggu</option>
                <option value="APPROVED" <?= $filters['status'] == 'APPROVED' ? 'selected' : '' ?>>Disetujui</option>
                <option value="REJECTED" <?= $filters['status'] == 'REJECTED' ? 'selected' : '' ?>>Ditolak</option>
            </select>
        </div>
        <div class="col-12 d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-at-primary"><i class="bi bi-search me-1"></i>Tampilkan</button>
            <a href="<?= site_url('admin/reports/requests') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            <a href="#" id="btnExportExcel" class="btn btn-outline-at-primary ms-auto"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
            <a href="#" id="btnExportPdf" target="_blank" class="btn btn-outline-at-primary"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
        </div>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="at-stat-card"><div class="icon-box bg-icon-blue"><i class="bi bi-file-earmark-text-fill"></i></div><div><div class="stat-value"><?= $summary['total'] ?></div><div class="stat-label">Total Pengajuan</div></div></div></div>
    <div class="col-6 col-md-3"><div class="at-stat-card"><div class="icon-box bg-icon-orange"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-value"><?= $summary['PENDING'] ?></div><div class="stat-label">Menunggu</div></div></div></div>
    <div class="col-6 col-md-3"><div class="at-stat-card"><div class="icon-box bg-icon-green"><i class="bi bi-check-circle-fill"></i></div><div><div class="stat-value"><?= $summary['APPROVED'] ?></div><div class="stat-label">Disetujui</div></div></div></div>
    <div class="col-6 col-md-3"><div class="at-stat-card"><div class="icon-box bg-icon-red"><i class="bi bi-x-circle-fill"></i></div><div><div class="stat-value"><?= $summary['REJECTED'] ?></div><div class="stat-label">Ditolak</div></div></div></div>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100">
            <thead><tr><th>Diajukan</th><th>Karyawan</th><th>Kantor</th><th>Jenis</th><th>Tanggal</th><th>Status</th></tr></thead>
            <tbody>
                <?php
                $typeLabels = array('LATE' => 'Terlambat', 'CHECK_IN' => 'Masuk', 'CHECK_OUT' => 'Pulang', 'LEAVE' => 'Cuti/Izin', 'OUTSIDE_OFFICE' => 'Absen Luar Kantor');
                $statusMap = array('PENDING' => ['Menunggu', 'badge-at-orange'], 'APPROVED' => ['Disetujui', 'badge-at-green'], 'REJECTED' => ['Ditolak', 'badge-at-red']);
                ?>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="text-center text-gray py-4">Tidak ada data pada periode ini.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): [$label, $cls] = $statusMap[$r['status']] ?? [$r['status'], 'badge-at-gray']; ?>
                    <tr>
                        <td><?= html_escape(substr($r['created_at'], 0, 16)) ?></td>
                        <td><?= html_escape($r['employee_name']) ?></td>
                        <td><?= html_escape($r['office_name']) ?></td>
                        <td><?= $typeLabels[$r['type']] ?? html_escape($r['type']) ?></td>
                        <td><?= $r['type'] === 'LEAVE' ? html_escape($r['start_date'] . ' s/d ' . $r['end_date']) : html_escape($r['date']) ?></td>
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
        e.preventDefault(); window.location.href = ADMIN_BASE_URL + 'admin/reports/requests_export_excel?' + buildQuery();
    });
    document.getElementById('btnExportPdf').addEventListener('click', function (e) {
        e.preventDefault(); window.open(ADMIN_BASE_URL + 'admin/reports/requests_export_pdf?' + buildQuery(), '_blank');
    });
});
</script>

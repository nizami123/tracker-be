<div class="mb-3">
    <h4 class="fw-bold mb-0">Laporan Absensi</h4>
    <p class="text-gray small mb-0">Default periode: bulan berjalan.</p>
</div>

<div class="alert alert-warning small py-2">
    <i class="bi bi-info-circle-fill me-1"></i>
    "Tidak Hadir" adalah estimasi (karyawan aktif × hari dalam rentang − Hadir − Izin/Cuti) karena sistem belum
    memiliki tabel jadwal/shift kerja. "Izin" dan "Cuti" digabung karena keduanya memakai tipe pengajuan yang sama (<code>LEAVE</code>).
</div>

<div class="at-card mb-3">
    <form id="filterForm" method="get" action="<?= site_url('admin/reports/attendance') ?>" class="row g-3 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Tanggal</label>
            <input type="date" class="form-control" name="date_from" value="<?= html_escape($filters['date_from']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Sampai Tanggal</label>
            <input type="date" class="form-control" name="date_to" value="<?= html_escape($filters['date_to']) ?>">
        </div>
        <?php if ($isSuperAdmin): ?>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Kantor</label>
            <select class="form-select" name="office_id">
                <option value="">Semua</option>
                <?php foreach ($offices as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $filters['office_id'] == $o['id'] ? 'selected' : '' ?>><?= html_escape($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Karyawan</label>
            <select class="form-select" name="employee_id">
                <option value="">Semua</option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $filters['employee_id'] == $e['id'] ? 'selected' : '' ?>><?= html_escape($e['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-at-primary"><i class="bi bi-search me-1"></i>Tampilkan</button>
            <a href="<?= site_url('admin/reports/attendance') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            <a href="#" id="btnExportExcel" class="btn btn-outline-at-primary ms-auto"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
            <a href="#" id="btnExportPdf" target="_blank" class="btn btn-outline-at-primary"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
        </div>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-2"><div class="at-stat-card"><div class="icon-box bg-icon-green"><i class="bi bi-check-circle-fill"></i></div><div><div class="stat-value"><?= $summary['hadir'] ?></div><div class="stat-label">Hadir</div></div></div></div>
    <div class="col-6 col-md-2"><div class="at-stat-card"><div class="icon-box bg-icon-orange"><i class="bi bi-clock-fill"></i></div><div><div class="stat-value"><?= $summary['terlambat'] ?></div><div class="stat-label">Terlambat</div></div></div></div>
    <div class="col-6 col-md-2"><div class="at-stat-card"><div class="icon-box bg-icon-red"><i class="bi bi-x-circle-fill"></i></div><div><div class="stat-value"><?= $summary['tidak_hadir'] ?></div><div class="stat-label">Tidak Hadir (estimasi)</div></div></div></div>
    <div class="col-6 col-md-2"><div class="at-stat-card"><div class="icon-box bg-icon-blue"><i class="bi bi-file-earmark-text-fill"></i></div><div><div class="stat-value"><?= $summary['izin_cuti'] ?></div><div class="stat-label">Izin / Cuti</div></div></div></div>
    <div class="col-6 col-md-2"><div class="at-stat-card"><div class="icon-box bg-icon-blue"><i class="bi bi-people-fill"></i></div><div><div class="stat-value"><?= $summary['total_karyawan'] ?></div><div class="stat-label">Total Karyawan</div></div></div></div>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100">
            <thead>
                <tr><th>Tanggal</th><th>Karyawan</th><th>Kantor</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="text-center text-gray py-4">Tidak ada data pada periode ini.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= html_escape($r['attendance_date']) ?></td>
                        <td><?= html_escape($r['employee_name']) ?> <span class="text-gray">(<?= html_escape($r['employee_code']) ?>)</span></td>
                        <td><?= html_escape($r['office_name']) ?></td>
                        <td><?= $r['check_in_time'] ? html_escape(substr($r['check_in_time'], 11, 8)) : '-' ?></td>
                        <td><?= $r['check_out_time'] ? html_escape(substr($r['check_out_time'], 11, 8)) : '-' ?></td>
                        <td><?= html_escape($r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function buildQuery() {
        return new URLSearchParams(new FormData(document.getElementById('filterForm'))).toString();
    }
    document.getElementById('btnExportExcel').addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = ADMIN_BASE_URL + 'admin/reports/attendance_export_excel?' + buildQuery();
    });
    document.getElementById('btnExportPdf').addEventListener('click', function (e) {
        e.preventDefault();
        window.open(ADMIN_BASE_URL + 'admin/reports/attendance_export_pdf?' + buildQuery(), '_blank');
    });
});
</script>

<div class="mb-3">
    <h4 class="fw-bold mb-0">Kalender Absensi</h4>
    <p class="text-gray small mb-0">Export rekap absensi bulanan dalam bentuk kalender, 1 halaman PDF per pegawai.</p>
</div>

<div class="alert alert-warning small py-2">
    <i class="bi bi-info-circle-fill me-1"></i>
    "Hari Kerja" dihitung otomatis (hari kalender − Minggu − Hari Libur pada master <a href="<?= site_url('admin/holidays') ?>">Hari Libur</a>).
    "Alfa" hanya dihitung untuk hari kerja yang sudah lewat tanpa absensi maupun pengajuan Izin/Cuti/Dinas Luar yang disetujui.
</div>

<div class="at-card mb-3">
    <form id="calendarFilterForm" class="row g-3 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Bulan</label>
            <select class="form-select" name="month">
                <?php foreach ($months as $num => $label): ?>
                    <option value="<?= $num ?>" <?= $filters['month'] == $num ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Tahun</label>
            <input type="number" class="form-control" name="year" min="2000" max="2100" value="<?= (int) $filters['year'] ?>">
        </div>
        <?php if ($isSuperAdmin): ?>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Kantor</label>
            <select class="form-select" name="office_id">
                <option value="">Semua Kantor</option>
                <?php foreach ($offices as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $filters['office_id'] == $o['id'] ? 'selected' : '' ?>><?= html_escape($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Pegawai</label>
            <select class="form-select" name="employee_id">
                <option value="">Semua Pegawai</option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $filters['employee_id'] == $e['id'] ? 'selected' : '' ?>><?= html_escape($e['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex gap-2 flex-wrap">
            <a href="#" id="btnExportPdf" target="_blank" class="btn btn-at-primary"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
        </div>
    </form>
</div>

<div class="at-card">
    <p class="text-gray small mb-0">
        <i class="bi bi-printer me-1"></i>
        PDF akan terbuka di tab baru dan otomatis membuka dialog cetak — pilih "Save as PDF" / "Simpan sebagai PDF" pada tujuan printer,
        lalu atur orientasi ke <strong>Landscape</strong> dan kertas <strong>A4</strong> jika belum otomatis terisi.
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function buildQuery() {
        return new URLSearchParams(new FormData(document.getElementById('calendarFilterForm'))).toString();
    }
    document.getElementById('btnExportPdf').addEventListener('click', function (e) {
        e.preventDefault();
        window.open(ADMIN_BASE_URL + 'admin/reports/attendance_calendar_pdf?' + buildQuery(), '_blank');
    });
});
</script>

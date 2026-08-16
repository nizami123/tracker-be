<div class="mb-3">
    <h4 class="fw-bold mb-0">History Absensi</h4>
    <p class="text-gray small mb-0">Menampilkan absensi hari ini secara default.</p>
</div>

<div class="at-card mb-3">
    <form id="filterForm" class="row g-3 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Tanggal</label>
            <input type="date" class="form-control" id="fDate" value="<?= $todayDate ?>">
        </div>
        <?php if ($isSuperAdmin): ?>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Kantor</label>
            <select class="form-select" id="fOffice">
                <option value="">Semua Kantor</option>
                <?php foreach ($offices as $o): ?>
                    <option value="<?= $o['id'] ?>"><?= html_escape($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Karyawan</label>
            <select class="form-select" id="fEmployee">
                <option value="">Semua Karyawan</option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= html_escape($e['name']) ?> (<?= html_escape($e['employee_code']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold">Status</label>
            <select class="form-select" id="fStatus">
                <option value="">Semua Status</option>
                <option value="PRESENT">Hadir</option>
                <option value="LATE">Terlambat</option>
                <option value="ABSENT">Tidak Hadir</option>
            </select>
        </div>
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-at-primary"><i class="bi bi-search me-1"></i>Cari</button>
            <button type="button" class="btn btn-outline-secondary" id="btnReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
        </div>
    </form>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100" id="tblAttendances">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Karyawan</th>
                    <th>Kantor</th>
                    <th>Jam Masuk</th>
                    <th>Lokasi Masuk</th>
                    <th>Jam Pulang</th>
                    <th>Lokasi Pulang</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const todayDate = '<?= $todayDate ?>';

    function statusBadge(s) {
        const map = { PRESENT: ['Hadir', 'badge-at-green'], LATE: ['Terlambat', 'badge-at-orange'], ABSENT: ['Tidak Hadir', 'badge-at-red'] };
        const m = map[s] || [s, 'badge-at-gray'];
        return `<span class="badge-at ${m[1]}">${m[0]}</span>`;
    }

    const table = $('#tblAttendances').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: ADMIN_BASE_URL + 'admin/attendances/list_data',
            type: 'POST',
            data: function (d) {
                d.filter_date = document.getElementById('fDate').value;
                d.filter_office_id = document.getElementById('fOffice')?.value || '';
                d.filter_employee_id = document.getElementById('fEmployee').value;
                d.filter_status = document.getElementById('fStatus').value;
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'attendance_date' },
            { data: null, render: row => `${row.employee_name}<div class="text-gray" style="font-size:11px;">${row.employee_code}</div>` },
            { data: 'office_name' },
            { data: 'check_in_time', render: d => d ? d.substring(11, 19) : '-' },
            { data: 'check_in_distance', render: d => d !== null ? Math.round(d) + ' m dari kantor' : '-' },
            { data: 'check_out_time', render: d => d ? d.substring(11, 19) : '-' },
            { data: 'check_out_distance', render: d => d !== null ? Math.round(d) + ' m dari kantor' : '-' },
            { data: 'status', render: statusBadge },
            {
                data: null, orderable: false, className: 'text-end',
                render: function (row) {
                    if (row.tracking_count > 0) {
                        return `<a href="${ADMIN_BASE_URL}admin/attendance_tracking/detail/${row.id}" class="btn btn-sm btn-outline-at-primary"><i class="bi bi-geo-alt me-1"></i>Lihat Tracking</a>`;
                    }
                    return `<span class="text-gray small">Tidak ada tracking</span>`;
                }
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/id.json' }
    });

    document.getElementById('filterForm').addEventListener('submit', function (e) {
        e.preventDefault();
        table.ajax.reload();
    });

    document.getElementById('btnReset').addEventListener('click', function () {
        document.getElementById('fDate').value = todayDate;
        if (document.getElementById('fOffice')) document.getElementById('fOffice').value = '';
        document.getElementById('fEmployee').value = '';
        document.getElementById('fStatus').value = '';
        table.ajax.reload();
    });
});
</script>

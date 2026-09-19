<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Hari Libur</h4>
        <p class="text-gray small mb-0">
            Tanggal di sini akan ditandai merah pada Kalender Absensi (Export PDF) dan mengurangi hitungan "Hari Kerja".
            <?php if ($isSuperAdmin): ?>
                Kosongkan "Kantor" saat menambah untuk libur nasional (berlaku semua kantor).
            <?php endif; ?>
        </p>
    </div>
    <button class="btn btn-at-primary" id="btnAddHoliday"><i class="bi bi-plus-lg me-1"></i>Tambah Hari Libur</button>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100" id="tblHolidays">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nama Libur</th>
                    <th>Cakupan</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="holidayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="holidayForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="holidayModalTitle">Tambah Hari Libur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="holidayFormError" class="alert alert-danger py-2 small d-none"></div>
                    <input type="hidden" name="id" id="holidayId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tanggal *</label>
                            <input type="date" class="form-control" name="holiday_date" id="holidayDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nama Libur *</label>
                            <input type="text" class="form-control" name="name" id="holidayName" placeholder="mis. Cuti Bersama" required>
                        </div>
                        <?php if ($isSuperAdmin): ?>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Kantor</label>
                            <select class="form-select" name="office_id" id="holidayOffice">
                                <option value="">Semua Kantor (Nasional)</option>
                                <?php foreach ($offices as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= html_escape($o['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-at-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const holidayModal = new bootstrap.Modal(document.getElementById('holidayModal'));

    const table = $('#tblHolidays').DataTable({
        ajax: { url: ADMIN_BASE_URL + 'admin/holidays/list_data', dataSrc: 'data' },
        order: [[0, 'asc']],
        columns: [
            { data: 'holiday_date' },
            { data: 'name' },
            { data: 'office_name', render: d => d ? d : '<span class="badge-at badge-at-gray">Nasional (Semua Kantor)</span>' },
            {
                data: null, orderable: false, className: 'text-end',
                render: function (row) {
                    return `
                        <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="${row.id}"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-delete" data-id="${row.id}"><i class="bi bi-trash"></i></button>
                    `;
                }
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/id.json' }
    });

    document.getElementById('btnAddHoliday').addEventListener('click', function () {
        document.getElementById('holidayForm').reset();
        document.getElementById('holidayId').value = '';
        document.getElementById('holidayModalTitle').textContent = 'Tambah Hari Libur';
        document.getElementById('holidayFormError').classList.add('d-none');
        holidayModal.show();
    });

    $('#tblHolidays tbody').on('click', '.btn-edit', function () {
        const id = $(this).data('id');
        fetch(ADMIN_BASE_URL + 'admin/holidays/detail/' + id).then(r => r.json()).then(res => {
            if (!res.success) { alert(res.message); return; }
            const h = res.data;
            document.getElementById('holidayForm').reset();
            document.getElementById('holidayId').value = h.id;
            document.getElementById('holidayDate').value = h.holiday_date;
            document.getElementById('holidayName').value = h.name;
            if (document.getElementById('holidayOffice')) {
                document.getElementById('holidayOffice').value = h.office_id ?? '';
            }
            document.getElementById('holidayModalTitle').textContent = 'Edit Hari Libur';
            document.getElementById('holidayFormError').classList.add('d-none');
            holidayModal.show();
        });
    });

    $('#tblHolidays tbody').on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        if (!confirm('Hapus hari libur ini?')) return;
        fetch(ADMIN_BASE_URL + 'admin/holidays/delete/' + id, { method: 'POST' })
            .then(r => r.json()).then(res => {
                if (res.success) { table.ajax.reload(null, false); }
                else { alert(res.message); }
            });
    });

    document.getElementById('holidayForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch(ADMIN_BASE_URL + 'admin/holidays/save', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    holidayModal.hide();
                    table.ajax.reload(null, false);
                } else {
                    const err = document.getElementById('holidayFormError');
                    err.textContent = res.message;
                    err.classList.remove('d-none');
                }
            });
    });
});
</script>

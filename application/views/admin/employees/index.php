<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Master Karyawan</h4>
        <p class="text-gray small mb-0">Kelola akun karyawan (EMPLOYEE) dan driver (DRIVER).</p>
    </div>
    <button class="btn btn-at-primary" id="btnAddEmployee"><i class="bi bi-plus-lg me-1"></i>Tambah Karyawan</button>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100" id="tblEmployees">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Kantor</th>
                    <th>Jabatan</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="employeeForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="employeeModalTitle">Tambah Karyawan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="employeeFormError" class="alert alert-danger py-2 small d-none"></div>
                    <input type="hidden" name="id" id="empId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">NIP</label>
                            <input type="text" class="form-control" name="nip" id="empNip">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Role *</label>
                            <select class="form-select" name="role" id="empRole" required>
                                <option value="EMPLOYEE">Employee</option>
                                <option value="DRIVER">Driver</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nama Lengkap *</label>
                            <input type="text" class="form-control" name="name" id="empName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Email</label>
                            <input type="email" class="form-control" name="email" id="empEmail">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">No. Telepon</label>
                            <input type="text" class="form-control" name="phone" id="empPhone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Jabatan</label>
                            <input type="text" class="form-control" name="position" id="empPosition">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kantor *</label>
                            <select class="form-select" name="office_id" id="empOffice" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Status</label>
                            <select class="form-select" name="status" id="empStatus">
                                <option value="ACTIVE">Aktif</option>
                                <option value="INACTIVE">Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Password <span id="empPasswordHint" class="text-gray fw-normal">(kosongkan jika tidak ingin mengubah)</span></label>
                            <input type="password" class="form-control" name="password" id="empPassword" placeholder="Minimal 6 karakter">
                        </div>
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

<div class="modal fade" id="syncModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="syncForm">
                <div class="modal-header">
                    <h5 class="modal-title">Sync Absensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-3">Karyawan: <strong id="syncEmpName">-</strong></p>
                    <input type="hidden" id="syncEmpId">
                    <div class="row g-3">
                        <div class="col-7">
                            <label class="form-label small fw-bold">Bulan</label>
                            <select class="form-select" id="syncBulan" name="bulan">
                                <option value="1">Januari</option>
                                <option value="2">Februari</option>
                                <option value="3">Maret</option>
                                <option value="4">April</option>
                                <option value="5">Mei</option>
                                <option value="6">Juni</option>
                                <option value="7">Juli</option>
                                <option value="8">Agustus</option>
                                <option value="9">September</option>
                                <option value="10">Oktober</option>
                                <option value="11">November</option>
                                <option value="12">Desember</option>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold">Tahun</label>
                            <input type="number" class="form-control" id="syncTahun" name="tahun" min="2000" max="2100">
                        </div>
                    </div>
                    <p class="text-gray small mt-3 mb-0">Data yang sudah ada tidak ditimpa; hanya jam masuk/pulang yang masih kosong yang dilengkapi.</p>
                    <div id="syncResult" class="alert py-2 small mt-3 mb-0 d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-at-primary" id="btnSyncRun"><i class="bi bi-arrow-repeat me-1"></i>Sync</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const offices = <?= json_encode($offices) ?>;
    const officeSelect = document.getElementById('empOffice');
    offices.forEach(o => {
        const opt = document.createElement('option');
        opt.value = o.id; opt.textContent = o.name;
        officeSelect.appendChild(opt);
    });

    const employeeModal = new bootstrap.Modal(document.getElementById('employeeModal'));

    const table = $('#tblEmployees').DataTable({
        ajax: { url: ADMIN_BASE_URL + 'admin/employees/list_data', dataSrc: 'data' },
        columns: [
            { data: 'name' },
            { data: 'email', render: d => d || '-' },
            { data: 'office_name', render: d => d || '-' },
            { data: 'position', render: d => d || '-' },
            { data: 'role', render: r => `<span class="badge-at ${r === 'DRIVER' ? 'badge-at-blue' : 'badge-at-gray'}">${r}</span>` },
            { data: 'status', render: s => `<span class="badge-at ${s === 'ACTIVE' ? 'badge-at-green' : 'badge-at-gray'}">${s === 'ACTIVE' ? 'Aktif' : 'Nonaktif'}</span>` },
            {
                data: null, orderable: false, className: 'text-end',
                render: row => `
                    <button class="btn btn-sm ${row.device === 'ANDROID' ? 'btn-outline-primary' : 'btn-outline-danger'} btn-sync" data-id="${row.id}" title="Sync absensi dari sistem lama"><i class="bi bi-arrow-repeat"></i></button>
                    <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="${row.id}"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-warning btn-toggle" data-id="${row.id}"><i class="bi bi-power"></i></button>
                    <button class="btn btn-sm btn-outline-danger btn-delete" data-id="${row.id}"><i class="bi bi-trash"></i></button>
                `
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/id.json' }
    });

    document.getElementById('btnAddEmployee').addEventListener('click', function () {
        document.getElementById('employeeForm').reset();
        document.getElementById('empId').value = '';
        document.getElementById('employeeModalTitle').textContent = 'Tambah Karyawan';
        document.getElementById('empPasswordHint').classList.add('d-none');
        document.getElementById('employeeFormError').classList.add('d-none');
        employeeModal.show();
    });

    $('#tblEmployees tbody').on('click', '.btn-edit', function () {
        const id = $(this).data('id');
        fetch(ADMIN_BASE_URL + 'admin/employees/detail/' + id).then(r => r.json()).then(res => {
            if (!res.success) { alert(res.message); return; }
            const e = res.data;
            document.getElementById('employeeForm').reset();
            document.getElementById('empId').value = e.id;
            document.getElementById('empCode').value = e.employee_code;
            document.getElementById('empNip').value = e.nip || '';
            document.getElementById('empName').value = e.name;
            document.getElementById('empEmail').value = e.email || '';
            document.getElementById('empPhone').value = e.phone || '';
            document.getElementById('empPosition').value = e.position || '';
            document.getElementById('empRole').value = e.role;
            document.getElementById('empOffice').value = e.office_id;
            document.getElementById('empStatus').value = e.status;
            document.getElementById('employeeModalTitle').textContent = 'Edit Karyawan';
            document.getElementById('empPasswordHint').classList.remove('d-none');
            document.getElementById('employeeFormError').classList.add('d-none');
            employeeModal.show();
        });
    });

    $('#tblEmployees tbody').on('click', '.btn-toggle', function () {
        const id = $(this).data('id');
        if (!confirm('Ubah status karyawan ini?')) return;
        fetch(ADMIN_BASE_URL + 'admin/employees/toggle_status/' + id, { method: 'POST' })
            .then(r => r.json()).then(res => { if (res.success) table.ajax.reload(null, false); else alert(res.message); });
    });

    $('#tblEmployees tbody').on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        if (!confirm('Hapus karyawan ini? Tindakan tidak bisa dibatalkan.')) return;
        fetch(ADMIN_BASE_URL + 'admin/employees/delete/' + id, { method: 'POST' })
            .then(r => r.json()).then(res => {
                if (res.success) table.ajax.reload(null, false);
                else alert(res.message);
            });
    });


    // ---- Sync absensi dari sistem lama ----
    const syncModal = new bootstrap.Modal(document.getElementById('syncModal'));
    const syncResult = document.getElementById('syncResult');

    $('#tblEmployees tbody').on('click', '.btn-sync', function () {
        const now = new Date();
        document.getElementById('syncEmpId').value = $(this).data('id');
        document.getElementById('syncEmpName').textContent = table.row($(this).closest('tr')).data().name;
        document.getElementById('syncBulan').value = String(now.getMonth() + 1); // default bulan sekarang
        document.getElementById('syncTahun').value = now.getFullYear();
        syncResult.classList.add('d-none');
        document.getElementById('btnSyncRun').disabled = false;
        syncModal.show();
    });

    document.getElementById('syncForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnSyncRun');
        const id = document.getElementById('syncEmpId').value;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyinkronkan...';
        syncResult.classList.add('d-none');

        fetch(ADMIN_BASE_URL + 'admin/employees/sync/' + id, { method: 'POST', body: new FormData(this) })
            .then(r => r.json().catch(() => ({ success: false, message: 'Respons server tidak valid' })))
            .then(res => {
                syncResult.className = 'alert py-2 small mt-3 mb-0 ' + (res.success ? 'alert-success' : 'alert-danger');
                if (res.success) {
                    const s = res.summary;
                    syncResult.innerHTML = '<strong>' + res.message + '</strong><br>' +
                        'Ditambahkan: ' + s.inserted + ' &middot; Dilengkapi: ' + s.updated +
                        ' &middot; Dilewati (sudah ada): ' + s.skipped + ' &middot; Tanpa data: ' + s.empty;
                } else {
                    syncResult.textContent = res.message || 'Sync gagal';
                }
            })
            .catch(() => {
                syncResult.className = 'alert alert-danger py-2 small mt-3 mb-0';
                syncResult.textContent = 'Tidak bisa terhubung ke server';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Sync';
            });
    });

    document.getElementById('employeeForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch(ADMIN_BASE_URL + 'admin/employees/save', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) { employeeModal.hide(); table.ajax.reload(null, false); }
                else {
                    const err = document.getElementById('employeeFormError');
                    err.textContent = res.message;
                    err.classList.remove('d-none');
                }
            });
    });
});
</script>
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
                    <th>Kode</th>
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
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Kode Karyawan *</label>
                            <input type="text" class="form-control" name="employee_code" id="empCode" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">NIP</label>
                            <input type="text" class="form-control" name="nip" id="empNip">
                        </div>
                        <div class="col-md-4">
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
            { data: 'employee_code' },
            { data: 'name' },
            { data: 'email', render: d => d || '-' },
            { data: 'office_name', render: d => d || '-' },
            { data: 'position', render: d => d || '-' },
            { data: 'role', render: r => `<span class="badge-at ${r === 'DRIVER' ? 'badge-at-blue' : 'badge-at-gray'}">${r}</span>` },
            { data: 'status', render: s => `<span class="badge-at ${s === 'ACTIVE' ? 'badge-at-green' : 'badge-at-gray'}">${s === 'ACTIVE' ? 'Aktif' : 'Nonaktif'}</span>` },
            {
                data: null, orderable: false, className: 'text-end',
                render: row => `
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

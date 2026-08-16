<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Master User Admin</h4>
        <p class="text-gray small mb-0">Kelola akun SUPER_ADMIN dan ADMIN_KANTOR.</p>
    </div>
    <button class="btn btn-at-primary" id="btnAddAdmin"><i class="bi bi-plus-lg me-1"></i>Tambah Admin</button>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100" id="tblAdmins">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Kantor</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="adminModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="adminForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminModalTitle">Tambah Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="adminFormError" class="alert alert-danger py-2 small d-none"></div>
                    <input type="hidden" name="id" id="adminId">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Kode *</label>
                            <input type="text" class="form-control" name="employee_code" id="adminCode" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Nama Lengkap *</label>
                            <input type="text" class="form-control" name="name" id="adminName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Email *</label>
                            <input type="email" class="form-control" name="email" id="adminEmail" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">No. Telepon</label>
                            <input type="text" class="form-control" name="phone" id="adminPhone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Role *</label>
                            <select class="form-select" name="role" id="adminRole" required>
                                <option value="ADMIN_KANTOR">Admin Kantor</option>
                                <option value="SUPER_ADMIN">Super Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kantor *</label>
                            <select class="form-select" name="office_id" id="adminOffice" required></select>
                            <div class="form-text">Untuk Super Admin, kantor hanya menentukan kantor default/asal akun.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Status</label>
                            <select class="form-select" name="status" id="adminStatus">
                                <option value="ACTIVE">Aktif</option>
                                <option value="INACTIVE">Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Password <span id="adminPasswordHint" class="text-gray fw-normal">(kosongkan jika tidak ingin mengubah)</span></label>
                            <input type="password" class="form-control" name="password" id="adminPassword" placeholder="Minimal 6 karakter">
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
    const officeSelect = document.getElementById('adminOffice');
    offices.forEach(o => {
        const opt = document.createElement('option');
        opt.value = o.id; opt.textContent = o.name;
        officeSelect.appendChild(opt);
    });

    const adminModal = new bootstrap.Modal(document.getElementById('adminModal'));

    const table = $('#tblAdmins').DataTable({
        ajax: { url: ADMIN_BASE_URL + 'admin/admin_users/list_data', dataSrc: 'data' },
        columns: [
            { data: 'employee_code' },
            { data: 'name' },
            { data: 'email' },
            { data: 'office_name', render: d => d || '-' },
            { data: 'role', render: r => `<span class="badge-at ${r === 'SUPER_ADMIN' ? 'badge-at-blue' : 'badge-at-gray'}">${r}</span>` },
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

    document.getElementById('btnAddAdmin').addEventListener('click', function () {
        document.getElementById('adminForm').reset();
        document.getElementById('adminId').value = '';
        document.getElementById('adminModalTitle').textContent = 'Tambah Admin';
        document.getElementById('adminPasswordHint').classList.add('d-none');
        document.getElementById('adminFormError').classList.add('d-none');
        adminModal.show();
    });

    $('#tblAdmins tbody').on('click', '.btn-edit', function () {
        const id = $(this).data('id');
        fetch(ADMIN_BASE_URL + 'admin/admin_users/detail/' + id).then(r => r.json()).then(res => {
            if (!res.success) { alert(res.message); return; }
            const a = res.data;
            document.getElementById('adminForm').reset();
            document.getElementById('adminId').value = a.id;
            document.getElementById('adminCode').value = a.employee_code;
            document.getElementById('adminName').value = a.name;
            document.getElementById('adminEmail').value = a.email || '';
            document.getElementById('adminPhone').value = a.phone || '';
            document.getElementById('adminRole').value = a.role;
            document.getElementById('adminOffice').value = a.office_id;
            document.getElementById('adminStatus').value = a.status;
            document.getElementById('adminModalTitle').textContent = 'Edit Admin';
            document.getElementById('adminPasswordHint').classList.remove('d-none');
            document.getElementById('adminFormError').classList.add('d-none');
            adminModal.show();
        });
    });

    $('#tblAdmins tbody').on('click', '.btn-toggle', function () {
        const id = $(this).data('id');
        if (!confirm('Ubah status admin ini?')) return;
        fetch(ADMIN_BASE_URL + 'admin/admin_users/toggle_status/' + id, { method: 'POST' })
            .then(r => r.json()).then(res => { if (res.success) table.ajax.reload(null, false); else alert(res.message); });
    });

    $('#tblAdmins tbody').on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        if (!confirm('Hapus admin ini?')) return;
        fetch(ADMIN_BASE_URL + 'admin/admin_users/delete/' + id, { method: 'POST' })
            .then(r => r.json()).then(res => {
                if (res.success) table.ajax.reload(null, false);
                else alert(res.message);
            });
    });

    document.getElementById('adminForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch(ADMIN_BASE_URL + 'admin/admin_users/save', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) { adminModal.hide(); table.ajax.reload(null, false); }
                else {
                    const err = document.getElementById('adminFormError');
                    err.textContent = res.message;
                    err.classList.remove('d-none');
                }
            });
    });
});
</script>

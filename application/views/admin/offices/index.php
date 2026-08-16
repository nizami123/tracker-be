<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Master Kantor</h4>
        <p class="text-gray small mb-0">Kelola data kantor/cabang. Radius dipakai untuk validasi lokasi absensi di aplikasi Android.</p>
    </div>
    <?php if ($isSuperAdmin): ?>
        <button class="btn btn-at-primary" id="btnAddOffice"><i class="bi bi-plus-lg me-1"></i>Tambah Kantor</button>
    <?php endif; ?>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100" id="tblOffices">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Kantor</th>
                    <th>Alamat</th>
                    <th>Radius Masuk</th>
                    <th>Radius Pulang</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="officeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="officeForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="officeModalTitle">Tambah Kantor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="officeFormError" class="alert alert-danger py-2 small d-none"></div>
                    <input type="hidden" name="id" id="officeId">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Kode Kantor *</label>
                            <input type="text" class="form-control" name="code" id="officeCode" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Nama Kantor *</label>
                            <input type="text" class="form-control" name="name" id="officeName" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Alamat</label>
                            <textarea class="form-control" name="address" id="officeAddress" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Radius Absen Masuk (meter) *</label>
                            <input type="number" class="form-control" name="check_in_radius" id="officeCheckInRadius" value="50" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Radius Absen Pulang (meter) *</label>
                            <input type="number" class="form-control" name="check_out_radius" id="officeCheckOutRadius" value="50" min="1" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Pilih Lokasi di Peta *</label>
                            <p class="text-gray small mb-2">Klik pada peta atau geser marker untuk menentukan titik kantor.</p>
                            <div id="officeMapPicker" class="at-map-sm"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Latitude *</label>
                            <input type="text" class="form-control" name="latitude" id="officeLat" required readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Longitude *</label>
                            <input type="text" class="form-control" name="longitude" id="officeLng" required readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Status</label>
                            <select class="form-select" name="status" id="officeStatus">
                                <option value="ACTIVE">Aktif</option>
                                <option value="INACTIVE">Nonaktif</option>
                            </select>
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
    const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
    const officeModal = new bootstrap.Modal(document.getElementById('officeModal'));
    let picker, pickerMarker;

    function initPicker(lat, lng) {
        if (picker) { picker.remove(); }
        picker = L.map('officeMapPicker').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(picker);
        pickerMarker = L.marker([lat, lng], { draggable: true }).addTo(picker);
        pickerMarker.on('dragend', function (e) {
            const pos = e.target.getLatLng();
            document.getElementById('officeLat').value = pos.lat.toFixed(7);
            document.getElementById('officeLng').value = pos.lng.toFixed(7);
        });
        picker.on('click', function (e) {
            pickerMarker.setLatLng(e.latlng);
            document.getElementById('officeLat').value = e.latlng.lat.toFixed(7);
            document.getElementById('officeLng').value = e.latlng.lng.toFixed(7);
        });
        setTimeout(function () { picker.invalidateSize(); }, 250);
    }

    const table = $('#tblOffices').DataTable({
        ajax: { url: ADMIN_BASE_URL + 'admin/offices/list_data', dataSrc: 'data' },
        columns: [
            { data: 'code' },
            { data: 'name' },
            { data: 'address', render: d => d || '-' },
            { data: 'check_in_radius', render: d => d + ' m' },
            { data: 'check_out_radius', render: d => d + ' m' },
            { data: 'status', render: s => `<span class="badge-at ${s === 'ACTIVE' ? 'badge-at-green' : 'badge-at-gray'}">${s === 'ACTIVE' ? 'Aktif' : 'Nonaktif'}</span>` },
            {
                data: null, orderable: false, className: 'text-end',
                render: function (row) {
                    if (!isSuperAdmin) return '-';
                    return `
                        <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="${row.id}"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-warning btn-toggle" data-id="${row.id}" data-status="${row.status}"><i class="bi bi-power"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-delete" data-id="${row.id}"><i class="bi bi-trash"></i></button>
                    `;
                }
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/id.json' }
    });

    document.getElementById('btnAddOffice')?.addEventListener('click', function () {
        document.getElementById('officeForm').reset();
        document.getElementById('officeId').value = '';
        document.getElementById('officeModalTitle').textContent = 'Tambah Kantor';
        document.getElementById('officeFormError').classList.add('d-none');
        officeModal.show();
        setTimeout(() => initPicker(-7.257472, 112.752090), 300);
    });

    $('#tblOffices tbody').on('click', '.btn-edit', function () {
        const id = $(this).data('id');
        fetch(ADMIN_BASE_URL + 'admin/offices/detail/' + id).then(r => r.json()).then(res => {
            if (!res.success) return;
            const o = res.data;
            document.getElementById('officeForm').reset();
            document.getElementById('officeId').value = o.id;
            document.getElementById('officeCode').value = o.code;
            document.getElementById('officeName').value = o.name;
            document.getElementById('officeAddress').value = o.address || '';
            document.getElementById('officeCheckInRadius').value = o.check_in_radius;
            document.getElementById('officeCheckOutRadius').value = o.check_out_radius;
            document.getElementById('officeStatus').value = o.status;
            document.getElementById('officeLat').value = o.latitude;
            document.getElementById('officeLng').value = o.longitude;
            document.getElementById('officeModalTitle').textContent = 'Edit Kantor';
            document.getElementById('officeFormError').classList.add('d-none');
            officeModal.show();
            setTimeout(() => initPicker(parseFloat(o.latitude), parseFloat(o.longitude)), 300);
        });
    });

    $('#tblOffices tbody').on('click', '.btn-toggle', function () {
        const id = $(this).data('id');
        if (!confirm('Ubah status kantor ini?')) return;
        fetch(ADMIN_BASE_URL + 'admin/offices/toggle_status/' + id, { method: 'POST' })
            .then(r => r.json()).then(res => { if (res.success) table.ajax.reload(null, false); });
    });

    $('#tblOffices tbody').on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        if (!confirm('Hapus kantor ini? Tindakan tidak bisa dibatalkan.')) return;
        fetch(ADMIN_BASE_URL + 'admin/offices/delete/' + id, { method: 'POST' })
            .then(r => r.json()).then(res => {
                if (res.success) { table.ajax.reload(null, false); }
                else { alert(res.message); }
            });
    });

    document.getElementById('officeForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch(ADMIN_BASE_URL + 'admin/offices/save', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    officeModal.hide();
                    table.ajax.reload(null, false);
                } else {
                    const err = document.getElementById('officeFormError');
                    err.textContent = res.message;
                    err.classList.remove('d-none');
                }
            });
    });
});
</script>

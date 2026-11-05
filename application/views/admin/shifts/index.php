<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Master Shift</h4>
        <p class="text-gray small mb-0">
            Setiap shift hanya berlaku di kantor yang dipilih. Atur siapa masuk shift apa di menu <strong>Jadwal Shift</strong>.
            Karyawan yang belum dijadwalkan tetap memakai jam kerja default (08:00 - 16:30).
        </p>
    </div>
    <button class="btn btn-at-primary" id="btnAddShift" <?= empty($tablesReady) ? 'disabled' : '' ?>><i class="bi bi-plus-lg me-1"></i>Tambah Shift</button>
</div>

<?php if (empty($tablesReady)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Tabel shift belum ada di database. Import file
        <code>database/migrations/2026_11_05_shift_management.sql</code> terlebih dahulu (aman, hanya membuat 2 tabel baru).
    </div>
<?php endif; ?>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100" id="tblShifts">
            <thead>
                <tr>
                    <th>Kantor</th>
                    <th>Kode</th>
                    <th>Nama Shift</th>
                    <th>Jam Kerja</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="shiftModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="shiftForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="shiftModalTitle">Tambah Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="shiftFormError" class="alert alert-danger py-2 small d-none"></div>
                    <input type="hidden" name="id" id="shiftId">

                    <div class="row g-3">
                        <?php if ($isSuperAdmin): ?>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Kantor *</label>
                            <select class="form-select" name="office_id" id="shiftOffice" required>
                                <option value="">- Pilih kantor -</option>
                                <?php foreach ($offices as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= html_escape($o['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="shiftOfficeHint">Kantor tidak bisa diubah setelah shift dibuat.</div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Kode *</label>
                            <input type="text" class="form-control text-uppercase" name="code" id="shiftCode" maxlength="20" placeholder="P / S / M" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Nama Shift *</label>
                            <input type="text" class="form-control" name="name" id="shiftName" maxlength="100" placeholder="mis. Shift Pagi" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Jam Mulai *</label>
                            <input type="time" class="form-control" name="start_time" id="shiftStart" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Jam Selesai *</label>
                            <input type="time" class="form-control" name="end_time" id="shiftEnd" required>
                        </div>
                        <div class="col-12">
                            <div class="form-text">Jika jam selesai lebih kecil dari jam mulai (mis. 22:00 - 06:00), shift dihitung melewati tengah malam.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Status</label>
                            <select class="form-select" name="status" id="shiftStatus">
                                <option value="ACTIVE">Aktif</option>
                                <option value="INACTIVE">Nonaktif</option>
                            </select>
                            <div class="form-text">Shift nonaktif tidak bisa dipilih di jadwal baru dan dianggap tidak ada di aplikasi karyawan.</div>
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
    const shiftModal = new bootstrap.Modal(document.getElementById('shiftModal'));
    const isSuper = <?= $isSuperAdmin ? 'true' : 'false' ?>;

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    const table = $('#tblShifts').DataTable({
        ajax: { url: ADMIN_BASE_URL + 'admin/shifts/list_data', dataSrc: 'data' },
        order: [[0, 'asc'], [3, 'asc']],
        columns: [
            { data: 'office_name', render: d => esc(d) },
            { data: 'code', render: d => '<span class="badge-at badge-at-gray">' + esc(d) + '</span>' },
            { data: 'name', render: d => esc(d) },
            {
                data: null,
                render: r => esc(r.start_time) + ' - ' + esc(r.end_time) +
                    (r.crosses_midnight ? ' <span class="badge-at badge-at-orange">lewat tengah malam</span>' : '')
            },
            {
                data: 'status',
                render: d => d === 'ACTIVE'
                    ? '<span class="badge-at badge-at-green">Aktif</span>'
                    : '<span class="badge-at badge-at-red">Nonaktif</span>'
            },
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

    function showError(msg) {
        const err = document.getElementById('shiftFormError');
        err.textContent = msg;
        err.classList.remove('d-none');
    }

    document.getElementById('btnAddShift').addEventListener('click', function () {
        document.getElementById('shiftForm').reset();
        document.getElementById('shiftId').value = '';
        document.getElementById('shiftModalTitle').textContent = 'Tambah Shift';
        document.getElementById('shiftFormError').classList.add('d-none');
        if (isSuper) document.getElementById('shiftOffice').disabled = false;
        shiftModal.show();
    });

    $('#tblShifts tbody').on('click', '.btn-edit', function () {
        const id = $(this).data('id');
        fetch(ADMIN_BASE_URL + 'admin/shifts/detail/' + id).then(r => r.json()).then(res => {
            if (!res.success) { alert(res.message); return; }
            const s = res.data;
            document.getElementById('shiftForm').reset();
            document.getElementById('shiftId').value = s.id;
            document.getElementById('shiftCode').value = s.code;
            document.getElementById('shiftName').value = s.name;
            document.getElementById('shiftStart').value = s.start_time;
            document.getElementById('shiftEnd').value = s.end_time;
            document.getElementById('shiftStatus').value = s.status;
            if (isSuper) {
                const sel = document.getElementById('shiftOffice');
                sel.value = s.office_id;
                sel.disabled = true; // kantor tidak bisa diganti
            }
            document.getElementById('shiftModalTitle').textContent = 'Edit Shift';
            document.getElementById('shiftFormError').classList.add('d-none');
            shiftModal.show();
        });
    });

    $('#tblShifts tbody').on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        if (!confirm('Hapus shift ini?')) return;
        fetch(ADMIN_BASE_URL + 'admin/shifts/delete/' + id, { method: 'POST' })
            .then(r => r.json()).then(res => {
                if (res.success) { table.ajax.reload(null, false); }
                else { alert(res.message); }
            });
    });

    document.getElementById('shiftForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        // select yang disabled tidak ikut terkirim; kantor tidak dipakai saat edit.
        fetch(ADMIN_BASE_URL + 'admin/shifts/save', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) {
                    shiftModal.hide();
                    table.ajax.reload(null, false);
                } else {
                    showError(res.message);
                }
            })
            .catch(() => showError('Gagal menyimpan, coba lagi.'));
    });
});
</script>

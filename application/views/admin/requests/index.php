<div class="mb-3">
    <h4 class="fw-bold mb-0"><?= html_escape($pageTitle) ?></h4>
    <p class="text-gray small mb-0">Kelola pengajuan terlambat, masuk, pulang, dan cuti/izin karyawan.</p>
</div>

<div class="at-card mb-3">
    <form id="filterForm" class="row g-3 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Tanggal</label>
            <input type="date" class="form-control" id="fDateFrom">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Sampai Tanggal</label>
            <input type="date" class="form-control" id="fDateTo">
        </div>
        <?php if ($isSuperAdmin): ?>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Kantor</label>
            <select class="form-select" id="fOffice">
                <option value="">Semua</option>
                <?php foreach ($offices as $o): ?><option value="<?= $o['id'] ?>"><?= html_escape($o['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Karyawan</label>
            <select class="form-select" id="fEmployee">
                <option value="">Semua</option>
                <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= html_escape($e['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Jenis</label>
            <select class="form-select" id="fType">
                <option value="">Semua</option>
                <option value="LATE">Terlambat</option>
                <option value="CHECK_IN">Masuk</option>
                <option value="OUTSIDE_OFFICE">Absen Luar Kantor</option>
                <option value="CHECK_OUT">Pulang</option>
                <option value="LEAVE">Cuti/Izin</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Status</label>
            <select class="form-select" id="fStatus">
                <option value="">Semua</option>
                <option value="PENDING">Menunggu</option>
                <option value="APPROVED">Disetujui</option>
                <option value="REJECTED">Ditolak</option>
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
        <table class="table table-at table-hover align-middle w-100" id="tblRequests">
            <thead>
                <tr>
                    <th>Diajukan</th>
                    <th>Karyawan</th>
                    <th>Kantor</th>
                    <th>Jenis</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Detail modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Pengajuan</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody"><div class="text-center text-gray py-4">Memuat...</div></div>
            <div class="modal-footer" id="detailFooter"></div>
        </div>
    </div>
</div>

<!-- Reject reason modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="rejectForm">
                <div class="modal-header">
                    <h5 class="modal-title">Tolak Pengajuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="rejectId">
                    <label class="form-label small fw-bold">Alasan Penolakan *</label>
                    <textarea class="form-control" id="rejectReason" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const defaultStatus = <?= json_encode($defaultStatus) ?>;
    document.getElementById('fStatus').value = defaultStatus;

    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));

    const typeLabels = { LATE: 'Terlambat', CHECK_IN: 'Pengajuan Masuk', CHECK_OUT: 'Pengajuan Pulang', LEAVE: 'Cuti/Izin', OUTSIDE_OFFICE: 'Absen Luar Kantor' };
    function statusBadge(s) {
        const map = { PENDING: ['Menunggu', 'badge-at-orange'], APPROVED: ['Disetujui', 'badge-at-green'], REJECTED: ['Ditolak', 'badge-at-red'] };
        const m = map[s] || [s, 'badge-at-gray'];
        return `<span class="badge-at ${m[1]}">${m[0]}</span>`;
    }
    function dateRangeText(row) {
        if (row.type === 'LEAVE') return `${row.start_date || '-'} s/d ${row.end_date || '-'}`;
        return row.date || '-';
    }

    const table = $('#tblRequests').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: ADMIN_BASE_URL + 'admin/requests/list_data',
            type: 'POST',
            data: function (d) {
                d.filter_date_from = document.getElementById('fDateFrom').value;
                d.filter_date_to = document.getElementById('fDateTo').value;
                d.filter_office_id = document.getElementById('fOffice')?.value || '';
                d.filter_employee_id = document.getElementById('fEmployee').value;
                d.filter_type = document.getElementById('fType').value;
                d.filter_status = document.getElementById('fStatus').value;
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'created_at', render: d => d ? d.substring(0, 16).replace('T', ' ') : '-' },
            { data: null, render: row => `${row.employee_name}<div class="text-gray" style="font-size:11px;">${row.employee_code}</div>` },
            { data: 'office_name' },
            { data: 'type', render: t => typeLabels[t] || t },
            { data: null, render: dateRangeText },
            { data: 'status', render: statusBadge },
            {
                data: null, orderable: false, className: 'text-end',
                render: row => `<button class="btn btn-sm btn-outline-at-primary btn-detail" data-id="${row.id}"><i class="bi bi-eye me-1"></i>Detail</button>`
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/id.json' }
    });

    document.getElementById('filterForm').addEventListener('submit', function (e) { e.preventDefault(); table.ajax.reload(); });
    document.getElementById('btnReset').addEventListener('click', function () {
        document.getElementById('filterForm').reset();
        document.getElementById('fStatus').value = '';
        table.ajax.reload();
    });

    function isImage(filename) {
        return /\.(jpg|jpeg|png)$/i.test(filename || '');
    }

    function openDetail(id) {
        document.getElementById('detailBody').innerHTML = '<div class="text-center text-gray py-4">Memuat...</div>';
        document.getElementById('detailFooter').innerHTML = '';
        detailModal.show();

        fetch(ADMIN_BASE_URL + 'admin/requests/detail/' + id).then(r => r.json()).then(res => {
            if (!res.success) { document.getElementById('detailBody').innerHTML = `<p class="text-danger">${res.message}</p>`; return; }
            const r = res.data;
            const attachUrl = r.attachment ? ADMIN_BASE_URL + 'uploads/request_attachments/' + r.attachment : null;

            let attachHtml = '<p class="text-gray small">Tidak ada lampiran.</p>';
            if (attachUrl) {
                attachHtml = isImage(r.attachment)
                    ? `<a href="${attachUrl}" target="_blank"><img src="${attachUrl}" class="img-fluid rounded border" style="max-height:280px;"></a>`
                    : `<a href="${attachUrl}" target="_blank" class="btn btn-outline-at-primary btn-sm"><i class="bi bi-file-earmark-arrow-down me-1"></i>Buka Lampiran</a>`;
            }

            document.getElementById('detailBody').innerHTML = `
                <div class="row g-2 small mb-3">
                    <div class="col-6"><span class="text-gray">Karyawan</span><div class="fw-bold">${r.employee_name} (${r.employee_code})</div></div>
                    <div class="col-6"><span class="text-gray">Kantor</span><div class="fw-bold">${r.office_name}</div></div>
                    <div class="col-6"><span class="text-gray">Jenis</span><div class="fw-bold">${typeLabels[r.type] || r.type}</div></div>
                    <div class="col-6"><span class="text-gray">Tanggal</span><div class="fw-bold">${dateRangeText(r)}</div></div>
                    ${r.time ? `<div class="col-6"><span class="text-gray">Jam</span><div class="fw-bold">${r.time}</div></div>` : ''}
                    <div class="col-6"><span class="text-gray">Status</span><div>${statusBadge(r.status)}</div></div>
                </div>
                <div class="mb-3">
                    <span class="text-gray small">Keterangan</span>
                    <p class="mb-0">${r.reason}</p>
                </div>
                <div class="mb-2">
                    <span class="text-gray small">Lampiran</span>
                    <div class="mt-1">${attachHtml}</div>
                </div>
                ${r.status === 'REJECTED' && r.rejection_reason ? `<div class="alert alert-danger small py-2 mt-3"><strong>Alasan ditolak:</strong> ${r.rejection_reason}</div>` : ''}
                ${r.status !== 'PENDING' && r.approved_by_name ? `<p class="text-gray small mb-0">Diproses oleh ${r.approved_by_name} pada ${(r.approved_at || '').substring(0, 16).replace('T', ' ')}</p>` : ''}
            `;

            const footer = document.getElementById('detailFooter');
            if (r.status === 'PENDING') {
                footer.innerHTML = `
                    <button class="btn btn-outline-danger" id="btnRejectFromDetail"><i class="bi bi-x-lg me-1"></i>Tolak</button>
                    <button class="btn btn-at-primary" id="btnApproveFromDetail"><i class="bi bi-check-lg me-1"></i>Setujui</button>
                `;
                document.getElementById('btnApproveFromDetail').addEventListener('click', () => approveRequest(r.id));
                document.getElementById('btnRejectFromDetail').addEventListener('click', () => {
                    detailModal.hide();
                    document.getElementById('rejectId').value = r.id;
                    document.getElementById('rejectReason').value = '';
                    rejectModal.show();
                });
            } else {
                footer.innerHTML = '';
            }
        });
    }

    function approveRequest(id) {
        if (!confirm('Setujui pengajuan ini?')) return;
        fetch(ADMIN_BASE_URL + 'admin/requests/approve/' + id, { method: 'POST' })
            .then(r => r.json()).then(res => {
                if (res.success) { detailModal.hide(); table.ajax.reload(null, false); }
                else alert(res.message);
            });
    }

    $('#tblRequests tbody').on('click', '.btn-detail', function () { openDetail($(this).data('id')); });

    document.getElementById('rejectForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const id = document.getElementById('rejectId').value;
        const fd = new FormData();
        fd.append('reason', document.getElementById('rejectReason').value);
        fetch(ADMIN_BASE_URL + 'admin/requests/reject/' + id, { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) { rejectModal.hide(); table.ajax.reload(null, false); }
                else alert(res.message);
            });
    });
});
</script>

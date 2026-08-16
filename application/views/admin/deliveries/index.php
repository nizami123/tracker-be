<div class="mb-3">
    <h4 class="fw-bold mb-0">History Pengiriman Kendaraan</h4>
    <p class="text-gray small mb-0">Menampilkan pengiriman hari ini secara default.</p>
</div>

<div class="at-card mb-3">
    <form id="filterForm" class="row g-3 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Tanggal</label>
            <input type="date" class="form-control" id="fDateFrom" value="<?= $todayDate ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Sampai Tanggal</label>
            <input type="date" class="form-control" id="fDateTo" value="<?= $todayDate ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Driver</label>
            <select class="form-select" id="fDriver">
                <option value="">Semua</option>
                <?php foreach ($drivers as $d): ?><option value="<?= $d['id'] ?>"><?= html_escape($d['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Kantor Tujuan</label>
            <select class="form-select" id="fDestination">
                <option value="">Semua</option>
                <?php foreach ($offices as $o): ?><option value="<?= $o['id'] ?>"><?= html_escape($o['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-bold">Status</label>
            <select class="form-select" id="fStatus">
                <option value="">Semua</option>
                <option value="IN_PROGRESS">Dalam Perjalanan</option>
                <option value="ARRIVED">Sampai Tujuan</option>
                <option value="COMPLETED">Selesai</option>
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
        <table class="table table-at table-hover align-middle w-100" id="tblDeliveries">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Driver</th>
                    <th>Merk / Tipe</th>
                    <th>No. Mesin</th>
                    <th>No. Rangka</th>
                    <th>Warna</th>
                    <th>Tujuan</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
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
        const map = { IN_PROGRESS: ['Dalam Perjalanan', 'badge-at-blue'], ARRIVED: ['Sampai Tujuan', 'badge-at-orange'], COMPLETED: ['Selesai', 'badge-at-green'] };
        const m = map[s] || [s, 'badge-at-gray'];
        return `<span class="badge-at ${m[1]}">${m[0]}</span>`;
    }

    const table = $('#tblDeliveries').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: ADMIN_BASE_URL + 'admin/deliveries/list_data',
            type: 'POST',
            data: function (d) {
                d.filter_date_from = document.getElementById('fDateFrom').value;
                d.filter_date_to = document.getElementById('fDateTo').value;
                d.filter_driver_id = document.getElementById('fDriver').value;
                d.filter_destination_office_id = document.getElementById('fDestination').value;
                d.filter_status = document.getElementById('fStatus').value;
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'created_at', render: d => d ? d.substring(0, 16).replace('T', ' ') : '-' },
            { data: null, render: row => `${row.driver_name}<div class="text-gray" style="font-size:11px;">${row.driver_code}</div>` },
            { data: null, render: row => `${row.brand} ${row.vehicle_type}` },
            { data: 'engine_number' },
            { data: 'chassis_number' },
            { data: 'color' },
            { data: 'destination_office_name', render: d => d || '-' },
            { data: 'pickup_time', render: d => d ? d.substring(11, 19) : '-' },
            { data: 'arrival_time', render: d => d ? d.substring(11, 19) : '-' },
            { data: 'status', render: statusBadge },
            {
                data: null, orderable: false, className: 'text-end',
                render: row => `
                    <a href="${ADMIN_BASE_URL}admin/deliveries/detail/${row.id}" class="btn btn-sm btn-outline-secondary" title="Detail"><i class="bi bi-eye"></i></a>
                    <a href="${ADMIN_BASE_URL}admin/delivery_tracking/detail/${row.id}" class="btn btn-sm btn-outline-at-primary" title="Lihat Tracking"><i class="bi bi-geo-alt"></i></a>
                `
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/id.json' }
    });

    document.getElementById('filterForm').addEventListener('submit', function (e) { e.preventDefault(); table.ajax.reload(); });
    document.getElementById('btnReset').addEventListener('click', function () {
        document.getElementById('fDateFrom').value = todayDate;
        document.getElementById('fDateTo').value = todayDate;
        document.getElementById('fDriver').value = '';
        document.getElementById('fDestination').value = '';
        document.getElementById('fStatus').value = '';
        table.ajax.reload();
    });
});
</script>

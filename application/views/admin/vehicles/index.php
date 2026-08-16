<div class="mb-3">
    <h4 class="fw-bold mb-0">Master Kendaraan</h4>
    <p class="text-gray small mb-0">Daftar kendaraan unik yang pernah dikirim driver.</p>
</div>

<div class="alert alert-warning d-flex align-items-start gap-2 small">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div>
        Halaman ini <strong>read-only</strong>. Sistem belum memiliki tabel master kendaraan
        tersendiri — data berikut diturunkan dari riwayat <em>Pengiriman Kendaraan</em>
        (dikelompokkan berdasarkan nomor mesin &amp; nomor rangka). Kendaraan baru otomatis
        muncul di sini setelah driver melakukan pengiriman pertama kalinya lewat aplikasi.
    </div>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100" id="tblVehicles">
            <thead>
                <tr>
                    <th>Nomor Mesin</th>
                    <th>Nomor Rangka</th>
                    <th>Merk</th>
                    <th>Tipe</th>
                    <th>Warna</th>
                    <th>Total Pengiriman</th>
                    <th>Pengiriman Terakhir</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="vehicleHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Riwayat Pengiriman Kendaraan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="vehicleHistoryBody">
                <div class="text-center text-gray py-4">Memuat...</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const historyModal = new bootstrap.Modal(document.getElementById('vehicleHistoryModal'));

    $('#tblVehicles').DataTable({
        ajax: { url: ADMIN_BASE_URL + 'admin/vehicles/list_data', dataSrc: 'data' },
        columns: [
            { data: 'engine_number' },
            { data: 'chassis_number' },
            { data: 'brand' },
            { data: 'vehicle_type' },
            { data: 'color' },
            { data: 'total_deliveries' },
            { data: 'last_delivery_at', render: d => d ? d.substring(0, 16).replace('T', ' ') : '-' },
            {
                data: null, orderable: false, className: 'text-end',
                render: row => `<button class="btn btn-sm btn-outline-at-primary btn-history" data-engine="${row.engine_number}"><i class="bi bi-clock-history me-1"></i>Riwayat</button>`
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/id.json' }
    });

    $('#tblVehicles tbody').on('click', '.btn-history', function () {
        const engine = $(this).data('engine');
        document.getElementById('vehicleHistoryBody').innerHTML = '<div class="text-center text-gray py-4">Memuat...</div>';
        historyModal.show();
        fetch(ADMIN_BASE_URL + 'admin/vehicles/history/' + encodeURIComponent(engine))
            .then(r => r.json()).then(res => {
                const rows = res.data || [];
                if (rows.length === 0) {
                    document.getElementById('vehicleHistoryBody').innerHTML = '<p class="text-gray text-center py-4">Belum ada riwayat.</p>';
                    return;
                }
                let html = '<div class="table-responsive"><table class="table table-at table-sm"><thead><tr><th>Tanggal</th><th>Driver</th><th>Tujuan</th><th>Status</th></tr></thead><tbody>';
                rows.forEach(r => {
                    const badge = r.status === 'COMPLETED' ? 'badge-at-green' : (r.status === 'ARRIVED' ? 'badge-at-orange' : 'badge-at-blue');
                    html += `<tr>
                        <td>${(r.created_at || '').substring(0, 16).replace('T', ' ')}</td>
                        <td>${r.driver_name || '-'}</td>
                        <td>${r.destination_office_name || '-'}</td>
                        <td><span class="badge-at ${badge}">${r.status}</span></td>
                    </tr>`;
                });
                html += '</tbody></table></div>';
                document.getElementById('vehicleHistoryBody').innerHTML = html;
            });
    });
});
</script>

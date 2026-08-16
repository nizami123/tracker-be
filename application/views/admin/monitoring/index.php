<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Tracking Aktif</h4>
        <p class="text-gray small mb-0">Karyawan dan driver yang sedang tracking saat ini. Posisi diperbarui otomatis tiap 30 detik.</p>
    </div>
    <span class="badge-at badge-at-green"><i class="bi bi-circle-fill" style="font-size:8px;"></i> <span id="totalActiveCount">0</span> Aktif</span>
</div>

<div class="at-card mb-3">
    <div id="monitorMap" class="at-map"></div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="at-card">
            <h6 class="fw-bold mb-3"><i class="bi bi-people-fill me-1"></i>Karyawan Tracking (<?= count($employees) ?>)</h6>
            <div class="table-responsive">
                <table class="table table-at table-sm align-middle">
                    <thead><tr><th>Nama</th><th>Kantor</th><th>Mulai</th><th>Update</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr><td colspan="5" class="text-center text-gray py-3">Tidak ada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($employees as $e): ?>
                            <tr>
                                <td><?= html_escape($e['person_name']) ?></td>
                                <td><?= html_escape($e['office_name']) ?></td>
                                <td><?= $e['started_at'] ? html_escape(substr($e['started_at'], 11, 8)) : '-' ?></td>
                                <td><?= $e['last_update'] ? html_escape(substr($e['last_update'], 11, 8)) : '-' ?></td>
                                <td><a href="<?= site_url('admin/attendance_tracking/detail/' . $e['id']) ?>" class="btn btn-sm btn-outline-at-primary"><i class="bi bi-geo-alt"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="at-card">
            <h6 class="fw-bold mb-3"><i class="bi bi-truck-front-fill me-1"></i>Driver Mengirim (<?= count($drivers) ?>)</h6>
            <div class="table-responsive">
                <table class="table table-at table-sm align-middle">
                    <thead><tr><th>Nama</th><th>Tujuan</th><th>Mulai</th><th>Update</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($drivers)): ?>
                            <tr><td colspan="5" class="text-center text-gray py-3">Tidak ada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($drivers as $d): ?>
                            <tr>
                                <td><?= html_escape($d['person_name']) ?></td>
                                <td><?= html_escape($d['office_name'] ?: '-') ?></td>
                                <td><?= $d['started_at'] ? html_escape(substr($d['started_at'], 11, 8)) : '-' ?></td>
                                <td><?= $d['last_update'] ? html_escape(substr($d['last_update'], 11, 8)) : '-' ?></td>
                                <td><a href="<?= site_url('admin/delivery_tracking/detail/' . $d['id']) ?>" class="btn btn-sm btn-outline-at-primary"><i class="bi bi-geo-alt"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const map = L.map('monitorMap').setView([-7.35, 112.55], 10); // rough East Java center as a fallback view
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const employeeIcon = L.divIcon({
        html: '<div style="background:#2E7BE0;color:#fff;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,.3);"><i class="bi bi-person-fill"></i></div>',
        className: '', iconSize: [26, 26], iconAnchor: [13, 13]
    });
    const driverIcon = L.divIcon({
        html: '<div style="background:#1E9E5A;color:#fff;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,.3);"><i class="bi bi-truck-front-fill"></i></div>',
        className: '', iconSize: [26, 26], iconAnchor: [13, 13]
    });

    let markers = {}; // key: "TYPE-id"

    function popupHtml(item) {
        return `
            <div style="font-size:12.5px;">
                <strong>${item.person_name}</strong> (${item.tracker_type === 'DRIVER' ? 'Driver' : 'Karyawan'})<br>
                Status: ${item.tracker_type === 'DRIVER' ? (item.delivery_status || '-') : 'Tracking aktif'}<br>
                Update terakhir: ${item.last_update || '-'}<br>
                Akurasi: ${item.last_accuracy ? Math.round(item.last_accuracy) + ' m' : '-'}<br>
                Kecepatan: ${item.last_speed ? parseFloat(item.last_speed).toFixed(1) + ' m/s' : '-'}
            </div>`;
    }

    function renderMarkers(employees, drivers) {
        const seen = new Set();
        const allLatLngs = [];

        [...employees, ...drivers].forEach(item => {
            if (!item.last_lat || !item.last_lng) return;
            const key = item.tracker_type + '-' + item.id;
            seen.add(key);
            const latlng = [parseFloat(item.last_lat), parseFloat(item.last_lng)];
            allLatLngs.push(latlng);

            if (markers[key]) {
                markers[key].setLatLng(latlng);
                markers[key].setPopupContent(popupHtml(item));
            } else {
                const marker = L.marker(latlng, { icon: item.tracker_type === 'DRIVER' ? driverIcon : employeeIcon })
                    .addTo(map)
                    .bindPopup(popupHtml(item));
                markers[key] = marker;
            }
        });

        // Remove markers for trackers that ended since the last poll.
        Object.keys(markers).forEach(key => {
            if (!seen.has(key)) {
                map.removeLayer(markers[key]);
                delete markers[key];
            }
        });

        document.getElementById('totalActiveCount').textContent = seen.size;

        if (allLatLngs.length > 0 && !map._hasFitOnce) {
            map.fitBounds(L.latLngBounds(allLatLngs), { padding: [40, 40] });
            map._hasFitOnce = true;
        }
    }

    renderMarkers(<?= json_encode($employees) ?>, <?= json_encode($drivers) ?>);

    // Polling: position-only refresh every 30s, no full-page reload,
    // no re-fetching route history — just current markers.
    setInterval(function () {
        fetch(ADMIN_BASE_URL + 'admin/monitoring/positions_data')
            .then(r => r.json()).then(res => {
                if (res.success) renderMarkers(res.employees, res.drivers);
            });
    }, 30000);
});
</script>

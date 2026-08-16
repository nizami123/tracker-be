<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= site_url('admin/deliveries') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h4 class="fw-bold mb-0">Tracking Pengiriman Kendaraan</h4>
        <p class="text-gray small mb-0"><?= html_escape($delivery['brand']) ?> <?= html_escape($delivery['vehicle_type']) ?> — <?= html_escape($delivery['driver_name']) ?></p>
    </div>
    <span id="liveBadge" class="badge-at badge-at-gray ms-auto"></span>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Driver</div><div class="fw-bold"><?= html_escape($delivery['driver_name']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">No. Mesin / Rangka</div><div class="fw-bold" style="font-size:12.5px;"><?= html_escape($delivery['engine_number']) ?> / <?= html_escape($delivery['chassis_number']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Merk / Tipe / Warna</div><div class="fw-bold" style="font-size:12.5px;"><?= html_escape($delivery['brand']) ?> <?= html_escape($delivery['vehicle_type']) ?>, <?= html_escape($delivery['color']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Tujuan</div><div class="fw-bold"><?= html_escape($delivery['destination_office_name'] ?: '-') ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Waktu Mulai</div><div class="fw-bold"><?= $delivery['pickup_time'] ? substr($delivery['pickup_time'], 11, 8) : '-' ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Waktu Selesai</div><div class="fw-bold"><?= $delivery['arrival_time'] ? substr($delivery['arrival_time'], 11, 8) : 'Belum selesai' ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Status</div><div class="fw-bold"><?= html_escape($delivery['status']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Total Titik</div><div class="fw-bold" id="totalPoints">-</div></div></div>
</div>

<div class="at-card">
    <div id="trackingMap" class="at-map"></div>
</div>

<div class="modal fade" id="pointModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">Detail Titik</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body small" id="pointModalBody"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deliveryId = <?= (int) $delivery['id'] ?>;
    const status = <?= json_encode($delivery['status']) ?>;
    const hasDestination = <?= $delivery['destination_latitude'] ? 'true' : 'false' ?>;
    const destLat = <?= (float) ($delivery['destination_latitude'] ?? 0) ?>;
    const destLng = <?= (float) ($delivery['destination_longitude'] ?? 0) ?>;
    const destRadius = <?= (int) ($delivery['destination_radius'] ?? 100) ?>;

    const liveBadge = document.getElementById('liveBadge');
    function setBadge(st) {
        const map = {
            IN_PROGRESS: ['<i class="bi bi-circle-fill" style="font-size:8px;"></i> DALAM PERJALANAN', 'badge-at-blue'],
            ARRIVED: ['SAMPAI TUJUAN — MENUNGGU SELESAI', 'badge-at-orange'],
            COMPLETED: ['<i class="bi bi-check-circle-fill"></i> SELESAI', 'badge-at-green']
        };
        const m = map[st] || [st, 'badge-at-gray'];
        liveBadge.innerHTML = m[0];
        liveBadge.className = 'badge-at ms-auto ' + m[1];
    }
    setBadge(status);

    const map = L.map('trackingMap');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    if (hasDestination) {
        const officeIcon = L.divIcon({
            html: '<div style="background:#1E9E5A;color:#fff;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.3);"><i class="bi bi-building"></i></div>',
            className: '', iconSize: [30, 30], iconAnchor: [15, 15]
        });
        L.marker([destLat, destLng], { icon: officeIcon }).addTo(map).bindPopup('Kantor Tujuan');
        L.circle([destLat, destLng], { radius: destRadius, color: '#1E9E5A', weight: 1, fillOpacity: .08 }).addTo(map);
    }

    let polyline = null;
    let markers = [];
    let lastPointTime = null;

    function pointPopupHtml(p) {
        return `
            <div style="font-size:12.5px;">
                <strong>${p.recorded_at}</strong><br>
                Lat: ${parseFloat(p.latitude).toFixed(6)}<br>
                Lng: ${parseFloat(p.longitude).toFixed(6)}<br>
                Akurasi: ${p.accuracy ? Math.round(p.accuracy) + ' m' : '-'}<br>
                Kecepatan: ${p.speed ? parseFloat(p.speed).toFixed(1) + ' m/s' : '-'}<br>
                Baterai: ${p.battery_level ? p.battery_level + '%' : '-'}
            </div>`;
    }

    function renderPoints(points) {
        if (polyline) map.removeLayer(polyline);
        markers.forEach(m => map.removeLayer(m));
        markers = [];

        if (points.length === 0) {
            if (hasDestination) map.setView([destLat, destLng], 14);
            document.getElementById('totalPoints').textContent = '0';
            return;
        }

        const latlngs = points.map(p => [parseFloat(p.latitude), parseFloat(p.longitude)]);
        polyline = L.polyline(latlngs, { color: '#1E9E5A', weight: 4 }).addTo(map);

        points.forEach((p, idx) => {
            let icon;
            if (idx === 0) {
                icon = L.divIcon({ html: '<div style="background:#2E7BE0;color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;box-shadow:0 2px 4px rgba(0,0,0,.3);">A</div>', className: '', iconSize: [22, 22], iconAnchor: [11, 11] });
            } else if (idx === points.length - 1) {
                icon = L.divIcon({ html: '<div style="background:#E53935;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,.3);"><i class="bi bi-truck"></i></div>', className: '', iconSize: [24, 24], iconAnchor: [12, 12] });
            } else {
                icon = L.divIcon({ html: '<div style="background:#fff;border:2px solid #1E9E5A;width:12px;height:12px;border-radius:50%;"></div>', className: '', iconSize: [12, 12], iconAnchor: [6, 6] });
            }
            const marker = L.marker([p.latitude, p.longitude], { icon }).addTo(map);
            marker.on('click', function () {
                document.getElementById('pointModalBody').innerHTML = pointPopupHtml(p);
                new bootstrap.Modal(document.getElementById('pointModal')).show();
            });
            markers.push(marker);
        });

        document.getElementById('totalPoints').textContent = points.length;
        lastPointTime = points[points.length - 1].recorded_at;

        const boundsPoints = hasDestination ? latlngs.concat([[destLat, destLng]]) : latlngs;
        map.fitBounds(L.latLngBounds(boundsPoints), { padding: [30, 30] });
    }

    fetch(ADMIN_BASE_URL + 'admin/delivery_tracking/points_data/' + deliveryId)
        .then(r => r.json()).then(res => { if (res.success) renderPoints(res.data); });

    if (status !== 'COMPLETED') {
        const pollInterval = setInterval(function () {
            fetch(ADMIN_BASE_URL + 'admin/delivery_tracking/latest_position/' + deliveryId)
                .then(r => r.json()).then(res => {
                    if (!res.success) return;
                    setBadge(res.status);
                    if (res.point && res.point.recorded_at !== lastPointTime) {
                        fetch(ADMIN_BASE_URL + 'admin/delivery_tracking/points_data/' + deliveryId)
                            .then(r => r.json()).then(res2 => { if (res2.success) renderPoints(res2.data); });
                    }
                    if (!res.is_active) clearInterval(pollInterval);
                });
        }, 30000);
    }
});
</script>

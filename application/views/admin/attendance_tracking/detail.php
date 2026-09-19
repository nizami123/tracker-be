<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= site_url('admin/attendances') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h4 class="fw-bold mb-0">Tracking Absensi</h4>
        <p class="text-gray small mb-0"><?= html_escape($attendance['employee_name']) ?></p>
    </div>
    <span id="liveBadge" class="badge-at badge-at-gray ms-auto"></span>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Karyawan</div><div class="fw-bold"><?= html_escape($attendance['employee_name']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">NIP</div><div class="fw-bold"><?= html_escape($attendance['nip'] ?: '-') ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Kantor</div><div class="fw-bold"><?= html_escape($attendance['office_name']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Tanggal</div><div class="fw-bold"><?= html_escape($attendance['attendance_date']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Jam Masuk</div><div class="fw-bold"><?= $attendance['check_in_time'] ? substr($attendance['check_in_time'], 11, 8) : '-' ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Jam Pulang</div><div class="fw-bold"><?= $attendance['check_out_time'] ? substr($attendance['check_out_time'], 11, 8) : (!empty($attendance['is_pending']) ? 'Belum absen masuk' : 'Masih bekerja') ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Status</div><div class="fw-bold"><?= html_escape($attendance['status']) ?></div></div></div>
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
    // Revisi: a pending (not-yet-checked-in) session has no real
    // attendance row, so it's addressed by employee_id against the
    // _pending AJAX endpoints instead of attendance_id against the
    // normal ones — everything else on this page (map, polling,
    // rendering) is identical either way.
    const isPending = <?= !empty($attendance['is_pending']) ? 'true' : 'false' ?>;
    const trackingKey = <?= (int) ($attendance['is_pending'] ? $attendance['employee_id'] : $attendance['id']) ?>;
    const pointsUrl = ADMIN_BASE_URL + 'admin/attendance_tracking/' + (isPending ? 'points_data_pending/' : 'points_data/') + trackingKey;
    const latestUrl = ADMIN_BASE_URL + 'admin/attendance_tracking/' + (isPending ? 'latest_position_pending/' : 'latest_position/') + trackingKey;
    const isCheckedOut = <?= $attendance['check_out_time'] ? 'true' : 'false' ?>;
    const officeLat = <?= (float) $attendance['office_latitude'] ?>;
    const officeLng = <?= (float) $attendance['office_longitude'] ?>;
    const officeRadius = <?= (int) $attendance['check_in_radius'] ?>;

    const liveBadge = document.getElementById('liveBadge');
    function setBadge(active) {
        if (isPending) {
            liveBadge.innerHTML = '<i class="bi bi-clock-history"></i> BELUM ABSEN MASUK — TRACKING AKTIF';
            liveBadge.className = 'badge-at ms-auto badge-at-orange';
            return;
        }
        liveBadge.innerHTML = active
            ? '<i class="bi bi-circle-fill" style="font-size:8px;"></i> TRACKING AKTIF'
            : '<i class="bi bi-check-circle-fill"></i> TRACKING SELESAI';
        liveBadge.className = 'badge-at ms-auto ' + (active ? 'badge-at-green' : 'badge-at-gray');
    }
    setBadge(!isCheckedOut);

    const map = L.map('trackingMap');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Office marker + radius circle.
    const officeIcon = L.divIcon({
        html: '<div style="background:#2F6FED;color:#fff;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.3);"><i class="bi bi-building"></i></div>',
        className: '', iconSize: [30, 30], iconAnchor: [15, 15]
    });
    L.marker([officeLat, officeLng], { icon: officeIcon }).addTo(map).bindPopup('Kantor');
    L.circle([officeLat, officeLng], { radius: officeRadius, color: '#2F6FED', weight: 1, fillOpacity: .08 }).addTo(map);

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
        if (points.length === 0) return;

        // Perbaikan: Total Titik & lastPointTime langsung diisi di sini,
        // SEBELUM proses gambar polyline/marker di peta. Sebelumnya baris
        // ini ada di paling bawah fungsi ini — jadi kalau ADA SATU SAJA
        // titik dengan lat/lng yang tidak valid (bikin Leaflet melempar
        // error "Invalid LatLng" saat bikin polyline/marker), seluruh
        // fungsi berhenti di tengah jalan (exception) dan baris ini
        // (yang ada di bawah) tidak pernah kejalan — hasilnya "Total
        // Titik" tetap kosong ("-") padahal datanya di database banyak.
        document.getElementById('totalPoints').textContent = points.length;
        lastPointTime = points[points.length - 1].recorded_at;

        if (polyline) map.removeLayer(polyline);
        markers.forEach(m => map.removeLayer(m));
        markers = [];

        // Buang titik yang lat/lng-nya tidak valid supaya satu baris data
        // rusak tidak menggagalkan seluruh peta (tetap dihitung di Total
        // Titik di atas, tapi tidak digambar).
        const validPoints = points.filter(p => {
            const lat = parseFloat(p.latitude), lng = parseFloat(p.longitude);
            return Number.isFinite(lat) && Number.isFinite(lng);
        });

        if (validPoints.length === 0) return;

        try {
            const latlngs = validPoints.map(p => [parseFloat(p.latitude), parseFloat(p.longitude)]);
            polyline = L.polyline(latlngs, { color: '#2F6FED', weight: 4 }).addTo(map);

            validPoints.forEach((p, idx) => {
                let icon;
                if (idx === 0) {
                    icon = L.divIcon({ html: '<div style="background:#2E7BE0;color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;box-shadow:0 2px 4px rgba(0,0,0,.3);">A</div>', className: '', iconSize: [22, 22], iconAnchor: [11, 11] });
                } else if (idx === validPoints.length - 1) {
                    icon = L.divIcon({ html: '<div style="background:#E53935;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,.3);"><i class="bi bi-geo-alt-fill"></i></div>', className: '', iconSize: [24, 24], iconAnchor: [12, 12] });
                } else {
                    icon = L.divIcon({ html: '<div style="background:#fff;border:2px solid #2F6FED;width:12px;height:12px;border-radius:50%;"></div>', className: '', iconSize: [12, 12], iconAnchor: [6, 6] });
                }
                const marker = L.marker([p.latitude, p.longitude], { icon }).addTo(map);
                marker.on('click', function () {
                    document.getElementById('pointModalBody').innerHTML = pointPopupHtml(p);
                    new bootstrap.Modal(document.getElementById('pointModal')).show();
                });
                markers.push(marker);
            });

            // Auto fit bounds so the whole route + office are visible.
            const bounds = L.latLngBounds(latlngs.concat([[officeLat, officeLng]]));
            map.fitBounds(bounds, { padding: [30, 30] });
        } catch (err) {
            // Total Titik sudah terisi di atas walau bagian gambar peta
            // ini gagal — jadi admin tetap tahu jumlah titiknya, cuma
            // petanya yang tidak bisa ditampilkan.
            console.error('Gagal menggambar titik tracking di peta:', err);
        }
    }

    // Initial full load (once).
    fetch(pointsUrl)
        .then(r => r.json()).then(res => {
            if (res.success) {
                if (res.data.length === 0) {
                    map.setView([officeLat, officeLng], 16);
                    document.getElementById('totalPoints').textContent = '0';
                } else {
                    renderPoints(res.data);
                }
            } else {
                console.error('points_data gagal:', res.message || res);
            }
        })
        .catch(err => console.error('Gagal memuat titik tracking:', err));

    // Realtime polling: only fetch the single latest point every 30s,
    // and only re-render if it's actually new — never re-fetch the
    // whole polyline on a timer.
    if (!isCheckedOut) {
        var pollInterval = setInterval(function () {
            fetch(latestUrl)
                .then(r => r.json()).then(res => {
                    if (!res.success) return;
                    setBadge(res.is_active);
                    if (res.point && res.point.recorded_at !== lastPointTime) {
                        // A genuinely new point arrived — reload the full polyline once to include it.
                        fetch(pointsUrl)
                            .then(r => r.json()).then(res2 => { if (res2.success) renderPoints(res2.data); });
                    }
                    if (!res.is_active) {
                        clearInterval(pollInterval);
                    }
                });
        }, 30000);
    }
});
</script>

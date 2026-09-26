<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= site_url('admin/deliveries') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h4 class="fw-bold mb-0">Detail Pengiriman</h4>
        <p class="text-gray small mb-0"><?= html_escape($delivery['brand']) ?> <?= html_escape($delivery['vehicle_type']) ?> — <?= html_escape($delivery['driver_name']) ?></p>
    </div>
    <?php
    $statusMap = array('IN_PROGRESS' => ['Dalam Perjalanan', 'badge-at-blue'], 'ARRIVED' => ['Sampai Tujuan', 'badge-at-orange'], 'COMPLETED' => ['Selesai', 'badge-at-green']);
    [$statusLabel, $statusClass] = $statusMap[$delivery['status']] ?? [$delivery['status'], 'badge-at-gray'];
    ?>
    <span class="badge-at <?= $statusClass ?> ms-auto"><?= $statusLabel ?></span>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Driver</div><div class="fw-bold"><?= html_escape($delivery['driver_name']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">No. Mesin</div><div class="fw-bold"><?= html_escape($delivery['engine_number']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">No. Rangka</div><div class="fw-bold"><?= html_escape($delivery['chassis_number']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Warna</div><div class="fw-bold"><?= html_escape($delivery['color']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Tujuan</div><div class="fw-bold"><?= html_escape($delivery['destination_office_name'] ?: $delivery['destination_name'] ?: '-') ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Alamat Tujuan</div><div class="fw-bold"><?= html_escape($delivery['destination_office_address'] ?: $delivery['destination_address'] ?: '-') ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Waktu Mulai</div><div class="fw-bold"><?= $delivery['pickup_time'] ? substr($delivery['pickup_time'], 0, 16) : '-' ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Waktu Selesai</div><div class="fw-bold"><?= $delivery['arrival_time'] ? substr($delivery['arrival_time'], 0, 16) : '-' ?></div></div></div>
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Jarak Saat Tiba</div><div class="fw-bold"><?= $delivery['arrival_distance'] !== null ? round($delivery['arrival_distance']) . ' m' : '-' ?></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="at-card h-100">
            <h6 class="fw-bold mb-2"><i class="bi bi-camera me-1"></i>Foto Saat Pengambilan</h6>
            <?php if (!empty($delivery['pickup_photo'])): ?>
                <a href="<?= base_url('uploads/attendance_photos/' . $delivery['pickup_photo']) ?>" target="_blank">
                    <img src="<?= base_url('uploads/attendance_photos/' . $delivery['pickup_photo']) ?>" class="img-fluid rounded border w-100" style="max-height:320px;object-fit:cover;">
                </a>
                <p class="text-gray small mt-2 mb-0"><?= $delivery['pickup_time'] ? substr($delivery['pickup_time'], 0, 16) : '-' ?></p>
            <?php else: ?>
                <p class="text-gray small mb-0">Belum ada foto.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="at-card h-100">
            <h6 class="fw-bold mb-2"><i class="bi bi-camera-fill me-1"></i>Foto Saat Tiba</h6>
            <?php if (!empty($delivery['arrival_photo'])): ?>
                <a href="<?= base_url('uploads/attendance_photos/' . $delivery['arrival_photo']) ?>" target="_blank">
                    <img src="<?= base_url('uploads/attendance_photos/' . $delivery['arrival_photo']) ?>" class="img-fluid rounded border w-100" style="max-height:320px;object-fit:cover;">
                </a>
                <p class="text-gray small mt-2 mb-0"><?= $delivery['arrival_time'] ? substr($delivery['arrival_time'], 0, 16) : '-' ?></p>
            <?php else: ?>
                <p class="text-gray small mb-0">Kendaraan belum tiba / belum diselesaikan.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($delivery['notes']) || !empty($delivery['arrival_notes'])): ?>
<div class="row g-3 mb-3">
    <?php if (!empty($delivery['notes'])): ?>
        <div class="col-md-6"><div class="at-card"><div class="text-gray small mb-1">Keterangan Saat Pengambilan</div><?= html_escape($delivery['notes']) ?></div></div>
    <?php endif; ?>
    <?php if (!empty($delivery['arrival_notes'])): ?>
        <div class="col-md-6"><div class="at-card"><div class="text-gray small mb-1">Keterangan Saat Tiba</div><?= html_escape($delivery['arrival_notes']) ?></div></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($delivery['destination_office_id'])): ?>
<div class="at-card mb-3">
    <h6 class="fw-bold mb-1"><i class="bi bi-geo-alt me-1"></i>Titik Koordinat Tujuan</h6>
    <p class="text-gray small mb-3">
        Tujuan pengiriman ini adalah alamat bebas yang ditulis driver, bukan kantor terdaftar.
        Driver hanya mengisi alamat lengkapnya — isi/perbarui titik lat &amp; lng di sini sebagai
        patokan radius kedatangan (validasi "Selesaikan Pengiriman" di aplikasi driver memakai titik ini).
        Selama titik ini belum diisi, pengiriman boleh diselesaikan driver tanpa pengecekan radius.
    </p>

    <div class="row g-3">
        <div class="col-md-5">
            <div class="mb-2">
                <label class="form-label small text-gray mb-1">Alamat dari Driver</label>
                <div class="fw-bold"><?= html_escape($delivery['destination_address'] ?: $delivery['destination_name'] ?: '-') ?></div>
            </div>
            <div class="mb-2">
                <label class="form-label small text-gray mb-1" for="destLat">Latitude</label>
                <input type="text" id="destLat" class="form-control form-control-sm" inputmode="decimal"
                       value="<?= $delivery['destination_latitude'] !== null ? html_escape($delivery['destination_latitude']) : '' ?>" placeholder="Klik peta di samping">
            </div>
            <div class="mb-2">
                <label class="form-label small text-gray mb-1" for="destLng">Longitude</label>
                <input type="text" id="destLng" class="form-control form-control-sm" inputmode="decimal"
                       value="<?= $delivery['destination_longitude'] !== null ? html_escape($delivery['destination_longitude']) : '' ?>" placeholder="Klik peta di samping">
            </div>
            <div class="mb-2">
                <label class="form-label small text-gray mb-1" for="destRadius">Radius Kedatangan (meter)</label>
                <input type="number" id="destRadius" class="form-control form-control-sm" min="1"
                       value="<?= html_escape($delivery['destination_radius'] ?: 100) ?>">
            </div>
            <div id="destPointError" class="text-danger small mb-2 d-none"></div>
            <button type="button" id="btnSaveDestPoint" class="btn btn-sm btn-at-primary">
                <i class="bi bi-check2 me-1"></i>Simpan Titik Koordinat
            </button>
            <span id="destPointSaved" class="text-success small ms-2 d-none"><i class="bi bi-check-circle-fill me-1"></i>Tersimpan</span>
        </div>
        <div class="col-md-7">
            <div id="destPointMap" style="height:280px;border-radius:8px;"></div>
            <p class="text-gray small mt-1 mb-0">Klik di peta atau geser marker untuk menentukan titik lokasi tujuan.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="at-card mb-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-1"></i>Timeline Pengiriman</h6>
        <a href="<?= site_url('admin/delivery_tracking/detail/' . $delivery['id']) ?>" class="btn btn-sm btn-outline-at-primary">
            <i class="bi bi-map me-1"></i>Lihat Peta Tracking
        </a>
    </div>

    <?php if (empty($timeline)): ?>
        <p class="text-gray small mb-0">Belum ada data untuk membentuk timeline.</p>
    <?php else: ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($timeline as $step): ?>
                <li class="d-flex gap-3 mb-3">
                    <div class="text-gray small" style="width:70px; flex-shrink:0;">
                        <?= $step['time'] ? html_escape(substr($step['time'], 11, 5)) : '' ?>
                    </div>
                    <div class="d-flex flex-column align-items-center" style="width:20px; flex-shrink:0;">
                        <div style="width:10px;height:10px;border-radius:50%;background:#2F6FED;"></div>
                        <div style="width:1px;flex:1;background:#E5E8EB;"></div>
                    </div>
                    <div class="small pb-1"><?= html_escape($step['label']) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php if (empty($delivery['destination_office_id'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const deliveryId = <?= (int) $delivery['id'] ?>;
    const latInput = document.getElementById('destLat');
    const lngInput = document.getElementById('destLng');

    const initialLat = parseFloat(latInput.value) || -7.257472;
    const initialLng = parseFloat(lngInput.value) || 112.752090;

    const map = L.map('destPointMap').setView([initialLat, initialLng], latInput.value ? 16 : 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;
    if (latInput.value && lngInput.value) {
        marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);
        bindMarkerDrag();
    }

    function bindMarkerDrag() {
        marker.on('dragend', function (e) {
            const pos = e.target.getLatLng();
            latInput.value = pos.lat.toFixed(7);
            lngInput.value = pos.lng.toFixed(7);
        });
    }

    map.on('click', function (e) {
        latInput.value = e.latlng.lat.toFixed(7);
        lngInput.value = e.latlng.lng.toFixed(7);
        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng, { draggable: true }).addTo(map);
            bindMarkerDrag();
        }
    });

    setTimeout(function () { map.invalidateSize(); }, 250);

    document.getElementById('btnSaveDestPoint').addEventListener('click', function () {
        const errEl = document.getElementById('destPointError');
        const savedEl = document.getElementById('destPointSaved');
        errEl.classList.add('d-none');
        savedEl.classList.add('d-none');

        const lat = latInput.value.trim();
        const lng = lngInput.value.trim();
        if (!lat || !lng || isNaN(parseFloat(lat)) || isNaN(parseFloat(lng))) {
            errEl.textContent = 'Klik peta atau isi latitude & longitude terlebih dahulu';
            errEl.classList.remove('d-none');
            return;
        }

        const fd = new FormData();
        fd.append('destination_latitude', lat);
        fd.append('destination_longitude', lng);
        fd.append('destination_radius', document.getElementById('destRadius').value);

        fetch(ADMIN_BASE_URL + 'admin/deliveries/set_destination_point/' + deliveryId, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    savedEl.classList.remove('d-none');
                } else {
                    errEl.textContent = res.message || 'Gagal menyimpan titik koordinat';
                    errEl.classList.remove('d-none');
                }
            })
            .catch(function () {
                errEl.textContent = 'Gagal menghubungi server';
                errEl.classList.remove('d-none');
            });
    });
});
</script>
<?php endif; ?>

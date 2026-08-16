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
    <div class="col-md-3 col-6"><div class="at-card py-2"><div class="text-gray small">Tujuan</div><div class="fw-bold"><?= html_escape($delivery['destination_office_name'] ?: '-') ?></div></div></div>
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
                        <div style="width:10px;height:10px;border-radius:50%;background:#1E9E5A;"></div>
                        <div style="width:1px;flex:1;background:#E5E8EB;"></div>
                    </div>
                    <div class="small pb-1"><?= html_escape($step['label']) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

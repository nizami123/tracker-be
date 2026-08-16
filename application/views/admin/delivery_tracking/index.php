<div class="mb-3">
    <h4 class="fw-bold mb-0">Tracking Kendaraan</h4>
    <p class="text-gray small mb-0">Pengiriman kendaraan yang masih berjalan (belum selesai).</p>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100">
            <thead>
                <tr>
                    <th>Driver</th>
                    <th>Kendaraan</th>
                    <th>Tujuan</th>
                    <th>Mulai</th>
                    <th>Total Titik</th>
                    <th>Update Terakhir</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-gray py-4">Tidak ada pengiriman yang sedang berjalan.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php $isArrived = $r['status'] === 'ARRIVED'; ?>
                    <tr>
                        <td><?= html_escape($r['driver_name']) ?><div class="text-gray" style="font-size:11px;"><?= html_escape($r['driver_code']) ?></div></td>
                        <td><?= html_escape($r['brand']) ?> <?= html_escape($r['vehicle_type']) ?></td>
                        <td><?= html_escape($r['destination_office_name'] ?: '-') ?></td>
                        <td><?= $r['pickup_time'] ? html_escape(substr($r['pickup_time'], 11, 8)) : '-' ?></td>
                        <td><?= (int) $r['tracking_count'] ?></td>
                        <td><?= $r['last_point_at'] ? html_escape(substr($r['last_point_at'], 11, 8)) : '-' ?></td>
                        <td>
                            <?php if ($isArrived): ?>
                                <span class="badge-at badge-at-orange">SAMPAI TUJUAN</span>
                            <?php else: ?>
                                <span class="badge-at badge-at-blue"><i class="bi bi-circle-fill" style="font-size:8px;"></i> DALAM PERJALANAN</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= site_url('admin/delivery_tracking/detail/' . $r['id']) ?>" class="btn btn-sm btn-outline-at-primary">
                                <i class="bi bi-geo-alt me-1"></i>Lihat Map
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

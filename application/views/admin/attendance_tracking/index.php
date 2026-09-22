<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Tracking Karyawan</h4>
        <p class="text-gray small mb-0">Karyawan yang punya titik tracking pada tanggal terpilih — tidak bergantung pada absensi (belum absen pun tetap tampil).</p>
    </div>
    <form method="get" action="<?= site_url('admin/attendance_tracking') ?>" class="d-flex align-items-center gap-2">
        <input type="date" name="date" class="form-control form-control-sm" value="<?= html_escape($trackingDate) ?>" max="<?= html_escape(date('Y-m-d')) ?>">
        <button type="submit" class="btn btn-sm btn-at-primary">Tampilkan</button>
    </form>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Kantor</th>
                    <th>Mulai Tracking</th>
                    <th>Update Terakhir</th>
                    <th>Total Titik</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-gray py-4">Tidak ada tracking pada <?= html_escape($trackingDate) ?>.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= html_escape($r['employee_name']) ?><div class="text-gray" style="font-size:11px;"><?= html_escape($r['employee_code']) ?></div></td>
                        <td><?= html_escape($r['office_name']) ?></td>
                        <td><?= $r['first_point_at'] ? html_escape(substr($r['first_point_at'], 11, 8)) : '-' ?></td>
                        <td><?= $r['last_point_at'] ? html_escape(substr($r['last_point_at'], 11, 8)) : '-' ?></td>
                        <td><?= (int) $r['tracking_count'] ?></td>
                        <td>
                            <?php if ($isToday): ?>
                                <span class="badge-at badge-at-green"><i class="bi bi-circle-fill" style="font-size:8px;"></i> TRACKING AKTIF</span>
                            <?php else: ?>
                                <span class="badge-at badge-at-gray"><i class="bi bi-check-circle-fill"></i> RIWAYAT</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= site_url('admin/attendance_tracking/detail/' . (int) $r['employee_id'] . '/' . $trackingDate) ?>" class="btn btn-sm btn-outline-at-primary">
                                <i class="bi bi-geo-alt me-1"></i>Lihat Map
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

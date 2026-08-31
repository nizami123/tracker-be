<div class="mb-3">
    <h4 class="fw-bold mb-0">Tracking Karyawan</h4>
    <p class="text-gray small mb-0">Tracking hari ini — karyawan yang sudah absen masuk (belum pulang), maupun yang sudah mulai ter-tracking tapi belum absen masuk.</p>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Kantor</th>
                    <th>Jam Masuk</th>
                    <th>Total Titik</th>
                    <th>Update Terakhir</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-gray py-4">Tidak ada tracking hari ini.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= html_escape($r['employee_name']) ?><div class="text-gray" style="font-size:11px;"><?= html_escape($r['employee_code']) ?></div></td>
                        <td><?= html_escape($r['office_name']) ?></td>
                        <td><?= $r['is_pending'] ? '-' : html_escape(substr($r['check_in_time'] ?? '', 11, 8)) ?></td>
                        <td><?= (int) $r['tracking_count'] ?></td>
                        <td><?= $r['last_point_at'] ? html_escape(substr($r['last_point_at'], 11, 8)) : '-' ?></td>
                        <td>
                            <?php if ($r['is_pending']): ?>
                                <span class="badge-at badge-at-orange"><i class="bi bi-clock-history"></i> BELUM ABSEN MASUK</span>
                            <?php else: ?>
                                <span class="badge-at badge-at-green"><i class="bi bi-circle-fill" style="font-size:8px;"></i> TRACKING AKTIF</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php $detailUrl = $r['is_pending']
                                ? site_url('admin/attendance_tracking/detail_pending/' . $r['employee_id'])
                                : site_url('admin/attendance_tracking/detail/' . $r['id']); ?>
                            <a href="<?= $detailUrl ?>" class="btn btn-sm btn-outline-at-primary">
                                <i class="bi bi-geo-alt me-1"></i>Lihat Map
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

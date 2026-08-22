<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pengajuan</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #1a1a1a; padding: 24px; }
        h2 { margin-bottom: 2px; }
        .sub { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f1f3f5; }
        .summary { display: flex; gap: 16px; margin-bottom: 16px; }
        .summary div { border: 1px solid #ccc; border-radius: 8px; padding: 8px 14px; }
        .summary strong { display: block; font-size: 16px; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">Cetak / Simpan sebagai PDF</button></div>

    <h2>Laporan Pengajuan</h2>
    <p class="sub">Periode: <?= html_escape($filters['date_from']) ?> s/d <?= html_escape($filters['date_to']) ?> — dicetak <?= date('d F Y H:i') ?></p>

    <div class="summary">
        <div>Total<strong><?= $summary['total'] ?></strong></div>
        <div>Menunggu<strong><?= $summary['PENDING'] ?></strong></div>
        <div>Disetujui<strong><?= $summary['APPROVED'] ?></strong></div>
        <div>Ditolak<strong><?= $summary['REJECTED'] ?></strong></div>
    </div>

    <table>
        <thead><tr><th>Diajukan</th><th>Karyawan</th><th>Kantor</th><th>Jenis</th><th>Tanggal</th><th>Status</th></tr></thead>
        <tbody>
            <?php
            $typeLabels = array('LATE' => 'Terlambat', 'CHECK_IN' => 'Masuk', 'CHECK_OUT' => 'Pulang', 'LEAVE' => 'Cuti/Izin', 'OUTSIDE_OFFICE' => 'Absen Luar Kantor');
            ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= html_escape(substr($r['created_at'], 0, 16)) ?></td>
                    <td><?= html_escape($r['employee_name']) ?></td>
                    <td><?= html_escape($r['office_name']) ?></td>
                    <td><?= $typeLabels[$r['type']] ?? html_escape($r['type']) ?></td>
                    <td><?= $r['type'] === 'LEAVE' ? html_escape($r['start_date'] . ' s/d ' . $r['end_date']) : html_escape($r['date']) ?></td>
                    <td><?= html_escape($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Absensi</title>
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
    <div class="no-print">
        <button onclick="window.print()">Cetak / Simpan sebagai PDF</button>
    </div>

    <h2>Laporan Absensi</h2>
    <p class="sub">Periode: <?= html_escape($filters['date_from']) ?> s/d <?= html_escape($filters['date_to']) ?> — dicetak <?= date('d F Y H:i') ?></p>

    <div class="summary">
        <div>Hadir<strong><?= $summary['hadir'] ?></strong></div>
        <div>Terlambat<strong><?= $summary['terlambat'] ?></strong></div>
        <div>Tidak Hadir (estimasi)<strong><?= $summary['tidak_hadir'] ?></strong></div>
        <div>Izin/Cuti<strong><?= $summary['izin_cuti'] ?></strong></div>
        <div>Total Karyawan<strong><?= $summary['total_karyawan'] ?></strong></div>
    </div>

    <table>
        <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Kantor</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= html_escape($r['attendance_date']) ?></td>
                    <td><?= html_escape($r['employee_name']) ?> (<?= html_escape($r['employee_code']) ?>)</td>
                    <td><?= html_escape($r['office_name']) ?></td>
                    <td><?= $r['check_in_time'] ? html_escape(substr($r['check_in_time'], 11, 8)) : '-' ?></td>
                    <td><?= $r['check_out_time'] ? html_escape(substr($r['check_out_time'], 11, 8)) : '-' ?></td>
                    <td><?= html_escape($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>

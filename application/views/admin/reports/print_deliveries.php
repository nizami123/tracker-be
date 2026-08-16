<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pengiriman</title>
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

    <h2>Laporan Pengiriman Kendaraan</h2>
    <p class="sub">Periode: <?= html_escape($filters['date_from']) ?> s/d <?= html_escape($filters['date_to']) ?> — dicetak <?= date('d F Y H:i') ?></p>

    <div class="summary">
        <div>Jumlah Pengiriman<strong><?= $summary['total'] ?></strong></div>
        <div>Aktif<strong><?= $summary['IN_PROGRESS'] + $summary['ARRIVED'] ?></strong></div>
        <div>Selesai<strong><?= $summary['COMPLETED'] ?></strong></div>
        <div>Dibatalkan<strong><?= $summary['CANCELLED'] ?></strong></div>
    </div>

    <table>
        <thead><tr><th>Tanggal</th><th>Driver</th><th>Kendaraan</th><th>Tujuan</th><th>Mulai</th><th>Selesai</th><th>Status</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= html_escape(substr($r['created_at'], 0, 10)) ?></td>
                    <td><?= html_escape($r['driver_name']) ?></td>
                    <td><?= html_escape($r['brand'] . ' ' . $r['vehicle_type']) ?></td>
                    <td><?= html_escape($r['destination_office_name'] ?: '-') ?></td>
                    <td><?= $r['pickup_time'] ? html_escape(substr($r['pickup_time'], 11, 8)) : '-' ?></td>
                    <td><?= $r['arrival_time'] ? html_escape(substr($r['arrival_time'], 11, 8)) : '-' ?></td>
                    <td><?= html_escape($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>

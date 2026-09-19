<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= html_escape($filename) ?></title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }

        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 16px; }

        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none; } }

        .cal-page { page-break-after: always; }
        .cal-page:last-child { page-break-after: auto; }

        .cal-header { text-align: center; margin-bottom: 10px; }
        .cal-header h2 { margin: 0 0 2px; font-size: 16px; letter-spacing: .5px; }
        .cal-header .cal-period { margin: 0 0 10px; font-size: 13px; font-weight: bold; letter-spacing: .5px; }

        .cal-identity { display: inline-block; text-align: left; margin: 0 auto 10px; font-size: 12px; }
        .cal-identity table { border-collapse: collapse; }
        .cal-identity td { padding: 1px 6px; }
        .cal-identity td.label { color: #555; white-space: nowrap; }

        .cal-body { display: flex; gap: 10px; align-items: flex-start; }
        .cal-table-wrap { flex: 1; }
        .cal-summary { width: 150px; flex: 0 0 150px; }

        table.cal-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.cal-table th, table.cal-table td {
            border: 1px solid #999; padding: 2px; text-align: center; vertical-align: top;
        }
        table.cal-table th { background: #f1f3f5; font-size: 10px; padding: 4px 2px; }
        table.cal-table td { height: 46px; width: 14.28%; }

        .cal-daynum { font-weight: bold; font-size: 11px; }
        .cal-time { font-size: 9.5px; margin-top: 2px; }
        .cal-label { font-size: 9px; margin-top: 1px; font-weight: bold; }
        .cal-holiday-name { font-size: 8px; margin-top: 1px; }

        td.cal-sunday .cal-daynum { color: #c0392b; }
        td.cal-holiday { background: #fdecea; }
        td.cal-holiday .cal-daynum, td.cal-holiday .cal-label, td.cal-holiday .cal-holiday-name { color: #c0392b; }
        .cal-type-telat .cal-label { color: #e67e22; }
        .cal-type-izin .cal-label { color: #2980b9; }
        .cal-type-dinas_luar .cal-label { color: #8e44ad; }
        .cal-type-alfa .cal-label { color: #c0392b; }
        .cal-type-hadir .cal-label { color: #27ae60; }

        .cal-summary-box { border: 1px solid #999; border-radius: 4px; overflow: hidden; }
        .cal-summary-title { background: #f1f3f5; text-align: center; font-weight: bold; font-size: 10px; padding: 4px; border-bottom: 1px solid #999; }
        .cal-summary-value { text-align: center; font-size: 22px; font-weight: bold; padding: 6px 0; border-bottom: 1px solid #999; }
        .cal-summary-rows { padding: 6px 10px; }
        .cal-summary-rows div { display: flex; justify-content: space-between; font-size: 11px; padding: 2px 0; }
        .cal-summary-rows span:last-child { font-weight: bold; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Cetak / Simpan sebagai PDF</button>
    </div>

    <?php if (empty($pages)): ?>
        <p>Tidak ada data pegawai untuk filter yang dipilih.</p>
    <?php endif; ?>

    <?php foreach ($pages as $page): $emp = $page['employee']; $summary = $page['summary']; ?>
        <div class="cal-page">
            <div class="cal-header">
                <h2>REKAP ABSENSI PEGAWAI</h2>
                <p class="cal-period">BULAN <?= strtoupper(html_escape($monthLabel)) ?> <?= (int) $year ?></p>
                <div class="cal-identity">
                    <table>
                        <tr><td class="label">Nama</td><td>: <?= html_escape($emp['name']) ?></td></tr>
                        <tr><td class="label">Kantor</td><td>: <?= html_escape($emp['office_name']) ?></td></tr>
                        <?php if (!empty($emp['nip'])): ?>
                            <tr><td class="label">NIP/NIK</td><td>: <?= html_escape($emp['nip']) ?></td></tr>
                        <?php endif; ?>
                        <tr><td class="label">Kode Pegawai</td><td>: <?= html_escape($emp['employee_code']) ?></td></tr>
                        <?php if (!empty($emp['position'])): ?>
                            <tr><td class="label">Jabatan</td><td>: <?= html_escape($emp['position']) ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <div class="cal-body">
                <div class="cal-table-wrap">
                    <table class="cal-table">
                        <thead>
                            <tr>
                                <th>MINGGU</th><th>SENIN</th><th>SELASA</th><th>RABU</th>
                                <th>KAMIS</th><th>JUMAT</th><th>SABTU</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($page['weeks'] as $week): ?>
                                <tr>
                                    <?php foreach ($week as $cell): ?>
                                        <?php if ($cell === null): ?>
                                            <td></td>
                                        <?php else: ?>
                                            <td class="<?= $cell['is_sunday'] ? 'cal-sunday' : '' ?> <?= $cell['is_holiday'] ? 'cal-holiday' : '' ?> cal-type-<?= $cell['type'] ?>">
                                                <div class="cal-daynum"><?= $cell['date'] ?></div>
                                                <?php if ($cell['type'] === 'libur'): ?>
                                                    <div class="cal-label">LIBUR</div>
                                                    <?php if ($cell['holiday_name']): ?>
                                                        <div class="cal-holiday-name"><?= html_escape($cell['holiday_name']) ?></div>
                                                    <?php endif; ?>
                                                <?php elseif ($cell['type'] === 'izin'): ?>
                                                    <div class="cal-label">Izin/Cuti</div>
                                                <?php elseif ($cell['type'] === 'dinas_luar'): ?>
                                                    <div class="cal-label">Dinas Luar</div>
                                                <?php elseif ($cell['type'] === 'alfa'): ?>
                                                    <div class="cal-label">Alfa</div>
                                                <?php elseif ($cell['type'] === 'hadir' || $cell['type'] === 'telat'): ?>
                                                    <div class="cal-time"><?= html_escape($cell['time_label']) ?></div>
                                                    <?php if ($cell['type'] === 'telat'): ?>
                                                        <div class="cal-label">Telat</div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="cal-time">-</div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cal-summary">
                    <div class="cal-summary-box">
                        <div class="cal-summary-title">HARI KERJA</div>
                        <div class="cal-summary-value"><?= $summary['hari_kerja'] ?></div>
                        <div class="cal-summary-title">RESUME</div>
                        <div class="cal-summary-rows">
                            <div><span>Hadir</span><span><?= $summary['hadir'] ?></span></div>
                            <div><span>Telat</span><span><?= $summary['telat'] ?></span></div>
                            <div><span>Izin/Cuti</span><span><?= $summary['izin_cuti'] ?></span></div>
                            <div><span>Dinas Luar</span><span><?= $summary['dinas_luar'] ?></span></div>
                            <div><span>Alfa</span><span><?= $summary['alfa'] ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>

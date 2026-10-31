<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= html_escape($filename) ?></title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }

        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #1a1a1a; margin: 0; padding: 0; }

        .no-print { margin: 8px; }
        @media print { .no-print { display: none; } }

        /* 1 lembar = 3 pegawai */
        .sheet { width: 194mm; height: 281mm; page-break-after: always; overflow: hidden; }
        .sheet:last-child { page-break-after: auto; }

        .emp-block { height: 93.6mm; padding: 2mm 0; border-bottom: 1px dashed #aaa; overflow: hidden; }
        .emp-block:last-child { border-bottom: none; }

        .cal-header { margin-bottom: 4px; }
        .cal-header .row1 { display: flex; justify-content: space-between; align-items: baseline; }
        .cal-header h2 { margin: 0; font-size: 12px; letter-spacing: .5px; }
        .cal-header .cal-period { margin: 0; font-size: 11px; font-weight: bold; letter-spacing: .5px; }
        .cal-identity { font-size: 10px; margin-top: 2px; }
        .cal-identity span.label { color: #555; }
        .cal-identity span + span { margin-left: 14px; }

        .cal-body { display: flex; gap: 6px; align-items: flex-start; }
        .cal-table-wrap { flex: 1; }
        .cal-summary { width: 26mm; flex: 0 0 26mm; }

        table.cal-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.cal-table th, table.cal-table td {
            border: 1px solid #999; padding: 1px; text-align: center; vertical-align: top;
        }
        table.cal-table th { background: #f1f3f5; font-size: 12px; padding: 2px 1px; }
        table.cal-table td { height: 11.2mm; width: 14.28%; line-height: 1.1; }

        .cal-daynum { font-weight: bold; font-size: 13px; }
        .cal-time { font-size: 11px; margin-top: 1px; }
        .cal-label { font-size: 11px; margin-top: 1px; font-weight: bold; }
        .cal-holiday-name { font-size: 6px; margin-top: 1px; line-height: 1; }

        td.cal-sunday .cal-daynum { color: #c0392b; }
        td.cal-holiday { background: #fdecea; }
        td.cal-holiday .cal-daynum, td.cal-holiday .cal-label, td.cal-holiday .cal-holiday-name { color: #c0392b; }
        .cal-type-telat .cal-label { color: #e67e22; }
        .cal-type-izin .cal-label { color: #2980b9; }
        .cal-type-dinas_luar .cal-label { color: #8e44ad; }
        .cal-type-alfa .cal-label { color: #c0392b; }
        .cal-type-hadir .cal-label { color: #27ae60; }

        .cal-summary-box { border: 1px solid #999; border-radius: 3px; overflow: hidden; }
        .cal-summary-title { background: #f1f3f5; text-align: center; font-weight: bold; font-size: 8px; padding: 3px; border-bottom: 1px solid #999; }
        .cal-summary-value { text-align: center; font-size: 18px; font-weight: bold; padding: 4px 0; border-bottom: 1px solid #999; }
        .cal-summary-rows { padding: 4px 6px; }
        .cal-summary-rows div { display: flex; justify-content: space-between; font-size: 9px; padding: 2px 0; }
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

    <?php foreach (array_chunk($pages, 3) as $sheet): ?>
        <div class="sheet">
            <?php foreach ($sheet as $page): $emp = $page['employee']; $summary = $page['summary']; ?>
                <div class="emp-block">
                    <div class="cal-header">
                        <div class="row1">
                            <h2>REKAP ABSENSI PEGAWAI</h2>
                            <p class="cal-period">BULAN <?= strtoupper(html_escape($monthLabel)) ?> <?= (int) $year ?></p>
                        </div>
                        <div class="cal-identity">
                            <span><span class="label">Nama:</span> <b><?= html_escape($emp['name']) ?></b></span>
                            <span><span class="label">Kantor:</span> <?= html_escape($emp['office_name']) ?></span>
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
        </div>
    <?php endforeach; ?>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
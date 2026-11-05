<style>
    .shift-grid-wrap { overflow: auto; max-height: 70vh; }
    table.shift-grid { border-collapse: separate; border-spacing: 0; font-size: 12px; }
    table.shift-grid th, table.shift-grid td { border-right: 1px solid #e6e9ee; border-bottom: 1px solid #e6e9ee; text-align: center; padding: 0; min-width: 38px; }
    table.shift-grid thead th { position: sticky; top: 0; background: #f7f8fa; z-index: 2; padding: 4px 2px; font-weight: 600; }
    table.shift-grid th.emp-col, table.shift-grid td.emp-col { position: sticky; left: 0; background: #fff; z-index: 1; text-align: left; min-width: 190px; max-width: 220px; padding: 6px 10px; }
    table.shift-grid thead th.emp-col { z-index: 3; background: #f7f8fa; }
    table.shift-grid th.weekend { color: #d63939; }
    .shift-cell { display: block; width: 100%; height: 34px; line-height: 34px; border: 0; background: transparent; cursor: pointer; font-weight: 700; }
    .shift-cell:hover { outline: 2px solid #86b7fe; outline-offset: -2px; }
    .shift-cell.empty { color: #c3c9d2; font-weight: 400; }
    .shift-cell.off { background: #eceff3; color: #6b7280; }
    .shift-cell.inactive { text-decoration: line-through; opacity: .55; }
    .shift-legend .chip { display: inline-flex; align-items: center; gap: 6px; margin: 0 12px 6px 0; font-size: 12.5px; }
    .shift-legend .dot { width: 22px; height: 22px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Jadwal Shift</h4>
        <p class="text-gray small mb-0">
            Klik sebuah kotak untuk mengatur shift karyawan pada tanggal itu, atau pakai <strong>Atur Massal / Rotasi</strong>.
            Kotak kosong = belum dijadwalkan (aplikasi memakai jam default 08:00 - 16:30). Jadwal tidak mempengaruhi proses absen.
        </p>
    </div>
    <button class="btn btn-at-primary" id="btnBulk" <?= empty($tablesReady) ? 'disabled' : '' ?>><i class="bi bi-arrow-repeat me-1"></i>Atur Massal / Rotasi</button>
</div>

<?php if (empty($tablesReady)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Tabel shift belum ada di database. Import file
        <code>database/migrations/2026_11_05_shift_management.sql</code> terlebih dahulu.
    </div>
<?php endif; ?>

<div class="at-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-bold mb-1">Kantor</label>
            <select class="form-select" id="fOffice" <?= $isSuperAdmin ? '' : 'disabled' ?>>
                <?php foreach ($offices as $o): ?>
                    <option value="<?= $o['id'] ?>"><?= html_escape($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold mb-1">Bulan</label>
            <input type="month" class="form-control" id="fMonth" value="<?= html_escape($defaultMonth) ?>">
        </div>
        <div class="col-md-5 d-flex gap-2">
            <button class="btn btn-outline-secondary" id="btnPrev"><i class="bi bi-chevron-left"></i></button>
            <button class="btn btn-outline-secondary" id="btnNext"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>
</div>

<div class="at-card p-3">
    <div class="shift-legend mb-2" id="legend"></div>
    <div id="gridMsg" class="text-gray small"></div>
    <div class="shift-grid-wrap"><table class="shift-grid" id="grid"></table></div>
</div>

<!-- Modal: satu sel -->
<div class="modal fade" id="cellModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="cellForm">
                <div class="modal-header">
                    <h6 class="modal-title">Atur Shift</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="cellError" class="alert alert-danger py-2 small d-none"></div>
                    <div class="small mb-2"><strong id="cellEmp"></strong><br><span class="text-gray" id="cellDate"></span></div>
                    <select class="form-select" id="cellValue"></select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-at-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: massal / rotasi -->
<div class="modal fade" id="bulkModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="bulkForm">
                <div class="modal-header">
                    <h5 class="modal-title">Atur Massal / Rotasi Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="bulkError" class="alert alert-danger py-2 small d-none"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Dari Tanggal *</label>
                            <input type="date" class="form-control" id="bFrom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Sampai Tanggal *</label>
                            <input type="date" class="form-control" id="bTo" required>
                            <div class="form-text">Maksimal 62 hari per proses.</div>
                        </div>

                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold mb-0">Karyawan *</label>
                                <div class="small">
                                    <a href="#" id="bAll">Pilih semua</a> · <a href="#" id="bNone">Kosongkan</a>
                                </div>
                            </div>
                            <div class="border rounded p-2" id="bEmployees" style="max-height:180px; overflow:auto;"></div>
                            <div class="form-text">Urutan karyawan di daftar ini dipakai untuk menggeser pola (lihat "Geser awal pola").</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Pola (diulang sampai tanggal akhir) *</label>
                            <div id="bPattern"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="bAddStep"><i class="bi bi-plus-lg me-1"></i>Tambah langkah</button>
                            <div class="form-text">
                                Contoh satu shift tetap: 1 langkah (Pagi x 7 hari). Rotasi mingguan: Pagi x 7, Siang x 7, Malam x 7.
                                Langkah "Libur" dipakai untuk hari off.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Geser awal pola per karyawan (hari)</label>
                            <input type="number" class="form-control" id="bStagger" min="0" max="366" value="0">
                            <div class="form-text">0 = semua karyawan mulai dari awal pola yang sama. Isi mis. 7 agar karyawan ke-2 mulai 7 hari lebih maju dari karyawan ke-1, dst. (supaya tiap orang di shift berbeda).</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Jika sudah ada jadwal</label>
                            <select class="form-select" id="bOverwrite">
                                <option value="1">Timpa jadwal yang sudah ada</option>
                                <option value="0">Lewati (hanya isi yang kosong)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-at-primary" id="bSubmit">Terapkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const READY = <?= !empty($tablesReady) ? 'true' : 'false' ?>;
    const DOW = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    const PALETTE = [
        ['#dbeafe', '#1d4ed8'], ['#fef3c7', '#b45309'], ['#e0e7ff', '#4338ca'], ['#dcfce7', '#15803d'],
        ['#fce7f3', '#be185d'], ['#ccfbf1', '#0f766e'], ['#ede9fe', '#6d28d9'], ['#ffedd5', '#c2410c']
    ];

    const fOffice = document.getElementById('fOffice');
    const fMonth = document.getElementById('fMonth');
    const grid = document.getElementById('grid');
    const gridMsg = document.getElementById('gridMsg');
    const cellModal = new bootstrap.Modal(document.getElementById('cellModal'));
    const bulkModal = new bootstrap.Modal(document.getElementById('bulkModal'));

    let state = { days: [], employees: [], shifts: [], schedule: {} };
    let shiftById = {};
    let editing = null; // {employeeId, date}

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function colorFor(shiftId) {
        const idx = state.shifts.findIndex(s => String(s.id) === String(shiftId));
        return PALETTE[(idx < 0 ? 0 : idx) % PALETTE.length];
    }
    function activeShifts() { return state.shifts.filter(s => s.status === 'ACTIVE'); }

    function load() {
        if (!READY || !fOffice.value) return;
        gridMsg.textContent = 'Memuat...';
        const url = ADMIN_BASE_URL + 'admin/shift_schedules/grid_data?office_id=' + encodeURIComponent(fOffice.value) +
                    '&month=' + encodeURIComponent(fMonth.value);
        fetch(url).then(r => r.json()).then(res => {
            if (!res.success) { gridMsg.textContent = res.message; return; }
            state = res;
            shiftById = {};
            state.shifts.forEach(s => shiftById[s.id] = s);
            gridMsg.textContent = '';
            render();
        }).catch(() => { gridMsg.textContent = 'Gagal memuat data.'; });
    }

    function render() {
        // Legenda
        const legend = document.getElementById('legend');
        if (state.shifts.length === 0) {
            legend.innerHTML = '<span class="text-gray small">Belum ada shift untuk kantor ini. Buat dulu di menu <a href="' + ADMIN_BASE_URL + 'admin/shifts">Master Shift</a>.</span>';
        } else {
            legend.innerHTML = state.shifts.map(s => {
                const c = colorFor(s.id);
                return '<span class="chip"><span class="dot" style="background:' + c[0] + ';color:' + c[1] + '">' + esc(s.code) + '</span>' +
                    esc(s.name) + ' (' + esc(s.start_time) + '-' + esc(s.end_time) + ')' +
                    (s.status !== 'ACTIVE' ? ' <em class="text-gray">nonaktif</em>' : '') + '</span>';
            }).join('') + '<span class="chip"><span class="dot" style="background:#eceff3;color:#6b7280">L</span>Libur</span>';
        }

        // Grid
        let h = '<thead><tr><th class="emp-col">Karyawan</th>';
        state.days.forEach(d => {
            const we = (d.dow === 0 || d.dow === 6) ? ' weekend' : '';
            h += '<th class="' + we.trim() + '">' + d.d + '<br><span style="font-weight:400">' + DOW[d.dow] + '</span></th>';
        });
        h += '</tr></thead><tbody>';

        if (state.employees.length === 0) {
            h += '<tr><td class="emp-col text-gray" colspan="' + (state.days.length + 1) + '">Tidak ada karyawan aktif di kantor ini.</td></tr>';
        }
        state.employees.forEach(e => {
            h += '<tr><td class="emp-col"><div class="fw-bold">' + esc(e.name) + '</div><div class="text-gray" style="font-size:11px">' +
                 esc(e.employee_code) + (e.position ? ' · ' + esc(e.position) : '') + '</div></td>';
            const row = state.schedule[e.id] || {};
            state.days.forEach(d => {
                const cell = row[d.date];
                let cls = 'shift-cell empty', label = '·', style = '', title = 'Belum dijadwalkan (jam default)';
                if (cell) {
                    if (cell.shift_id === null) {
                        cls = 'shift-cell off'; label = 'L'; title = 'Libur';
                    } else {
                        const s = shiftById[cell.shift_id];
                        const c = colorFor(cell.shift_id);
                        cls = 'shift-cell' + (s && s.status !== 'ACTIVE' ? ' inactive' : '');
                        label = s ? esc(s.code) : '?';
                        style = 'background:' + c[0] + ';color:' + c[1] + ';';
                        title = s ? (s.name + ' ' + s.start_time + '-' + s.end_time + (s.status !== 'ACTIVE' ? ' (nonaktif - di aplikasi dianggap belum dijadwalkan)' : '')) : '';
                    }
                }
                h += '<td><button type="button" class="' + cls + '" style="' + style + '" title="' + esc(title) + '" data-emp="' + e.id + '" data-date="' + d.date + '">' + label + '</button></td>';
            });
            h += '</tr>';
        });
        h += '</tbody>';
        grid.innerHTML = h;
    }

    // ---- klik sel ----
    grid.addEventListener('click', function (ev) {
        const btn = ev.target.closest('button.shift-cell');
        if (!btn) return;
        const empId = btn.dataset.emp, date = btn.dataset.date;
        const emp = state.employees.find(e => String(e.id) === empId);
        const cell = (state.schedule[empId] || {})[date];
        editing = { employeeId: empId, date: date };
        document.getElementById('cellEmp').textContent = emp ? emp.name : '';
        document.getElementById('cellDate').textContent = date;
        document.getElementById('cellError').classList.add('d-none');

        let opts = '<option value="">- Belum dijadwalkan (jam default) -</option><option value="OFF">Libur</option>';
        activeShifts().forEach(s => {
            opts += '<option value="' + s.id + '">' + esc(s.code) + ' - ' + esc(s.name) + ' (' + esc(s.start_time) + '-' + esc(s.end_time) + ')</option>';
        });
        const sel = document.getElementById('cellValue');
        sel.innerHTML = opts;
        sel.value = !cell ? '' : (cell.shift_id === null ? 'OFF' : String(cell.shift_id));
        cellModal.show();
    });

    document.getElementById('cellForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!editing) return;
        const fd = new FormData();
        fd.append('employee_id', editing.employeeId);
        fd.append('date', editing.date);
        fd.append('value', document.getElementById('cellValue').value);
        fetch(ADMIN_BASE_URL + 'admin/shift_schedules/save_cell', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) { cellModal.hide(); load(); }
                else {
                    const err = document.getElementById('cellError');
                    err.textContent = res.message; err.classList.remove('d-none');
                }
            });
    });

    // ---- navigasi ----
    fOffice.addEventListener('change', load);
    fMonth.addEventListener('change', load);
    function shiftMonth(delta) {
        const [y, m] = fMonth.value.split('-').map(Number);
        const d = new Date(y, m - 1 + delta, 1);
        fMonth.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        load();
    }
    document.getElementById('btnPrev').addEventListener('click', () => shiftMonth(-1));
    document.getElementById('btnNext').addEventListener('click', () => shiftMonth(1));

    // ---- massal / rotasi ----
    const bPattern = document.getElementById('bPattern');
    function shiftOptions() {
        return '<option value="OFF">Libur</option>' + activeShifts().map(s =>
            '<option value="' + s.id + '">' + esc(s.code) + ' - ' + esc(s.name) + ' (' + esc(s.start_time) + '-' + esc(s.end_time) + ')</option>').join('');
    }
    function addStep(shiftVal, days) {
        const row = document.createElement('div');
        row.className = 'd-flex gap-2 mb-1 align-items-center bstep';
        row.innerHTML = '<select class="form-select form-select-sm bstep-shift">' + shiftOptions() + '</select>' +
            '<span class="small">x</span>' +
            '<input type="number" class="form-control form-control-sm bstep-days" min="1" max="31" value="' + (days || 7) + '" style="max-width:80px">' +
            '<span class="small">hari</span>' +
            '<button type="button" class="btn btn-sm btn-outline-danger bstep-del"><i class="bi bi-x"></i></button>';
        if (shiftVal) row.querySelector('.bstep-shift').value = shiftVal;
        bPattern.appendChild(row);
    }
    bPattern.addEventListener('click', function (e) {
        const del = e.target.closest('.bstep-del');
        if (del && bPattern.children.length > 1) del.closest('.bstep').remove();
    });
    document.getElementById('bAddStep').addEventListener('click', () => addStep(null, 7));

    document.getElementById('btnBulk').addEventListener('click', function () {
        if (activeShifts().length === 0) {
            alert('Belum ada shift aktif untuk kantor ini. Buat dulu di menu Master Shift.');
            return;
        }
        document.getElementById('bulkError').classList.add('d-none');
        // Default rentang = bulan yang sedang ditampilkan.
        const first = state.days[0], last = state.days[state.days.length - 1];
        document.getElementById('bFrom').value = first ? first.date : '';
        document.getElementById('bTo').value = last ? last.date : '';
        document.getElementById('bStagger').value = 0;
        document.getElementById('bOverwrite').value = '1';
        document.getElementById('bEmployees').innerHTML = state.employees.map(e =>
            '<label class="d-flex align-items-center gap-2 py-1"><input type="checkbox" class="form-check-input mt-0 bemp" value="' + e.id + '">' +
            '<span>' + esc(e.name) + ' <span class="text-gray small">' + esc(e.employee_code) + '</span></span></label>').join('') ||
            '<span class="text-gray small">Tidak ada karyawan aktif.</span>';
        bPattern.innerHTML = '';
        addStep(String(activeShifts()[0].id), 7);
        bulkModal.show();
    });
    document.getElementById('bAll').addEventListener('click', e => { e.preventDefault(); document.querySelectorAll('.bemp').forEach(c => c.checked = true); });
    document.getElementById('bNone').addEventListener('click', e => { e.preventDefault(); document.querySelectorAll('.bemp').forEach(c => c.checked = false); });

    document.getElementById('bulkForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('office_id', fOffice.value);
        fd.append('date_from', document.getElementById('bFrom').value);
        fd.append('date_to', document.getElementById('bTo').value);
        fd.append('stagger', document.getElementById('bStagger').value || 0);
        fd.append('overwrite', document.getElementById('bOverwrite').value);
        document.querySelectorAll('.bemp:checked').forEach(c => fd.append('employee_ids[]', c.value));
        document.querySelectorAll('.bstep').forEach(r => {
            fd.append('pattern_shift[]', r.querySelector('.bstep-shift').value);
            fd.append('pattern_days[]', r.querySelector('.bstep-days').value);
        });
        const btn = document.getElementById('bSubmit');
        btn.disabled = true;
        fetch(ADMIN_BASE_URL + 'admin/shift_schedules/bulk_save', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                btn.disabled = false;
                if (res.success) { bulkModal.hide(); load(); alert(res.message); }
                else {
                    const err = document.getElementById('bulkError');
                    err.textContent = res.message; err.classList.remove('d-none');
                }
            }).catch(() => { btn.disabled = false; });
    });

    load();
});
</script>

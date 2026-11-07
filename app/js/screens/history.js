import { Attendance } from '../repo.js';
import { Time, icon, esc, $, $$ } from '../util.js';

const LABEL = { LATE: 'Terlambat', PRESENT: 'Hadir', ABSENT: 'Tidak Hadir' };
const TONE = { LATE: 'orange', ABSENT: 'red' };

export function history(view, ctx) {
  view.innerHTML = `
  <div class="topbar"><h1>Riwayat Absensi</h1><button class="icon-btn" id="refresh" aria-label="Segarkan">${icon('refresh', 22)}</button></div>
  <div class="pad" style="padding-bottom:0"></div>
  <div class="tabs" id="tabs"><button class="active" data-t="0">Harian</button><button data-t="1">Mingguan</button><button data-t="2">Bulanan</button></div>
  <div id="note"></div>
  <div id="list"><div class="loading"><span class="spinner"></span></div></div>`;

  let items = [], tab = 0, fromCache = false;

  function filtered() {
    const today = Time.today();
    if (tab === 1) { const from = Time.daysAgo(7); return items.filter((a) => a.attendance_date > from); }
    if (tab === 2) return items.filter((a) => a.attendance_date.slice(0, 7) === today.slice(0, 7));
    return items;
  }

  function render() {
    $('#note', view).innerHTML = fromCache
      ? `<div class="banner warn">${icon('info', 18)}<div>Menampilkan data tersimpan (offline).</div></div>` : '';
    const list = filtered();
    $('#list', view).innerHTML = list.length ? list.map((a) => `
      <button class="card item" data-d="${esc(a.attendance_date)}" style="display:block;width:calc(100% - 32px);text-align:left;border:0">
        <div class="top"><div class="ttl">${Time.longDate(a.attendance_date)}</div>
          <span class="pill ${TONE[a.status] || 'green'}">${esc(LABEL[a.status] || a.status)}</span></div>
        <div class="times">
          <div><span class="muted">Masuk</span><b>${a.check_in_time ? Time.withLabel(a.check_in_time) : '-'}</b>
            <span class="small muted">${a.check_in_distance != null ? Math.round(a.check_in_distance) + ' m dari kantor' : ''}</span></div>
          <div><span class="muted">Pulang</span><b>${a.check_out_time ? Time.withLabel(a.check_out_time) : '-'}</b>
            <span class="small muted">${a.check_out_distance != null ? Math.round(a.check_out_distance) + ' m dari kantor' : ''}</span></div>
        </div>
        <div class="sub row between"><span>Durasi kerja: <b>${Time.durationHM(a.check_in_time, a.check_out_time)}</b></span><span class="link">Lihat rute ›</span></div>
      </button>`).join('')
      : `<div class="empty">${icon('history', 44)}<p>Belum ada riwayat absensi</p></div>`;
    $$('[data-d]', view).forEach((b) => b.addEventListener('click', () => ctx.go('trackdetail/' + b.dataset.d)));
  }

  async function load() {
    const r = await Attendance.history();
    if (!ctx.isCurrent()) return;
    items = [...r.list].sort((a, b) => b.attendance_date.localeCompare(a.attendance_date));
    fromCache = r.fromCache;
    render();
  }

  $$('#tabs button', view).forEach((b) => b.addEventListener('click', () => {
    tab = +b.dataset.t;
    $$('#tabs button', view).forEach((x) => x.classList.toggle('active', x === b));
    render();
  }));
  $('#refresh', view).addEventListener('click', () => { $('#list', view).innerHTML = '<div class="loading"><span class="spinner"></span></div>'; load(); });
  load();
}

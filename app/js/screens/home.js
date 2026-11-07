import { session } from '../store.js';
import { Employee, Office, Attendance, Shift, Delivery } from '../repo.js';
import { Time, icon, esc, $, toast } from '../util.js';
import { CONFIG } from '../config.js';

function shiftHours(office, shift) {
  const z = Time.code;
  if (shift && shift.source === 'SHIFT' && shift.start_time && shift.end_time) return `${shift.start_time} - ${shift.end_time} ${z}`;
  if (shift && shift.source === 'OFF') return 'Libur';
  return `${CONFIG.WORK_START} - ${CONFIG.WORK_END} ${z}`;
}
function shiftName(shift) {
  if (!shift) return 'Pagi';
  if (shift.source === 'SHIFT') return shift.shift_name || shift.shift_code || 'Pagi';
  if (shift.source === 'OFF') return 'Libur';
  return 'Pagi';
}

export function home(view, ctx) {
  const emp0 = session.employee || {};
  view.innerHTML = `
  <div class="hero">
    <div class="hello" id="hello">${Time.greeting()}</div>
    <div class="name" id="name">${esc(emp0.name || '-')}</div>
    <div class="date">${Time.longDate()}</div>
  </div>
  <div class="pull-up">
    <div class="card" id="statusCard">
      <div class="small muted">Status Hari Ini</div>
      <div class="status-big mt8" id="stHead">Memuat...</div>
      <div class="muted" id="stSub">&nbsp;</div>
      <button class="btn primary block mt12" id="btnAbsen" disabled>${icon('camera', 20)} <span id="btnAbsenTxt">ABSEN</span></button>
      <div class="grid2 mt16">
        <div><div class="lbl">Jam Kerja</div><div class="val" id="hours">${CONFIG.WORK_START} - ${CONFIG.WORK_END} ${Time.code}</div></div>
        <div><div class="lbl">Kantor</div><div class="val" id="office">-</div></div>
        <div><div class="lbl">Radius</div><div class="val" id="radius">-</div></div>
        <div><div class="lbl">Shift</div><div class="val" id="shift">Pagi</div></div>
      </div>
    </div>

    <button class="card flat hidden" id="deliveryCard" style="display:block;width:calc(100% - 32px);text-align:left">
      <div class="row gap">
        <div class="tile" style="padding:0;box-shadow:none;background:none"><span class="badge">${icon('truck', 24)}</span></div>
        <div class="grow"><div style="font-weight:700">Pengiriman Kendaraan</div><div class="small muted" id="deliverySub">Memuat...</div></div>
        ${icon('chevron', 20)}
      </div>
    </button>

    <h3 class="section-title">Menu Cepat</h3>
    <div class="tiles">
      <button class="tile" data-go="checkin"><span class="badge">${icon('camera', 22)}</span>Absen</button>
      <button class="tile" data-go="history"><span class="badge">${icon('history', 22)}</span>Riwayat</button>
      <button class="tile" data-go="requests"><span class="badge">${icon('doc', 22)}</span>Pengajuan</button>
      <button class="tile" data-go="profile"><span class="badge">${icon('user', 22)}</span>Profil</button>
    </div>
    <div class="banner mt16">${icon('info', 18)}<div>Pastikan GPS aktif dan izinkan akses lokasi &amp; kamera agar absensi berhasil.</div></div>
  </div>`;

  let attendance = null, emp = emp0;

  const openAbsen = () => {
    if (attendance && attendance.check_out_time) { toast('Anda sudah absen pulang hari ini'); return; }
    const out = !!(attendance && attendance.check_in_time && !attendance.check_out_time);
    ctx.go(out ? 'checkin?out=1' : 'checkin');
  };
  $('#btnAbsen', view).addEventListener('click', openAbsen);
  view.querySelectorAll('[data-go]').forEach((b) => b.addEventListener('click', () => {
    if (b.dataset.go === 'checkin') openAbsen(); else ctx.go(b.dataset.go);
  }));

  function renderStatus() {
    const head = $('#stHead', view), sub = $('#stSub', view), btn = $('#btnAbsen', view), txt = $('#btnAbsenTxt', view);
    if (!attendance) {
      head.textContent = 'Belum Absen Masuk'; sub.textContent = 'Silakan lakukan absen untuk memulai';
      txt.textContent = 'ABSEN MASUK'; btn.disabled = false;
    } else if (!attendance.check_out_time) {
      head.textContent = 'Sedang Bekerja'; sub.textContent = `Masuk pukul ${Time.withLabel(attendance.check_in_time)}`;
      txt.textContent = 'ABSEN PULANG'; btn.disabled = false;
    } else {
      head.textContent = 'Sudah Absen Pulang'; sub.textContent = 'Sampai jumpa besok!';
      txt.textContent = 'SELESAI HARI INI'; btn.disabled = true;
    }
  }

  async function load() {
    // 1) cache langsung (cepat)  2) segarkan dari server
    emp = (await Employee.refresh()) || emp;
    if (!ctx.isCurrent()) return;
    $('#name', view).textContent = emp.name || '-';
    const [office, shift, att] = await Promise.all([Office.get(emp.office_id), Shift.current(), Attendance.today()]);
    if (!ctx.isCurrent()) return;
    attendance = att;
    renderStatus();
    $('#office', view).textContent = office ? office.name.replace(/^Kantor /, '') : '-';
    $('#radius', view).textContent = office ? `${office.check_in_radius} meter` : '-';
    $('#hours', view).textContent = shiftHours(office, shift);
    $('#shift', view).textContent = shiftName(shift);

    if (emp.role === 'DRIVER') {
      const card = $('#deliveryCard', view);
      card.classList.remove('hidden');
      card.onclick = async () => ctx.go((await Delivery.active()) ? 'delivery' : 'pickup');
      const active = await Delivery.active();
      if (!ctx.isCurrent()) return;
      $('#deliverySub', view).textContent = active
        ? `Pengiriman sedang berjalan — ${active.brand} ${active.vehicle_type}`
        : 'Tidak ada pengiriman aktif';
    }
  }
  load();
}

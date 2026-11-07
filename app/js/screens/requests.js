import { session } from '../store.js';
import { Requests } from '../repo.js';
import { Time, icon, esc, $, $$, getPosition, toast } from '../util.js';
import { imageFileToJpeg, fileToBase64 } from '../camera.js';

const TYPES = [
  ['LATE', 'Pengajuan Terlambat'],
  ['CHECK_IN', 'Pengajuan Masuk'],
  ['OUTSIDE_OFFICE', 'Absen Luar Kantor'],
  ['CHECK_OUT', 'Pengajuan Pulang'],
  ['OVERTIME', 'Pengajuan Lembur'],
  ['LEAVE', 'Cuti / Izin'],
];
const typeLabel = (t) => (TYPES.find((x) => x[0] === t) || [, t])[1];
const typeIcon = (t) => ({ LEAVE: 'doc', LATE: 'clock', OUTSIDE_OFFICE: 'briefcase', OVERTIME: 'clock' }[t] || 'pin');
const STATUS = {
  APPROVED: ['Disetujui', 'green'], REJECTED: ['Ditolak', 'red'], PENDING: ['Menunggu', 'orange'],
};

export function requests(view, ctx) {
  view.innerHTML = `
  <div class="topbar"><h1>Status Pengajuan</h1><button class="icon-btn" id="refresh" aria-label="Segarkan">${icon('refresh', 22)}</button></div>
  <div class="pad" style="padding-bottom:0"></div>
  <div class="tabs" id="tabs"><button class="active" data-s="">Semua</button><button data-s="PENDING">Menunggu</button><button data-s="APPROVED">Disetujui</button><button data-s="REJECTED">Ditolak</button></div>
  <div id="note"></div>
  <div id="list"><div class="loading"><span class="spinner"></span></div></div>
  <button class="fab" id="add" aria-label="Pengajuan baru">${icon('plus', 28)}</button>`;

  let items = [], filter = '', fromCache = false;

  function dateText(r) {
    return r.type === 'LEAVE' ? `${Time.shortDate(r.start_date)} - ${Time.shortDate(r.end_date)}` : Time.shortDate(r.date);
  }
  function render() {
    $('#note', view).innerHTML = fromCache
      ? `<div class="banner warn">${icon('info', 18)}<div>Menampilkan data tersimpan (offline).</div></div>` : '';
    const list = filter ? items.filter((r) => r.status === filter) : items;
    $('#list', view).innerHTML = list.length ? list.map((r) => {
      const [label, tone] = STATUS[r.status] || STATUS.PENDING;
      return `<div class="card item">
        <div class="top"><span class="pill blue" style="padding:8px;display:grid;place-items:center">${icon(typeIcon(r.type), 18)}</span>
          <div class="ttl">${esc(typeLabel(r.type))}<div class="small muted" style="font-weight:500">${esc(dateText(r))}${r.type === 'OVERTIME' ? ' • ' + esc((r.time || '-').slice(0, 5)) + ' - ' + esc((r.end_time || '-').slice(0, 5)) : (r.time ? ' • ' + esc(r.time) : '')}</div></div>
          <span class="pill ${tone}">${label}</span></div>
        <div class="sub">${esc(r.reason)}</div>
        ${r.status === 'REJECTED' && r.rejection_reason ? `<div class="error" style="margin-bottom:0">Alasan ditolak: ${esc(r.rejection_reason)}</div>` : ''}
      </div>`;
    }).join('') : `<div class="empty">${icon('doc', 44)}<p>Belum ada pengajuan</p></div>`;
  }
  async function load() {
    const r = await Requests.list();
    if (!ctx.isCurrent()) return;
    items = [...r.list].sort((a, b) => (b.id || 0) - (a.id || 0)); fromCache = r.fromCache; render();
  }
  $$('#tabs button', view).forEach((b) => b.addEventListener('click', () => {
    filter = b.dataset.s; $$('#tabs button', view).forEach((x) => x.classList.toggle('active', x === b)); render();
  }));
  $('#refresh', view).addEventListener('click', load);
  $('#add', view).addEventListener('click', () => ctx.go('newrequest'));
  load();
}

// ---------------------------------------------------------------------------
export function newRequest(view, ctx) {
  const emp = session.employee;
  const today = Time.today();
  view.innerHTML = `
  <div class="topbar"><button class="icon-btn" id="back" aria-label="Kembali">${icon('back', 24)}</button><h1>Pengajuan Baru</h1></div>
  <div class="pad">
    <div class="field"><label for="type">Jenis Pengajuan</label>
      <select class="input" id="type">${TYPES.map(([v, l]) => `<option value="${v}">${l}</option>`).join('')}</select></div>

    <div id="single">
      <div class="field"><label for="date">Tanggal</label><input class="input" type="date" id="date" value="${today}"></div>
      <div class="field"><label for="time" id="timeLbl">Jam</label><input class="input" type="time" id="time"></div>
      <div class="field hidden" id="endWrap"><label for="endTime">Jam Selesai Lembur</label><input class="input" type="time" id="endTime"></div>
    </div>
    <div id="leave" class="hidden">
      <div class="field"><label for="sd">Tanggal Mulai</label><input class="input" type="date" id="sd" value="${today}"></div>
      <div class="field"><label for="ed">Tanggal Selesai</label><input class="input" type="date" id="ed" value="${today}"></div>
    </div>
    <div id="overtimeInfo" class="banner warn hidden">${icon('info', 18)}<div>Pengajuan lembur perlu disetujui admin. Setelah disetujui, keterangan lembur tampil di kalender absen dan riwayat absensi.</div></div>
    <div id="outside" class="banner hidden">${icon('pin', 18)}<div><b>Absen luar kantor</b><div id="outsideSt" class="small">Mencari lokasi…</div>
      <div class="small">Posisi GPS Anda dicatat sebagai bukti, tanpa validasi radius kantor.</div></div></div>

    <div class="field"><label for="reason">Alasan</label><textarea class="input" id="reason" rows="3" placeholder="Tuliskan alasan pengajuan"></textarea></div>

    <div class="field"><label>Lampiran <span class="muted">(wajib)</span></label>
      <div class="card flat" style="margin:0;padding:12px">
        <div class="row gap"><img class="thumb hidden" id="thumb" alt=""><div class="grow" id="attName" style="word-break:break-all">Pilih file atau buka kamera</div>
          <button class="icon-btn hidden" id="attClear" aria-label="Hapus lampiran">${icon('close', 20)}</button></div>
        <div class="row gap mt12">
          <button type="button" class="btn outline sm grow" id="pick">${icon('paperclip', 18)} Pilih File</button>
          <button type="button" class="btn outline sm grow" id="shoot">${icon('camera', 18)} Buka Kamera</button>
        </div>
        <input type="file" id="fileAny" accept="image/*,application/pdf" class="hidden">
        <input type="file" id="fileCam" accept="image/*" capture="environment" class="hidden">
      </div></div>

    <div id="err" class="error hidden" role="alert"></div>
    <button class="btn primary block" id="submit">KIRIM PENGAJUAN</button>
  </div>`;

  $('#back', view).addEventListener('click', () => ctx.go('requests'));
  let att = null, coords = null;
  const err = $('#err', view);
  const showErr = (m) => { err.textContent = m || ''; err.classList.toggle('hidden', !m); };

  $('#type', view).addEventListener('change', async (e) => {
    const t = e.target.value;
    $('#leave', view).classList.toggle('hidden', t !== 'LEAVE');
    $('#single', view).classList.toggle('hidden', t === 'LEAVE');
    $('#outside', view).classList.toggle('hidden', t !== 'OUTSIDE_OFFICE');
    $('#endWrap', view).classList.toggle('hidden', t !== 'OVERTIME');
    $('#overtimeInfo', view).classList.toggle('hidden', t !== 'OVERTIME');
    $('#timeLbl', view).textContent = t === 'OVERTIME' ? 'Jam Mulai Lembur' : 'Jam';
    if (t === 'OUTSIDE_OFFICE' && !coords) {
      try {
        const p = await getPosition();
        coords = { lat: p.coords.latitude, lng: p.coords.longitude };
        $('#outsideSt', view).textContent = `Lokasi tercatat (akurasi ${Math.round(p.coords.accuracy)} m)`;
      } catch (ex) { $('#outsideSt', view).textContent = ex.message; }
    }
  });

  function setAtt(a) {
    att = a;
    $('#attName', view).textContent = a ? a.name : 'Pilih file atau buka kamera';
    $('#attClear', view).classList.toggle('hidden', !a);
    const th = $('#thumb', view);
    if (a && a.thumb) { th.src = a.thumb; th.classList.remove('hidden'); } else th.classList.add('hidden');
    if (a) showErr('');
  }
  async function onFile(f) {
    if (!f) return;
    try {
      if (f.type === 'application/pdf') {
        if (f.size > 8 * 1024 * 1024) { showErr('Ukuran PDF maksimal 8 MB'); return; }
        setAtt({ base64: await fileToBase64(f), name: f.name || 'lampiran.pdf' });
      } else {
        const j = await imageFileToJpeg(f);
        const base = (f.name || `foto_${Date.now()}`).replace(/\.[^.]+$/, '');
        setAtt({ base64: j.base64, name: `${base}.jpg`, thumb: j.dataUrl });
      }
    } catch { showErr('Gagal membaca file'); }
  }
  $('#pick', view).addEventListener('click', () => $('#fileAny', view).click());
  $('#shoot', view).addEventListener('click', () => $('#fileCam', view).click());
  [$('#fileAny', view), $('#fileCam', view)].forEach((i) => i.addEventListener('change', async (e) => { await onFile(e.target.files[0]); e.target.value = ''; }));
  $('#attClear', view).addEventListener('click', () => setAtt(null));

  $('#submit', view).addEventListener('click', async () => {
    showErr('');
    const type = $('#type', view).value;
    const reason = $('#reason', view).value.trim();
    if (!att) return showErr('Lampiran wajib diisi');
    let date = null, start = null, end = null, time = null, endTime = null;
    if (type === 'LEAVE') {
      start = $('#sd', view).value; end = $('#ed', view).value || start;
      if (!start) return showErr('Tanggal wajib diisi');
      if (end < start) return showErr('Tanggal selesai tidak boleh sebelum tanggal mulai');
    } else {
      date = $('#date', view).value; time = $('#time', view).value || null;
      if (!date) return showErr('Tanggal wajib diisi');
      if (type === 'OVERTIME') {
        endTime = $('#endTime', view).value || null;
        if (!time || !endTime) return showErr('Jam mulai dan jam selesai lembur wajib diisi');
        if (endTime <= time) return showErr('Jam selesai lembur harus setelah jam mulai');
      }
    }
    if (!reason) return showErr('Alasan wajib diisi');

    const btn = $('#submit', view);
    btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Mengirim...';
    const res = await Requests.submit({
      id: 0, employee_id: emp.id, office_id: emp.office_id, type, date, start_date: start, end_date: end, time, end_time: endTime, reason,
      latitude: type === 'OUTSIDE_OFFICE' && coords ? coords.lat : null,
      longitude: type === 'OUTSIDE_OFFICE' && coords ? coords.lng : null,
      attachment: null, attachment_base64: att.base64, attachment_filename: att.name, status: 'PENDING', rejection_reason: null,
    });
    if (res.ok) { toast('Pengajuan terkirim'); ctx.go('requests'); return; }
    btn.disabled = false; btn.textContent = 'KIRIM PENGAJUAN'; showErr(res.message);
  });
}

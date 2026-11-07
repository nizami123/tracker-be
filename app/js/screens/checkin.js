import { session } from '../store.js';
import { Office, Attendance } from '../repo.js';
import { Time, icon, esc, $, haversine, toast } from '../util.js';
import { Camera, cameraSupported, imageFileToJpeg } from '../camera.js';

export async function checkin(view, ctx) {
  const isOut = ctx.query.out === '1';
  const title = isOut ? 'Absen Pulang' : 'Absen Masuk';
  const emp = session.employee;

  view.innerHTML = `
  <div class="topbar"><button class="icon-btn" id="back" aria-label="Kembali">${icon('back', 24)}</button><h1>${title}</h1>
    <button class="icon-btn" id="refresh" aria-label="Segarkan lokasi">${icon('refresh', 22)}</button></div>
  <div class="pad">
    <div class="card" style="margin:0 0 14px">
      <div class="row gap"><div class="grow"><div style="font-weight:700" id="offName">-</div><div class="small muted" id="offAddr">&nbsp;</div></div>
        <span class="pill gray" id="area">Mencari lokasi</span></div>
    </div>

    <div class="cam" id="cam">
      <video id="video" playsinline muted autoplay></video>
      <img class="shot hidden" id="shot" alt="Foto absen">
      <div class="oval" id="oval"></div>
      <div class="hint" id="hint">Posisikan wajah di dalam bingkai</div>
      <div class="msg hidden" id="camMsg"></div>
    </div>
    <div class="row gap mt12">
      <button class="btn outline grow" id="capture" disabled>${icon('camera', 20)} Ambil Foto</button>
      <button class="btn ghost hidden" id="retake">Ulangi</button>
    </div>
    <input type="file" id="fileCam" accept="image/*" capture="user" class="hidden">

    <div class="card mt16" style="margin:16px 0 0">
      <div class="kv"><span>Jarak dari kantor</span><b id="vDist">-</b></div>
      <div class="kv"><span>Akurasi GPS</span><b id="vAcc">-</b></div>
      <div class="kv"><span>Waktu</span><b id="vTime">${Time.clock()} ${Time.label}</b></div>
      <div class="kv"><span>Tanggal</span><b>${Time.longDate()}</b></div>
    </div>
    <div id="status" class="center mt12" style="font-weight:800">Mencari lokasi GPS...</div>
    <div id="err" class="error hidden" role="alert"></div>
    <button class="btn primary block mt12" id="submit" disabled>${isOut ? 'ABSEN PULANG' : 'ABSEN MASUK'}</button>
  </div>`;

  $('#back', view).addEventListener('click', () => ctx.go('home'));

  let office = null, loc = null, photo = null, locCheck = null, watchId = null, submitting = false;
  const cam = new Camera($('#video', view), 'user');
  const err = $('#err', view);
  const showErr = (m) => { err.textContent = m; err.classList.toggle('hidden', !m); };

  function updateSubmit() { $('#submit', view).disabled = submitting || !(locCheck && locCheck.valid && photo); }

  function evaluate() {
    if (!office || !loc) return;
    const radius = isOut ? office.check_out_radius : office.check_in_radius;
    const dist = haversine(loc.latitude, loc.longitude, office.latitude, office.longitude);
    locCheck = { dist, radius, valid: dist <= radius };
    $('#vDist', view).textContent = `${Math.round(dist)} meter`;
    const area = $('#area', view), status = $('#status', view);
    area.className = 'pill ' + (locCheck.valid ? 'green' : 'red');
    area.textContent = locCheck.valid ? 'Di dalam area' : 'Di luar area';
    status.textContent = locCheck.valid ? '✓ LOKASI VALID' : `✕ LOKASI DI LUAR RADIUS (maks. ${radius} m)`;
    status.style.color = locCheck.valid ? 'var(--green)' : 'var(--red)';
    updateSubmit();
  }

  function startWatch() {
    if (watchId != null) navigator.geolocation.clearWatch(watchId);
    if (!('geolocation' in navigator)) { $('#status', view).textContent = 'Perangkat tidak mendukung GPS'; return; }
    $('#status', view).textContent = 'Mencari lokasi GPS...'; $('#status', view).style.color = '';
    watchId = navigator.geolocation.watchPosition((pos) => {
      loc = pos.coords;
      $('#vAcc', view).textContent = `${Math.round(pos.coords.accuracy)} meter`;
      evaluate();
    }, (e) => {
      $('#status', view).style.color = 'var(--red)';
      $('#status', view).textContent = e.code === 1
        ? 'Izin lokasi ditolak. Aktifkan di Pengaturan iPhone › Privasi › Layanan Lokasi.'
        : 'Tidak dapat mengambil lokasi GPS. Pastikan GPS aktif, keluar ruangan/dekat jendela, lalu ketuk segarkan.';
    }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 3000 });
  }

  async function startCamera() {
    $('#camMsg', view).classList.add('hidden');
    try {
      await cam.start();
      $('#capture', view).disabled = false;
    } catch (e) {
      $('#camMsg', view).innerHTML = `<div>${esc(e.message)}<br><br>Anda tetap bisa mengambil foto dengan kamera iPhone lewat tombol “Ambil Foto”.</div>`;
      $('#camMsg', view).classList.remove('hidden');
      $('#oval', view).classList.add('hidden'); $('#hint', view).classList.add('hidden');
      $('#capture', view).disabled = false;
      cam.failed = true;
    }
  }

  function setPhoto(p) {
    photo = p;
    const shot = $('#shot', view);
    shot.src = p.dataUrl; shot.classList.remove('hidden');
    $('#video', view).classList.add('hidden'); $('#oval', view).classList.add('hidden'); $('#hint', view).classList.add('hidden');
    $('#camMsg', view).classList.add('hidden');
    $('#capture', view).classList.add('hidden'); $('#retake', view).classList.remove('hidden');
    updateSubmit();
  }

  $('#capture', view).addEventListener('click', () => {
    if (cam.stream) {
      try { setPhoto(cam.capture()); } catch (e) { showErr('Gagal mengambil foto: ' + e.message); }
    } else {
      $('#fileCam', view).click();
    }
  });
  $('#fileCam', view).addEventListener('change', async (e) => {
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    try { setPhoto(await imageFileToJpeg(f)); } catch { showErr('Gagal membaca foto'); }
    e.target.value = '';
  });
  $('#retake', view).addEventListener('click', async () => {
    photo = null; updateSubmit();
    $('#shot', view).classList.add('hidden'); $('#retake', view).classList.add('hidden'); $('#capture', view).classList.remove('hidden');
    if (cam.stream) {
      $('#video', view).classList.remove('hidden'); $('#oval', view).classList.remove('hidden'); $('#hint', view).classList.remove('hidden');
    } else if (!cam.failed) { await startCamera(); $('#video', view).classList.remove('hidden'); }
    else { $('#camMsg', view).classList.remove('hidden'); }
  });
  $('#refresh', view).addEventListener('click', async () => {
    startWatch();
    const o = await Office.refresh().catch(() => []);
    if (o.length) { office = await Office.get(emp.office_id); fillOffice(); evaluate(); }
  });

  function fillOffice() {
    if (!office) return;
    $('#offName', view).textContent = office.name;
    $('#offAddr', view).textContent = office.address || `${office.latitude}, ${office.longitude}`;
  }

  $('#submit', view).addEventListener('click', async () => {
    if (!loc || !photo || !office || submitting) return;
    showErr('');
    submitting = true; const btn = $('#submit', view);
    btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Mengirim...';
    const base = {
      employee_id: emp.id, latitude: loc.latitude, longitude: loc.longitude,
      accuracy: loc.accuracy, timestamp: Time.now(), photo_base64: photo.base64,
    };
    let res;
    if (isOut) {
      const today = await Attendance.today();
      if (!today || !today.check_in_time) {
        res = { type: 'error', message: 'Data absen masuk hari ini tidak ditemukan' };
      } else res = await Attendance.checkOut({ ...base, attendance_id: today.id });
    } else res = await Attendance.checkIn(base);

    submitting = false;
    if (res.type === 'ok') {
      toast(isOut ? 'Absen pulang berhasil' : 'Absen masuk berhasil');
      ctx.go('home');
      return;
    }
    btn.innerHTML = isOut ? 'ABSEN PULANG' : 'ABSEN MASUK';
    updateSubmit();
    if (res.type === 'rejected') {
      $('#status', view).style.color = 'var(--red)';
      $('#status', view).textContent = `✕ ${res.message}${res.radius ? ` (${Math.round(res.distance)} m > ${res.radius} m)` : ''}`;
    } else showErr(res.message);
  });

  // jam berjalan
  const clock = setInterval(() => { const el = $('#vTime', view); if (el) el.textContent = `${Time.clock()} ${Time.label}`; }, 1000);

  office = await Office.get(emp.office_id);
  fillOffice();
  if (!cameraSupported()) { cam.failed = true; await startCamera(); } else startCamera();
  startWatch();

  return () => {
    clearInterval(clock);
    if (watchId != null) navigator.geolocation.clearWatch(watchId);
    cam.stop();
  };
}

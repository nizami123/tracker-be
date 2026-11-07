// Fitur Pengiriman Kendaraan (role DRIVER): ambil kendaraan -> berjalan -> sampai tujuan.
import { session, pointsDb } from '../store.js';
import { Office, Delivery } from '../repo.js';
import { Time, icon, esc, $, $$, haversine, totalDistanceKm, modal, toast, mapsLink } from '../util.js';
import { startDeliveryTracking, stopDeliveryTracking, DeliveryTracker, onTrackerChange, syncKind } from '../tracker.js';
import { photoBox } from './photo.js';
import { drawMap } from '../map.js';

// ---------------------------------------------------------------------------
// Layar 1: form data kendaraan + foto + tujuan
// ---------------------------------------------------------------------------
export async function pickup(view, ctx) {
  const active = await Delivery.active();
  if (!ctx.isCurrent()) return;
  if (active) { ctx.go('delivery', { replace: true }); return; }

  view.innerHTML = `
  <div class="topbar"><button class="icon-btn" id="back" aria-label="Kembali">${icon('back', 24)}</button><h1>Pengiriman Kendaraan</h1></div>
  <div class="pad">
    <p class="muted">Isi data kendaraan dan ambil foto sebelum memulai pengiriman.</p>

    <h3 class="section-title" style="margin:16px 0 10px">Data Kendaraan</h3>
    <div class="field"><label for="engine">Nomor Mesin *</label><input class="input" id="engine" autocapitalize="characters" autocorrect="off" spellcheck="false"></div>
    <div class="field"><label for="chassis">Nomor Rangka *</label><input class="input" id="chassis" autocapitalize="characters" autocorrect="off" spellcheck="false"></div>
    <div class="field"><label for="brand">Merk Kendaraan *</label><input class="input" id="brand" autocapitalize="words"></div>
    <div class="field"><label for="vtype">Tipe Kendaraan *</label><input class="input" id="vtype" autocapitalize="words"></div>
    <div class="field"><label for="color">Warna *</label><input class="input" id="color" autocapitalize="words"></div>

    <h3 class="section-title" style="margin:16px 0 10px">Tujuan Pengiriman</h3>
    <button class="card flat" id="dest" style="display:block;width:100%;margin:0;text-align:left">
      <div class="row gap"><span class="grow"><b id="destName">Pilih tujuan pengiriman</b><div class="small muted" id="destAddr">-</div></span>${icon('chevron', 20)}</div>
    </button>

    <h3 class="section-title" style="margin:16px 0 10px">Keterangan (opsional)</h3>
    <textarea class="input" id="notes" maxlength="150" rows="2" placeholder="Catatan tambahan"></textarea>
    <div class="counter" id="cnt">0/150</div>

    <h3 class="section-title" style="margin:16px 0 10px">Foto Kendaraan Saat Diambil</h3>
    <div id="photo"></div>
    <p class="small muted mt8">Pastikan foto jelas dari tampak depan atau samping kendaraan.</p>

    <div class="small muted mt12" id="gps">Mencari lokasi GPS…</div>
    <div id="err" class="error hidden" role="alert"></div>
    <button class="btn primary block mt12" id="submit">MULAI PENGIRIMAN</button>
  </div>`;

  $('#back', view).addEventListener('click', () => ctx.go('home'));
  const err = $('#err', view);
  const showErr = (m) => { err.textContent = m || ''; err.classList.toggle('hidden', !m); };

  let destination = null, loc = null, offices = [];
  const pb = photoBox($('#photo', view), { facing: 'environment', hint: 'Arahkan kamera ke kendaraan' });

  const setDest = (d) => {
    destination = d;
    $('#destName', view).textContent = d ? d.name : 'Pilih tujuan pengiriman';
    $('#destAddr', view).textContent = d ? (d.address || '-') : '-';
  };
  Office.refresh().catch(() => []).then(() => { offices = session.offices.filter((o) => o.status !== 'INACTIVE'); });
  offices = session.offices.filter((o) => o.status !== 'INACTIVE');

  $('#notes', view).addEventListener('input', (e) => { $('#cnt', view).textContent = `${e.target.value.length}/150`; });

  $('#dest', view).addEventListener('click', async () => {
    const choice = await modal((box, close) => {
      box.innerHTML = `<h3>Pilih Tujuan Pengiriman</h3>
        ${offices.map((o) => `<button class="opt ${destination && destination.officeId === o.id ? 'sel' : ''}" data-o="${o.id}">${icon('building', 20)}<span><b>${esc(o.name)}</b><br><span class="small muted">${esc(o.address || '')}</span></span></button>`).join('')}
        <button class="opt" data-o="other">${icon('pin', 20)}<span><b>Tujuan lain…</b><br><span class="small muted">Alamat tujuan yang bukan kantor terdaftar</span></span></button>`;
      $$('.opt', box).forEach((b) => b.addEventListener('click', () => close(b.dataset.o)));
    }, { sheet: true });
    if (!choice) return;
    if (choice === 'other') {
      const custom = await modal((box, close) => {
        box.innerHTML = `<h3>Tujuan Lain</h3>
          <div class="field"><label>Nama tujuan</label><input class="input" id="cn" placeholder="mis. Dealer Sidoarjo"></div>
          <div class="field"><label>Alamat lengkap *</label><textarea class="input" id="ca" rows="2"></textarea></div>
          <div class="field"><label>Radius kedatangan (meter)</label><input class="input" id="cr" type="number" inputmode="numeric" value="100"></div>
          <div id="ce" class="error hidden"></div>
          <div class="row gap end"><button class="btn ghost sm" id="cx">Batal</button><button class="btn primary sm" id="cok">Gunakan Tujuan Ini</button></div>`;
        $('#cx', box).addEventListener('click', () => close(null));
        $('#cok', box).addEventListener('click', () => {
          const address = $('#ca', box).value.trim();
          if (!address) { $('#ce', box).textContent = 'Alamat lengkap tujuan wajib diisi'; $('#ce', box).classList.remove('hidden'); return; }
          const name = $('#cn', box).value.trim() || 'Tujuan Lain';
          close({ name, address, radius: parseInt($('#cr', box).value, 10) || 100 });
        });
      });
      if (custom) setDest({ custom: true, name: custom.name, address: custom.address, radius: custom.radius });
    } else {
      const o = offices.find((x) => String(x.id) === choice);
      if (o) setDest({ officeId: o.id, name: o.name, address: o.address });
    }
  });

  // GPS
  let watch = null;
  if ('geolocation' in navigator) {
    watch = navigator.geolocation.watchPosition((p) => {
      loc = p.coords; $('#gps', view).textContent = `Lokasi GPS siap (akurasi ${Math.round(p.coords.accuracy)} m)`;
    }, (e) => {
      $('#gps', view).textContent = e.code === 1 ? 'Izin lokasi ditolak — aktifkan di Pengaturan iPhone › Privasi › Layanan Lokasi' : 'Lokasi GPS belum didapat…';
    }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 3000 });
  }

  $('#submit', view).addEventListener('click', async () => {
    showErr('');
    const v = (id) => $('#' + id, view).value.trim();
    if (!v('engine') || !v('chassis') || !v('brand') || !v('vtype') || !v('color')) return showErr('Nomor mesin, nomor rangka, merk, tipe, dan warna wajib diisi');
    if (!destination) return showErr('Pilih tujuan pengiriman terlebih dahulu');
    if (!loc) return showErr('Lokasi GPS belum didapat, coba lagi');
    if (!pb.photo) return showErr('Ambil foto kendaraan terlebih dahulu');

    const base = {
      engine_number: v('engine'), chassis_number: v('chassis'), brand: v('brand'), vehicle_type: v('vtype'), color: v('color'),
      notes: v('notes') || null, pickup_photo_base64: pb.photo.base64,
      pickup_latitude: loc.latitude, pickup_longitude: loc.longitude, timestamp: Time.now(),
    };
    const req = destination.custom
      ? { ...base, destination_name: destination.name, destination_address: destination.address, destination_latitude: null, destination_longitude: null, destination_radius: destination.radius }
      : { ...base, destination_office_id: destination.officeId };

    const btn = $('#submit', view); btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Memproses...';
    const res = await Delivery.start(req);
    if (res.type === 'ok') {
      startDeliveryTracking(res.delivery);
      toast('Pengiriman dimulai');
      ctx.go('delivery');
      return;
    }
    btn.disabled = false; btn.textContent = 'MULAI PENGIRIMAN';
    showErr(res.message);
  });

  return () => { if (watch != null) navigator.geolocation.clearWatch(watch); pb.stop(); };
}

// ---------------------------------------------------------------------------
// Layar 2: pengiriman berjalan / sampai tujuan
// ---------------------------------------------------------------------------
export async function deliveryScreen(view, ctx) {
  view.innerHTML = '<div class="loading"><span class="spinner"></span></div>';
  const d = await Delivery.active();
  if (!ctx.isCurrent()) return;
  if (!d) { ctx.go('pickup', { replace: true }); return; }

  startDeliveryTracking(d); // lanjutkan tracking bila aplikasi dibuka ulang
  Delivery.remotePoints(d.id);

  const hasCoords = d.destination_latitude != null && d.destination_longitude != null;
  const radius = d.destination_radius || 100;
  let arrived = false, loc = null, points = [], pb = null, mapCtl = null, watch = null, ticker = null, completing = false;

  view.innerHTML = `
  <div class="topbar"><button class="icon-btn" id="back" aria-label="Kembali">${icon('back', 24)}</button><h1 id="ttl">Pengiriman Berjalan</h1></div>
  <div class="pad">
    <div class="card blue" style="margin:0 0 14px">
      <div class="row between"><div style="font-weight:800;font-size:17px" id="hd">Dalam Perjalanan</div>
        <span class="pill" style="background:rgba(255,255,255,.22);color:#fff"><span class="dot-live"></span>Aktif</span></div>
      <div id="sub" style="opacity:.9;font-size:13px;margin:4px 0 12px">Lokasi dicatat otomatis selama pengiriman</div>
      <div class="grid2" style="color:#fff">
        <div><div class="lbl" style="color:#D6E3FF">Mulai</div><div class="val">${d.pickup_time ? Time.withLabel(d.pickup_time) : '-'}</div></div>
        <div><div class="lbl" style="color:#D6E3FF">Durasi</div><div class="val" id="dur">00:00:00</div></div>
      </div>
    </div>

    <div id="mapWrap"><div id="map" class="map tall"></div></div>
    <div class="small muted mt8" id="upd">&nbsp;</div>

    <div class="card mt12" style="margin:12px 0">
      <div class="kv"><span>Jarak Tempuh</span><b id="mDist">0.0 km</b></div>
      <div class="kv"><span>Kecepatan Rata-rata</span><b id="mSpd">-</b></div>
      <div class="kv"><span>Lokasi Terakhir</span><b id="mLast">-</b></div>
      <div class="kv"><span>Akurasi GPS</span><b id="mAcc">-</b></div>
      <div class="kv"><span>Menunggu sinkron</span><b id="mPend">0</b></div>
    </div>

    <div class="card" style="margin:0 0 12px">
      <h3>Tujuan</h3>
      <div style="font-weight:700;margin-top:8px">${esc(d.destination_name || '-')}</div>
      <div class="small muted">${esc(d.destination_address || '-')}</div>
      <div class="small muted mt8">Radius kedatangan: ${radius} m${hasCoords ? '' : ' • titik koordinat belum diatur admin (validasi radius dilewati)'}</div>
    </div>
    <div class="card" style="margin:0 0 12px">
      <h3>Kendaraan</h3>
      <div class="kv"><span>Merk / Tipe</span><b>${esc(d.brand)} ${esc(d.vehicle_type)}</b></div>
      <div class="kv"><span>Warna</span><b>${esc(d.color)}</b></div>
      <div class="kv"><span>No. Mesin</span><b>${esc(d.engine_number)}</b></div>
      <div class="kv"><span>No. Rangka</span><b>${esc(d.chassis_number)}</b></div>
    </div>

    <div id="enroute"><div id="waitMsg" class="banner">${icon('pin', 18)}<div id="waitTxt">${hasCoords ? 'Tombol penyelesaian muncul saat Anda berada di area tujuan.' : 'Tekan tombol di bawah setelah kendaraan tiba di tujuan.'}</div></div>
      ${hasCoords ? '' : `<button class="btn primary block" id="manualArrive">SAYA SUDAH SAMPAI</button>`}</div>

    <div id="arrive" class="hidden">
      <h3 class="section-title" style="margin:8px 0 6px">Foto Kendaraan Saat Tiba</h3>
      <div id="aphoto"></div>
      <div class="field mt12"><label for="anotes">Catatan (opsional)</label><textarea class="input" id="anotes" maxlength="150" rows="2"></textarea></div>
      <div id="aerr" class="error hidden" role="alert"></div>
      <button class="btn primary block" id="complete">SELESAIKAN PENGIRIMAN</button>
    </div>
  </div>`;

  $('#back', view).addEventListener('click', () => ctx.go('home'));

  const startMs = Time.parse(d.pickup_time) || Date.now();

  async function refreshStats() {
    points = await pointsDb.all('del', d.id);
    const last = points[points.length - 1];
    const km = totalDistanceKm(points);
    $('#mDist', view).textContent = `${km.toFixed(1)} km`;
    const hours = Math.max((Date.now() - startMs) / 3600e3, 1 / 3600);
    $('#mSpd', view).textContent = km > 0 ? `${Math.round(km / hours)} km/jam` : '-';
    if (last) {
      $('#mLast', view).textContent = `${last.latitude.toFixed(5)}, ${last.longitude.toFixed(5)}`;
      $('#mAcc', view).textContent = last.accuracy != null ? `${Math.round(last.accuracy)} meter` : '-';
      $('#upd', view).textContent = `Terakhir diperbarui: ${Time.withLabel(last.recorded_at)}`;
    }
    $('#mPend', view).textContent = await pointsDb.countPending('del');
    drawRoute();
  }

  function drawRoute() {
    const el = $('#map', view);
    if (!el) return;
    const last = points[points.length - 1];
    const markers = [];
    if (hasCoords) markers.push({ lat: d.destination_latitude, lng: d.destination_longitude, color: '#1E9E5A' });
    const cur = loc ? { lat: loc.latitude, lng: loc.longitude } : last ? { lat: last.latitude, lng: last.longitude } : null;
    if (cur) markers.push({ ...cur, color: '#2F6FED' });
    if (mapCtl) mapCtl.destroy();
    mapCtl = drawMap(el, {
      route: points, markers,
      circle: hasCoords ? { lat: d.destination_latitude, lng: d.destination_longitude, radius } : null,
      emptyText: 'Menunggu titik lokasi pertama…',
    });
  }

  function showArrival(distance) {
    if (arrived) return;
    arrived = true;
    $('#ttl', view).textContent = 'Sampai Tujuan';
    $('#hd', view).textContent = 'Sampai Tujuan';
    $('#sub', view).textContent = distance != null
      ? `Anda berada di area ${d.destination_name || 'tujuan'}`
      : 'Konfirmasi kedatangan Anda dengan mengambil foto kendaraan dan menekan tombol di bawah.';
    $('#enroute', view).classList.add('hidden');
    $('#arrive', view).classList.remove('hidden');
    pb = photoBox($('#aphoto', view), { facing: 'environment', hint: 'Foto kendaraan di lokasi tujuan' });
  }

  const manual = $('#manualArrive', view);
  if (manual) manual.addEventListener('click', () => showArrival(null));

  if ('geolocation' in navigator) {
    watch = navigator.geolocation.watchPosition((p) => {
      loc = p.coords;
      if (hasCoords) {
        const dist = haversine(loc.latitude, loc.longitude, d.destination_latitude, d.destination_longitude);
        $('#waitTxt', view).textContent = `Jarak ke tujuan ±${Math.round(dist)} m (radius ${radius} m)`;
        if (dist <= radius) showArrival(dist);
      }
    }, () => {}, { enableHighAccuracy: true, timeout: 20000, maximumAge: 5000 });
  }

  $('#complete', view).addEventListener('click', async () => {
    const aerr = $('#aerr', view);
    const fail = (m) => { aerr.textContent = m; aerr.classList.remove('hidden'); };
    aerr.classList.add('hidden');
    if (!loc || !pb || !pb.photo) return fail('Lokasi atau foto belum siap');
    if (completing) return;
    completing = true;
    const btn = $('#complete', view); btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Menyimpan...';
    const res = await Delivery.complete({
      delivery_id: d.id, arrival_latitude: loc.latitude, arrival_longitude: loc.longitude, accuracy: loc.accuracy,
      arrival_photo_base64: pb.photo.base64, notes: $('#anotes', view).value.trim() || null, timestamp: Time.now(),
    });
    completing = false;
    if (res.type === 'ok') {
      stopDeliveryTracking();
      syncKind('del');
      toast('Pengiriman selesai');
      ctx.go('home');
      return;
    }
    btn.disabled = false; btn.textContent = 'SELESAIKAN PENGIRIMAN';
    fail(res.type === 'rejected' ? `${res.message}${res.radius ? ` (${Math.round(res.distance)} m > ${res.radius} m)` : ''}` : res.message);
  });

  ticker = setInterval(() => { const el = $('#dur', view); if (el) el.textContent = Time.hms(Date.now() - startMs); }, 1000);
  $('#dur', view).textContent = Time.hms(Date.now() - startMs);
  const off = onTrackerChange(refreshStats);
  await refreshStats();

  return () => {
    clearInterval(ticker); off();
    if (watch != null) navigator.geolocation.clearWatch(watch);
    if (mapCtl) mapCtl.destroy();
    if (pb) pb.stop();
  };
}

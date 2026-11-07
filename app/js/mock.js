// Mode demo: server tiruan di dalam browser (padanan MockApiService.kt).
// Dipakai untuk mencoba aplikasi tanpa backend. Data disimpan di localStorage.
import { Time, haversine } from './util.js';
import { session } from './store.js';

const KEY = 'st_mock_db';
const ok = (data, status = 200) => ({ ok: true, status, data });
const fail = (status, message) => ({ ok: false, status, data: { message } });

const OFFICES = [
  { id: 1, code: 'LMG', name: 'Kantor Lamongan', address: 'Jl. Veteran No. 123, Lamongan, Jawa Timur', latitude: -7.116, longitude: 112.4145, check_in_radius: 50, check_out_radius: 50, status: 'ACTIVE', tracking_interval_seconds: 120, timezone: 'WIB' },
  { id: 2, code: 'SBY', name: 'Kantor Surabaya', address: 'Jl. Basuki Rahmat No. 8, Surabaya', latitude: -7.2655, longitude: 112.7392, check_in_radius: 100, check_out_radius: 100, status: 'ACTIVE', tracking_interval_seconds: 300, timezone: 'WIB' },
  { id: 3, code: 'MKS', name: 'Kantor Makassar', address: 'Jl. Sultan Hasanuddin No. 5, Makassar', latitude: -5.1477, longitude: 119.4327, check_in_radius: 100, check_out_radius: 100, status: 'ACTIVE', tracking_interval_seconds: 300, timezone: 'WITA' },
];

const EMPLOYEES = [
  { id: 1, employee_code: 'EMP001', nip: '199205152020011001', name: 'Ahmad Kurniawan', email: 'ahmad@example.com', phone: '081234567890', office_id: 1, position: 'Staff Operasional', photo: null, status: 'ACTIVE', role: 'EMPLOYEE', _pw: 'demo' },
  { id: 2, employee_code: 'DRV001', nip: null, name: 'Budi Santoso', email: 'sopir@example.com', phone: '081298765432', office_id: 1, position: 'Driver Pengiriman', photo: null, status: 'ACTIVE', role: 'DRIVER', _pw: 'demo' },
];

function seed() {
  const hist = [];
  let id = 1;
  for (let i = 1; i <= 9; i++) {
    const date = Time.daysAgo(i);
    const dow = new Date(date + 'T00:00:00Z').getUTCDay();
    if (dow === 0 || dow === 6) continue;
    for (const e of [1, 2]) {
      const late = (i + e) % 4 === 0;
      hist.push({
        id: id++, employee_id: e, office_id: 1, attendance_date: date,
        check_in_time: `${date} ${late ? '08:17:42' : '07:52:10'}`, check_in_photo: null,
        check_in_latitude: -7.1161, check_in_longitude: 112.4146, check_in_accuracy: 9, check_in_distance: 14 + i,
        check_out_time: `${date} 16:35:${String(10 + i).padStart(2, '0')}`, check_out_photo: null,
        check_out_latitude: -7.1161, check_out_longitude: 112.4146, check_out_accuracy: 11, check_out_distance: 18,
        status: late ? 'LATE' : 'PRESENT',
      });
    }
  }
  const requests = [
    { id: 1, employee_id: 1, office_id: 1, type: 'LATE', date: Time.daysAgo(3), start_date: null, end_date: null, time: '08:30', reason: 'Ban motor bocor di jalan', latitude: null, longitude: null, attachment: 'bukti1.jpg', status: 'APPROVED', rejection_reason: null },
    { id: 2, employee_id: 1, office_id: 1, type: 'LEAVE', date: null, start_date: Time.daysAgo(-5), end_date: Time.daysAgo(-6), time: null, reason: 'Acara keluarga', latitude: null, longitude: null, attachment: 'surat.pdf', status: 'PENDING', rejection_reason: null },
    { id: 3, employee_id: 1, office_id: 1, type: 'CHECK_OUT', date: Time.daysAgo(8), start_date: null, end_date: null, time: '16:30', reason: 'Lupa absen pulang', latitude: null, longitude: null, attachment: 'foto.jpg', status: 'REJECTED', rejection_reason: 'Bukti tidak jelas' },
  ];
  return { nextId: 1000, attendances: hist, requests, tracking: [], deliveries: [], deliveryTracking: [], passwords: {} };
}

let db;
function load() {
  if (db) return db;
  try { db = JSON.parse(localStorage.getItem(KEY)); } catch { db = null; }
  if (!db) db = seed();
  return db;
}
function save() { try { localStorage.setItem(KEY, JSON.stringify(db)); } catch { /* penuh */ } }
export function resetMock() { db = null; try { localStorage.removeItem(KEY); } catch { /* */ } }

function authUser(token) {
  const m = /^demo-(\d+)$/.exec(token || '');
  return m ? EMPLOYEES.find((e) => e.id === +m[1]) : null;
}
const pub = (e) => { const { _pw, ...rest } = e; return rest; };
const delay = (ms = 220) => new Promise((r) => setTimeout(r, ms));



export async function mockRequest(method, path, { body, query } = {}) {
  await delay();
  const d = load();
  const p = path.replace(/^\//, '');

  if (p === 'api/auth/login' && method === 'POST') {
    const u = EMPLOYEES.find((e) => e.email === String(body.email || '').trim().toLowerCase());
    const pw = u && (d.passwords[u.id] || u._pw);
    if (!u || pw !== body.password) return fail(401, 'Email atau password salah');
    return ok({ token: `demo-${u.id}`, employee: pub(u) });
  }

  const me = authUser(session.token);
  if (!me) { window.dispatchEvent(new Event('st:unauthorized')); return fail(401, 'Sesi berakhir'); }
  const office = OFFICES.find((o) => o.id === me.office_id);

  if (p === 'api/auth/change-password') {
    const pw = d.passwords[me.id] || me._pw;
    if (body.current_password !== pw) return ok({ success: false, message: 'Password lama salah' });
    d.passwords[me.id] = body.new_password; save();
    return ok({ success: true, message: 'Password berhasil diubah' });
  }
  if (p === 'api/offices') return ok(OFFICES);
  if (p === 'api/employees/me') return ok(pub(me));
  if (p === 'api/shifts/my') {
    const today = Time.today();
    return ok({ date: today, timezone: office.timezone, office_id: office.id, current: { date: today, source: 'SHIFT', shift_id: 1, shift_code: 'P', shift_name: 'Pagi', start_time: '08:00', end_time: '16:30', crosses_midnight: false }, upcoming: [] });
  }

  if (p === 'api/attendance/today') {
    const a = d.attendances.find((x) => x.employee_id === me.id && x.attendance_date === Time.today());
    return ok(a || null);
  }
  if (p === 'api/attendance/history') {
    return ok(d.attendances.filter((x) => x.employee_id === me.id).sort((a, b) => b.attendance_date.localeCompare(a.attendance_date)));
  }
  if (p === 'api/attendance/check-in' || p === 'api/attendance/check-out') {
    const out = p.endsWith('check-out');
    const dist = haversine(body.latitude, body.longitude, office.latitude, office.longitude);
    const radius = out ? office.check_out_radius : office.check_in_radius;
    if (dist > radius) return ok({ success: false, message: 'Lokasi Anda di luar radius kantor', distance: Math.round(dist), radius });
    const date = String(body.timestamp).slice(0, 10);
    if (!out) {
      if (d.attendances.some((x) => x.employee_id === me.id && x.attendance_date === date)) {
        return ok({ success: false, message: 'Anda sudah absen masuk hari ini', distance: Math.round(dist), radius });
      }
      const late = String(body.timestamp).slice(11, 16) > '08:00';
      const a = {
        id: d.nextId++, employee_id: me.id, office_id: office.id, attendance_date: date,
        check_in_time: body.timestamp, check_in_photo: 'demo.jpg', check_in_latitude: body.latitude, check_in_longitude: body.longitude,
        check_in_accuracy: body.accuracy, check_in_distance: Math.round(dist),
        check_out_time: null, check_out_photo: null, check_out_latitude: null, check_out_longitude: null, check_out_accuracy: null, check_out_distance: null,
        status: late ? 'LATE' : 'PRESENT',
      };
      d.attendances.push(a); save();
      return ok({ success: true, message: 'Absen masuk berhasil', distance: Math.round(dist), radius, attendance: a });
    }
    const a = d.attendances.find((x) => x.id === body.attendance_id);
    if (!a) return ok({ success: false, message: 'Data absen masuk tidak ditemukan', distance: 0, radius });
    Object.assign(a, { check_out_time: body.timestamp, check_out_photo: 'demo.jpg', check_out_latitude: body.latitude, check_out_longitude: body.longitude, check_out_accuracy: body.accuracy, check_out_distance: Math.round(dist) });
    save();
    return ok({ success: true, message: 'Absen pulang berhasil', distance: Math.round(dist), radius, attendance: a });
  }

  if (p === 'api/tracking/sync') {
    const ids = [];
    for (const pt of body.points) {
      if (!d.tracking.some((t) => t.employee_id === pt.employee_id && t.recorded_at === pt.recorded_at)) {
        d.tracking.push({ employee_id: pt.employee_id, office_id: pt.office_id, latitude: pt.latitude, longitude: pt.longitude, accuracy: pt.accuracy, speed: pt.speed, bearing: pt.bearing, battery_level: pt.battery_level, recorded_at: pt.recorded_at });
      }
      ids.push(pt.localId);
    }
    save();
    return ok({ success: true, synced_ids: ids });
  }
  if (p === 'api/tracking') {
    return ok(d.tracking.filter((t) => t.employee_id === me.id && t.recorded_at.startsWith(query.date)));
  }

  if (p === 'api/requests' && method === 'GET') {
    return ok(d.requests.filter((r) => r.employee_id === me.id).sort((a, b) => b.id - a.id));
  }
  if (p === 'api/requests' && method === 'POST') {
    if (!body.attachment_base64) return fail(422, 'Lampiran wajib diisi');
    const r = { ...body, id: d.nextId++, employee_id: me.id, office_id: me.office_id, attachment: body.attachment_filename, status: 'PENDING', rejection_reason: null };
    delete r.attachment_base64; delete r.attachment_filename;
    d.requests.push(r); save();
    return ok(r, 201);
  }

  if (p === 'api/deliveries/start') {
    if (me.role !== 'DRIVER') return fail(403, 'Hanya untuk driver');
    if (d.deliveries.some((x) => x.driver_id === me.id && x.status === 'IN_PROGRESS')) return ok({ success: false, message: 'Masih ada pengiriman yang berjalan' });
    const dest = body.destination_office_id ? OFFICES.find((o) => o.id === body.destination_office_id) : null;
    const dl = {
      id: d.nextId++, driver_id: me.id,
      engine_number: body.engine_number, chassis_number: body.chassis_number, brand: body.brand, vehicle_type: body.vehicle_type, color: body.color,
      destination_office_id: dest ? dest.id : null,
      destination_name: dest ? dest.name : body.destination_name,
      destination_address: dest ? dest.address : body.destination_address,
      destination_latitude: dest ? dest.latitude : null,
      destination_longitude: dest ? dest.longitude : null,
      destination_radius: dest ? dest.check_in_radius : body.destination_radius,
      notes: body.notes || null,
      pickup_photo: 'demo.jpg', pickup_time: body.timestamp, pickup_latitude: body.pickup_latitude, pickup_longitude: body.pickup_longitude,
      arrival_photo: null, arrival_time: null, arrival_latitude: null, arrival_longitude: null, arrival_distance: null, arrival_notes: null,
      status: 'IN_PROGRESS',
    };
    d.deliveries.push(dl); save();
    return ok({ success: true, message: 'Pengiriman dimulai', distance: 0, radius: 0, delivery: dl });
  }
  if (p === 'api/deliveries/active') {
    return ok(d.deliveries.find((x) => x.driver_id === me.id && x.status !== 'COMPLETED') || null);
  }
  if (p === 'api/deliveries/history') return ok(d.deliveries.filter((x) => x.driver_id === me.id));
  if (p === 'api/deliveries/complete') {
    const dl = d.deliveries.find((x) => x.id === body.delivery_id);
    if (!dl) return ok({ success: false, message: 'Pengiriman tidak ditemukan' });
    let dist = 0;
    const radius = dl.destination_radius || 100;
    if (dl.destination_latitude != null) {
      dist = haversine(body.arrival_latitude, body.arrival_longitude, dl.destination_latitude, dl.destination_longitude);
      if (dist > radius) return ok({ success: false, message: 'Anda belum berada di area tujuan', distance: Math.round(dist), radius });
    }
    Object.assign(dl, { status: 'COMPLETED', arrival_time: body.timestamp, arrival_latitude: body.arrival_latitude, arrival_longitude: body.arrival_longitude, arrival_distance: Math.round(dist), arrival_notes: body.notes || null, arrival_photo: 'demo.jpg' });
    save();
    return ok({ success: true, message: 'Pengiriman selesai', distance: Math.round(dist), radius, delivery: dl });
  }
  if (p === 'api/delivery-tracking/sync') {
    const ids = [];
    for (const pt of body.points) {
      if (!d.deliveryTracking.some((t) => t.delivery_id === pt.delivery_id && t.recorded_at === pt.recorded_at)) d.deliveryTracking.push(pt);
      ids.push(pt.localId);
    }
    save();
    return ok({ success: true, synced_ids: ids });
  }
  const m = /^api\/delivery-tracking\/(\d+)$/.exec(p);
  if (m) return ok(d.deliveryTracking.filter((t) => t.delivery_id === +m[1]));

  return fail(404, `Endpoint demo tidak dikenal: ${p}`);
}

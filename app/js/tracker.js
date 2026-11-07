// Mesin tracking GPS (absensi & pengiriman).
//
// BATASAN iOS (penting): PWA/Safari TIDAK bisa membaca GPS di latar belakang. Titik hanya
// tercatat saat aplikasi terbuka dan layar menyala. Untuk itu:
//  - saat layar menyala kami meminta Screen Wake Lock (bila didukung iOS),
//  - saat aplikasi kembali dibuka, titik langsung dicatat bila sudah lewat interval,
//  - semua titik disimpan di IndexedDB lebih dulu (offline-first) lalu disinkronkan ke server.
import { api } from './api.js';
import { pointsDb, session, settings } from './store.js';
import { Time, getPosition } from './util.js';
import { CONFIG } from './config.js';

const listeners = new Set();
export const onTrackerChange = (fn) => { listeners.add(fn); return () => listeners.delete(fn); };
const emit = () => listeners.forEach((f) => { try { f(); } catch { /* */ } });

async function batteryLevel() {
  try {
    if (navigator.getBattery) { const b = await navigator.getBattery(); return Math.round(b.level * 100); }
  } catch { /* tidak didukung di iOS */ }
  return null;
}

// ---- Sinkronisasi ke server ----
const syncing = { att: false, del: false };
const wire = (p) => {
  const { kind, ref, synced, ...rest } = p;
  return { ...rest, synced: false };
};

export async function syncKind(kind) {
  if (syncing[kind]) return 0;
  syncing[kind] = true;
  let total = 0;
  try {
    for (let guard = 0; guard < 50; guard++) {
      const pending = await pointsDb.pending(kind, CONFIG.SYNC_BATCH);
      if (!pending.length) break;
      const fn = kind === 'att' ? api.syncTracking : api.syncDeliveryTracking;
      const res = await fn(pending.map(wire));
      const ids = res.ok && res.data && res.data.success ? (res.data.synced_ids || []) : [];
      if (!ids.length) break; // server menolak / offline: sisanya tetap antre
      await pointsDb.markSynced(ids);
      total += ids.length;
    }
  } catch { /* offline: tetap antre */ } finally { syncing[kind] = false; }
  if (total) emit();
  return total;
}
export const syncAll = () => Promise.all([syncKind('att'), syncKind('del')]);

// ---- Wake lock ----
let wakeLock = null;
async function updateWakeLock() {
  const want = settings.get().wakeLock && !document.hidden && (EmployeeTracker.active || DeliveryTracker.active);
  try {
    if (want && !wakeLock && 'wakeLock' in navigator) {
      wakeLock = await navigator.wakeLock.request('screen');
      wakeLock.addEventListener('release', () => { wakeLock = null; });
    } else if (!want && wakeLock) {
      await wakeLock.release(); wakeLock = null;
    }
  } catch { wakeLock = null; /* ditolak (mis. hemat baterai) */ }
}
export const wakeLockActive = () => !!wakeLock;
export const refreshWakeLock = updateWakeLock;

// ---- Engine ----
class Engine {
  constructor(kind) {
    this.kind = kind;
    this.ctx = null;          // { ref, intervalS, make(coords, extra) }
    this.active = false;
    this.last = null;
    this.lastAt = 0;
    this.startedAt = 0;
    this.error = null;        // { code, message }
    this.busy = false;
    this.timer = null;
  }

  get intervalMs() { return Math.max(30, this.ctx?.intervalS || 600) * 1000; }

  start(ctx) {
    const same = this.active && this.ctx && this.ctx.ref === ctx.ref;
    this.ctx = ctx;
    if (same) return false;
    this.active = true;
    this.error = null;
    this.startedAt = Date.now();
    this.lastAt = 0;
    clearInterval(this.timer);
    this.timer = setInterval(() => this.tick(), 15000);
    this.tick(true);
    updateWakeLock();
    emit();
    return true;
  }

  stop() {
    this.active = false; this.ctx = null; this.last = null; this.error = null;
    clearInterval(this.timer); this.timer = null;
    updateWakeLock(); emit();
  }

  tick(force = false) {
    if (!this.active || document.hidden) return;
    if (force || Date.now() - this.lastAt >= this.intervalMs) this.record();
  }

  async record() {
    if (this.busy || !this.active) return null;
    this.busy = true;
    try {
      const pos = await getPosition({ timeout: 25000, maximumAge: 0 });
      if (!this.active) return null;
      const c = pos.coords;
      const point = this.ctx.make({
        latitude: c.latitude, longitude: c.longitude,
        accuracy: c.accuracy != null ? Math.round(c.accuracy * 10) / 10 : null,
        speed: c.speed != null && !Number.isNaN(c.speed) ? Math.round(c.speed * 100) / 100 : null,
        bearing: c.heading != null && !Number.isNaN(c.heading) ? Math.round(c.heading * 10) / 10 : null,
        battery_level: await batteryLevel(),
        recorded_at: Time.now(),
      });
      const saved = await pointsDb.add({ ...point, kind: this.kind, ref: this.ctx.ref });
      this.lastAt = Date.now();
      this.error = null;
      if (saved) this.last = saved;
      emit();
      syncKind(this.kind);
      return saved;
    } catch (e) {
      this.error = { code: e.code, message: e.message };
      if (e.code === 1) { // izin ditolak -> berhenti sampai pengguna mengaktifkan lagi
        this.active = false; clearInterval(this.timer); this.timer = null; updateWakeLock();
      } else {
        this.lastAt = Date.now() - this.intervalMs + 60000; // coba lagi sekitar 1 menit
      }
      emit();
      return null;
    } finally { this.busy = false; }
  }
}

export const EmployeeTracker = new Engine('att');
export const DeliveryTracker = new Engine('del');

async function permissionState() {
  try {
    if (navigator.permissions && navigator.permissions.query) {
      return (await navigator.permissions.query({ name: 'geolocation' })).state;
    }
  } catch { /* Safari lama */ }
  return 'unknown';
}

/** Padanan EmployeeTrackingCoordinator.ensureTracking(): 'started'|'already'|'denied'|'na' */
export async function ensureEmployeeTracking() {
  const emp = session.employee;
  if (!session.isLoggedIn() || !emp || !['EMPLOYEE', 'DRIVER'].includes(emp.role)) return 'na';
  if (await permissionState() === 'denied') {
    EmployeeTracker.error = { code: 1, message: 'Izin lokasi ditolak' }; emit();
    return 'denied';
  }
  const office = session.offices.find((o) => o.id === emp.office_id);
  const intervalS = office && office.tracking_interval_seconds > 0 ? office.tracking_interval_seconds : CONFIG.TRACKING_INTERVAL_S;
  const started = EmployeeTracker.start({
    ref: emp.id,
    intervalS,
    make: (c) => ({ employee_id: emp.id, office_id: emp.office_id, ...c }),
  });
  return started ? 'started' : 'already';
}

export function startDeliveryTracking(delivery) {
  const intervalS = Math.min(CONFIG.DELIVERY_INTERVAL_S, settings.get().demo ? 60 : CONFIG.DELIVERY_INTERVAL_S);
  return DeliveryTracker.start({
    ref: delivery.id,
    intervalS,
    make: (c) => ({ delivery_id: delivery.id, driver_id: session.employeeId, ...c }),
  });
}
export const stopDeliveryTracking = () => DeliveryTracker.stop();

export function stopAllTracking() { EmployeeTracker.stop(); DeliveryTracker.stop(); }

/** Aktifkan ulang dari tombol (gesture pengguna) setelah izin sebelumnya ditolak. */
export async function retryEmployeeTracking() {
  EmployeeTracker.error = null;
  EmployeeTracker.active = false;
  return ensureEmployeeTracking();
}

// ---- Siklus hidup halaman ----
document.addEventListener('visibilitychange', () => {
  updateWakeLock();
  if (!document.hidden) {
    EmployeeTracker.tick(); DeliveryTracker.tick();
    syncAll();
    emit();
  }
});
window.addEventListener('online', () => { syncAll(); });

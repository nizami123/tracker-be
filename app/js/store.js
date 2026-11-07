// Penyimpanan lokal: sesi & pengaturan (localStorage) + titik tracking (IndexedDB).
import { CONFIG } from './config.js';

function lsGet(key, fallback) {
  try { const v = localStorage.getItem(key); return v ? JSON.parse(v) : fallback; } catch { return fallback; }
}
function lsSet(key, value) {
  try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* mode privat / penuh */ }
}

// Fallback in-memory bila localStorage tidak bisa dipakai
const mem = {};

// ---- Pengaturan (bertahan setelah logout) ----
const SETTINGS_KEY = 'st_settings';
const defaults = { dark: false, wakeLock: true, demo: false, apiBase: '', email: '' };
export const settings = {
  get() { return { ...defaults, ...(lsGet(SETTINGS_KEY, mem[SETTINGS_KEY]) || {}) }; },
  set(patch) { const v = { ...this.get(), ...patch }; mem[SETTINGS_KEY] = v; lsSet(SETTINGS_KEY, v); return v; },
  get apiBase() { return this.get().apiBase || CONFIG.API_BASE; },
};

// ---- Sesi login ----
const SESSION_KEY = 'st_session';
export const session = {
  get data() { return lsGet(SESSION_KEY, mem[SESSION_KEY]) || {}; },
  patch(p) { const v = { ...this.data, ...p }; mem[SESSION_KEY] = v; lsSet(SESSION_KEY, v); },
  get token() { return this.data.token || ''; },
  get employeeId() { return this.data.employeeId || 0; },
  get employee() { return this.data.employee || null; },
  get offices() { return this.data.offices || []; },
  isLoggedIn() { return !!this.token && !!this.employeeId; },
  clear() { delete mem[SESSION_KEY]; try { localStorage.removeItem(SESSION_KEY); } catch { /* */ } },
};

// ---- IndexedDB: titik tracking (offline-first, sama seperti Room di Android) ----
const DB_NAME = 'samsu-tracker';
let dbPromise;
function openDb() {
  if (dbPromise) return dbPromise;
  dbPromise = new Promise((resolve, reject) => {
    if (!('indexedDB' in window)) return reject(new Error('IndexedDB tidak tersedia'));
    const req = indexedDB.open(DB_NAME, 1);
    req.onupgradeneeded = () => {
      const db = req.result;
      const s = db.createObjectStore('points', { keyPath: 'localId', autoIncrement: true });
      s.createIndex('uniq', ['kind', 'ref', 'recorded_at'], { unique: true });
      s.createIndex('pending', ['kind', 'synced']);
      s.createIndex('byref', ['kind', 'ref']);
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
  return dbPromise;
}
const wrap = (req) => new Promise((res, rej) => { req.onsuccess = () => res(req.result); req.onerror = () => rej(req.error); });

// Cadangan in-memory bila IndexedDB gagal (mis. mode privat tertentu)
const memPoints = [];
let memId = 1;

async function store(mode = 'readonly') {
  const db = await openDb();
  return db.transaction('points', mode).objectStore('points');
}

export const pointsDb = {
  /** kind: 'att' (absensi, ref = employeeId) | 'del' (pengiriman, ref = deliveryId) */
  async add(point) {
    const p = { ...point, synced: point.synced ? 1 : 0 };
    delete p.localId;
    try {
      const s = await store('readwrite');
      p.localId = await wrap(s.add(p));
      return p;
    } catch (e) {
      if (e && e.name === 'ConstraintError') return null; // titik dengan waktu sama sudah ada
      if (memPoints.some((m) => m.kind === p.kind && m.ref === p.ref && m.recorded_at === p.recorded_at)) return null;
      p.localId = memId++; memPoints.push(p); return p;
    }
  },
  async all(kind, ref) {
    try {
      const s = await store();
      const rows = await wrap(s.index('byref').getAll([kind, ref]));
      return rows.sort((a, b) => a.recorded_at.localeCompare(b.recorded_at));
    } catch {
      return memPoints.filter((m) => m.kind === kind && m.ref === ref).sort((a, b) => a.recorded_at.localeCompare(b.recorded_at));
    }
  },
  async forDate(ref, date) {
    return (await this.all('att', ref)).filter((p) => p.recorded_at.startsWith(date));
  },
  async pending(kind, limit = 200) {
    try {
      const s = await store();
      const rows = await wrap(s.index('pending').getAll([kind, 0], limit));
      return rows;
    } catch {
      return memPoints.filter((m) => m.kind === kind && !m.synced).slice(0, limit);
    }
  },
  async countPending(kind) { return (await this.pending(kind, 100000)).length; },
  async markSynced(ids) {
    const set = new Set(ids);
    try {
      const db = await openDb();
      const tx = db.transaction('points', 'readwrite');
      const s = tx.objectStore('points');
      for (const id of set) {
        const row = await wrap(s.get(id));
        if (row) { row.synced = 1; s.put(row); }
      }
      await new Promise((r) => { tx.oncomplete = r; tx.onerror = r; });
    } catch {
      memPoints.forEach((m) => { if (set.has(m.localId)) m.synced = 1; });
    }
  },
  /** Gabungkan titik dari server tanpa menggandakan titik lokal (unik per waktu). */
  async mergeRemote(kind, ref, remote) {
    for (const r of remote) {
      await this.add({ ...r, kind, ref, synced: 1, localId: undefined });
    }
  },
  async clearAll() {
    try { const s = await store('readwrite'); await wrap(s.clear()); } catch { /* */ }
    memPoints.length = 0;
  },
};

// ---- Cache data server (cadangan saat offline; padanan Room cache di Android) ----
export const cache = {
  get(key, fallback = null) { return lsGet('st_cache_' + key, mem['c' + key] ?? fallback); },
  set(key, value) { mem['c' + key] = value; lsSet('st_cache_' + key, value); },
  clearAll() {
    Object.keys(mem).forEach((k) => { if (k.startsWith('c')) delete mem[k]; });
    try { Object.keys(localStorage).filter((k) => k.startsWith('st_cache_')).forEach((k) => localStorage.removeItem(k)); } catch { /* */ }
  },
};

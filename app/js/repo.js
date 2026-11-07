// Lapisan data: server dulu, cadangan cache lokal (padanan semua *Repository.kt).
import { api, serverMessage } from './api.js';
import { session, cache, pointsDb } from './store.js';
import { Time } from './util.js';

const netErr = (e) => `Gagal terhubung ke server: ${e.message}`;

export const Office = {
  async refresh() {
    const res = await api.offices();
    const list = res.ok && Array.isArray(res.data) ? res.data : [];
    if (list.length) session.patch({ offices: list });
    return list;
  },
  async get(id) {
    let o = session.offices.find((x) => x.id === id);
    if (!o) { try { await this.refresh(); } catch { /* offline */ } o = session.offices.find((x) => x.id === id); }
    return o || null;
  },
  /** Zona waktu karyawan = zona kantornya (diambil ulang dari server bila bisa). */
  async applyZone(employee, { fresh = false } = {}) {
    try {
      if (fresh) { try { await this.refresh(); } catch { /* offline: pakai cache */ } }
      const office = employee ? await this.get(employee.office_id) : null;
      if (office) { Time.setCode(office.timezone); session.patch({ tz: Time.code }); }
    } catch { /* biarkan zona sebelumnya */ }
  },
};

export const Auth = {
  async login(email, password) {
    try {
      const res = await api.login(email, password);
      if (res.ok && res.data && res.data.token && res.data.employee) {
        session.patch({ token: res.data.token, employeeId: res.data.employee.id, employee: res.data.employee });
        try { await Office.refresh(); } catch { /* */ }
        await Office.applyZone(res.data.employee);
        return { ok: true, name: res.data.employee.name };
      }
      if (res.status === 401 || res.status === 400 || res.status === 403 || res.status === 422) {
        return { ok: false, message: serverMessage(res, 'Email atau password salah') };
      }
      return { ok: false, message: serverMessage(res, `Login gagal (HTTP ${res.status})`) };
    } catch (e) { return { ok: false, message: netErr(e) }; }
  },
  async changePassword(current, next) {
    try {
      const res = await api.changePassword(current, next);
      if (res.ok && res.data && res.data.success) return { ok: true };
      return { ok: false, message: serverMessage(res, 'Gagal mengubah password') };
    } catch (e) { return { ok: false, message: netErr(e) }; }
  },
};

export const Employee = {
  async refresh() {
    try {
      const res = await api.me();
      if (res.ok && res.data && res.data.id) { session.patch({ employee: res.data }); return res.data; }
    } catch { /* offline */ }
    return session.employee;
  },
};

export const Attendance = {
  async today() {
    const id = session.employeeId;
    try {
      const res = await api.today(id);
      if (res.ok) {
        const a = res.data && res.data.id ? res.data : null;
        cache.set(`today_${id}`, { date: Time.today(), a });
        return a;
      }
    } catch { /* offline */ }
    const c = cache.get(`today_${id}`);
    return c && c.date === Time.today() ? c.a : null;
  },
  /** return { list, fromCache } */
  async history() {
    const id = session.employeeId;
    try {
      const res = await api.history(id);
      if (res.ok && Array.isArray(res.data)) { cache.set(`history_${id}`, res.data); return { list: res.data, fromCache: false }; }
    } catch { /* offline */ }
    return { list: cache.get(`history_${id}`, []), fromCache: true };
  },
  async _submit(call, payload) {
    try {
      const res = await call(payload);
      const b = res.data;
      if (res.ok && b && b.success && b.attendance) {
        cache.set(`today_${session.employeeId}`, { date: Time.today(), a: b.attendance });
        return { type: 'ok', attendance: b.attendance, distance: b.distance, radius: b.radius };
      }
      if (b && b.success === false) return { type: 'rejected', message: b.message, distance: b.distance || 0, radius: b.radius || 0 };
      return { type: 'error', message: serverMessage(res, 'Tidak ada respons dari server') };
    } catch (e) { return { type: 'error', message: netErr(e) }; }
  },
  checkIn: (p) => Attendance._submit(api.checkIn, p),
  checkOut: (p) => Attendance._submit(api.checkOut, p),
};

export const Shift = {
  async current() {
    const id = session.employeeId;
    try {
      const res = await api.myShift();
      if (res.ok && res.data && res.data.current) { cache.set(`shift_${id}`, res.data); return res.data.current; }
    } catch { /* offline */ }
    const c = cache.get(`shift_${id}`);
    if (!c) return null;
    return [c.current, ...(c.upcoming || [])].filter(Boolean).find((d) => d.date === Time.today()) || null;
  },
};

export const Requests = {
  async list() {
    const id = session.employeeId;
    try {
      const res = await api.myRequests(id);
      if (res.ok && Array.isArray(res.data)) { cache.set(`requests_${id}`, res.data); return { list: res.data, fromCache: false }; }
    } catch { /* offline */ }
    return { list: cache.get(`requests_${id}`, []), fromCache: true };
  },
  async submit(body) {
    try {
      const res = await api.submitRequest(body);
      if (res.ok && res.data) return { ok: true, data: res.data };
      return { ok: false, message: serverMessage(res, `Gagal mengirim pengajuan (HTTP ${res.status})`) };
    } catch (e) { return { ok: false, message: e.message }; }
  },
};

export const Delivery = {
  async active() {
    const id = session.employeeId;
    try {
      const res = await api.activeDelivery();
      if (res.ok) {
        const d = res.data && res.data.id ? res.data : null;
        cache.set(`delivery_${id}`, d);
        return d;
      }
    } catch { /* offline */ }
    return cache.get(`delivery_${id}`, null);
  },
  async _call(fn, payload) {
    try {
      const res = await fn(payload);
      const b = res.data;
      if (res.ok && b && b.success && b.delivery) {
        cache.set(`delivery_${session.employeeId}`, b.delivery.status === 'COMPLETED' ? null : b.delivery);
        return { type: 'ok', delivery: b.delivery, distance: b.distance, radius: b.radius };
      }
      if (b && b.success === false) return { type: 'rejected', message: b.message, distance: b.distance || 0, radius: b.radius || 0 };
      return { type: 'error', message: serverMessage(res, 'Tidak ada respons dari server') };
    } catch (e) { return { type: 'error', message: netErr(e) }; }
  },
  start: (p) => Delivery._call(api.startDelivery, p),
  complete: (p) => Delivery._call(api.completeDelivery, p),
  async remotePoints(id) {
    try {
      const res = await api.getDeliveryTracking(id);
      if (res.ok && Array.isArray(res.data)) await pointsDb.mergeRemote('del', id, res.data);
    } catch { /* offline */ }
  },
};

export const TrackingRepo = {
  async refreshFromServer(date) {
    try {
      const res = await api.getTracking(date);
      if (res.ok && Array.isArray(res.data)) await pointsDb.mergeRemote('att', session.employeeId, res.data);
    } catch { /* offline */ }
  },
};

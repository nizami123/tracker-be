// Klien API: kontrak sama dengan ApiService.kt (CI3 backend), plus mode demo.
import { session, settings } from './store.js';
import { mockRequest } from './mock.js';

export class ApiError extends Error {}

function buildUrl(path, query) {
  let base = settings.apiBase || '/';
  if (!base.endsWith('/')) base += '/';
  const url = new URL(path, new URL(base, location.href));
  if (query) Object.entries(query).forEach(([k, v]) => v != null && url.searchParams.set(k, v));
  return url.toString();
}

/**
 * Mengembalikan { ok, status, data }. Melempar ApiError bila jaringan gagal.
 * 401 (selain login) -> sesi dihapus & event 'st:unauthorized' (sama seperti authInterceptor Android).
 */
export async function request(method, path, { body, query } = {}) {
  if (settings.get().demo) return mockRequest(method, path, { body, query });

  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (session.token) headers.Authorization = `Bearer ${session.token}`;

  let res;
  try {
    res = await fetch(buildUrl(path, query), {
      method, headers, body: body !== undefined ? JSON.stringify(body) : undefined, cache: 'no-store',
    });
  } catch (e) {
    throw new ApiError(
      navigator.onLine === false
        ? 'Tidak ada koneksi internet'
        : 'Tidak dapat menghubungi server (periksa koneksi / pengaturan CORS server)',
    );
  }

  let data = null;
  const text = await res.text();
  if (text) { try { data = JSON.parse(text); } catch { data = { message: text.slice(0, 200) }; } }

  if (res.status === 401 && !path.includes('auth/login')) {
    session.clear();
    window.dispatchEvent(new Event('st:unauthorized'));
  }
  return { ok: res.ok, status: res.status, data };
}

export const serverMessage = (res, fallback) =>
  (res && res.data && (res.data.message || res.data.error)) || fallback;

export const api = {
  login: (email, password) => request('POST', 'api/auth/login', { body: { email, password } }),
  changePassword: (current_password, new_password) =>
    request('POST', 'api/auth/change-password', { body: { current_password, new_password } }),
  offices: () => request('GET', 'api/offices'),
  me: () => request('GET', 'api/employees/me'),
  checkIn: (body) => request('POST', 'api/attendance/check-in', { body }),
  checkOut: (body) => request('POST', 'api/attendance/check-out', { body }),
  today: (employee_id) => request('GET', 'api/attendance/today', { query: { employee_id } }),
  history: (employee_id) => request('GET', 'api/attendance/history', { query: { employee_id } }),
  myShift: () => request('GET', 'api/shifts/my'),
  syncTracking: (points) => request('POST', 'api/tracking/sync', { body: { points } }),
  getTracking: (date) => request('GET', 'api/tracking', { query: { date } }),
  submitRequest: (body) => request('POST', 'api/requests', { body }),
  myRequests: (employee_id) => request('GET', 'api/requests', { query: { employee_id } }),
  startDelivery: (body) => request('POST', 'api/deliveries/start', { body }),
  completeDelivery: (body) => request('POST', 'api/deliveries/complete', { body }),
  activeDelivery: () => request('GET', 'api/deliveries/active'),
  deliveryHistory: () => request('GET', 'api/deliveries/history'),
  syncDeliveryTracking: (points) => request('POST', 'api/delivery-tracking/sync', { body: { points } }),
  getDeliveryTracking: (id) => request('GET', `api/delivery-tracking/${id}`),
};

// Utilitas umum: escape HTML, waktu (WIB/WITA/WIT), jarak, ikon, toast, dialog.

export const $ = (sel, el = document) => el.querySelector(sel);
export const $$ = (sel, el = document) => [...el.querySelectorAll(sel)];

export function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------------------------------------------------------------------------
// Waktu. Zona ditentukan per kantor (WIB/WITA/WIT). Indonesia tidak memakai DST,
// jadi offset tetap. Semua jam yang dikirim ke / dibaca dari server = jam dinding
// zona kantor ("yyyy-MM-dd HH:mm:ss"), sama seperti aplikasi Android (AppTime).
// ---------------------------------------------------------------------------
const OFFSETS = { WIB: 7, WITA: 8, WIT: 9 };
const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const pad = (n, l = 2) => String(n).padStart(l, '0');

export const Time = {
  code: 'WIB',
  setCode(code) {
    const c = String(code || 'WIB').trim().toUpperCase();
    this.code = OFFSETS[c] ? c : 'WIB';
  },
  get offset() { return OFFSETS[this.code] || 7; },
  get label() { return this.code; },
  /** Date UTC yang "digeser" sehingga getUTC* = jam dinding zona kantor. */
  shifted(ms = Date.now()) { return new Date(ms + this.offset * 3600e3); },
  now() { return this.shifted().toISOString().replace('T', ' ').slice(0, 19); },
  today() { return this.shifted().toISOString().slice(0, 10); },
  hour() { return this.shifted().getUTCHours(); },
  /** "yyyy-MM-dd HH:mm:ss" (jam dinding zona) -> epoch ms */
  parse(str) {
    if (!str) return null;
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/.exec(str);
    if (!m) return null;
    return Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0)) - this.offset * 3600e3;
  },
  fromMs(ms) { return this.shifted(ms).toISOString().replace('T', ' ').slice(0, 19); },
  /** "08:35:10 WIB" */
  withLabel(dt) { return dt ? `${String(dt).slice(-8)} ${this.label}` : '-'; },
  clock() { return this.now().slice(11); },
  /** "Jumat, 08 Agustus 2026" */
  longDate(dateStr) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(dateStr || this.today());
    if (!m) return dateStr || '';
    const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
    return `${DAYS[d.getUTCDay()]}, ${pad(+m[3])} ${MONTHS[+m[2] - 1]} ${m[1]}`;
  },
  /** "08 Agu 2026" */
  shortDate(dateStr) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(dateStr || '');
    if (!m) return dateStr || '-';
    return `${pad(+m[3])} ${MONTHS[+m[2] - 1].slice(0, 3)} ${m[1]}`;
  },
  greeting() {
    const h = this.hour();
    return h < 11 ? 'Selamat pagi,' : h < 15 ? 'Selamat siang,' : h < 18 ? 'Selamat sore,' : 'Selamat malam,';
  },
  durationHM(startStr, endStr) {
    const a = this.parse(startStr), b = this.parse(endStr);
    if (a == null || b == null) return '-';
    const mins = Math.max(0, Math.round((b - a) / 60000));
    return `${Math.floor(mins / 60)}j ${mins % 60}m`;
  },
  hms(ms) {
    const t = Math.max(0, Math.floor(ms / 1000));
    return `${pad(Math.floor(t / 3600))}:${pad(Math.floor((t % 3600) / 60))}:${pad(t % 60)}`;
  },
  /** tanggal yyyy-MM-dd, n hari lalu (relatif hari ini zona kantor) */
  daysAgo(n) { return this.shifted(Date.now() - n * 86400e3).toISOString().slice(0, 10); },
};

// ---------------------------------------------------------------------------
// Geo
// ---------------------------------------------------------------------------
export function haversine(lat1, lon1, lat2, lon2) {
  const R = 6371000, rad = Math.PI / 180;
  const dLat = (lat2 - lat1) * rad, dLon = (lon2 - lon1) * rad;
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLon / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(a));
}

export function totalDistanceKm(points) {
  let m = 0;
  for (let i = 1; i < points.length; i++) {
    m += haversine(points[i - 1].latitude, points[i - 1].longitude, points[i].latitude, points[i].longitude);
  }
  return m / 1000;
}

export function mapsLink(lat, lng) {
  return `https://maps.apple.com/?ll=${lat},${lng}&q=${lat},${lng}`;
}

/** Posisi GPS sekali (Promise). Menolak dengan Error ber-pesan Indonesia. */
export function getPosition({ timeout = 20000, maximumAge = 0 } = {}) {
  return new Promise((resolve, reject) => {
    if (!('geolocation' in navigator)) return reject(new Error('Perangkat tidak mendukung GPS'));
    navigator.geolocation.getCurrentPosition(resolve, (err) => {
      const msg = err.code === 1
        ? 'Izin lokasi ditolak. Aktifkan di Pengaturan iPhone › Privasi › Layanan Lokasi › Safari/Aplikasi ini.'
        : err.code === 3
          ? 'Waktu pencarian GPS habis. Keluar ruangan/dekat jendela lalu coba lagi.'
          : 'Tidak dapat mengambil lokasi GPS. Pastikan GPS aktif.';
      const e = new Error(msg); e.code = err.code; reject(e);
    }, { enableHighAccuracy: true, timeout, maximumAge });
  });
}

// ---------------------------------------------------------------------------
// Platform
// ---------------------------------------------------------------------------
export const isIOS = () => /iphone|ipad|ipod/i.test(navigator.userAgent) ||
  (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
export const isStandalone = () => window.navigator.standalone === true ||
  window.matchMedia('(display-mode: standalone)').matches;

// ---------------------------------------------------------------------------
// Ikon (inline SVG, stroke)
// ---------------------------------------------------------------------------
const P = {
  home: '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
  pin: '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
  history: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
  doc: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  building: '<path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16M14 9h5a1 1 0 0 1 1 1v11M3 21h18M8 8h2M8 12h2M8 16h2"/>',
  briefcase: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 13h18"/>',
  phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
  mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
  lock: '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
  eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  eyeoff: '<path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4M6.5 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.8 9.8 0 0 0 4-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
  camera: '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>',
  gear: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
  logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
  chevron: '<path d="m9 6 6 6-6 6"/>',
  back: '<path d="M15 6l-6 6 6 6"/>',
  close: '<path d="M6 6l12 12M18 6 6 18"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
  battery: '<rect x="2" y="7" width="17" height="10" rx="2"/><path d="M22 11v2M6 10v4M10 10v4"/>',
  refresh: '<path d="M20 11a8 8 0 0 0-14-4L4 9M4 4v5h5M4 13a8 8 0 0 0 14 4l2-2M20 20v-5h-5"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  truck: '<path d="M2 6h11v10H2zM13 9h4l4 4v3h-8"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
  bell: '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8zM10 20a2 2 0 0 0 4 0"/>',
  moon: '<path d="M21 13A9 9 0 1 1 11 3a7 7 0 0 0 10 10z"/>',
  globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
  share: '<path d="M12 3v12M8 7l4-4 4 4M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/>',
  paperclip: '<path d="M21 11l-9 9a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8"/>',
  check: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
  sync: '<path d="M4 4v6h6M20 20v-6h-6"/><path d="M20 10A8 8 0 0 0 6.3 6.3L4 10M4 14a8 8 0 0 0 13.7 3.7L20 14"/>',
};
export function icon(name, size = 22, cls = '') {
  return `<svg class="ic ${cls}" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${P[name] || ''}</svg>`;
}

// ---------------------------------------------------------------------------
// Toast & dialog
// ---------------------------------------------------------------------------
let toastTimer;
export function toast(msg, ms = 2600) {
  const el = $('#toast');
  if (!el) return;
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), ms);
}

/** Dialog modal generik. build(box, close) mengisi isi; resolve dengan nilai close(v). */
export function modal(build, { sheet = false } = {}) {
  return new Promise((resolve) => {
    const back = document.createElement('div');
    back.className = 'modal-back';
    const box = document.createElement('div');
    box.className = sheet ? 'modal sheet' : 'modal';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    back.appendChild(box);
    const close = (v) => { back.remove(); resolve(v); };
    back.addEventListener('click', (e) => { if (e.target === back) close(null); });
    build(box, close);
    document.body.appendChild(back);
  });
}

export function confirmDialog({ title, message, ok = 'Ya', cancel = 'Batal', danger = false }) {
  return modal((box, close) => {
    box.innerHTML = `<h3>${esc(title)}</h3><p class="muted">${esc(message)}</p>
      <div class="row gap end"><button class="btn ghost sm" data-r="0">${esc(cancel)}</button>
      <button class="btn ${danger ? 'danger' : 'primary'} sm" data-r="1">${esc(ok)}</button></div>`;
    $$('button', box).forEach((b) => b.addEventListener('click', () => close(b.dataset.r === '1')));
  }).then((v) => v === true);
}

export function pill(text, tone) {
  return `<span class="pill ${tone}">${esc(text)}</span>`;
}

export function row(label, value, id = '') {
  return `<div class="kv"><span>${esc(label)}</span><b ${id ? `id="${id}"` : ''}>${esc(value ?? '-')}</b></div>`;
}

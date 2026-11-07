// Service worker: cache "app shell" agar aplikasi terbuka cepat & tetap bisa dibuka saat offline.
// Panggilan API (/api/...) dan ubin peta TIDAK pernah di-cache di sini.
const VERSION = 'v1.0.0';
const CACHE = `samsu-tracker-${VERSION}`;
const SHELL = [
  './', 'index.html', 'manifest.webmanifest', 'css/app.css',
  'js/app.js', 'js/api.js', 'js/camera.js', 'js/config.js', 'js/map.js', 'js/mock.js', 'js/repo.js', 'js/store.js',
  'js/theme.js', 'js/tracker.js', 'js/util.js',
  'js/screens/checkin.js', 'js/screens/delivery.js', 'js/screens/history.js', 'js/screens/home.js', 'js/screens/login.js',
  'js/screens/photo.js', 'js/screens/profile.js', 'js/screens/requests.js', 'js/screens/tracking.js',
  'icons/logo.png', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/apple-touch-icon.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k.startsWith('samsu-tracker-') && k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin || url.pathname.includes('/api/')) return; // jaringan langsung

  // Navigasi: jaringan dulu, cadangan index.html dari cache (offline)
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('index.html').then((r) => r || caches.match('./'))));
    return;
  }
  // Aset statis: cache dulu + perbarui di belakang layar
  e.respondWith(
    caches.match(req).then((hit) => {
      const net = fetch(req).then((res) => {
        if (res && res.ok) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(req, copy)); }
        return res;
      }).catch(() => hit);
      return hit || net;
    }),
  );
});

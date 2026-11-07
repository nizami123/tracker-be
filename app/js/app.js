// Router & shell. Hash routing (#/home, #/checkin, ...) — cocok untuk PWA statis.
import { session, settings } from './store.js';
import { Time, icon, $, $$, esc, toast } from './util.js';
import { Office, Employee } from './repo.js';
import { ensureEmployeeTracking, stopAllTracking, syncAll, onTrackerChange } from './tracker.js';
import { applyTheme } from './theme.js';
import { login } from './screens/login.js';
import { home } from './screens/home.js';
import { checkin } from './screens/checkin.js';
import { trackingTab, trackingDetail } from './screens/tracking.js';
import { history } from './screens/history.js';
import { requests, newRequest } from './screens/requests.js';
import { profile, settingsScreen, changePassword } from './screens/profile.js';
import { pickup, deliveryScreen } from './screens/delivery.js';

const appEl = document.getElementById('app');

const TABS = [
  { id: 'home', label: 'Beranda', ic: 'home' },
  { id: 'tracking', label: 'Tracking', ic: 'pin' },
  { id: 'history', label: 'Riwayat', ic: 'history' },
  { id: 'requests', label: 'Pengajuan', ic: 'doc' },
  { id: 'profile', label: 'Profil', ic: 'user' },
];

// route -> { fn, tab?: bool, public?: bool }
const ROUTES = {
  login: { fn: login, public: true },
  home: { fn: home, tab: true },
  tracking: { fn: trackingTab, tab: true },
  history: { fn: history, tab: true },
  requests: { fn: requests, tab: true },
  profile: { fn: profile, tab: true },
  checkin: { fn: checkin },
  trackdetail: { fn: trackingDetail },
  newrequest: { fn: newRequest },
  settings: { fn: settingsScreen },
  password: { fn: changePassword },
  pickup: { fn: pickup },
  delivery: { fn: deliveryScreen },
};

let cleanup = null;
let renderSeq = 0;

export function go(route, { replace = false } = {}) {
  const target = '#/' + route.replace(/^#?\/?/, '');
  if (location.hash === target) return render();
  if (replace) location.replace(target); else location.hash = target;
}

function parseHash() {
  const raw = (location.hash || '').replace(/^#\/?/, '');
  const [path, qs] = raw.split('?');
  const [name, ...rest] = (path || '').split('/');
  return { name: name || '', arg: rest.join('/'), query: Object.fromEntries(new URLSearchParams(qs || '')) };
}



export function logout() {
  stopAllTracking();
  session.clear();
  go('login', { replace: true });
}

async function render() {
  const seq = ++renderSeq;
  if (cleanup) { try { cleanup(); } catch { /* */ } cleanup = null; }
  document.querySelectorAll('.modal-back').forEach((n) => n.remove());

  let { name, arg, query } = parseHash();
  const loggedIn = session.isLoggedIn();
  if (!ROUTES[name]) name = loggedIn ? 'home' : 'login';
  if (!ROUTES[name].public && !loggedIn) name = 'login';
  if (name === 'login' && loggedIn) name = 'home';
  const route = ROUTES[name];
  const wantHash = '#/' + name + (arg ? '/' + arg : '');
  if (!location.hash.startsWith(wantHash)) window.history.replaceState(null, '', wantHash);

  appEl.innerHTML = '';
  const view = document.createElement('div');
  view.className = 'view' + (route.tab ? '' : ' no-tabs');
  appEl.appendChild(view);

  if (route.tab) {
    const nav = document.createElement('nav');
    nav.className = 'tabbar';
    nav.innerHTML = TABS.map((t) =>
      `<a href="#/${t.id}" class="${t.id === name ? 'active' : ''}" aria-label="${t.label}">${icon(t.ic, 24)}<span>${t.label}</span></a>`).join('');
    appEl.appendChild(nav);
  }
  window.scrollTo(0, 0);

  const ctx = { go, arg, query, view, logout, seq, isCurrent: () => seq === renderSeq };
  const result = await route.fn(view, ctx);
  if (seq !== renderSeq) { if (typeof result === 'function') result(); return; }
  cleanup = typeof result === 'function' ? result : null;
}

// ---- Event global ----
window.addEventListener('hashchange', render);
window.addEventListener('st:unauthorized', () => { stopAllTracking(); toast('Sesi berakhir, silakan masuk kembali'); go('login', { replace: true }); });

// Dipanggil sekali per pembukaan aplikasi / kembali ke aplikasi: padanan MainActivity.onResume()
async function onAppActive() {
  if (!session.isLoggedIn()) return;
  const emp = await Employee.refresh();
  await Office.applyZone(emp, { fresh: true });
  await ensureEmployeeTracking();
  syncAll();
}
document.addEventListener('visibilitychange', () => { if (!document.hidden) onAppActive(); });

// Tick jam di UI yang butuh (mis. durasi) ditangani tiap layar. Di sini hanya boot:
(async function boot() {
  applyTheme();
  Time.setCode(session.data.tz || 'WIB');
  if ('serviceWorker' in navigator && location.protocol !== 'file:') {
    navigator.serviceWorker.register('sw.js').catch(() => { /* hosting tanpa HTTPS / dev */ });
  }
  await render();
  onAppActive();
})();


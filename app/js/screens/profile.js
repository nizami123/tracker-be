import { session, settings } from '../store.js';
import { Employee, Office, Auth } from '../repo.js';
import { icon, esc, $, $$, confirmDialog, toast, isIOS, isStandalone } from '../util.js';
import { CONFIG } from '../config.js';
import { applyTheme } from '../theme.js';
import { refreshWakeLock } from '../tracker.js';
import { resetMock } from '../mock.js';

export function profile(view, ctx) {
  const e0 = session.employee || {};
  view.innerHTML = `
  <div class="hero" style="padding-bottom:28px;border-radius:0 0 28px 28px">
    <div class="row between" style="align-items:flex-start"><div style="font-size:18px;font-weight:700">Profil</div>
      <button class="icon-btn" id="gear" style="color:#fff" aria-label="Pengaturan">${icon('gear', 24)}</button></div>
    <div class="row gap mt16">
      <div class="avatar" id="av">${esc((e0.name || '?').trim().charAt(0).toUpperCase())}</div>
      <div><div class="name" id="name" style="font-size:20px;font-weight:800">${esc(e0.name || '-')}</div>
        <div id="nip" style="opacity:.9;font-size:13px">${esc(e0.nip ? 'NIP. ' + e0.nip : e0.employee_code || '')}</div>
        <div id="pos" style="opacity:.9;font-size:13px">${esc(e0.position || '-')}</div></div>
    </div>
  </div>
  <h3 class="section-title">Informasi Pribadi</h3>
  <div class="card">
    <div class="kv"><span>${icon('mail', 16)} Email</span><b id="email">${esc(e0.email || '-')}</b></div>
    <div class="kv"><span>${icon('phone', 16)} No. Telepon</span><b id="phone">${esc(e0.phone || '-')}</b></div>
    <div class="kv"><span>${icon('building', 16)} Kantor</span><b id="office">-</b></div>
    <div class="kv"><span>${icon('briefcase', 16)} Jabatan</span><b id="pos2">${esc(e0.position || '-')}</b></div>
  </div>
  <h3 class="section-title">Aplikasi</h3>
  <div class="card" style="padding:4px 16px">
    <button class="list-btn" data-go="settings"><span class="badge">${icon('gear', 18)}</span>Pengaturan<span class="chev">${icon('chevron', 18)}</span></button>
    <button class="list-btn" data-go="password"><span class="badge">${icon('lock', 18)}</span>Ubah Password<span class="chev">${icon('chevron', 18)}</span></button>
    <div class="list-btn"><span class="badge">${icon('info', 18)}</span>Tentang Aplikasi<span class="chev small">Versi ${CONFIG.VERSION}</span></div>
    <button class="list-btn danger" id="logout"><span class="badge">${icon('logout', 18)}</span>Keluar</button>
  </div>`;

  $$('[data-go]', view).forEach((b) => b.addEventListener('click', () => ctx.go(b.dataset.go)));
  $('#gear', view).addEventListener('click', () => ctx.go('settings'));
  $('#logout', view).addEventListener('click', async () => {
    if (await confirmDialog({ title: 'Keluar', message: 'Anda yakin ingin keluar dari akun ini?', ok: 'Keluar', danger: true })) ctx.logout();
  });

  (async () => {
    const e = (await Employee.refresh()) || e0;
    if (!ctx.isCurrent()) return;
    $('#name', view).textContent = e.name; $('#nip', view).textContent = e.nip ? 'NIP. ' + e.nip : e.employee_code || '';
    $('#pos', view).textContent = e.position || '-'; $('#pos2', view).textContent = e.position || '-';
    $('#email', view).textContent = e.email || '-'; $('#phone', view).textContent = e.phone || '-';
    $('#av', view).textContent = (e.name || '?').trim().charAt(0).toUpperCase();
    const o = await Office.get(e.office_id);
    if (ctx.isCurrent()) $('#office', view).textContent = o ? o.name.replace(/^Kantor /, '') : '-';
  })();
}

// ---------------------------------------------------------------------------
export function settingsScreen(view, ctx) {
  const st = settings.get();
  const emp = session.employee || {};
  const office = session.offices.find((o) => o.id === emp.office_id);
  const interval = office && office.tracking_interval_seconds > 0 ? office.tracking_interval_seconds : CONFIG.TRACKING_INTERVAL_S;
  const every = interval % 60 === 0 ? `Setiap ${interval / 60} menit` : `Setiap ${interval} detik`;

  view.innerHTML = `
  <div class="topbar"><button class="icon-btn" id="back" aria-label="Kembali">${icon('back', 24)}</button><h1>Pengaturan</h1></div>
  <h3 class="section-title">Umum</h3>
  <div class="card">
    <div class="kv"><span>${icon('globe', 16)} Bahasa</span><b>Indonesia</b></div>
    <div class="kv"><span>${icon('moon', 16)} Mode Gelap</span><label class="switch"><input type="checkbox" id="dark" ${st.dark ? 'checked' : ''}><i></i></label></div>
    <div class="kv"><span style="flex:1">Jaga layar tetap menyala saat tracking<br><small>Disarankan di iPhone agar tracking tidak berhenti</small></span>
      <label class="switch"><input type="checkbox" id="wake" ${st.wakeLock ? 'checked' : ''}><i></i></label></div>
    <div class="kv"><span>Interval Tracking</span><b>${every}</b></div>
    <div class="kv"><span>Zona Waktu</span><b>${esc(session.data.tz || 'WIB')}</b></div>
  </div>
  <h3 class="section-title">Keamanan</h3>
  <div class="card" style="padding:4px 16px">
    <button class="list-btn" id="pw"><span class="badge">${icon('lock', 18)}</span>Ubah Password<span class="chev">${icon('chevron', 18)}</span></button>
    <button class="list-btn danger" id="logout"><span class="badge">${icon('logout', 18)}</span>Keluar</button>
  </div>
  ${st.demo ? `<h3 class="section-title">Mode Demo</h3><div class="card"><p class="muted small">Anda memakai data tiruan di browser ini.</p>
    <button class="btn ghost sm mt12" id="reset">Reset Data Demo</button></div>` : ''}
  ${isIOS() && !isStandalone() ? `<div class="banner warn">${icon('share', 18)}<div>Aplikasi belum dipasang. Ketuk <b>Bagikan</b> › <b>Tambah ke Layar Utama</b> agar tampil layar penuh dan izin lokasi lebih stabil.</div></div>` : ''}`;

  $('#back', view).addEventListener('click', () => ctx.go('profile'));
  $('#pw', view).addEventListener('click', () => ctx.go('password'));
  $('#dark', view).addEventListener('change', (e) => { settings.set({ dark: e.target.checked }); applyTheme(); });
  $('#wake', view).addEventListener('change', (e) => { settings.set({ wakeLock: e.target.checked }); refreshWakeLock(); });
  $('#logout', view).addEventListener('click', async () => {
    if (await confirmDialog({ title: 'Keluar', message: 'Anda yakin ingin keluar dari akun ini?', ok: 'Keluar', danger: true })) ctx.logout();
  });
  const r = $('#reset', view);
  if (r) r.addEventListener('click', () => { resetMock(); toast('Data demo direset'); });
}

// ---------------------------------------------------------------------------
export function changePassword(view, ctx) {
  const fields = [['cur', 'Password Saat Ini'], ['new', 'Password Baru'], ['conf', 'Konfirmasi Password Baru']];
  view.innerHTML = `
  <div class="topbar"><button class="icon-btn" id="back" aria-label="Kembali">${icon('back', 24)}</button><h1>Ubah Password</h1></div>
  <form class="pad" id="f" novalidate>
    ${fields.map(([id, label]) => `<div class="field"><label for="${id}">${label}</label>
      <div class="input-wrap"><input class="input" id="${id}" type="password" autocomplete="${id === 'cur' ? 'current-password' : 'new-password'}">
      <button type="button" class="icon-btn" data-eye="${id}" aria-label="Tampilkan">${icon('eyeoff', 20)}</button></div></div>`).join('')}
    <div id="err" class="error hidden" role="alert"></div>
    <button class="btn primary block" id="save" type="submit">SIMPAN PASSWORD BARU</button>
  </form>`;
  $('#back', view).addEventListener('click', () => ctx.go('settings'));
  $$('[data-eye]', view).forEach((b) => b.addEventListener('click', () => {
    const i = $('#' + b.dataset.eye, view); const show = i.type === 'password';
    i.type = show ? 'text' : 'password'; b.innerHTML = icon(show ? 'eye' : 'eyeoff', 20);
  }));
  const err = $('#err', view);
  const showErr = (m) => { err.textContent = m; err.classList.toggle('hidden', !m); };
  $('#f', view).addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const cur = $('#cur', view).value, nw = $('#new', view).value, conf = $('#conf', view).value;
    if (!cur || !nw || !conf) return showErr('Semua kolom wajib diisi');
    if (nw.length < 6) return showErr('Password baru minimal 6 karakter');
    if (nw !== conf) return showErr('Konfirmasi password baru tidak cocok');
    if (nw === cur) return showErr('Password baru tidak boleh sama dengan password lama');
    showErr('');
    const b = $('#save', view); b.disabled = true; b.innerHTML = '<span class="spinner"></span> Menyimpan...';
    const res = await Auth.changePassword(cur, nw);
    if (res.ok) { toast('Password berhasil diubah'); ctx.go('settings'); return; }
    b.disabled = false; b.textContent = 'SIMPAN PASSWORD BARU'; showErr(res.message);
  });
}

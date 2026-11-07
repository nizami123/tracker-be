# Samsu Tracker — versi PWA (iPhone)

Port dari aplikasi Android: login, absen masuk/pulang (kamera + GPS + radius), tracking lokasi,
riwayat + rute, pengajuan (dengan lampiran), profil/pengaturan/ubah password, dan pengiriman kendaraan (driver).
Tanpa build step: file statis (HTML/CSS/JS).

## Pasang di server
1. Upload SELURUH isi folder ini ke hosting **HTTPS** (wajib untuk GPS, kamera, service worker).
   Contoh: `https://tracker.siternal.my.id/app/`
2. Atur `API_BASE` di `js/config.js` (default `https://tracker.siternal.my.id/`).
   - Jika PWA di domain yang SAMA dengan API: tidak perlu CORS.
   - Jika domain BEDA: backend CI3 harus mengirim header CORS
     (`Access-Control-Allow-Origin`, `-Headers: Authorization, Content-Type`, `-Methods: GET, POST, OPTIONS`)
     dan menjawab request OPTIONS dengan 200.
3. Di iPhone: buka URL di **Safari** → Bagikan → **Tambah ke Layar Utama** → buka dari ikon.

## Coba tanpa server
Di layar login buka "Pengaturan lanjutan" → aktifkan **Mode demo**.
Akun: `ahmad@example.com` (karyawan) / `sopir@example.com` (driver), password `demo`.

## Batasan iOS (tidak bisa diatasi oleh PWA)
- **Tracking tidak jalan di latar belakang.** Titik hanya dicatat saat aplikasi terbuka & layar menyala
  (aplikasi meminta Wake Lock bila iOS mengizinkan). Saat dibuka kembali, titik langsung dicatat dan disinkronkan.
  Titik disimpan offline dulu (IndexedDB) lalu dikirim ke `api/tracking/sync`.
- Level baterai tidak tersedia di iOS (dikirim `null`).
- Peta memakai OpenStreetMap (bukan Google Maps), tanpa API key.
- Izin lokasi/kamera bisa ditanya ulang oleh iOS; jika ditolak: Pengaturan › Safari / Privasi.
- Jika kamera langsung gagal, tombol "Ambil Foto" membuka kamera bawaan iPhone.

## Struktur
`index.html`, `manifest.webmanifest`, `sw.js` (offline shell), `icons/`, `css/app.css`,
`js/` (api, repo, tracker, camera, map, mock, screens/*).

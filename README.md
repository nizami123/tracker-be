# Employee Tracker — Backend (CodeIgniter 3 + MySQL/MariaDB)

Backend REST API untuk aplikasi Android Employee Tracker. Melakukan
**validasi lokasi absensi di server** (bukan hanya di Android) sesuai
spesifikasi, dan menyimpan semua data ke MySQL/MariaDB.

## 1. Yang perlu Anda siapkan sendiri

Folder ini **bukan** instalasi CodeIgniter 3 yang lengkap — hanya
file `application/` (controller, model, config, helper) yang perlu
ditaruh di atas instalasi CI3 asli. Ini karena core framework CI3
(folder `system/`) sebaiknya diunduh langsung dari sumber resmi, bukan
ditulis ulang manual.

Langkah instalasi:

1. Download CodeIgniter 3 dari https://codeigniter.com/download
   (atau `composer create-project codeigniter4/appstarter` **tidak** —
   pastikan ambil **CodeIgniter 3.x**, bukan 4, karena strukturnya beda).
2. Ekstrak, Anda akan dapat folder `system/`, `application/`, `index.php`, dll.
3. **Timpa** folder `application/` bawaan dengan folder `application/` dari
   paket ini (atau salin isi `config/`, `controllers/api/`, `core/`,
   `helpers/`, `models/` satu per satu).
4. Salin juga `.htaccess` ke root project.
5. Buka `application/config/config.php` bawaan CI3, lalu terapkan
   perubahan yang tercantum di `CONFIG_CHANGES.php` (paket ini).
6. Buat database MySQL/MariaDB baru, lalu import:
   ```
   mysql -u root -p employee_tracker < database/schema.sql
   ```
   (buat database `employee_tracker` dulu jika belum ada:
   `CREATE DATABASE employee_tracker CHARACTER SET utf8mb4;`)
7. Sesuaikan kredensial di `application/config/database.php`.
8. Pastikan folder `uploads/attendance_photos/` bisa ditulis web server
   (`chmod 755` atau `775` di Linux).
9. Jalankan dengan Apache/Nginx (XAMPP/Laragon paling gampang untuk lokal),
   arahkan ke folder ini sebagai document root / virtual host.

## 2. Akun demo (sudah ada di schema.sql)

Semua password: **`demo123`**

| Email               | Nama  | Kantor    | Role         |
|---------------------|-------|-----------|--------------|
| ahmad@example.com   | Ahmad | Surabaya  | EMPLOYEE     |
| budi@example.com    | Budi  | Lamongan  | EMPLOYEE     |
| citra@example.com   | Citra | Mojokerto | ADMIN_KANTOR |
| deni@example.com    | Deni  | Gresik    | EMPLOYEE     |
| admin@example.com   | -     | -         | SUPER_ADMIN  |

## 3. Menguji API dengan curl

```bash
# Login
curl -X POST http://192.168.1.100/employee-tracker-backend/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ahmad@example.com","password":"demo123"}'

# -> simpan "token" dari respons, lalu:

# Lihat daftar kantor
curl http://192.168.1.100/employee-tracker-backend/api/offices \
  -H "Authorization: Bearer TOKEN_DISINI"

# Absen masuk (lokasi TEPAT di titik Kantor Surabaya -> harus sukses)
curl -X POST http://192.168.1.100/employee-tracker-backend/api/attendance/check-in \
  -H "Authorization: Bearer TOKEN_DISINI" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 101,
    "latitude": -7.257472,
    "longitude": 112.752090,
    "accuracy": 8.5,
    "timestamp": "2026-08-10 08:01:20",
    "photo_base64": ""
  }'

# Absen masuk dari lokasi jauh (>50m) -> harus DITOLAK (success:false)
curl -X POST http://192.168.1.100/employee-tracker-backend/api/attendance/check-in \
  -H "Authorization: Bearer TOKEN_DISINI" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 101,
    "latitude": -7.30,
    "longitude": 112.80,
    "accuracy": 8.5,
    "timestamp": "2026-08-10 08:01:20",
    "photo_base64": ""
  }'
```

## 4. Daftar endpoint (harus sama persis dengan `ApiService.kt` di Android)

| Method | Path                        | Auth | Keterangan |
|--------|-----------------------------|:---:|------------|
| POST   | `/api/auth/login`           | -   | `{email, password}` -> `{token, employee}` |
| GET    | `/api/offices`               | ✔  | Daftar kantor aktif |
| GET    | `/api/employees/me`          | ✔  | Profil karyawan yang login |
| POST   | `/api/attendance/check-in`   | ✔  | Validasi lokasi di server, simpan absensi |
| POST   | `/api/attendance/check-out`  | ✔  | Idem, pakai `check_out_radius` |
| GET    | `/api/attendance/today`      | ✔  | Status absen hari ini |
| GET    | `/api/attendance/history`    | ✔  | Riwayat absensi |
| POST   | `/api/tracking/sync`         | ✔  | Kirim batch titik lokasi (setiap 10 menit) |
| GET    | `/api/tracking/{attendance_id}` | ✔ | Titik-titik tracking untuk satu sesi absen |
| POST   | `/api/requests`              | ✔  | Kirim pengajuan (LATE/CHECK_IN/CHECK_OUT/LEAVE) |
| GET    | `/api/requests`               | ✔  | Riwayat pengajuan milik sendiri |

Semua endpoint ber-`✔` memerlukan header:
```
Authorization: Bearer <token dari login>
```

## 5. Keamanan yang sudah diterapkan (sesuai spec bagian 25)

- **office_id tidak pernah dipercaya dari client.** Setiap endpoint
  mengambil `office_id` dari data karyawan yang sudah terautentikasi
  di server (`employees.office_id`), bukan dari body/query request.
- **Kepemilikan data selalu dicek ulang.** Check-out dan sinkronisasi
  tracking memverifikasi `attendance.employee_id` cocok dengan token
  yang sedang login sebelum menulis/membaca apa pun.
- **Jarak & radius selalu dihitung ulang di server** (`distance_helper.php`,
  fungsi Haversine) — hasil perhitungan di Android hanya untuk
  tampilan UI, keputusan sebenarnya selalu dari sini.
- Password disimpan sebagai hash bcrypt (`password_hash`/`password_verify`),
  bukan plaintext.
- Token bearer disimpan di tabel `auth_tokens` dengan masa berlaku
  (default 30 hari), bukan token statis/API-key.

## 6. Menyambungkan Android app ke backend ini

Di project Android:
1. Buka `app/src/main/java/com/employeetracker/app/util/Constants.kt`,
   ubah `USE_MOCK_API` menjadi `false`.
2. Buka `app/build.gradle.kts`, ubah `BASE_URL` ke alamat backend Anda,
   contoh: `"http://192.168.1.100/employee-tracker-backend/"`
   (harus diakhiri `/`, dan pakai IP LAN, bukan `localhost`, kalau
   diuji dari HP fisik/emulator).
3. Sync Gradle & jalankan ulang. Tidak ada kode UI/ViewModel yang perlu
   diubah karena `MockApiService` dan Retrofit sama-sama memenuhi
   interface `ApiService` yang sama.

## 7. Yang belum termasuk (menyusul)

- Web admin (Master Kantor, Master Karyawan, approve/reject pengajuan,
  dashboard tracking) — akan dibuat setelah alur karyawan ini stabil.
- Refresh-token / logout endpoint (saat ini token cukup lama masa
  berlakunya untuk kebutuhan mobile).
- Rate limiting pada endpoint login.

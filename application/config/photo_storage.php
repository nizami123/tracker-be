<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Penyimpanan file di VPS terpisah (bukan di server API).
 *
 * Struktur sama seperti sebelumnya, hanya lokasinya pindah ke hosting foto:
 *   attendance_photos/    foto absen masuk/pulang, luar kantor, pengiriman
 *   request_attachments/  lampiran pengajuan
 *
 * API mengirim file ke upload.php di hosting foto; yang disimpan di database
 * tetap NAMA FILE saja.
 */

// URL folder (harus diakhiri "/") — dipakai untuk menampilkan file di web admin.
$config['photo_base_url']      = 'https://samsu.samuderasuri.com/tracker/attendance_photos/';
$config['attachment_base_url'] = 'https://samsu.samuderasuri.com/tracker/request_attachments/';

// Endpoint skrip penerima upload di hosting foto (file: vps_foto/upload.php).
$config['photo_upload_url'] = 'https://samsu.samuderasuri.com/tracker/upload.php';

// Timeout cURL (detik). Upload berjalan di dalam request API, jadi total waktunya
// harus lebih kecil dari timeout HTTP di aplikasi Android.
$config['photo_upload_connect_timeout'] = 5;
$config['photo_upload_timeout'] = 15;

// Jika upload ke hosting foto gagal, simpan sementara di folder uploads/ lokal
// agar file tidak hilang (bisa dipindah manual ke hosting foto nanti).
$config['photo_fallback_local'] = TRUE;

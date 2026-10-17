<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Penyimpanan file di VPS terpisah (bukan di server API).
 *
 * Struktur sama seperti sebelumnya, hanya lokasinya pindah ke VPS foto:
 *   attendance_photos/    foto absen masuk/pulang, luar kantor, pengiriman
 *   request_attachments/  lampiran pengajuan
 *
 * API mengirim file ke upload.php di VPS foto; yang disimpan di database
 * tetap NAMA FILE saja.
 */

// URL folder (harus diakhiri "/") — dipakai untuk menampilkan file di web admin.
$config['photo_base_url']      = 'https://m.xcloud.co.id/sssby/tracker_foto/attendance_photos/';
$config['attachment_base_url'] = 'https://m.xcloud.co.id/sssby/tracker_foto/request_attachments/';

// Endpoint skrip penerima upload di VPS foto (file: vps_foto/upload.php).
$config['photo_upload_url'] = 'https://m.xcloud.co.id/sssby/tracker_foto/upload.php';

// Timeout cURL (detik).
$config['photo_upload_timeout'] = 20;

// Jika upload ke VPS foto gagal, simpan sementara di folder uploads/ lokal
// agar file tidak hilang (bisa dipindah manual ke VPS foto nanti).
$config['photo_fallback_local'] = TRUE;

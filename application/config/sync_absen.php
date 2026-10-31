<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Sync data absensi dari sistem lama (api_absen.php) ke tabel `attendances`.
 * Dipakai oleh tombol "Sync" di halaman Master Karyawan.
 */

// URL API sistem lama (param yang dikirim: no_hp & bulan=YYYY-MM)
$config['sync_api_url'] = 'https://samsu.samuderasuri.com/api_absen.php';

// Isi jika api_absen.php memakai API_KEY (dikirim sebagai ?key=...). Kosong = tidak dikirim.
$config['sync_api_key'] = '';

// Kolom di tabel `employees` yang dicocokkan dengan parameter `no_hp` pada API lama.
$config['sync_match_column'] = 'confirm_code';

// Timeout cURL (detik)
$config['sync_connect_timeout'] = 10;
$config['sync_timeout'] = 60;

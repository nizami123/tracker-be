<?php
/**
 * File ini BUKAN config.php penuh — CodeIgniter 3 sudah menyediakan
 * application/config/config.php lengkap saat Anda download frameworknya.
 * Ini hanya daftar baris yang perlu Anda UBAH di file itu supaya API
 * ini berjalan dengan benar untuk aplikasi Android (JSON, tanpa index.php
 * di URL, tanpa proteksi CSRF berbasis form/cookie).
 */

// 1) Base URL — sesuaikan dengan lokasi folder backend Anda
$config['base_url'] = 'http://192.168.1.100/employee-tracker-backend/';
// Gunakan IP LAN komputer Anda (bukan "localhost"/127.0.0.1) supaya bisa
// diakses dari HP/emulator yang terhubung ke jaringan yang sama.

// 2) Hilangkan "index.php" dari URL (butuh .htaccess yang sudah disediakan)
$config['index_page'] = '';

// 3) API berbasis token bearer, bukan session/cookie -> matikan CSRF
$config['csrf_protection'] = FALSE;

// 4) Ganti dengan string acak Anda sendiri (dipakai fitur enkripsi CI, opsional)
$config['encryption_key'] = 'ganti-dengan-string-acak-anda-sendiri';

// 5) Permissive CORS untuk masa development (sudah di-set juga di
//    MY_Controller, baris ini opsional/cadangan)
$config['charset'] = 'UTF-8';

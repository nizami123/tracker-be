<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Perbaikan: seluruh proses tracking (waktu mulai, tiap titik, tanggal
// tracking, waktu berhenti, pergantian hari) WAJIB memakai waktu server
// GMT+7 (Asia/Jakarta), bukan timezone default PHP/hosting (yang sering
// UTC) ataupun waktu perangkat/client. Helper ini di-load oleh MY_Controller
// (API Android) dan MY_Admin_Controller (panel admin), jadi baris ini
// otomatis berjalan di awal setiap request sebelum date()/now_datetime()/
// today_date() dipakai di mana pun. Lihat juga MY_Controller /
// MY_Admin_Controller yang menyamakan session timezone MySQL ke +07:00
// supaya NOW()/CURDATE() di raw SQL konsisten dengan ini.
date_default_timezone_set('Asia/Jakarta');

if (!function_exists('now_datetime')) {
    function now_datetime(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('today_date')) {
    function today_date(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('valid_date_string')) {
    /** True hanya untuk tanggal kalender yang valid berformat persis Y-m-d (mis. 2026-09-21). */
    function valid_date_string($value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }
}

if (!function_exists('day_range')) {
    /**
     * Batas satu hari untuk query tracking: [awal, akhir) — awal = 00:00:00
     * tanggal itu, akhir = 00:00:00 hari berikutnya.
     *
     * Tracking dicari per employee_id + tanggal. Sengaja dipakai sebagai
     * rentang recorded_at (recorded_at >= awal AND recorded_at < akhir),
     * BUKAN DATE(recorded_at) = tanggal, supaya index
     * unique_tracking (employee_id, recorded_at) tetap bisa dipakai.
     */
    function day_range(string $date): array
    {
        return array(
            $date . ' 00:00:00',
            date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00',
        );
    }
}

if (!function_exists('save_base64_photo')) {
    /**
     * Decodes a base64 JPEG string from the Android app and saves it
     * under uploads/attendance_photos/. Returns the stored filename
     * (relative), or null on failure.
     */
    function save_base64_photo(string $base64, string $prefix): ?string
    {
        if (empty($base64)) return null;

        $data = base64_decode($base64, true);
        if ($data === false) return null;

        $dir = FCPATH . 'uploads/attendance_photos/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $prefix . '_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.jpg';
        $path = $dir . $filename;

        if (file_put_contents($path, $data) === false) {
            return null;
        }

        return $filename;
    }
}

if (!function_exists('save_base64_attachment')) {
    /**
     * Decodes a base64 file (image OR PDF — used by the Lampiran field
     * on Pengajuan, which lets the person either pick an existing file
     * or take a photo with the camera) and saves it under
     * uploads/request_attachments/. Only jpg/jpeg/png/pdf extensions are
     * kept from the original filename; anything else (or no filename)
     * falls back to .jpg since the camera path always sends a JPEG.
     * Returns the stored filename (relative), or null on failure.
     */
    function save_base64_attachment(string $base64, string $prefix, ?string $originalFilename = null): ?string
    {
        if (empty($base64)) return null;

        $data = base64_decode($base64, true);
        if ($data === false) return null;

        $allowedExt = array('jpg', 'jpeg', 'png', 'pdf');
        $ext = 'jpg';
        if (!empty($originalFilename)) {
            $candidate = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            if (in_array($candidate, $allowedExt, true)) {
                $ext = $candidate;
            }
        }

        $dir = FCPATH . 'uploads/request_attachments/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $prefix . '_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
        $path = $dir . $filename;

        if (file_put_contents($path, $data) === false) {
            return null;
        }

        return $filename;
    }
}

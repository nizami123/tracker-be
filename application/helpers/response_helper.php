<?php
defined('BASEPATH') OR exit('No direct script access allowed');

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

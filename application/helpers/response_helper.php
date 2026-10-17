<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Perbaikan: seluruh proses tracking (waktu mulai, tiap titik, tanggal
// tracking, waktu berhenti, pergantian hari) WAJIB memakai waktu server,
// bukan timezone default PHP/hosting (yang sering UTC) ataupun waktu
// perangkat/client. Default server = WIB (Asia/Jakarta); zona tiap
// karyawan mengikuti kantornya (lihat bagian "Zona waktu per KANTOR" di bawah). Helper ini di-load oleh MY_Controller
// (API Android) dan MY_Admin_Controller (panel admin), jadi baris ini
// otomatis berjalan di awal setiap request sebelum date()/now_datetime()/
// today_date() dipakai di mana pun. Lihat juga MY_Controller /
// MY_Admin_Controller yang menyamakan session timezone MySQL ke +07:00
// supaya NOW()/CURDATE() di raw SQL konsisten dengan ini.
date_default_timezone_set('Asia/Jakarta');

// ------------------------------------------------------------------
// Zona waktu per KANTOR (WIB / WITA / WIT)
// ------------------------------------------------------------------
// Zona waktu diatur admin di Master Kantor (kolom offices.timezone):
//   WIB  = GMT+7 (Asia/Jakarta)
//   WITA = GMT+8 (Asia/Makassar)
//   WIT  = GMT+9 (Asia/Jayapura)
// Semua karyawan/driver yang terdaftar di kantor tersebut otomatis
// memakai zona kantornya. Semua waktu yang MASUK (absen, pengajuan,
// tracking, pengiriman) disimpan sebagai jam dinding zona karyawan,
// dan semua yang TAMPIL (API, panel admin, laporan) memakai jam yang
// sama + label WIB/WITA/WIT.
//
// Kantor tanpa nilai timezone (data lama / belum diatur) dianggap WIB.
if (!defined('TZ_WIB'))  define('TZ_WIB',  'Asia/Jakarta');
if (!defined('TZ_WITA')) define('TZ_WITA', 'Asia/Makassar');
if (!defined('TZ_WIT'))  define('TZ_WIT',  'Asia/Jayapura');

if (!function_exists('tz_codes')) {
    /** Kode zona yang valid, sesuai ENUM offices.timezone. */
    function tz_codes(): array
    {
        return array('WIB', 'WITA', 'WIT');
    }
}

if (!function_exists('normalize_tz_code')) {
    /** "wita" / " WIT " / null -> 'WIB'|'WITA'|'WIT' (nilai tak dikenal = 'WIB'). */
    function normalize_tz_code($code): string
    {
        $c = strtoupper(trim((string) $code));
        return in_array($c, tz_codes(), true) ? $c : 'WIB';
    }
}

if (!function_exists('tz_code_to_php')) {
    /** 'WIB'|'WITA'|'WIT' -> nama timezone PHP. */
    function tz_code_to_php($code): string
    {
        switch (normalize_tz_code($code)) {
            case 'WITA': return TZ_WITA;
            case 'WIT':  return TZ_WIT;
            default:     return TZ_WIB;
        }
    }
}

if (!function_exists('tz_label')) {
    /** Label "WIB"/"WITA"/"WIT" dari nama timezone PHP ATAU dari kodenya. */
    function tz_label($tzOrCode): string
    {
        if ($tzOrCode === TZ_WITA) return 'WITA';
        if ($tzOrCode === TZ_WIT)  return 'WIT';
        if ($tzOrCode === TZ_WIB)  return 'WIB';
        return normalize_tz_code($tzOrCode);
    }
}

if (!function_exists('office_timezone')) {
    /**
     * Nama timezone PHP untuk satu kantor. $office = array baris tabel
     * offices (kolom `timezone`), atau array hasil join yang memuat
     * `office_timezone` / `timezone`. null / tanpa kolom = WIB.
     */
    function office_timezone($office): string
    {
        if (!is_array($office)) return TZ_WIB;
        return tz_code_to_php($office['timezone'] ?? ($office['office_timezone'] ?? null));
    }
}

if (!function_exists('office_tz_label')) {
    /** Label WIB/WITA/WIT langsung dari kode zona kantor (kolom offices.timezone) — dipakai di tampilan admin. */
    function office_tz_label($officeTimezone): string
    {
        return tz_label($officeTimezone);
    }
}

if (!function_exists('employee_timezone')) {
    /** Timezone karyawan = timezone kantor tempat ia terdaftar (employees.office_id). */
    function employee_timezone($employee): string
    {
        static $cache = array();
        $officeId = is_array($employee) ? (int) ($employee['office_id'] ?? 0) : (int) $employee;
        if ($officeId <= 0) return TZ_WIB;
        if (!isset($cache[$officeId])) {
            $CI =& get_instance();
            $row = $CI->db->select('timezone')->where('id', $officeId)->get('offices')->row_array();
            $cache[$officeId] = office_timezone($row ?: null);
        }
        return $cache[$officeId];
    }
}

if (!function_exists('office_ids_by_tz')) {
    /** ID kantor dikelompokkan per zona: array('WIB' => [..], 'WITA' => [..], 'WIT' => [..]). */
    function office_ids_by_tz(): array
    {
        static $map = null;
        if ($map === null) {
            $CI =& get_instance();
            $map = array('WIB' => array(), 'WITA' => array(), 'WIT' => array());
            foreach ($CI->db->select('id, timezone')->get('offices')->result_array() as $o) {
                $map[normalize_tz_code($o['timezone'] ?? null)][] = (int) $o['id'];
            }
        }
        return $map;
    }
}

if (!function_exists('sql_office_zone_case')) {
    /**
     * Ekspresi SQL `CASE` yang memilih nilai per zona kantor. Dipakai
     * query "hari ini" supaya tiap karyawan dibandingkan dengan tanggal
     * di zona kantornya sendiri.
     *
     *   $officeIdCol = kolom id kantor di query (mis. 'employees.office_id')
     *   $valueFor    = fn(string $phpTimezone): string  -> nilai literal untuk zona itu
     *
     * Kantor yang tidak terdaftar / zona WIB = cabang ELSE.
     */
    function sql_office_zone_case(string $officeIdCol, callable $valueFor): string
    {
        $CI =& get_instance();
        $by = office_ids_by_tz();
        $parts = array();
        foreach (array('WITA', 'WIT') as $code) {
            if (!empty($by[$code])) {
                $parts[] = 'WHEN ' . $officeIdCol . ' IN (' . implode(',', $by[$code]) . ') THEN '
                         . $CI->db->escape($valueFor(tz_code_to_php($code)));
            }
        }
        $else = $CI->db->escape($valueFor(TZ_WIB));
        return empty($parts) ? $else : 'CASE ' . implode(' ', $parts) . ' ELSE ' . $else . ' END';
    }
}

if (!function_exists('now_datetime')) {
    /** Waktu sekarang "Y-m-d H:i:s" di zona $tz (default: WIB server). */
    function now_datetime(?string $tz = null): string
    {
        $dt = new DateTime('now', new DateTimeZone($tz ?: TZ_WIB));
        return $dt->format('Y-m-d H:i:s');
    }
}

if (!function_exists('today_date')) {
    /** Tanggal hari ini "Y-m-d" di zona $tz (default: WIB server). */
    function today_date(?string $tz = null): string
    {
        $dt = new DateTime('now', new DateTimeZone($tz ?: TZ_WIB));
        return $dt->format('Y-m-d');
    }
}

if (!function_exists('normalize_client_datetime')) {
    /**
     * Waktu kiriman aplikasi ("Y-m-d H:i:s", seharusnya sudah jam dinding
     * zona karyawan). Dipakai hanya bila formatnya valid DAN selisihnya
     * dengan jam server (di zona karyawan) tidak lebih dari
     * $toleranceSeconds. Kalau tidak (format salah, jam HP ngawur, atau
     * aplikasi versi lama yang masih mengirim jam WIB untuk karyawan WIT),
     * dipakai jam server di zona karyawan — jadi data yang masuk selalu
     * konsisten dengan WIB/WIT yang benar.
     */
    function normalize_client_datetime($value, string $tz, int $toleranceSeconds = 600): string
    {
        $now = now_datetime($tz);
        if (!is_string($value)) return $now;
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone($tz));
        if ($d === false || $d->format('Y-m-d H:i:s') !== $value) return $now;
        $diff = abs($d->getTimestamp() - (new DateTime($now, new DateTimeZone($tz)))->getTimestamp());
        return $diff <= $toleranceSeconds ? $value : $now;
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

if (!function_exists('photo_storage_config')) {
    /** Ambil satu nilai dari application/config/photo_storage.php. */
    function photo_storage_config(string $key, $default = null)
    {
        $CI =& get_instance();
        $CI->config->load('photo_storage', TRUE, TRUE);
        $value = $CI->config->item($key, 'photo_storage');
        return $value === null ? $default : $value;
    }
}

if (!function_exists('attendance_photo_base_url')) {
    /** Base URL folder attendance_photos di VPS foto (selalu diakhiri "/"). */
    function attendance_photo_base_url(): string
    {
        return rtrim((string) photo_storage_config('photo_base_url', ''), '/') . '/';
    }
}

if (!function_exists('attendance_photo_url')) {
    /** URL lengkap sebuah foto di VPS foto dari nama filenya. */
    function attendance_photo_url(string $filename): string
    {
        return attendance_photo_base_url() . rawurlencode($filename);
    }
}

if (!function_exists('request_attachment_base_url')) {
    /** Base URL folder request_attachments di VPS foto (selalu diakhiri "/"). */
    function request_attachment_base_url(): string
    {
        return rtrim((string) photo_storage_config('attachment_base_url', ''), '/') . '/';
    }
}

if (!function_exists('upload_file_to_remote')) {
    /**
     * Kirim isi file (binary) ke skrip penerima di VPS foto.
     * Body = binary mentah; nama file & folder tujuan lewat header.
     * $folder: 'attendance_photos' atau 'request_attachments'.
     * Mengembalikan true jika VPS foto membalas HTTP 200/201.
     */
    function upload_file_to_remote(string $binary, string $filename, string $folder): bool
    {
        $url = (string) photo_storage_config('photo_upload_url', '');
        if ($url === '') {
            log_message('error', 'photo_storage: photo_upload_url belum diisi');
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $binary,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => (int) photo_storage_config('photo_upload_timeout', 20),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/octet-stream',
                'X-Folder: ' . $folder,
                'X-Filename: ' . $filename,
            ),
        ));
        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false || ($code !== 200 && $code !== 201)) {
            log_message('error', 'photo_storage: upload gagal (' . $folder . '/' . $filename . ') HTTP ' . $code . ' ' . $err . ' ' . substr((string) $response, 0, 200));
            return false;
        }
        return true;
    }
}

if (!function_exists('store_file_remote_or_local')) {
    /** Kirim ke VPS foto; jika gagal & fallback aktif, simpan lokal di uploads/<folder>/. */
    function store_file_remote_or_local(string $data, string $filename, string $folder): ?string
    {
        if (upload_file_to_remote($data, $filename, $folder)) {
            return $filename;
        }

        if (photo_storage_config('photo_fallback_local', true)) {
            $dir = FCPATH . 'uploads/' . $folder . '/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (file_put_contents($dir . $filename, $data) !== false) {
                log_message('error', 'photo_storage: ' . $folder . '/' . $filename . ' disimpan lokal (fallback), perlu dipindah ke VPS foto');
                return $filename;
            }
        }

        return null;
    }
}

if (!function_exists('save_base64_photo')) {
    /**
     * Decodes a base64 JPEG string from the Android app and mengirimnya ke
     * VPS foto folder attendance_photos/. Returns the stored filename
     * (relative — URL lengkap lewat attendance_photo_url()), or null on failure.
     */
    function save_base64_photo(string $base64, string $prefix): ?string
    {
        if (empty($base64)) return null;

        $data = base64_decode($base64, true);
        if ($data === false) return null;

        $filename = $prefix . '_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.jpg';

        return store_file_remote_or_local($data, $filename, 'attendance_photos');
    }
}

if (!function_exists('save_base64_attachment')) {
    /**
     * Decodes a base64 file (image OR PDF — used by the Lampiran field
     * on Pengajuan, which lets the person either pick an existing file
     * or take a photo with the camera) and saves it under
     * VPS foto folder request_attachments/. Only jpg/jpeg/png/pdf extensions are
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

        // Ekstensi disesuaikan dengan isi file sebenarnya (VPS foto memeriksa
        // kecocokan isi vs ekstensi).
        if (substr($data, 0, 3) === "\xFF\xD8\xFF") {
            $ext = ($ext === 'jpeg') ? 'jpeg' : 'jpg';
        } elseif (substr($data, 0, 8) === "\x89PNG\r\n\x1a\n") {
            $ext = 'png';
        } elseif (substr($data, 0, 5) === '%PDF-') {
            $ext = 'pdf';
        }

        $filename = $prefix . '_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;

        return store_file_remote_or_local($data, $filename, 'request_attachments');
    }
}

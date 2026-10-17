<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

/**
 * TES SEMENTARA — HAPUS FILE INI SETELAH SELESAI TES.
 *
 * Melewati jalur yang sama dengan aplikasi (CodeIgniter -> MY_Controller ->
 * helper save_base64_photo / save_base64_attachment -> hosting foto), tapi:
 *   - tanpa token login
 *   - TIDAK menulis ke database
 *
 *   GET  /api/tes_upload   -> cek konfigurasi server (tanpa upload)
 *   POST /api/tes_upload   -> body JSON:
 *        { "photo_base64": "...", "folder": "attendance_photos" | "request_attachments",
 *          "filename": "nama_asli.jpg" }
 *
 * Halaman pengujinya: tes_foto.html (di folder yang sama dengan index.php).
 */
class Tes_upload extends MY_Controller
{
    public function index()
    {
        @set_time_limit(120);

        // Kalau PHP mati (mis. kehabisan memori) tetap kirim JSON, bukan layar kosong.
        register_shutdown_function(function () {
            $e = error_get_last();
            if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode(array(
                    'success' => false,
                    'fatal'   => $e['message'],
                    'file'    => basename($e['file']),
                    'line'    => $e['line'],
                ));
            }
        });

        if ($this->input->method() !== 'post') {
            return $this->json_response(array('success' => true, 'info' => 'GET ok — route & CI jalan', 'env' => $this->env_info()), 200);
        }

        $now = microtime(true);
        $bootstrap = $now - (isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : $now);
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

        $body = $this->json_input();
        $b64 = (string) ($body['photo_base64'] ?? '');

        if ($b64 === '') {
            return $this->json_response(array(
                'success'        => false,
                'message'        => 'photo_base64 kosong / body tidak terbaca. Kalau content_length besar, kemungkinan melebihi post_max_size.',
                'content_length' => $contentLength,
                'env'            => $this->env_info(),
            ), 422);
        }

        $folder = (($body['folder'] ?? '') === 'request_attachments') ? 'request_attachments' : 'attendance_photos';

        $t1 = microtime(true);
        if ($folder === 'request_attachments') {
            $filename = save_base64_attachment($b64, 'tes', isset($body['filename']) ? (string) $body['filename'] : null);
        } else {
            $filename = save_base64_photo($b64, 'tes');
        }
        $saveSec = microtime(true) - $t1;

        $dbg = isset($GLOBALS['photo_upload_debug']) ? $GLOBALS['photo_upload_debug'] : null;

        // File ada di folder lokal = upload ke hosting gagal dan terpakai fallback.
        $localPath = $filename ? FCPATH . 'uploads/' . $folder . '/' . $filename : null;
        $isLocal = $localPath && is_file($localPath);
        if ($isLocal) {
            @unlink($localPath); // file tes, jangan dibiarkan menumpuk
        }

        $where = !$filename ? 'GAGAL' : ($isLocal ? 'LOKAL (fallback) — upload ke hosting GAGAL' : 'HOSTING');
        $url = null;
        if ($filename && !$isLocal) {
            $url = ($folder === 'request_attachments')
                ? request_attachment_base_url() . rawurlencode($filename)
                : attendance_photo_url($filename);
        }

        return $this->json_response(array(
            'success'      => (bool) $filename && !$isLocal,
            'tersimpan_di' => $where,
            'folder'       => $folder,
            'filename'     => $filename,
            'url'          => $url,
            'waktu_detik'  => array(
                'bootstrap_ci_dan_db' => round($bootstrap, 3),
                'simpan_dan_upload'   => round($saveSec, 3),
                'total_server'        => round(microtime(true) - (isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : $now), 3),
            ),
            'ukuran'       => array(
                'body_request_byte' => $contentLength,
                'foto_byte'         => (int) (strlen($b64) * 3 / 4),
            ),
            'upload_debug' => $dbg,
            'env'          => $this->env_info(),
        ), 200);
    }

    private function env_info(): array
    {
        return array(
            'php'                 => PHP_VERSION,
            'curl_aktif'          => function_exists('curl_init'),
            'photo_upload_url'    => photo_storage_config('photo_upload_url', '(KOSONG — photo_storage.php tidak terbaca)'),
            'photo_base_url'      => attendance_photo_base_url(),
            'fallback_local'      => (bool) photo_storage_config('photo_fallback_local', true),
            'timeout_konek_dtk'   => (int) photo_storage_config('photo_upload_connect_timeout', 5),
            'timeout_total_dtk'   => (int) photo_storage_config('photo_upload_timeout', 15),
            'max_execution_time'  => ini_get('max_execution_time'),
            'memory_limit'        => ini_get('memory_limit'),
            'post_max_size'       => ini_get('post_max_size'),
        );
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

/**
 * Master Karyawan — role EMPLOYEE & DRIVER only. Admin accounts
 * (SUPER_ADMIN/ADMIN_KANTOR) are managed separately on the User Admin
 * page (Admin_users.php) even though it's the same `employees` table.
 */
class Employees extends MY_Admin_Controller
{
    private $roles = array('EMPLOYEE', 'DRIVER');

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_employee_model');
        $this->load->model('Admin_office_model');
        $this->load->model('Admin_attendance_model');
    }

    public function index()
    {
        $offices = $this->Admin_office_model->getAll();
        if ($this->officeScope()) {
            $offices = array_values(array_filter($offices, function ($o) {
                return (int) $o['id'] === $this->officeScope();
            }));
        }

        $this->render('admin/employees/index', array(
            'activeMenu' => 'master_karyawan',
            'pageTitle'  => 'Master Karyawan',
            'offices'    => $offices,
        ));
    }

    public function list_data()
    {
        $rows = $this->Admin_employee_model->getAll($this->roles, $this->officeScope());
        foreach ($rows as &$r) unset($r['password']); // never leak the hash to the browser
        $this->json(array('data' => $rows));
    }

    public function detail($id)
    {
        $emp = $this->Admin_employee_model->getById((int) $id);
        if (!$emp || !in_array($emp['role'], $this->roles, true)) {
            return $this->json(array('success' => false, 'message' => 'Karyawan tidak ditemukan'), 404);
        }
        // Office-scope check for ADMIN_KANTOR — never trust the id from the URL alone.
        if ($this->officeScope() && (int) $emp['office_id'] !== $this->officeScope()) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak berwenang atas data ini'), 403);
        }
        unset($emp['password']);
        $this->json(array('success' => true, 'data' => $emp));
    }

    public function save()
    {
        $id = (int) $this->input->post('id');
        // employee_code tidak diinput user: dibuat otomatis saat karyawan baru disimpan.
        $nip = trim((string) $this->input->post('nip'));
        $name = trim((string) $this->input->post('name'));
        $email = trim((string) $this->input->post('email'));
        $phone = trim((string) $this->input->post('phone'));
        $password = (string) $this->input->post('password');
        $officeId = (int) $this->input->post('office_id');
        $position = trim((string) $this->input->post('position'));
        $role = $this->input->post('role');
        $status = $this->input->post('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';

        if (!in_array($role, $this->roles, true)) {
            return $this->json(array('success' => false, 'message' => 'Role tidak valid'), 422);
        }
        if ($name === '' || $officeId <= 0) {
            return $this->json(array('success' => false, 'message' => 'Nama dan kantor wajib diisi'), 422);
        }
        // ADMIN_KANTOR can only create/edit employees in their own office.
        if ($this->officeScope() && $officeId !== $this->officeScope()) {
            return $this->json(array('success' => false, 'message' => 'Anda hanya dapat mengelola karyawan kantor Anda sendiri'), 403);
        }
        if ($email !== '' && $this->Admin_employee_model->emailExists($email, $id ?: null)) {
            return $this->json(array('success' => false, 'message' => 'Email sudah dipakai'), 422);
        }

        $data = array(
            'nip'           => $nip ?: null,
            'name'          => $name,
            'email'         => $email ?: null,
            'phone'         => $phone ?: null,
            'office_id'     => $officeId,
            'position'      => $position ?: null,
            'role'          => $role,
            'status'        => $status,
        );

        if ($id > 0) {
            // Re-check ownership against the EXISTING row before allowing an update.
            $existing = $this->Admin_employee_model->getById($id);
            if (!$existing || ($this->officeScope() && (int) $existing['office_id'] !== $this->officeScope())) {
                return $this->json(array('success' => false, 'message' => 'Anda tidak berwenang mengubah data ini'), 403);
            }
            // employee_code sengaja tidak diubah saat edit.
            if ($password !== '') {
                $data['password'] = password_hash($password, PASSWORD_BCRYPT);
            }
            $this->Admin_employee_model->update($id, $data);
        } else {
            if ($password === '') {
                return $this->json(array('success' => false, 'message' => 'Password wajib diisi untuk karyawan baru'), 422);
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
            $data['employee_code'] = $this->generateEmployeeCode();
            $id = $this->Admin_employee_model->insert($data);
        }

        $updated = $this->Admin_employee_model->getById($id);
        unset($updated['password']);
        $this->json(array('success' => true, 'message' => 'Data karyawan tersimpan (' . $updated['employee_code'] . ')', 'data' => $updated));
    }

    public function toggle_status($id)
    {
        $id = (int) $id;
        $emp = $this->Admin_employee_model->getById($id);
        if (!$emp || ($this->officeScope() && (int) $emp['office_id'] !== $this->officeScope())) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak berwenang atas data ini'), 403);
        }
        $this->Admin_employee_model->toggleStatus($id);
        $this->json(array('success' => true));
    }

    public function delete($id)
    {
        $id = (int) $id;
        $emp = $this->Admin_employee_model->getById($id);
        if (!$emp || ($this->officeScope() && (int) $emp['office_id'] !== $this->officeScope())) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak berwenang atas data ini'), 403);
        }
        $ok = $this->Admin_employee_model->delete($id);
        if (!$ok) {
            return $this->json(array('success' => false, 'message' => 'Karyawan tidak bisa dihapus karena sudah punya riwayat absensi/pengiriman/pengajuan. Nonaktifkan saja.'), 409);
        }
        $this->json(array('success' => true, 'message' => 'Karyawan dihapus'));
    }

    /**
     * POST admin/employees/sync/{id}  (bulan=1..12, tahun=YYYY)
     *
     * Mengambil absensi sebulan dari API sistem lama (api_absen.php) untuk
     * karyawan ini, lalu memasukkannya ke tabel `attendances`.
     *  - employee_id & office_id diambil dari data karyawan yang diklik
     *    (bukan dari client).
     *  - Parameter no_hp pada API lama dicocokkan dengan kolom
     *    employees.<sync_match_column> (default: confirm_code).
     *  - Tanggal yang sudah ada TIDAK ditimpa: hanya jam masuk / pulang
     *    yang masih kosong yang dilengkapi.
     */
    public function sync($id)
    {
        if ($this->input->method() !== 'post') {
            return $this->json(array('success' => false, 'message' => 'Method tidak diizinkan'), 405);
        }

        $id = (int) $id;
        $emp = $this->Admin_employee_model->getById($id);
        if (!$emp || !in_array($emp['role'], $this->roles, true)) {
            return $this->json(array('success' => false, 'message' => 'Karyawan tidak ditemukan'), 404);
        }
        if ($this->officeScope() && (int) $emp['office_id'] !== $this->officeScope()) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak berwenang atas data ini'), 403);
        }

        $bulan = (int) $this->input->post('bulan');
        $tahun = (int) $this->input->post('tahun');
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
            return $this->json(array('success' => false, 'message' => 'Bulan atau tahun tidak valid'), 422);
        }
        $periode = sprintf('%04d-%02d', $tahun, $bulan);

        $this->config->load('sync_absen', true);
        $cfg = $this->config->item('sync_absen');
        $col = $cfg['sync_match_column'];

        if (!array_key_exists($col, $emp)) {
            return $this->json(array('success' => false, 'message' => 'Kolom employees.' . $col . ' tidak ditemukan di database. Tambahkan kolomnya atau ubah sync_match_column di config/sync_absen.php'), 422);
        }
        $noHp = trim((string) $emp[$col]);
        if ($noHp === '') {
            return $this->json(array('success' => false, 'message' => 'Karyawan ini belum memiliki ' . $col), 422);
        }

        $office = $this->db->where('id', (int) $emp['office_id'])->get('offices')->row_array();
        if (!$office) {
            return $this->json(array('success' => false, 'message' => 'Kantor karyawan tidak ditemukan'), 422);
        }

        // ---- Ambil data dari API lama ----
        $query = array('no_hp' => $noHp, 'bulan' => $periode);
        if (!empty($cfg['sync_api_key'])) $query['key'] = $cfg['sync_api_key'];

        @set_time_limit(120);
        $ch = curl_init($cfg['sync_api_url'] . '?' . http_build_query($query));
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => (int) $cfg['sync_connect_timeout'],
            CURLOPT_TIMEOUT        => (int) $cfg['sync_timeout'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $http !== 200) {
            log_message('error', 'sync_absen: gagal ambil API (HTTP ' . $http . ') ' . $err . ' ' . substr((string) $body, 0, 300));
            return $this->json(array('success' => false, 'message' => 'Gagal mengambil data dari API (HTTP ' . $http . ')' . ($err ? ': ' . $err : '')), 502);
        }
        $res = json_decode($body, true);
        if (!is_array($res) || empty($res['status']) || !isset($res['data']) || !is_array($res['data'])) {
            $msg = is_array($res) && !empty($res['pesan']) ? $res['pesan'] : 'Respons API tidak valid';
            return $this->json(array('success' => false, 'message' => $msg), 502);
        }

        // ---- Ubah ke format tabel attendances & simpan ----
        $tz = employee_timezone($emp);
        $nowStr = now_datetime($tz);
        $sum = array('inserted' => 0, 'updated' => 0, 'skipped' => 0, 'empty' => 0);

        $this->db->trans_start();
        foreach ($res['data'] as $day) {
            $tgl = isset($day['tanggal']) ? $day['tanggal'] : null;
            if (!valid_date_string($tgl)) continue;

            $in  = !empty($day['masuk'])  && is_array($day['masuk'])  ? $day['masuk']  : null;
            $out = !empty($day['pulang']) && is_array($day['pulang']) ? $day['pulang'] : null;
            if (!$in && !$out) { $sum['empty']++; continue; }

            $fields = array();
            if ($in)  $fields += $this->syncPoint('check_in', $in, $office);
            if ($out) $fields += $this->syncPoint('check_out', $out, $office);

            $existing = $this->Admin_attendance_model->getByEmployeeDate($id, $tgl);

            if (!$existing) {
                $row = array_merge(array(
                    'employee_id'     => $id,
                    'office_id'       => (int) $emp['office_id'],
                    'attendance_date' => $tgl,
                    'status'          => 'PRESENT',
                    'created_at'      => $nowStr,
                    'updated_at'      => null,
                ), $fields);
                $this->Admin_attendance_model->insertSynced($row);
                $sum['inserted']++;
                continue;
            }

            // Sudah ada: lengkapi hanya bagian yang masih kosong, jangan timpa data aplikasi.
            $upd = array();
            foreach (array('check_in', 'check_out') as $p) {
                if (isset($fields[$p . '_time']) && empty($existing[$p . '_time'])) {
                    foreach ($fields as $k => $v) {
                        if (strpos($k, $p . '_') === 0) $upd[$k] = $v;
                    }
                }
            }
            if ($upd) {
                $upd['updated_at'] = $nowStr;
                $this->Admin_attendance_model->updateSynced((int) $existing['id'], $upd);
                $sum['updated']++;
            } else {
                $sum['skipped']++;
            }
        }
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return $this->json(array('success' => false, 'message' => 'Gagal menyimpan data absensi ke database'), 500);
        }

        $this->json(array(
            'success' => true,
            'message' => 'Sync ' . $emp['name'] . ' periode ' . $periode . ' selesai',
            'summary' => $sum,
        ));
    }

    /** Ubah satu titik absen (masuk/pulang) dari API lama ke kolom check_in_* / check_out_*. */
    private function syncPoint(string $prefix, array $p, array $office): array
    {
        $lat = isset($p['lat']) && is_numeric($p['lat']) ? (float) $p['lat'] : null;
        $lng = isset($p['lng']) && is_numeric($p['lng']) ? (float) $p['lng'] : null;

        $distance = null;
        if ($lat !== null && $lng !== null) {
            $distance = round(distance_meters($lat, $lng, (float) $office['latitude'], (float) $office['longitude']), 2);
        }

        $photo = null;
        if (!empty($p['foto_absen'])) {
            $photo = substr(basename((string) $p['foto_absen']), 0, 255);
        }

        return array(
            $prefix . '_time'      => $p['jam'],
            $prefix . '_photo'     => $photo,
            $prefix . '_latitude'  => $lat,
            $prefix . '_longitude' => $lng,
            $prefix . '_accuracy'  => null, // sistem lama tidak menyimpan akurasi GPS
            $prefix . '_distance'  => $distance,
        );
    }

    /** Kode otomatis: SAMSU + (nomor urut terbesar saat ini + 1), mis. SAMSU0007. */
    private function generateEmployeeCode(): string
    {
        $prefix = 'SAMSU';
        $row = $this->db->query(
            "SELECT MAX(CAST(SUBSTRING(employee_code, ?) AS UNSIGNED)) AS max_no
               FROM employees
              WHERE employee_code LIKE ?
                AND SUBSTRING(employee_code, ?) REGEXP '^[0-9]+$'",
            array(strlen($prefix) + 1, $prefix . '%', strlen($prefix) + 1)
        )->row_array();

        $next = ((int) ($row['max_no'] ?? 0)) + 1;
        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
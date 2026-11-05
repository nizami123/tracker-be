<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

/**
 * Jadwal Shift — admin menentukan karyawan X pada tanggal Y masuk shift
 * apa (atau libur). Rotasi dilakukan dengan mengisi shift berbeda di
 * tanggal berbeda, baik per sel maupun massal memakai pola rotasi.
 *
 * Halaman ini hanya menulis tabel `employee_shift_schedules`. Tidak ada
 * satu pun query ke tabel absensi, jadi membuat/mengubah jadwal tidak
 * mempengaruhi absen yang sudah berjalan.
 *
 * Otorisasi: ADMIN_KANTOR hanya bisa mengelola kantornya sendiri
 * (officeScope()); office_id dari request tidak pernah dipercaya untuk
 * ADMIN_KANTOR. Shift yang dipasang harus milik kantor karyawan itu.
 */
class Shift_schedules extends MY_Admin_Controller
{
    const MAX_RANGE_DAYS = 62;
    const MAX_BULK_CELLS = 5000;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Shift_model');
        $this->load->model('Admin_office_model');
    }

    public function index()
    {
        $offices = $this->isSuperAdmin()
            ? $this->Admin_office_model->getAll()
            : array($this->Admin_office_model->getById((int) $this->officeScope()));
        $offices = array_values(array_filter($offices));

        $this->render('admin/shift_schedules/index', array(
            'activeMenu'  => 'jadwal_shift',
            'pageTitle'   => 'Jadwal Shift',
            'tablesReady' => $this->Shift_model->tablesReady(),
            'offices'     => $offices,
            'defaultMonth'=> date('Y-m'),
        ));
    }

    /** GET AJAX — data grid satu kantor untuk satu bulan. */
    public function grid_data()
    {
        $officeId = $this->resolveOfficeId($this->input->get('office_id'));
        if (!$officeId) return $this->json(array('success' => false, 'message' => 'Kantor tidak valid'), 422);

        $month = (string) $this->input->get('month');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return $this->json(array('success' => false, 'message' => 'Bulan tidak valid'), 422);
        }

        $first = $month . '-01';
        $daysInMonth = (int) date('t', strtotime($first));
        $last = $month . '-' . str_pad((string) $daysInMonth, 2, '0', STR_PAD_LEFT);

        $days = array();
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $month . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $days[] = array('date' => $date, 'd' => $d, 'dow' => (int) date('w', strtotime($date)));
        }

        $this->json(array(
            'success'   => true,
            'days'      => $days,
            'employees' => $this->Shift_model->getScheduleEmployees($officeId),
            'shifts'    => $this->Shift_model->getShifts($officeId),
            'schedule'  => (object) $this->Shift_model->getOfficeSchedule($officeId, $first, $last),
        ));
    }

    /**
     * POST AJAX — ubah satu sel.
     * value: '' = hapus (kembali ke default), 'OFF' = libur, angka = id shift.
     */
    public function save_cell()
    {
        if (!$this->Shift_model->tablesReady()) return $this->notReady();

        $employeeId = (int) $this->input->post('employee_id');
        $date = (string) $this->input->post('date');
        $value = trim((string) $this->input->post('value'));

        if (!valid_date_string($date)) {
            return $this->json(array('success' => false, 'message' => 'Tanggal tidak valid'), 422);
        }

        $employee = $this->getManagedEmployee($employeeId);
        if (!$employee) {
            return $this->json(array('success' => false, 'message' => 'Karyawan tidak ditemukan atau tidak dapat diakses'), 403);
        }

        if ($value === '') {
            $this->Shift_model->clearSchedule($employeeId, $date);
            return $this->json(array('success' => true, 'message' => 'Jadwal dikosongkan'));
        }

        $shiftId = null;
        if ($value !== 'OFF') {
            $shiftId = $this->validShiftIdForOffice((int) $value, (int) $employee['office_id']);
            if ($shiftId === null) {
                return $this->json(array('success' => false, 'message' => 'Shift tidak valid untuk kantor karyawan ini'), 422);
            }
        }

        $this->Shift_model->setSchedule($employeeId, $date, $shiftId, (int) $this->adminUser['id']);
        $this->json(array('success' => true, 'message' => 'Jadwal tersimpan'));
    }

    /**
     * POST AJAX — pengaturan massal / pola rotasi.
     *
     * Pola = urutan langkah [shift (atau libur) x jumlah hari] yang
     * diulang terus sepanjang rentang tanggal. `stagger` menggeser awal
     * pola tiap karyawan sebanyak (urutan karyawan x stagger) hari, supaya
     * karyawan berada di shift berbeda pada saat yang sama (rotasi).
     * Contoh: pola [Pagi x7, Siang x7, Malam x7] dengan stagger 7 ->
     * karyawan 1 mulai Pagi, karyawan 2 mulai Siang, karyawan 3 mulai Malam.
     */
    public function bulk_save()
    {
        if (!$this->Shift_model->tablesReady()) return $this->notReady();

        $officeId = $this->resolveOfficeId($this->input->post('office_id'));
        if (!$officeId) return $this->json(array('success' => false, 'message' => 'Kantor tidak valid'), 422);

        $from = (string) $this->input->post('date_from');
        $to   = (string) $this->input->post('date_to');
        if (!valid_date_string($from) || !valid_date_string($to) || $to < $from) {
            return $this->json(array('success' => false, 'message' => 'Rentang tanggal tidak valid'), 422);
        }
        $dayCount = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
        if ($dayCount > self::MAX_RANGE_DAYS) {
            return $this->json(array('success' => false, 'message' => 'Rentang tanggal maksimal ' . self::MAX_RANGE_DAYS . ' hari per proses'), 422);
        }

        // Karyawan: hanya yang benar-benar karyawan aktif kantor ini.
        $requestedIds = $this->input->post('employee_ids');
        $requestedIds = is_array($requestedIds) ? array_map('intval', $requestedIds) : array();
        $allowed = array();
        foreach ($this->Shift_model->getScheduleEmployees($officeId) as $e) $allowed[(int) $e['id']] = true;
        $employeeIds = array();
        foreach ($requestedIds as $id) {
            if (isset($allowed[$id]) && !in_array($id, $employeeIds, true)) $employeeIds[] = $id;
        }
        if (empty($employeeIds)) {
            return $this->json(array('success' => false, 'message' => 'Pilih minimal satu karyawan'), 422);
        }

        if ($dayCount * count($employeeIds) > self::MAX_BULK_CELLS) {
            return $this->json(array('success' => false, 'message' => 'Terlalu banyak data sekaligus. Kurangi jumlah karyawan atau rentang tanggal.'), 422);
        }

        // Pola.
        $patShift = $this->input->post('pattern_shift');
        $patDays  = $this->input->post('pattern_days');
        if (!is_array($patShift) || !is_array($patDays) || count($patShift) === 0 || count($patShift) !== count($patDays) || count($patShift) > 20) {
            return $this->json(array('success' => false, 'message' => 'Pola shift tidak valid'), 422);
        }
        $pattern = array();
        $cycleLen = 0;
        foreach ($patShift as $i => $sv) {
            $n = (int) $patDays[$i];
            if ($n < 1 || $n > 31) {
                return $this->json(array('success' => false, 'message' => 'Jumlah hari tiap langkah pola harus 1 - 31'), 422);
            }
            $sv = trim((string) $sv);
            if ($sv === 'OFF') {
                $shiftId = null;
            } else {
                $shiftId = $this->validShiftIdForOffice((int) $sv, $officeId);
                if ($shiftId === null) {
                    return $this->json(array('success' => false, 'message' => 'Ada shift pada pola yang tidak valid / nonaktif untuk kantor ini'), 422);
                }
            }
            $pattern[] = array('shift_id' => $shiftId, 'days' => $n);
            $cycleLen += $n;
        }

        $stagger = max(0, min(366, (int) $this->input->post('stagger')));
        $overwrite = $this->input->post('overwrite') === '1';

        $cells = array();
        foreach ($employeeIds as $idx => $employeeId) {
            for ($i = 0; $i < $dayCount; $i++) {
                $pos = ($i + $idx * $stagger) % $cycleLen;
                $shiftId = null;
                $acc = 0;
                foreach ($pattern as $step) {
                    $acc += $step['days'];
                    if ($pos < $acc) { $shiftId = $step['shift_id']; break; }
                }
                $date = date('Y-m-d', strtotime($from . ' +' . $i . ' day'));
                $cells[] = array($employeeId, $date, $shiftId);
            }
        }

        $written = $this->Shift_model->bulkSetSchedule($cells, $overwrite, (int) $this->adminUser['id']);

        $this->json(array(
            'success' => true,
            'message' => $written . ' jadwal tersimpan untuk ' . count($employeeIds) . ' karyawan (' . $from . ' s/d ' . $to . ')',
            'written' => $written,
        ));
    }

    // ------------------------------------------------------------

    private function notReady()
    {
        return $this->json(array('success' => false, 'message' => 'Tabel shift belum dibuat. Jalankan SQL migrasi database/migrations/2026_11_05_shift_management.sql terlebih dahulu.'), 500);
    }

    /**
     * ADMIN_KANTOR -> selalu kantornya sendiri (input diabaikan).
     * SUPER_ADMIN  -> kantor dari input, harus ada.
     */
    private function resolveOfficeId($input): int
    {
        $scope = $this->officeScope();
        if ($scope) return $scope;
        $id = (int) $input;
        return ($id > 0 && $this->Admin_office_model->getById($id)) ? $id : 0;
    }

    /** Karyawan (EMPLOYEE/DRIVER, aktif) yang boleh dikelola admin ini, atau null. */
    private function getManagedEmployee(int $employeeId)
    {
        if ($employeeId <= 0) return null;
        $emp = $this->db->select('id, office_id, role, status')->where('id', $employeeId)->get('employees')->row_array();
        if (!$emp || !in_array($emp['role'], array('EMPLOYEE', 'DRIVER'), true) || $emp['status'] !== 'ACTIVE') return null;
        $scope = $this->officeScope();
        if ($scope !== null && (int) $emp['office_id'] !== $scope) return null;
        return $emp;
    }

    /** Mengembalikan $shiftId bila shift ACTIVE dan milik $officeId, selain itu null. */
    private function validShiftIdForOffice(int $shiftId, int $officeId): ?int
    {
        if ($shiftId <= 0) return null;
        $s = $this->Shift_model->getShiftById($shiftId);
        if (!$s || $s['status'] !== 'ACTIVE' || (int) $s['office_id'] !== $officeId) return null;
        return $shiftId;
    }
}

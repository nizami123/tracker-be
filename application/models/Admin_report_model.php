<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * NOTE on "Izin" vs "Cuti": the actual schema only has ONE request
 * type for both — `attendance_requests.type = 'LEAVE'` — there is no
 * separate CUTI/IZIN distinction in the database (see
 * ADMIN_README.md). This report merges them into a single "Izin/Cuti"
 * figure rather than inventing a column/value that doesn't exist.
 *
 * NOTE on jam kerja / "Tidak Hadir" / "Terlambat": jam kerja per tanggal
 * ditentukan oleh Shift_model::resolveDay() — jadwal shift karyawan bila
 * ada, kalau tidak ada jadwal memakai jam normal (Senin-Jumat 08:00-16:30,
 * Sabtu 08:00-12:30, Minggu & hari libur master = libur). "Terlambat" =
 * check-in lewat jam mulai hari itu (+1 menit toleransi), "Tidak Hadir" =
 * hari kerja yang sudah lewat tanpa absensi / Izin-Cuti / Dinas Luar
 * (sama persis dengan hitungan "Alfa" di Kalender Absensi).
 */
class Admin_report_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Holiday_model');
        $this->load->model('Shift_model');
    }

    // ---------------- Laporan Absensi ----------------

    /**
     * Baris + ringkasan Laporan Absensi dengan jam kerja per tanggal
     * (shift bila dijadwalkan, jam normal bila tidak).
     *
     * Tiap baris ditambah: schedule_label (mis. "P 07:00-15:00" / "Normal 08:00-12:30"),
     * is_late dan status_label (Hadir / Terlambat / Tidak Hadir).
     */
    public function attendanceReport(string $dateFrom, string $dateTo, ?int $officeId, ?int $employeeId): array
    {
        $rows = $this->attendanceRows($dateFrom, $dateTo, $officeId, $employeeId);
        $employees = $this->calendarEmployees($officeId, $employeeId);

        $ids = array();
        foreach ($employees as $e) $ids[] = (int) $e['id'];
        foreach ($rows as $r) $ids[] = (int) $r['employee_id'];
        $schedules = $this->Shift_model->getSchedulesForEmployees($ids, $dateFrom, $dateTo);

        $holidayCache = array();
        $holidaysFor = function (int $oid) use (&$holidayCache, $dateFrom, $dateTo) {
            if (!isset($holidayCache[$oid])) {
                $holidayCache[$oid] = $this->Holiday_model->getMapInRange($dateFrom, $dateTo, $oid);
            }
            return $holidayCache[$oid];
        };

        $terlambat = 0;
        foreach ($rows as &$r) {
            $date = $r['attendance_date'];
            $empId = (int) $r['employee_id'];
            $holidays = $holidaysFor((int) $r['employee_office_id']);
            $day = Shift_model::resolveDay($date, $schedules[$empId][$date] ?? null, isset($holidays[$date]));

            $late = Shift_model::isLate($date, $day, $r['check_in_time']) || $r['status'] === 'LATE';
            $r['schedule_label'] = Shift_model::dayLabel($day);
            $r['is_late'] = $late;
            if ($r['status'] === 'ABSENT') {
                $r['status_label'] = 'Tidak Hadir';
            } else {
                $r['status_label'] = $late ? 'Terlambat' : 'Hadir';
                if ($late) $terlambat++;
            }
        }
        unset($r);

        $today = today_date();
        $tidakHadir = 0;
        foreach ($employees as $emp) {
            $cells = $this->employeeDayStatuses($emp, $dateFrom, $dateTo, $today, $schedules[(int) $emp['id']] ?? array());
            foreach ($cells as $c) {
                if ($c['type'] === 'alfa') $tidakHadir++;
            }
        }

        $q = $this->db->where('type', 'LEAVE')->where('status', 'APPROVED')
            ->where('start_date <=', $dateTo)->where('end_date >=', $dateFrom);
        if ($officeId) $q->where('office_id', $officeId);
        if ($employeeId) $q->where('employee_id', $employeeId);
        $izinCuti = (int) $q->count_all_results('attendance_requests');

        return array(
            'rows'    => $rows,
            'summary' => array(
                'hadir'          => count($rows),
                'terlambat'      => $terlambat,
                'tidak_hadir'    => $tidakHadir,
                'izin_cuti'      => $izinCuti,
                'total_karyawan' => count($employees),
            ),
        );
    }

    public function attendanceRows(string $dateFrom, string $dateTo, ?int $officeId, ?int $employeeId): array
    {
        $this->db->select("attendances.*, employees.name as employee_name, employees.employee_code, employees.office_id as employee_office_id, offices.name as office_name, offices.timezone as office_timezone")
            ->from('attendances')
            ->join('employees', 'employees.id = attendances.employee_id')
            ->join('offices', 'offices.id = attendances.office_id')
            ->where('attendances.attendance_date >=', $dateFrom)
            ->where('attendances.attendance_date <=', $dateTo)
            ->order_by('attendances.attendance_date', 'ASC');
        if ($officeId) $this->db->where('attendances.office_id', $officeId);
        if ($employeeId) $this->db->where('attendances.employee_id', $employeeId);
        return $this->db->get()->result_array();
    }

    // ---------------- Kalender Absensi (Export PDF format kalender) ----------------

    /**
     * Status tiap tanggal untuk 1 pegawai (dipakai Kalender Absensi & hitungan
     * "Tidak Hadir" di Laporan Absensi, supaya angkanya selalu sama).
     *
     * $scheduleMap = jadwal pegawai ini: array[Y-m-d] => array('off'=>bool,'shift'=>array|null)
     * (dari Shift_model::getSchedulesForEmployees()); kosong = tanpa jadwal -> jam normal.
     *
     * Prioritas status per tanggal (paling kuat duluan): data absensi NYATA >
     * Izin/Cuti disetujui > Dinas Luar disetujui > bukan hari kerja (Libur
     * jadwal / Minggu / hari libur master) > "alfa" (hari kerja yang sudah
     * lewat tanpa data apa pun) > belum terjadi (dibiarkan kosong).
     * Telat = check-in lewat jam mulai SHIFT hari itu (atau jam normal 08:00).
     *
     * Hasil: array[Y-m-d] => array(date, dow, is_sunday, is_holiday, holiday_name,
     *   day (hasil Shift_model::resolveDay), shift_code, type, time_label)
     * type: none | hadir | telat | izin | dinas_luar | libur | alfa
     */
    public function employeeDayStatuses(array $emp, string $dateFrom, string $dateTo, string $today, array $scheduleMap): array
    {
        $empId = (int) $emp['id'];
        $attendanceMap = $this->calendarAttendanceMap($empId, $dateFrom, $dateTo);
        $leaveMap = $this->calendarLeaveMap($empId, $dateFrom, $dateTo);
        $outsideMap = $this->calendarOutsideOfficeMap($empId, $dateFrom, $dateTo);
        $overtimeMap = $this->calendarOvertimeMap($empId, $dateFrom, $dateTo);
        $holidayMap = $this->Holiday_model->getMapInRange($dateFrom, $dateTo, (int) $emp['office_id']);

        $cells = array();
        $cursor = $dateFrom;
        while ($cursor <= $dateTo) {
            $dow = (int) date('w', strtotime($cursor)); // 0=Minggu .. 6=Sabtu
            $isHoliday = isset($holidayMap[$cursor]);
            $day = Shift_model::resolveDay($cursor, $scheduleMap[$cursor] ?? null, $isHoliday);

            $cell = array(
                'date'         => (int) date('j', strtotime($cursor)),
                'dow'          => $dow,
                'is_sunday'    => ($dow === 0),
                // Shift yang dijadwalkan admin pada tanggal merah = hari kerja, jangan diwarnai libur.
                'is_holiday'   => $isHoliday && $day['source'] !== 'SHIFT',
                'holiday_name' => $holidayMap[$cursor] ?? null,
                'day'          => $day,
                'shift_code'   => $day['source'] === 'SHIFT' ? ($day['code'] ?: $day['name']) : null,
                'type'         => 'none',
                'time_label'   => null,
                // Lembur yang sudah di-ACC admin: keterangan tambahan (tidak mengganti status hadir/libur/dll).
                'overtime_label'   => isset($overtimeMap[$cursor]) ? $overtimeMap[$cursor]['label'] : null,
                'overtime_minutes' => isset($overtimeMap[$cursor]) ? $overtimeMap[$cursor]['minutes'] : 0,
            );

            if (isset($attendanceMap[$cursor])) {
                $a = $attendanceMap[$cursor];
                $in = $a['check_in_time'] ? substr($a['check_in_time'], 11, 5) : null;
                $out = $a['check_out_time'] ? substr($a['check_out_time'], 11, 5) : null;
                if ($in && $out) $cell['time_label'] = $in . '-' . $out;
                elseif ($in) $cell['time_label'] = $in . '- -';
                elseif ($out) $cell['time_label'] = '- -' . $out;
                else $cell['time_label'] = '-';

                $cell['type'] = Shift_model::isLate($cursor, $day, $a['check_in_time'] ?: null) ? 'telat' : 'hadir';
            } elseif (isset($leaveMap[$cursor])) {
                $cell['type'] = 'izin';
            } elseif (isset($outsideMap[$cursor])) {
                $cell['type'] = 'dinas_luar';
            } elseif (!$day['is_workday']) {
                $cell['type'] = 'libur';
            } elseif ($cursor < $today) {
                $cell['type'] = 'alfa';
            }

            $cells[$cursor] = $cell;
            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }
        return $cells;
    }

    /** Ringkasan 1 pegawai dari hasil employeeDayStatuses(). */
    public function summarizeDays(array $cells): array
    {
        $s = array('hari_kerja' => 0, 'hadir' => 0, 'telat' => 0, 'izin_cuti' => 0, 'dinas_luar' => 0, 'alfa' => 0, 'lembur' => 0, 'lembur_menit' => 0);
        foreach ($cells as $c) {
            if ($c['day']['is_workday']) $s['hari_kerja']++;
            if (!empty($c['overtime_label'])) {
                $s['lembur']++;
                $s['lembur_menit'] += (int) $c['overtime_minutes'];
            }
            switch ($c['type']) {
                case 'hadir':      $s['hadir']++; break;
                case 'telat':      $s['telat']++; break;
                case 'izin':       $s['izin_cuti']++; break;
                case 'dinas_luar': $s['dinas_luar']++; break;
                case 'alfa':       $s['alfa']++; break;
            }
        }
        return $s;
    }

    /**
     * Daftar pegawai untuk digenerate 1 halaman kalender per pegawai.
     * Jika $employeeId diisi, hanya kembalikan pegawai tersebut
     * (terlepas dari status aktif/nonaktif — memilih 1 pegawai secara
     * eksplisit selalu ditampilkan). Kalau tidak, hanya pegawai AKTIF,
     * opsional difilter per kantor ($officeId null = semua kantor).
     */
    public function calendarEmployees(?int $officeId, ?int $employeeId): array
    {
        $this->db->select('employees.id, employees.name, employees.employee_code, employees.nip, employees.position, employees.office_id, offices.name as office_name, offices.timezone as office_timezone')
            ->from('employees')
            ->join('offices', 'offices.id = employees.office_id')
            ->where_in('employees.role', array('EMPLOYEE', 'DRIVER'))
            ->order_by('offices.name', 'ASC')
            ->order_by('employees.name', 'ASC');

        if ($employeeId) {
            $this->db->where('employees.id', $employeeId);
        } else {
            $this->db->where('employees.status', 'ACTIVE');
            if ($officeId) $this->db->where('employees.office_id', $officeId);
        }

        return $this->db->get()->result_array();
    }

    /** attendance_date (Y-m-d) => baris attendances, untuk 1 pegawai & 1 rentang tanggal. */
    public function calendarAttendanceMap(int $employeeId, string $dateFrom, string $dateTo): array
    {
        $rows = $this->db->select('attendance_date, check_in_time, check_out_time, status')
            ->where('employee_id', $employeeId)
            ->where('attendance_date >=', $dateFrom)
            ->where('attendance_date <=', $dateTo)
            ->get('attendances')->result_array();

        $map = array();
        foreach ($rows as $r) $map[$r['attendance_date']] = $r;
        return $map;
    }

    /**
     * tanggal (Y-m-d) => true untuk setiap tanggal yang tercakup oleh
     * pengajuan LEAVE (Izin/Cuti — schema tidak membedakan keduanya,
     * lihat catatan di atas) yang sudah APPROVED dan beririsan dengan
     * rentang tanggal kalender.
     */
    public function calendarLeaveMap(int $employeeId, string $dateFrom, string $dateTo): array
    {
        $rows = $this->db->select('start_date, end_date')
            ->where('employee_id', $employeeId)
            ->where('type', 'LEAVE')->where('status', 'APPROVED')
            ->where('start_date <=', $dateTo)->where('end_date >=', $dateFrom)
            ->get('attendance_requests')->result_array();

        $map = array();
        foreach ($rows as $r) {
            $cursor = max($r['start_date'], $dateFrom);
            $end = min($r['end_date'], $dateTo);
            while ($cursor <= $end) {
                $map[$cursor] = true;
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }
        }
        return $map;
    }

    /** tanggal (Y-m-d) => true untuk pengajuan OUTSIDE_OFFICE (Dinas Luar) yang APPROVED pada tanggal tersebut. */
    public function calendarOutsideOfficeMap(int $employeeId, string $dateFrom, string $dateTo): array
    {
        $rows = $this->db->select('date')
            ->where('employee_id', $employeeId)
            ->where('type', 'OUTSIDE_OFFICE')->where('status', 'APPROVED')
            ->where('date >=', $dateFrom)->where('date <=', $dateTo)
            ->get('attendance_requests')->result_array();

        $map = array();
        foreach ($rows as $r) $map[$r['date']] = true;
        return $map;
    }

    /**
     * tanggal (Y-m-d) => array('label' => "17:00-20:00" (beberapa pengajuan digabung koma), 'minutes' => total menit)
     * untuk pengajuan OVERTIME (Lembur) yang sudah APPROVED admin. Pengajuan PENDING/REJECTED tidak ikut.
     */
    public function calendarOvertimeMap(int $employeeId, string $dateFrom, string $dateTo): array
    {
        $rows = $this->db->select('date, time, end_time')
            ->where('employee_id', $employeeId)
            ->where('type', 'OVERTIME')->where('status', 'APPROVED')
            ->where('date >=', $dateFrom)->where('date <=', $dateTo)
            ->order_by('time', 'ASC')
            ->get('attendance_requests')->result_array();

        $map = array();
        foreach ($rows as $r) {
            $start = substr((string) $r['time'], 0, 5);
            $end = substr((string) $r['end_time'], 0, 5);
            $minutes = 0;
            if ($start !== '' && $end !== '') {
                $minutes = max(0, (strtotime('1970-01-01 ' . $end) - strtotime('1970-01-01 ' . $start)) / 60);
            }
            $d = $r['date'];
            if (!isset($map[$d])) $map[$d] = array('label' => '', 'minutes' => 0);
            $map[$d]['label'] .= ($map[$d]['label'] === '' ? '' : ', ') . $start . '-' . $end;
            $map[$d]['minutes'] += (int) $minutes;
        }
        return $map;
    }

    // ---------------- Laporan Pengajuan ----------------

    public function requestRows(array $filters, ?int $officeId): array
    {
        $this->db->select("attendance_requests.*, employees.name as employee_name, employees.employee_code, offices.name as office_name, offices.timezone as office_timezone")
            ->from('attendance_requests')
            ->join('employees', 'employees.id = attendance_requests.employee_id')
            ->join('offices', 'offices.id = attendance_requests.office_id')
            ->order_by('attendance_requests.created_at', 'ASC');

        if (!empty($filters['date_from'])) $this->db->where('DATE(attendance_requests.created_at) >=', $filters['date_from']);
        if (!empty($filters['date_to'])) $this->db->where('DATE(attendance_requests.created_at) <=', $filters['date_to']);
        if (!empty($filters['employee_id'])) $this->db->where('attendance_requests.employee_id', (int) $filters['employee_id']);
        if (!empty($filters['type'])) $this->db->where('attendance_requests.type', $filters['type']);
        if (!empty($filters['status'])) $this->db->where('attendance_requests.status', $filters['status']);
        if ($officeId) $this->db->where('attendance_requests.office_id', $officeId);

        return $this->db->get()->result_array();
    }

    public function requestSummary(array $rows): array
    {
        $summary = array('total' => count($rows), 'PENDING' => 0, 'APPROVED' => 0, 'REJECTED' => 0);
        foreach ($rows as $r) {
            if (isset($summary[$r['status']])) $summary[$r['status']]++;
        }
        return $summary;
    }

    // ---------------- Laporan Pengiriman ----------------

    public function deliveryRows(array $filters, ?int $officeId): array
    {
        $this->db->select("
                vehicle_deliveries.*,
                employees.name as driver_name, employees.employee_code as driver_code,
                dest.name as destination_office_name,
                driver_office.name as driver_office_name,
                driver_office.code as driver_office_code, driver_office.timezone as driver_office_timezone
            ")
            ->from('vehicle_deliveries')
            ->join('employees', 'employees.id = vehicle_deliveries.driver_id')
            ->join('offices as dest', 'dest.id = vehicle_deliveries.destination_office_id', 'left')
            ->join('offices as driver_office', 'driver_office.id = employees.office_id', 'left')
            ->order_by('vehicle_deliveries.created_at', 'ASC');

        if (!empty($filters['date_from'])) $this->db->where('DATE(vehicle_deliveries.created_at) >=', $filters['date_from']);
        if (!empty($filters['date_to'])) $this->db->where('DATE(vehicle_deliveries.created_at) <=', $filters['date_to']);
        if (!empty($filters['driver_id'])) $this->db->where('vehicle_deliveries.driver_id', (int) $filters['driver_id']);
        if (!empty($filters['destination_office_id'])) $this->db->where('vehicle_deliveries.destination_office_id', (int) $filters['destination_office_id']);
        if (!empty($filters['status'])) $this->db->where('vehicle_deliveries.status', $filters['status']);
        if ($officeId) $this->db->where('employees.office_id', $officeId);

        return $this->db->get()->result_array();
    }

    /** No CANCELLED status exists in the schema — see ADMIN_README.md. Always 0, kept in the shape for spec parity. */
    public function deliverySummary(array $rows): array
    {
        $summary = array('total' => count($rows), 'IN_PROGRESS' => 0, 'ARRIVED' => 0, 'COMPLETED' => 0, 'CANCELLED' => 0);
        foreach ($rows as $r) {
            if (isset($summary[$r['status']])) $summary[$r['status']]++;
        }
        return $summary;
    }
}

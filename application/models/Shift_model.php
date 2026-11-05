<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Manajemen Shift — dipakai oleh:
 *  - api/Shifts.php (Android: jadwal shift milik karyawan yang login)
 *  - admin/Shifts.php (Master Shift per kantor)
 *  - admin/Shift_schedules.php (penjadwalan shift per karyawan per tanggal)
 *
 * Model ini HANYA membaca/menulis tabel `shifts` dan
 * `employee_shift_schedules`. Tabel absensi tidak pernah disentuh,
 * jadi penjadwalan shift tidak bisa mempengaruhi absen yang berjalan.
 *
 * Aturan:
 *  - Satu shift milik satu kantor (shifts.office_id).
 *  - Jadwal = 1 baris per karyawan per tanggal. shift_id NULL = LIBUR.
 *  - Tidak ada baris untuk tanggal itu = belum dijadwalkan -> pemanggil
 *    memakai jam kerja default (perilaku lama).
 *  - Jadwal hanya "berlaku" bila shift-nya ACTIVE dan office_id shift
 *    sama dengan kantor karyawan saat ini (kalau karyawan pindah kantor,
 *    jadwal lama otomatis diabaikan).
 */
class Shift_model extends CI_Model
{
    /** Jam kerja default (perilaku lama aplikasi) bila karyawan belum dijadwalkan. */
    const DEFAULT_START = '08:00';
    const DEFAULT_END   = '16:30';

    /** Tabel sudah dimigrasi? (aman dipanggil API walau SQL migrasi belum dijalankan) */
    public function tablesReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            $ready = $this->db->table_exists('shifts') && $this->db->table_exists('employee_shift_schedules');
        }
        return $ready;
    }

    // ------------------------------------------------------------
    // Master shift
    // ------------------------------------------------------------

    /** $officeId null = semua kantor (SUPER_ADMIN). */
    public function getShifts(?int $officeId, bool $activeOnly = false): array
    {
        $this->db->select('shifts.*, offices.name AS office_name')
            ->from('shifts')
            ->join('offices', 'offices.id = shifts.office_id', 'left')
            ->order_by('offices.name', 'ASC')
            ->order_by('shifts.start_time', 'ASC');
        if ($officeId) $this->db->where('shifts.office_id', $officeId);
        if ($activeOnly) $this->db->where('shifts.status', 'ACTIVE');
        $rows = $this->db->get()->result_array();
        foreach ($rows as &$r) {
            $r['crosses_midnight'] = self::crossesMidnight($r['start_time'], $r['end_time']);
            $r['start_time'] = substr($r['start_time'], 0, 5);
            $r['end_time']   = substr($r['end_time'], 0, 5);
        }
        unset($r);
        return $rows;
    }

    public function getShiftById(int $id)
    {
        $row = $this->db->select('shifts.*, offices.name AS office_name')
            ->from('shifts')
            ->join('offices', 'offices.id = shifts.office_id', 'left')
            ->where('shifts.id', $id)
            ->get()->row_array();
        if (!$row) return null;
        $row['crosses_midnight'] = self::crossesMidnight($row['start_time'], $row['end_time']);
        $row['start_time'] = substr($row['start_time'], 0, 5);
        $row['end_time']   = substr($row['end_time'], 0, 5);
        return $row;
    }

    public function codeExists(int $officeId, string $code, ?int $exceptId = null): bool
    {
        $q = $this->db->where('office_id', $officeId)->where('code', $code);
        if ($exceptId) $q->where('id !=', $exceptId);
        return $q->count_all_results('shifts') > 0;
    }

    public function insertShift(array $data): int
    {
        $data['created_at'] = now_datetime();
        $this->db->insert('shifts', $data);
        return (int) $this->db->insert_id();
    }

    public function updateShift(int $id, array $data): void
    {
        $data['updated_at'] = now_datetime();
        $this->db->where('id', $id)->update('shifts', $data);
    }

    public function shiftHasSchedules(int $id): bool
    {
        return $this->db->where('shift_id', $id)->count_all_results('employee_shift_schedules') > 0;
    }

    public function deleteShift(int $id): void
    {
        $this->db->where('id', $id)->delete('shifts');
    }

    /** end <= start berarti shift melewati tengah malam (mis. 22:00 - 06:00). */
    public static function crossesMidnight(string $start, string $end): bool
    {
        return substr($end, 0, 5) <= substr($start, 0, 5);
    }

    // ------------------------------------------------------------
    // Jadwal (admin)
    // ------------------------------------------------------------

    /** Karyawan aktif (EMPLOYEE/DRIVER) satu kantor, untuk kolom baris jadwal. */
    public function getScheduleEmployees(int $officeId): array
    {
        return $this->db->select('id, employee_code, name, position, role')
            ->where('office_id', $officeId)
            ->where_in('role', array('EMPLOYEE', 'DRIVER'))
            ->where('status', 'ACTIVE')
            ->order_by('name', 'ASC')
            ->get('employees')->result_array();
    }

    /**
     * Jadwal satu kantor pada rentang tanggal:
     * array[employee_id][Y-m-d] = array('shift_id' => int|null)
     * Hanya baris yang valid untuk kantor ini (shift NULL/libur, atau shift milik kantor ini).
     */
    public function getOfficeSchedule(int $officeId, string $from, string $to): array
    {
        $rows = $this->db->select('s.employee_id, s.schedule_date, s.shift_id')
            ->from('employee_shift_schedules s')
            ->join('employees e', 'e.id = s.employee_id')
            ->join('shifts sh', 'sh.id = s.shift_id', 'left')
            ->where('e.office_id', $officeId)
            ->where('s.schedule_date >=', $from)
            ->where('s.schedule_date <=', $to)
            ->group_start()
                ->where('s.shift_id IS NULL', null, false)
                ->or_where('sh.office_id', $officeId)
            ->group_end()
            ->get()->result_array();

        $map = array();
        foreach ($rows as $r) {
            $map[(int) $r['employee_id']][$r['schedule_date']] = array(
                'shift_id' => $r['shift_id'] === null ? null : (int) $r['shift_id'],
            );
        }
        return $map;
    }

    /**
     * Isi / ubah / hapus satu sel jadwal.
     * $shiftId: int = shift, null = LIBUR. Untuk menghapus pakai clearSchedule().
     */
    public function setSchedule(int $employeeId, string $date, ?int $shiftId, ?int $adminId): void
    {
        $now = now_datetime();
        $this->db->query(
            'INSERT INTO employee_shift_schedules (employee_id, schedule_date, shift_id, created_by, created_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id), created_by = VALUES(created_by), updated_at = ?',
            array($employeeId, $date, $shiftId, $adminId, $now, $now)
        );
    }

    public function clearSchedule(int $employeeId, string $date): void
    {
        $this->db->where('employee_id', $employeeId)->where('schedule_date', $date)->delete('employee_shift_schedules');
    }

    /**
     * Isi banyak sel sekaligus dalam satu transaksi.
     * $cells = list of array(employee_id, date, shift_id|null).
     * $overwrite=false -> sel yang sudah terisi dibiarkan.
     * Mengembalikan jumlah sel yang ditulis.
     */
    public function bulkSetSchedule(array $cells, bool $overwrite, ?int $adminId): int
    {
        if (empty($cells)) return 0;
        $written = 0;
        $now = now_datetime();

        $this->db->trans_start();
        foreach ($cells as $c) {
            list($employeeId, $date, $shiftId) = $c;
            if ($overwrite) {
                $this->db->query(
                    'INSERT INTO employee_shift_schedules (employee_id, schedule_date, shift_id, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id), created_by = VALUES(created_by), updated_at = ?',
                    array($employeeId, $date, $shiftId, $adminId, $now, $now)
                );
            } else {
                $this->db->query(
                    'INSERT IGNORE INTO employee_shift_schedules (employee_id, schedule_date, shift_id, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?)',
                    array($employeeId, $date, $shiftId, $adminId, $now)
                );
            }
            if ($this->db->affected_rows() > 0) $written++;
        }
        $this->db->trans_complete();

        return $this->db->trans_status() === false ? 0 : $written;
    }

    // ------------------------------------------------------------
    // Jadwal (Android / API)
    // ------------------------------------------------------------

    /**
     * Jadwal valid satu karyawan untuk rentang tanggal.
     * Hasil: array[Y-m-d] = array('off' => bool, 'shift' => array|null)
     * Tanggal yang tidak ada di hasil = belum dijadwalkan (pakai default).
     */
    public function getEmployeeSchedule(int $employeeId, int $officeId, string $from, string $to): array
    {
        $rows = $this->db->select('s.schedule_date, s.shift_id, sh.id AS sh_id, sh.code, sh.name, sh.start_time, sh.end_time, sh.status, sh.office_id AS sh_office_id')
            ->from('employee_shift_schedules s')
            ->join('shifts sh', 'sh.id = s.shift_id', 'left')
            ->where('s.employee_id', $employeeId)
            ->where('s.schedule_date >=', $from)
            ->where('s.schedule_date <=', $to)
            ->get()->result_array();

        $out = array();
        foreach ($rows as $r) {
            if ($r['shift_id'] === null) {
                $out[$r['schedule_date']] = array('off' => true, 'shift' => null);
                continue;
            }
            // Shift nonaktif / milik kantor lain (karyawan sudah pindah) -> abaikan, jatuh ke default.
            if ($r['sh_id'] === null || $r['status'] !== 'ACTIVE' || (int) $r['sh_office_id'] !== $officeId) {
                continue;
            }
            $out[$r['schedule_date']] = array('off' => false, 'shift' => self::publicShift($r));
        }
        return $out;
    }

    /** Bentuk shift yang dikirim ke Android. $r punya kolom sh_id/code/name/start_time/end_time. */
    public static function publicShift(array $r): array
    {
        return array(
            'id'               => (int) $r['sh_id'],
            'code'             => $r['code'],
            'name'             => $r['name'],
            'start_time'       => substr($r['start_time'], 0, 5),
            'end_time'         => substr($r['end_time'], 0, 5),
            'crosses_midnight' => self::crossesMidnight($r['start_time'], $r['end_time']),
        );
    }
}

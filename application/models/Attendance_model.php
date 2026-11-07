<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Attendance_model extends CI_Model
{
    /** $tz = zona waktu karyawan (WIB/WITA/WIT) — menentukan "hari ini" miliknya. */
    public function getToday(int $employeeId, ?string $tz = null)
    {
        return $this->db->where('employee_id', $employeeId)
            ->where('attendance_date', today_date($tz))
            ->get('attendances')
            ->row_array();
    }

    /**
     * Riwayat absensi + keterangan lembur (`overtime_label`, mis. "17:00-20:00")
     * dari pengajuan Lembur yang sudah APPROVED admin pada tanggal itu; null bila tidak ada.
     */
    public function getHistory(int $employeeId, int $limit = 90): array
    {
        $this->db->select('attendances.*', false)
            ->select("(SELECT GROUP_CONCAT(CONCAT(TIME_FORMAT(r.time, '%H:%i'), '-', TIME_FORMAT(r.end_time, '%H:%i')) ORDER BY r.time SEPARATOR ', ') FROM attendance_requests r WHERE r.type = 'OVERTIME' AND r.status = 'APPROVED' AND r.employee_id = attendances.employee_id AND r.date = attendances.attendance_date) as overtime_label", false)
            ->from('attendances')
            ->where('attendances.employee_id', $employeeId)
            ->order_by('attendances.attendance_date', 'DESC')
            ->limit($limit);
        return $this->db->get()->result_array();
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('attendances')->row_array();
    }

    /**
     * Deliberately scoped to TODAY's date only, not "any still-open
     * session" — a forgotten check-out from a previous day must NOT
     * block a fresh check-in today. Tracking for a forgotten day is
     * expected to have already stopped on its own at midnight
     * (client-side, see TrackingForegroundService), and that
     * attendance row's check_out_time is simply left empty forever.
     */
    public function alreadyCheckedInToday(int $employeeId, ?string $tz = null): bool
    {
        return (bool) $this->getToday($employeeId, $tz);
    }

    public function insertCheckIn(array $data): int
    {
        $this->db->insert('attendances', $data);
        return (int) $this->db->insert_id();
    }

    public function updateCheckOut(int $attendanceId, array $data): void
    {
        $this->db->where('id', $attendanceId)->update('attendances', $data);
    }
}

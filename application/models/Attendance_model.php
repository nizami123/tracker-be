<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Attendance_model extends CI_Model
{
    public function getToday(int $employeeId)
    {
        return $this->db->where('employee_id', $employeeId)
            ->where('attendance_date', today_date())
            ->get('attendances')
            ->row_array();
    }

    public function getHistory(int $employeeId, int $limit = 90): array
    {
        return $this->db->where('employee_id', $employeeId)
            ->order_by('attendance_date', 'DESC')
            ->limit($limit)
            ->get('attendances')
            ->result_array();
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('attendances')->row_array();
    }

    public function alreadyCheckedInToday(int $employeeId): bool
    {
        return (bool) $this->getToday($employeeId);
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

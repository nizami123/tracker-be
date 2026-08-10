<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tracking_model extends CI_Model
{
    public function insertBatch(array $rows): void
    {
        if (empty($rows)) return;
        // insert_batch skips rows that violate the unique(attendance_id,
        // recorded_at) constraint only if we catch duplicate errors per
        // row; CI3's insert_batch does one multi-row INSERT, so instead
        // we insert one by one with INSERT IGNORE semantics to tolerate
        // a point being re-sent after a flaky network ack.
        foreach ($rows as $row) {
            $this->db->query(
                "INSERT IGNORE INTO attendance_tracking
                    (attendance_id, employee_id, office_id, latitude, longitude,
                     accuracy, speed, bearing, battery_level, recorded_at, server_received_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array(
                    $row['attendance_id'], $row['employee_id'], $row['office_id'],
                    $row['latitude'], $row['longitude'], $row['accuracy'],
                    $row['speed'], $row['bearing'], $row['battery_level'],
                    $row['recorded_at'], now_datetime(), now_datetime(),
                )
            );
        }
    }

    public function getForAttendance(int $attendanceId): array
    {
        return $this->db->where('attendance_id', $attendanceId)
            ->order_by('recorded_at', 'ASC')
            ->get('attendance_tracking')
            ->result_array();
    }

    public function belongsToEmployee(int $attendanceId, int $employeeId): bool
    {
        $row = $this->db->where('id', $attendanceId)
            ->where('employee_id', $employeeId)
            ->get('attendances')
            ->row_array();
        return (bool) $row;
    }
}

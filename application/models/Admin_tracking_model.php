<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_tracking_model extends CI_Model
{
    /** Full attendance + employee + office info for the tracking detail header. Office-scope aware. */
    public function getAttendanceDetail(int $attendanceId, ?int $officeId)
    {
        $this->db->select("
                attendances.*,
                employees.name as employee_name,
                employees.employee_code,
                employees.nip,
                offices.name as office_name,
                offices.latitude as office_latitude,
                offices.longitude as office_longitude,
                offices.check_in_radius,
                offices.check_out_radius
            ")
            ->from('attendances')
            ->join('employees', 'employees.id = attendances.employee_id')
            ->join('offices', 'offices.id = attendances.office_id')
            ->where('attendances.id', $attendanceId);

        if ($officeId) $this->db->where('attendances.office_id', $officeId);

        return $this->db->get()->row_array();
    }

    public function getTrackingPoints(int $attendanceId): array
    {
        return $this->db->where('attendance_id', $attendanceId)
            ->order_by('recorded_at', 'ASC')
            ->get('attendance_tracking')
            ->result_array();
    }

    /** Just the newest point — used by the 30s realtime polling endpoint (cheap query, no full reload). */
    public function getLatestPoint(int $attendanceId)
    {
        return $this->db->where('attendance_id', $attendanceId)
            ->order_by('recorded_at', 'DESC')
            ->limit(1)
            ->get('attendance_tracking')
            ->row_array();
    }

    /** For the Monitoring Aktif page: every attendance still checked-in (no check_out yet) today or recently. */
    public function getActiveTrackingList(?int $officeId): array
    {
        $this->db->select("
                attendances.*,
                employees.name as employee_name,
                employees.employee_code,
                offices.name as office_name,
                (SELECT COUNT(*) FROM attendance_tracking WHERE attendance_tracking.attendance_id = attendances.id) as tracking_count,
                (SELECT MAX(recorded_at) FROM attendance_tracking WHERE attendance_tracking.attendance_id = attendances.id) as last_point_at
            ")
            ->from('attendances')
            ->join('employees', 'employees.id = attendances.employee_id')
            ->join('offices', 'offices.id = attendances.office_id')
            ->where('attendances.check_in_time IS NOT NULL', null, false)
            ->where('attendances.check_out_time IS NULL', null, false)
            ->order_by('attendances.check_in_time', 'DESC');

        if ($officeId) $this->db->where('attendances.office_id', $officeId);

        return $this->db->get()->result_array();
    }
}

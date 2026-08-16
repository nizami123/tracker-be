<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * NOTE on "Izin" vs "Cuti": the actual schema only has ONE request
 * type for both — `attendance_requests.type = 'LEAVE'` — there is no
 * separate CUTI/IZIN distinction in the database (see
 * ADMIN_README.md). This report merges them into a single "Izin/Cuti"
 * figure rather than inventing a column/value that doesn't exist.
 *
 * NOTE on "Tidak Hadir": there is no work-schedule/shift table, so
 * this is an ESTIMATE — (active employees × working days in range) −
 * Hadir − Izin/Cuti — not an exact figure tied to a real roster.
 * Flagged again here and in the report view itself.
 */
class Admin_report_model extends CI_Model
{
    // ---------------- Laporan Absensi ----------------

    public function attendanceSummary(string $dateFrom, string $dateTo, ?int $officeId, ?int $employeeId): array
    {
        $q = $this->db->where('attendance_date >=', $dateFrom)->where('attendance_date <=', $dateTo);
        if ($officeId) $q->where('office_id', $officeId);
        if ($employeeId) $q->where('employee_id', $employeeId);
        $hadir = (int) $q->count_all_results('attendances');

        $q = $this->db->where('attendance_date >=', $dateFrom)->where('attendance_date <=', $dateTo)->where('status', 'LATE');
        if ($officeId) $q->where('office_id', $officeId);
        if ($employeeId) $q->where('employee_id', $employeeId);
        $terlambat = (int) $q->count_all_results('attendances');

        $q = $this->db->where('type', 'LEAVE')->where('status', 'APPROVED')
            ->where('start_date <=', $dateTo)->where('end_date >=', $dateFrom);
        if ($officeId) $q->where('office_id', $officeId);
        if ($employeeId) $q->where('employee_id', $employeeId);
        $izinCuti = (int) $q->count_all_results('attendance_requests');

        $empQ = $this->db->where('status', 'ACTIVE')->where_in('role', array('EMPLOYEE', 'DRIVER'));
        if ($officeId) $empQ->where('office_id', $officeId);
        if ($employeeId) $empQ->where('id', $employeeId);
        $totalEmployees = (int) $empQ->count_all_results('employees');

        $workingDays = (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1;
        $expectedAttendances = $totalEmployees * max(1, (int) $workingDays);
        $tidakHadir = max(0, $expectedAttendances - $hadir - $izinCuti);

        return array(
            'hadir'       => $hadir,
            'terlambat'   => $terlambat,
            'tidak_hadir' => $tidakHadir,
            'izin_cuti'   => $izinCuti,
            'total_karyawan' => $totalEmployees,
        );
    }

    public function attendanceRows(string $dateFrom, string $dateTo, ?int $officeId, ?int $employeeId): array
    {
        $this->db->select("attendances.*, employees.name as employee_name, employees.employee_code, offices.name as office_name")
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

    // ---------------- Laporan Pengajuan ----------------

    public function requestRows(array $filters, ?int $officeId): array
    {
        $this->db->select("attendance_requests.*, employees.name as employee_name, employees.employee_code, offices.name as office_name")
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
                dest.name as destination_office_name
            ")
            ->from('vehicle_deliveries')
            ->join('employees', 'employees.id = vehicle_deliveries.driver_id')
            ->join('offices as dest', 'dest.id = vehicle_deliveries.destination_office_id', 'left')
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

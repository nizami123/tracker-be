<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Combines active employee attendance tracking AND active vehicle
 * delivery tracking into one list/map — this is the dedicated
 * "Monitoring Aktif" page (TAHAP 8), distinct from (and complementary
 * to) the per-feature "Tracking Karyawan" / "Tracking Kendaraan" list
 * pages built in TAHAP 4/7. Not in the literal sidebar list you gave
 * me, but explicitly described in the spec body ("MONITORING AKTIF" /
 * "MAP MONITORING AKTIF") as its own feature — linked from the
 * Dashboard's "Sedang Tracking" / "Pengiriman Aktif" stat cards and
 * added as its own sidebar entry. Flagged in ADMIN_README.md.
 */
class Admin_monitoring_model extends CI_Model
{
    public function getActiveEmployees(?int $officeId): array
    {
        $this->db->select("
                attendances.id, 'EMPLOYEE' as tracker_type,
                employees.name as person_name, employees.employee_code as person_code,
                offices.name as office_name,
                attendances.check_in_time as started_at,
                (SELECT COUNT(*) FROM attendance_tracking WHERE attendance_tracking.attendance_id = attendances.id) as point_count,
                (SELECT latitude FROM attendance_tracking WHERE attendance_tracking.attendance_id = attendances.id ORDER BY recorded_at DESC LIMIT 1) as last_lat,
                (SELECT longitude FROM attendance_tracking WHERE attendance_tracking.attendance_id = attendances.id ORDER BY recorded_at DESC LIMIT 1) as last_lng,
                (SELECT accuracy FROM attendance_tracking WHERE attendance_tracking.attendance_id = attendances.id ORDER BY recorded_at DESC LIMIT 1) as last_accuracy,
                (SELECT speed FROM attendance_tracking WHERE attendance_tracking.attendance_id = attendances.id ORDER BY recorded_at DESC LIMIT 1) as last_speed,
                (SELECT recorded_at FROM attendance_tracking WHERE attendance_tracking.attendance_id = attendances.id ORDER BY recorded_at DESC LIMIT 1) as last_update
            ")
            ->from('attendances')
            ->join('employees', 'employees.id = attendances.employee_id')
            ->join('offices', 'offices.id = attendances.office_id')
            ->where('attendances.check_in_time IS NOT NULL', null, false)
            ->where('attendances.check_out_time IS NULL', null, false);
        if ($officeId) $this->db->where('attendances.office_id', $officeId);
        return $this->db->get()->result_array();
    }

    public function getActiveDrivers(?int $officeId): array
    {
        $this->db->select("
                vehicle_deliveries.id, 'DRIVER' as tracker_type,
                employees.name as person_name, employees.employee_code as person_code,
                dest.name as office_name,
                vehicle_deliveries.pickup_time as started_at,
                vehicle_deliveries.status as delivery_status,
                (SELECT COUNT(*) FROM vehicle_delivery_tracking WHERE vehicle_delivery_tracking.delivery_id = vehicle_deliveries.id) as point_count,
                (SELECT latitude FROM vehicle_delivery_tracking WHERE vehicle_delivery_tracking.delivery_id = vehicle_deliveries.id ORDER BY recorded_at DESC LIMIT 1) as last_lat,
                (SELECT longitude FROM vehicle_delivery_tracking WHERE vehicle_delivery_tracking.delivery_id = vehicle_deliveries.id ORDER BY recorded_at DESC LIMIT 1) as last_lng,
                (SELECT accuracy FROM vehicle_delivery_tracking WHERE vehicle_delivery_tracking.delivery_id = vehicle_deliveries.id ORDER BY recorded_at DESC LIMIT 1) as last_accuracy,
                (SELECT speed FROM vehicle_delivery_tracking WHERE vehicle_delivery_tracking.delivery_id = vehicle_deliveries.id ORDER BY recorded_at DESC LIMIT 1) as last_speed,
                (SELECT recorded_at FROM vehicle_delivery_tracking WHERE vehicle_delivery_tracking.delivery_id = vehicle_deliveries.id ORDER BY recorded_at DESC LIMIT 1) as last_update
            ")
            ->from('vehicle_deliveries')
            ->join('employees', 'employees.id = vehicle_deliveries.driver_id')
            ->join('offices as dest', 'dest.id = vehicle_deliveries.destination_office_id', 'left')
            ->where_in('vehicle_deliveries.status', array('IN_PROGRESS', 'ARRIVED'));
        if ($officeId) $this->db->where('employees.office_id', $officeId);
        return $this->db->get()->result_array();
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Every method takes a nullable $officeId:
 *   null       -> SUPER_ADMIN, no office filter (sees everything)
 *   (int) id   -> ADMIN_KANTOR, scoped to that office only
 *
 * This mirrors the authorization rule from the spec: "ADMIN_KANTOR
 * hanya dapat melihat data sesuai kantor yang menjadi kewenangannya"
 * enforced here in the model layer (used by every admin controller),
 * not just hidden in the view.
 *
 * ASSUMPTIONS (documented rather than silently guessed — no shift/
 * schedule table exists yet in the schema):
 *  - "Total Karyawan" counts role IN ('EMPLOYEE','DRIVER') only —
 *    admin accounts (SUPER_ADMIN/ADMIN_KANTOR) aren't "karyawan" being
 *    monitored for attendance.
 *  - "Belum Absen" = Total Karyawan - Hadir Hari Ini. There's no work-
 *    schedule table, so every active employee is assumed to be an
 *    expected attendee every day.
 *  - "Pengiriman Kendaraan Aktif" is scoped by the DRIVER's home
 *    office (employees.office_id), not the delivery's destination
 *    office — i.e. "kendaraan yang sedang dikirim oleh driver kantor
 *    saya", since that's the office an ADMIN_KANTOR actually manages
 *    staff for.
 */
class Admin_dashboard_model extends CI_Model
{
    public function totalKaryawan(?int $officeId): int
    {
        $q = $this->db->where('status', 'ACTIVE')->where_in('role', array('EMPLOYEE', 'DRIVER'));
        if ($officeId) $q->where('office_id', $officeId);
        return (int) $q->count_all_results('employees');
    }

    public function hadirHariIni(?int $officeId): int
    {
        $q = $this->db->where('attendance_date', today_date());
        if ($officeId) $q->where('office_id', $officeId);
        return (int) $q->count_all_results('attendances');
    }

    public function sedangTracking(?int $officeId): int
    {
        $q = $this->db->where('attendance_date', today_date())
            ->where('check_in_time IS NOT NULL', null, false)
            ->where('check_out_time IS NULL', null, false);
        if ($officeId) $q->where('office_id', $officeId);
        return (int) $q->count_all_results('attendances');
    }

    public function pengajuanMenunggu(?int $officeId): int
    {
        $q = $this->db->where('status', 'PENDING');
        if ($officeId) $q->where('office_id', $officeId);
        return (int) $q->count_all_results('attendance_requests');
    }

    public function pengirimanAktif(?int $officeId): int
    {
        $this->db->select('vehicle_deliveries.id')
            ->from('vehicle_deliveries')
            ->where_in('vehicle_deliveries.status', array('IN_PROGRESS', 'ARRIVED'));
        if ($officeId) {
            $this->db->join('employees', 'employees.id = vehicle_deliveries.driver_id')
                ->where('employees.office_id', $officeId);
        }
        return (int) $this->db->count_all_results();
    }

    /** Kehadiran 7 hari terakhir -> [{date, total}] for the bar/line chart. */
    public function kehadiranTerakhir(?int $officeId, int $days = 7): array
    {
        $start = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
        $this->db->select('attendance_date, COUNT(*) as total')
            ->from('attendances')
            ->where('attendance_date >=', $start)
            ->group_by('attendance_date')
            ->order_by('attendance_date', 'ASC');
        if ($officeId) $this->db->where('office_id', $officeId);
        return $this->db->get()->result_array();
    }

    public function pengajuanByStatus(?int $officeId): array
    {
        $this->db->select('status, COUNT(*) as total')->from('attendance_requests')->group_by('status');
        if ($officeId) $this->db->where('office_id', $officeId);
        return $this->db->get()->result_array();
    }

    public function pengirimanByStatus(?int $officeId): array
    {
        $this->db->select('vehicle_deliveries.status, COUNT(*) as total')
            ->from('vehicle_deliveries')
            ->group_by('vehicle_deliveries.status');
        if ($officeId) {
            $this->db->join('employees', 'employees.id = vehicle_deliveries.driver_id')
                ->where('employees.office_id', $officeId);
        }
        return $this->db->get()->result_array();
    }
}

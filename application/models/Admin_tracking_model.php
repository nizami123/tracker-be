<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Halaman admin "Tracking Karyawan". Sumber datanya HANYA tabel
 * attendance_tracking, dikunci ke employee_id + tanggal — tidak ada
 * join/filter ke tabel attendances. Karyawan yang belum (atau tidak
 * pernah) absen tetap tampil selama ada titik trackingnya, dan
 * status absen masuk/pulang tidak memengaruhi apa yang tampil.
 *
 * Semua method menerima $officeId nullable (null = SUPER_ADMIN tanpa
 * batasan, int = ADMIN_KANTOR hanya kantornya sendiri; dicocokkan ke
 * employees.office_id).
 */
class Admin_tracking_model extends CI_Model
{
    /**
     * Satu baris per karyawan yang punya titik tracking pada $date,
     * urut dari yang paling baru update.
     */
    public function getTrackingList(?int $officeId, string $date): array
    {
        list($start, $end) = day_range($date);

        $sql = "
            SELECT
                employees.id AS employee_id,
                employees.name AS employee_name,
                employees.employee_code,
                offices.name AS office_name,
                COUNT(*) AS tracking_count,
                MIN(attendance_tracking.recorded_at) AS first_point_at,
                MAX(attendance_tracking.recorded_at) AS last_point_at
            FROM attendance_tracking
            JOIN employees ON employees.id = attendance_tracking.employee_id
            JOIN offices ON offices.id = employees.office_id
            WHERE attendance_tracking.recorded_at >= ?
              AND attendance_tracking.recorded_at < ?";
        $params = array($start, $end);

        if ($officeId) {
            $sql .= " AND employees.office_id = ?";
            $params[] = $officeId;
        }

        $sql .= "
            GROUP BY employees.id, employees.name, employees.employee_code, offices.name
            ORDER BY last_point_at DESC";

        return $this->db->query($sql, $params)->result_array();
    }

    /**
     * Header halaman detail: identitas karyawan + kantor (untuk marker
     * & lingkaran radius di peta) untuk satu tanggal. Null (-> 404) bila
     * karyawan tidak ada atau di luar kewenangan ADMIN_KANTOR.
     */
    public function getEmployeeDetail(int $employeeId, string $date, ?int $officeId)
    {
        $this->db->select("
                employees.id as employee_id,
                employees.name as employee_name,
                employees.employee_code,
                employees.nip,
                offices.name as office_name,
                offices.latitude as office_latitude,
                offices.longitude as office_longitude,
                offices.check_in_radius
            ")
            ->from('employees')
            ->join('offices', 'offices.id = employees.office_id')
            ->where('employees.id', $employeeId);

        if ($officeId) $this->db->where('employees.office_id', $officeId);

        $row = $this->db->get()->row_array();
        if (!$row) return null;

        $row['tracking_date'] = $date;
        return $row;
    }

    /** Semua titik satu karyawan pada satu tanggal, urut waktu (untuk polyline di peta). */
    public function getTrackingPoints(int $employeeId, string $date): array
    {
        list($start, $end) = day_range($date);

        return $this->db->where('employee_id', $employeeId)
            ->where('recorded_at >=', $start)
            ->where('recorded_at <', $end)
            ->order_by('recorded_at', 'ASC')
            ->get('attendance_tracking')
            ->result_array();
    }

    /** Hanya titik terbaru — dipakai polling realtime 30 detik (query ringan, tanpa reload penuh). */
    public function getLatestPoint(int $employeeId, string $date)
    {
        list($start, $end) = day_range($date);

        return $this->db->where('employee_id', $employeeId)
            ->where('recorded_at >=', $start)
            ->where('recorded_at <', $end)
            ->order_by('recorded_at', 'DESC')
            ->limit(1)
            ->get('attendance_tracking')
            ->row_array();
    }
}

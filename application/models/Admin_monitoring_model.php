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
    /**
     * "Karyawan Tracking" pada halaman Tracking Aktif.
     *
     * Sumber datanya HANYA tabel attendance_tracking, dikunci ke
     * employee_id + tanggal hari ini: siapa pun yang punya titik dengan
     * recorded_at hari ini (GMT+7) tampil di sini — sudah absen masuk,
     * sudah absen pulang, atau belum absen sama sekali. Tabel attendances
     * tidak dipakai sama sekali, jadi absensi tidak memengaruhi siapa
     * yang tampil. Satu baris per karyawan; posisi terakhirnya diambil
     * dari titik terbaru hari ini (join ke unique key employee_id +
     * recorded_at, jadi pasti tepat satu baris).
     *
     * Pakai $this->db->query() mentah (bukan query builder) karena butuh
     * subquery di klausa FROM — kalau lewat ->from() query builder CI
     * akan mencoba "protect_identifiers" string subquery itu dan bisa
     * merusak SQL-nya.
     */
    public function getActiveEmployees(?int $officeId): array
    {
        // Batas hari per zona kantor (WIB / WITA / WIT): tiap karyawan
        // dibandingkan dengan awal & akhir "hari ini" di zona kantornya.
        $startExpr = sql_office_zone_case('te.office_id', fn($tz) => day_range(today_date($tz))[0]);
        $endExpr   = sql_office_zone_case('te.office_id', fn($tz) => day_range(today_date($tz))[1]);

        $sql = "
            SELECT
                employees.id AS employee_id, 'EMPLOYEE' AS tracker_type,
                employees.name AS person_name, employees.employee_code AS person_code,
                offices.name AS office_name, offices.code AS office_code, offices.timezone AS office_timezone,
                today.started_at, today.point_count,
                last_point.latitude AS last_lat, last_point.longitude AS last_lng,
                last_point.accuracy AS last_accuracy, last_point.speed AS last_speed,
                last_point.recorded_at AS last_update
            FROM (
                SELECT t.employee_id,
                       MIN(t.recorded_at) AS started_at,
                       MAX(t.recorded_at) AS last_at,
                       COUNT(*) AS point_count
                  FROM attendance_tracking AS t
                  JOIN employees AS te ON te.id = t.employee_id
                 WHERE t.recorded_at >= $startExpr
                   AND t.recorded_at < $endExpr
                 GROUP BY t.employee_id
            ) AS today
            JOIN attendance_tracking AS last_point
              ON last_point.employee_id = today.employee_id
             AND last_point.recorded_at = today.last_at
            JOIN employees ON employees.id = today.employee_id
            JOIN offices ON offices.id = employees.office_id";
        $params = array();

        if ($officeId) {
            $sql .= " WHERE employees.office_id = ?";
            $params[] = $officeId;
        }

        $sql .= " ORDER BY today.last_at DESC";

        $rows = $this->db->query($sql, $params)->result_array();
        foreach ($rows as &$r) {
            $r['tz_label'] = office_tz_label($r['office_timezone'] ?? null);
        }
        unset($r);
        return $rows;
    }

    public function getActiveDrivers(?int $officeId): array
    {
        $this->db->select("
                vehicle_deliveries.id, 'DRIVER' as tracker_type,
                employees.name as person_name, employees.employee_code as person_code,
                dest.name as office_name,
                driver_office.name as driver_office_name, driver_office.code as driver_office_code, driver_office.timezone as driver_office_timezone,
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
            ->join('offices as driver_office', 'driver_office.id = employees.office_id', 'left')
            ->where_in('vehicle_deliveries.status', array('IN_PROGRESS', 'ARRIVED'));
        if ($officeId) $this->db->where('employees.office_id', $officeId);
        $rows = $this->db->get()->result_array();
        foreach ($rows as &$r) {
            // Jam pengiriman memakai zona kantor DRIVER, bukan kantor tujuan.
            $r['tz_label'] = office_tz_label($r['driver_office_timezone'] ?? null);
        }
        unset($r);
        return $rows;
    }
}

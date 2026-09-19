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
     * Revisi: sumber datanya adalah tabel attendance_tracking (tabel
     * yang benar-benar menyimpan titik-titik tracking), BUKAN status
     * check_in_time/check_out_time di attendances. Sebelumnya karyawan
     * hanya dianggap "aktif" kalau sudah absen masuk dan belum absen
     * pulang — jadi begitu absen pulang (atau kalau titik GPS masuk
     * sebelum absen masuk / pending), dia langsung hilang dari daftar
     * walau titik tracking hari itu masih ada / terus bertambah.
     * Sekarang: siapa pun yang PUNYA baris attendance_tracking dengan
     * recorded_at = hari ini akan muncul di sini, jam masuk dan jam
     * pulang tidak dipakai sebagai filter sama sekali. Ini sejalan
     * dengan Admin_tracking_model::getTodayTrackingList() yang dipakai
     * halaman "Tracking Karyawan" — dua fungsi getX/getPendingX di
     * bawah ini sengaja meniru pola yang sama (linked vs pending,
     * digabung jadi satu daftar dengan flag is_pending) supaya kedua
     * halaman konsisten menampilkan hal yang sama.
     */
    public function getActiveEmployees(?int $officeId): array
    {
        $linked = $this->getLinkedEmployeeTracking($officeId);
        $pending = $this->getPendingEmployeeTracking($officeId);
        return array_merge($linked, $pending);
    }

    /**
     * Karyawan yang sudah pernah absen masuk hari ini yang punya titik
     * tracking hari ini. Dikunci ke employee_id + tanggal, BUKAN
     * attendance_id: sebuah titik bisa saja masih attendance_id NULL
     * meskipun karyawannya sudah absen masuk — misalnya titik itu
     * direkam offline sebelum absen lalu baru sampai ke server SETELAH
     * absen masuk (dan reassignPendingPoints() sudah keburu jalan
     * duluan). Kalau masih dikunci ke attendance_id, karyawan begini
     * akan hilang total dari halaman ini: EXISTS di bawah gagal karena
     * baris trackingnya belum attendance_id, sementara guard NOT EXISTS
     * di getPendingEmployeeTracking() juga mengecualikan dia karena dia
     * sudah punya attendance hari ini.
     */
    private function getLinkedEmployeeTracking(?int $officeId): array
    {
        $today = $this->db->escape(today_date());

        $this->db->select("
                attendances.id, attendances.employee_id, 0 as is_pending, 'EMPLOYEE' as tracker_type,
                employees.name as person_name, employees.employee_code as person_code,
                offices.name as office_name,
                (SELECT MIN(recorded_at) FROM attendance_tracking WHERE attendance_tracking.employee_id = attendances.employee_id AND DATE(recorded_at) = {$today}) as started_at,
                (SELECT COUNT(*) FROM attendance_tracking WHERE attendance_tracking.employee_id = attendances.employee_id AND DATE(recorded_at) = {$today}) as point_count,
                (SELECT latitude FROM attendance_tracking WHERE attendance_tracking.employee_id = attendances.employee_id AND DATE(recorded_at) = {$today} ORDER BY recorded_at DESC LIMIT 1) as last_lat,
                (SELECT longitude FROM attendance_tracking WHERE attendance_tracking.employee_id = attendances.employee_id AND DATE(recorded_at) = {$today} ORDER BY recorded_at DESC LIMIT 1) as last_lng,
                (SELECT accuracy FROM attendance_tracking WHERE attendance_tracking.employee_id = attendances.employee_id AND DATE(recorded_at) = {$today} ORDER BY recorded_at DESC LIMIT 1) as last_accuracy,
                (SELECT speed FROM attendance_tracking WHERE attendance_tracking.employee_id = attendances.employee_id AND DATE(recorded_at) = {$today} ORDER BY recorded_at DESC LIMIT 1) as last_speed,
                (SELECT recorded_at FROM attendance_tracking WHERE attendance_tracking.employee_id = attendances.employee_id AND DATE(recorded_at) = {$today} ORDER BY recorded_at DESC LIMIT 1) as last_update
            ", false)
            ->from('attendances')
            ->join('employees', 'employees.id = attendances.employee_id')
            ->join('offices', 'offices.id = attendances.office_id')
            ->where(
                "EXISTS (SELECT 1 FROM attendance_tracking WHERE attendance_tracking.employee_id = attendances.employee_id AND DATE(attendance_tracking.recorded_at) = {$today})",
                null,
                false
            );
        if ($officeId) $this->db->where('attendances.office_id', $officeId);
        return $this->db->get()->result_array();
    }

    /**
     * Karyawan yang titik trackingnya sudah masuk hari ini tapi belum
     * absen masuk sama sekali (attendance_id NULL). Pakai
     * $this->db->query() mentah (bukan query builder) karena butuh
     * subquery di klausa FROM — kalau lewat ->from() query builder CI
     * akan mencoba "protect_identifiers" string subquery itu dan bisa
     * merusak SQL-nya.
     */
    private function getPendingEmployeeTracking(?int $officeId): array
    {
        $today = today_date();

        $sql = "
            SELECT
                0 as id, pending.employee_id, 1 as is_pending, 'EMPLOYEE' as tracker_type,
                employees.name as person_name, employees.employee_code as person_code,
                offices.name as office_name,
                (SELECT MIN(recorded_at) FROM attendance_tracking WHERE attendance_tracking.employee_id = pending.employee_id AND attendance_tracking.attendance_id IS NULL AND DATE(recorded_at) = ?) as started_at,
                (SELECT COUNT(*) FROM attendance_tracking WHERE attendance_tracking.employee_id = pending.employee_id AND attendance_tracking.attendance_id IS NULL AND DATE(recorded_at) = ?) as point_count,
                (SELECT latitude FROM attendance_tracking WHERE attendance_tracking.employee_id = pending.employee_id AND attendance_tracking.attendance_id IS NULL AND DATE(recorded_at) = ? ORDER BY recorded_at DESC LIMIT 1) as last_lat,
                (SELECT longitude FROM attendance_tracking WHERE attendance_tracking.employee_id = pending.employee_id AND attendance_tracking.attendance_id IS NULL AND DATE(recorded_at) = ? ORDER BY recorded_at DESC LIMIT 1) as last_lng,
                (SELECT accuracy FROM attendance_tracking WHERE attendance_tracking.employee_id = pending.employee_id AND attendance_tracking.attendance_id IS NULL AND DATE(recorded_at) = ? ORDER BY recorded_at DESC LIMIT 1) as last_accuracy,
                (SELECT speed FROM attendance_tracking WHERE attendance_tracking.employee_id = pending.employee_id AND attendance_tracking.attendance_id IS NULL AND DATE(recorded_at) = ? ORDER BY recorded_at DESC LIMIT 1) as last_speed,
                (SELECT recorded_at FROM attendance_tracking WHERE attendance_tracking.employee_id = pending.employee_id AND attendance_tracking.attendance_id IS NULL AND DATE(recorded_at) = ? ORDER BY recorded_at DESC LIMIT 1) as last_update
            FROM (SELECT DISTINCT employee_id FROM attendance_tracking WHERE attendance_id IS NULL AND DATE(recorded_at) = ?) as pending
            JOIN employees ON employees.id = pending.employee_id
            JOIN offices ON offices.id = employees.office_id
            -- Backstop yang sama seperti Admin_tracking_model::getPendingTrackingList() —
            -- kalau ternyata sudah ada attendance hari ini, jangan tampilkan di sini juga
            -- (mencegah karyawan yang sama muncul dobel: sebagai pending & sebagai linked).
            WHERE NOT EXISTS (SELECT 1 FROM attendances a2 WHERE a2.employee_id = pending.employee_id AND a2.attendance_date = ?)
        ";
        // 9 tanda "?" di query di atas: 7 di subquery SELECT (started_at,
        // point_count, last_lat, last_lng, last_accuracy, last_speed,
        // last_update) + 1 di subquery FROM (pending) + 1 di WHERE NOT
        // EXISTS. Jumlah ini HARUS sama persis dengan jumlah elemen di
        // $params, kalau tidak CodeIgniter diam-diam batal melakukan
        // binding dan mengirim SQL mentah dengan tanda "?" apa adanya ke
        // MariaDB (persis error 1064 yang sebelumnya muncul).
        $params = array_fill(0, 9, $today);

        if ($officeId) {
            $sql .= ' AND employees.office_id = ?';
            $params[] = $officeId;
        }

        return $this->db->query($sql, $params)->result_array();
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

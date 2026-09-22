<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tracking karyawan = tabel attendance_tracking, berfokus ke
 * employee_id + tanggal (DATE(recorded_at)). Tidak ada relasi ke
 * tabel attendances sama sekali: titik tracking tetap masuk walau
 * karyawan belum/tidak absen, dan absen masuk/pulang tidak mengubah
 * satu baris pun di tabel ini.
 */
class Tracking_model extends CI_Model
{
    /**
     * Inserts every row and returns only the 'localId' values that were
     * actually confirmed saved (or already present) in the database.
     *
     * Each row is a separate INSERT IGNORE wrapped in its own try/catch:
     * one bad/failing row (a transient DB error, a lock timeout, an
     * unexpected value) must never abort the rest of the batch or leave
     * the caller unable to tell which points made it in — Tracking::sync()
     * only reports back the localIds returned here as synced, so the
     * Android app keeps retrying anything that isn't in this list instead
     * of wrongly deleting/marking-synced a point that was never saved.
     */
    public function insertBatch(array $rows): array
    {
        $syncedLocalIds = array();
        if (empty($rows)) return $syncedLocalIds;

        foreach ($rows as $row) {
            try {
                $success = $this->db->query(
                    "INSERT IGNORE INTO attendance_tracking
                        (employee_id, office_id, latitude, longitude,
                         accuracy, speed, bearing, battery_level, recorded_at, server_received_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    array(
                        $row['employee_id'], $row['office_id'],
                        $row['latitude'], $row['longitude'], $row['accuracy'],
                        $row['speed'], $row['bearing'], $row['battery_level'],
                        $row['recorded_at'], now_datetime(), now_datetime(),
                    )
                );

                if ($success) {
                    // INSERT IGNORE: $success is true even when the row
                    // was a duplicate of one already stored (unique_tracking
                    // on employee_id + recorded_at) — that still counts as
                    // "safely in the database", so it's correct to report
                    // it as synced either way.
                    $syncedLocalIds[] = $row['localId'];
                } else {
                    log_message('error', 'Tracking insert returned false: employee_id=' . $row['employee_id'] . ' recorded_at=' . $row['recorded_at']);
                }
            } catch (\Throwable $e) {
                log_message('error', 'Tracking insert threw: employee_id=' . $row['employee_id'] . ' recorded_at=' . $row['recorded_at'] . ' — ' . $e->getMessage());
            }
        }

        return $syncedLocalIds;
    }

    /** Semua titik tracking milik satu karyawan pada satu tanggal (Y-m-d), urut waktu. */
    public function getForEmployeeDate(int $employeeId, string $date): array
    {
        list($start, $end) = day_range($date);

        return $this->db->where('employee_id', $employeeId)
            ->where('recorded_at >=', $start)
            ->where('recorded_at <', $end)
            ->order_by('recorded_at', 'ASC')
            ->get('attendance_tracking')
            ->result_array();
    }
}

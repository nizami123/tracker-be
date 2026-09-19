<?php
defined('BASEPATH') OR exit('No direct script access allowed');

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
     * attendance_id may be NULL (points recorded before check-in exists
     * yet, or whose attendance_id no longer belongs to this employee —
     * see Tracking::sync()) — still inserted, tracking never depends on
     * having a valid attendance_id.
     */
    public function insertBatch(array $rows): array
    {
        $syncedLocalIds = array();
        if (empty($rows)) return $syncedLocalIds;

        foreach ($rows as $row) {
            try {
                $success = $this->db->query(
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

                if ($success) {
                    // INSERT IGNORE: $success is true even when the row
                    // was a duplicate of one already stored (unique_tracking)
                    // — that still counts as "safely in the database", so
                    // it's correct to report it as synced either way.
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

    /**
     * Called right after a successful check-in (spec parity with the
     * Android app's TrackingRepository.reassignPendingPoints): claims
     * every pending point (attendance_id IS NULL) recorded TODAY for
     * this employee and attaches it to the attendance record that was
     * just created, so this morning's pre-check-in location history
     * shows up under the employee's attendance as expected.
     */
    public function reassignPendingPoints(int $employeeId, int $attendanceId): void
    {
        $this->db->query(
            "UPDATE attendance_tracking
                SET attendance_id = ?
              WHERE employee_id = ?
                AND attendance_id IS NULL
                AND DATE(recorded_at) = CURDATE()",
            array($attendanceId, $employeeId)
        );
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

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Tracking extends MY_Controller
{
    /**
     * POST /api/tracking/sync
     * Batch-inserts pending tracking points saved locally by the
     * Android foreground service. employee_id/office_id on each point
     * are trusted for storage but every point's attendance_id is
     * cross-checked against the authenticated employee before insert,
     * so one employee can never write tracking data into another
     * employee's attendance record.
     *
     * The Android app uses attendance_id = 0 as a sentinel for points
     * recorded BEFORE check-in (no attendance record exists yet). Those
     * points are still inserted — with attendance_id stored as NULL —
     * instead of being dropped, so the employee's location is never
     * lost just because they haven't absen masuk yet. They get claimed
     * by the real attendance record once check-in happens (see
     * Attendance::check_in() -> Tracking_model::reassignPendingPoints()).
     */
    public function sync()
    {
        $employee = $this->require_auth();
        $body = $this->json_input();
        $points = $body['points'] ?? array();

        if (!is_array($points) || empty($points)) {
            return $this->json_response(array('success' => true, 'synced_ids' => array()), 200);
        }

        $this->load->model('Tracking_model');

        $validRows = array();
        $syncedIds = array();
        $checkedAttendance = array(); // cache ownership checks per attendance_id

        foreach ($points as $point) {
            $rawAttendanceId = (int) ($point['attendance_id'] ?? 0);
            $isPending = $rawAttendanceId <= 0; // sentinel: not checked in yet
            $localId = $point['localId'] ?? null;
            if ($localId === null) continue;

            if (!$isPending) {
                if (!isset($checkedAttendance[$rawAttendanceId])) {
                    $checkedAttendance[$rawAttendanceId] =
                        $this->Tracking_model->belongsToEmployee($rawAttendanceId, (int) $employee['id']);
                }
                if (!$checkedAttendance[$rawAttendanceId]) {
                    continue; // silently skip points that don't belong to this employee
                }
            }

            $validRows[] = array(
                // NULL (not 0) for pending points: attendance_id has a
                // FOREIGN KEY to attendances(id), so it must be NULL,
                // never a fake row id.
                'attendance_id' => $isPending ? null : $rawAttendanceId,
                'employee_id'   => $employee['id'],
                'office_id'     => $employee['office_id'],
                'latitude'      => (float) ($point['latitude'] ?? 0),
                'longitude'     => (float) ($point['longitude'] ?? 0),
                'accuracy'      => $point['accuracy'] ?? null,
                'speed'         => $point['speed'] ?? null,
                'bearing'       => $point['bearing'] ?? null,
                'battery_level' => $point['battery_level'] ?? null,
                'recorded_at'   => $point['recorded_at'] ?? now_datetime(),
            );
            $syncedIds[] = $localId;
        }

        $this->Tracking_model->insertBatch($validRows);

        $this->json_response(array('success' => true, 'synced_ids' => $syncedIds), 200);
    }

    /** GET /api/tracking/{attendance_id} — used by the tracking-detail map screen. */
    public function for_attendance($attendanceId)
    {
        $employee = $this->require_auth();
        $this->load->model('Tracking_model');

        if (!$this->Tracking_model->belongsToEmployee((int) $attendanceId, (int) $employee['id'])) {
            return $this->json_response(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);
        }

        $this->json_response($this->Tracking_model->getForAttendance((int) $attendanceId), 200);
    }
}

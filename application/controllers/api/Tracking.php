<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Tracking extends MY_Controller
{
    /**
     * POST /api/tracking/sync
     * Batch-inserts pending tracking points saved locally by the
     * Android foreground service. employee_id/office_id on each point
     * always come from the authenticated employee (never trusted from
     * the client), so tracking data is safely attributed without ever
     * needing attendance_id for that purpose.
     *
     * attendance_id is treated as a pure best-effort label, never a
     * gate: the Android app sends attendance_id = 0 as a sentinel for
     * points recorded BEFORE check-in (no attendance record exists
     * yet). A point is NEVER dropped just because its attendance_id is
     * missing OR stale (e.g. it no longer belongs to this employee —
     * the attendance record could have been edited/removed by an admin,
     * or the app cached an id from a previous day). In every case the
     * point is still stored, falling back to attendance_id = NULL, so
     * a client never loses tracking data over a mismatched id. Points
     * later get their attendance_id filled in once check-in happens
     * (see Attendance::check_in() -> Tracking_model::reassignPendingPoints()).
     *
     * A point is only reported back as synced (and only then does the
     * Android app stop retrying/uploading it) once the database insert
     * for it has actually succeeded — see Tracking_model::insertBatch().
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
        $checkedAttendance = array(); // cache ownership checks per attendance_id

        foreach ($points as $point) {
            $localId = $point['localId'] ?? null;
            if ($localId === null) continue;

            $rawAttendanceId = (int) ($point['attendance_id'] ?? 0);
            $isPending = $rawAttendanceId <= 0; // sentinel: not checked in yet
            $attendanceIdToStore = null;

            if (!$isPending) {
                if (!isset($checkedAttendance[$rawAttendanceId])) {
                    $checkedAttendance[$rawAttendanceId] =
                        $this->Tracking_model->belongsToEmployee($rawAttendanceId, (int) $employee['id']);
                }
                if ($checkedAttendance[$rawAttendanceId]) {
                    $attendanceIdToStore = $rawAttendanceId;
                }
                // If it doesn't belong to this employee (or no longer
                // exists), we do NOT skip the point anymore — tracking
                // must never depend on attendance_id. It's stored below
                // with attendance_id NULL instead, same as a pre-check-in
                // point, so the location data is never lost.
            }

            // NULL (not 0) when there's no valid attendance link:
            // attendance_id has a FOREIGN KEY to attendances(id), so it
            // must be NULL, never a fake row id.
            $validRows[] = array(
                'localId'       => $localId,
                'attendance_id' => $attendanceIdToStore,
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
        }

        // insertBatch() actually inserts each row and returns only the
        // localIds that were confirmed saved (or already present) in the
        // database — never assume success up front (see method docblock).
        $syncedIds = $this->Tracking_model->insertBatch($validRows);

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

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
            $attendanceId = (int) ($point['attendance_id'] ?? 0);
            $localId = $point['localId'] ?? null;
            if (!$attendanceId || $localId === null) continue;

            if (!isset($checkedAttendance[$attendanceId])) {
                $checkedAttendance[$attendanceId] =
                    $this->Tracking_model->belongsToEmployee($attendanceId, (int) $employee['id']);
            }
            if (!$checkedAttendance[$attendanceId]) {
                continue; // silently skip points that don't belong to this employee
            }

            $validRows[] = array(
                'attendance_id' => $attendanceId,
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

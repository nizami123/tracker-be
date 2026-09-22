<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Tracking extends MY_Controller
{
    /**
     * POST /api/tracking/sync
     * Batch-inserts tracking points saved locally by the Android
     * foreground service into attendance_tracking.
     *
     * Tracking is keyed by employee_id + tanggal only. employee_id and
     * office_id on every point always come from the authenticated
     * employee (never trusted from the client), and NOTHING here looks
     * at attendances: a point is stored whether or not the employee has
     * checked in today, and checking in/out never touches these rows.
     * Any legacy "attendance_id" field an older app build may still
     * send is simply ignored.
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

        $rows = array();

        foreach ($points as $point) {
            if (!is_array($point)) continue;

            $localId = $point['localId'] ?? null;
            if ($localId === null) continue;

            $rows[] = array(
                'localId'       => $localId,
                'employee_id'   => $employee['id'],
                'office_id'     => $employee['office_id'],
                'latitude'      => (float) ($point['latitude'] ?? 0),
                'longitude'     => (float) ($point['longitude'] ?? 0),
                'accuracy'      => $point['accuracy'] ?? null,
                'speed'         => $point['speed'] ?? null,
                'bearing'       => $point['bearing'] ?? null,
                'battery_level' => $point['battery_level'] ?? null,
                'recorded_at'   => $this->normalizeRecordedAt($point['recorded_at'] ?? null),
            );
        }

        // insertBatch() actually inserts each row and returns only the
        // localIds that were confirmed saved (or already present) in the
        // database — never assume success up front (see method docblock).
        $syncedIds = $this->Tracking_model->insertBatch($rows);

        $this->json_response(array('success' => true, 'synced_ids' => $syncedIds), 200);
    }

    /**
     * GET /api/tracking?date=YYYY-MM-DD
     * The authenticated employee's own tracking points for one date
     * (defaults to today, GMT+7). employee_id always comes from the
     * token — used by the Android tracking-detail screen.
     */
    public function index()
    {
        $employee = $this->require_auth();

        $date = $this->input->get('date');
        if ($date === null || $date === '') $date = today_date();

        if (!valid_date_string($date)) {
            return $this->json_response(array('success' => false, 'message' => 'Format tanggal harus YYYY-MM-DD'), 422);
        }

        $this->load->model('Tracking_model');
        $this->json_response($this->Tracking_model->getForEmployeeDate((int) $employee['id'], $date), 200);
    }

    /**
     * recorded_at decides which DATE a point belongs to, so it must be a
     * real "Y-m-d H:i:s" timestamp. Anything missing/malformed falls
     * back to the server's own time (GMT+7) rather than dropping the
     * point — same "never lose a tracking point" rule as before.
     */
    private function normalizeRecordedAt($value): string
    {
        if (is_string($value)) {
            $d = DateTime::createFromFormat('Y-m-d H:i:s', $value);
            if ($d !== false && $d->format('Y-m-d H:i:s') === $value) {
                return $value;
            }
        }
        return now_datetime();
    }
}

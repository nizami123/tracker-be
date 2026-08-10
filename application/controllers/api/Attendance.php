<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Attendance extends MY_Controller
{
    /**
     * POST /api/attendance/check-in
     *
     * This is the authoritative validation described in spec section 7.
     * The employee_id in the request body is NEVER trusted directly —
     * it must match the authenticated token's employee. office_id and
     * its coordinates/radius are always looked up server-side from that
     * employee's record, never accepted from the client.
     */
    public function check_in()
    {
        $employee = $this->require_auth();
        $body = $this->json_input();

        $lat = isset($body['latitude']) ? (float) $body['latitude'] : null;
        $lng = isset($body['longitude']) ? (float) $body['longitude'] : null;
        $accuracy = isset($body['accuracy']) ? (float) $body['accuracy'] : null;
        $timestamp = $body['timestamp'] ?? now_datetime();
        $photoBase64 = $body['photo_base64'] ?? null;

        if ($lat === null || $lng === null) {
            return $this->json_response(array('success' => false, 'message' => 'Koordinat lokasi wajib dikirim', 'distance' => 0, 'radius' => 0), 422);
        }

        $this->load->model('Attendance_model');
        $this->load->model('Office_model');

        if ($this->Attendance_model->alreadyCheckedInToday((int) $employee['id'])) {
            return $this->json_response(array(
                'success' => false,
                'message' => 'Anda sudah melakukan absen masuk hari ini',
                'distance' => 0,
                'radius' => 0,
            ), 409);
        }

        // office_id ALWAYS comes from the employee's own record (spec section 25),
        // never from the client, so a modified request body can't fake
        // membership in a different office.
        $office = $this->Office_model->getById((int) $employee['office_id']);
        if (!$office || $office['status'] !== 'ACTIVE') {
            return $this->json_response(array('success' => false, 'message' => 'Kantor tidak ditemukan/nonaktif', 'distance' => 0, 'radius' => 0), 422);
        }

        $distance = distance_meters($lat, $lng, (float) $office['latitude'], (float) $office['longitude']);
        $radius = (int) $office['check_in_radius'];

        if ($distance > $radius) {
            return $this->json_response(array(
                'success'  => false,
                'message'  => 'Lokasi berada di luar radius kantor',
                'distance' => round($distance, 2),
                'radius'   => $radius,
            ), 200);
        }

        $photoFilename = $photoBase64 ? save_base64_photo($photoBase64, 'checkin_' . $employee['id']) : null;

        $attendanceId = $this->Attendance_model->insertCheckIn(array(
            'employee_id'         => $employee['id'],
            'office_id'           => $office['id'],
            'attendance_date'     => today_date(),
            'check_in_time'       => $timestamp,
            'check_in_photo'      => $photoFilename,
            'check_in_latitude'   => $lat,
            'check_in_longitude'  => $lng,
            'check_in_accuracy'   => $accuracy,
            'check_in_distance'   => round($distance, 2),
            'status'              => 'PRESENT',
            'created_at'          => now_datetime(),
        ));

        $attendance = $this->Attendance_model->getById($attendanceId);

        $this->json_response(array(
            'success'    => true,
            'message'    => 'Absensi masuk berhasil',
            'distance'   => round($distance, 2),
            'radius'     => $radius,
            'attendance' => $attendance,
        ), 200);
    }

    /** POST /api/attendance/check-out — mirrors check_in with checkout_radius. */
    public function check_out()
    {
        $employee = $this->require_auth();
        $body = $this->json_input();

        $attendanceId = isset($body['attendance_id']) ? (int) $body['attendance_id'] : null;
        $lat = isset($body['latitude']) ? (float) $body['latitude'] : null;
        $lng = isset($body['longitude']) ? (float) $body['longitude'] : null;
        $accuracy = isset($body['accuracy']) ? (float) $body['accuracy'] : null;
        $timestamp = $body['timestamp'] ?? now_datetime();
        $photoBase64 = $body['photo_base64'] ?? null;

        if (!$attendanceId || $lat === null || $lng === null) {
            return $this->json_response(array('success' => false, 'message' => 'Data tidak lengkap', 'distance' => 0, 'radius' => 0), 422);
        }

        $this->load->model('Attendance_model');
        $this->load->model('Office_model');

        $attendance = $this->Attendance_model->getById($attendanceId);

        // Ownership check (spec section 25): an employee can only check
        // out their OWN attendance record, regardless of what id they send.
        if (!$attendance || (int) $attendance['employee_id'] !== (int) $employee['id']) {
            return $this->json_response(array('success' => false, 'message' => 'Data absen masuk tidak ditemukan', 'distance' => 0, 'radius' => 0), 404);
        }

        if (!empty($attendance['check_out_time'])) {
            return $this->json_response(array('success' => false, 'message' => 'Anda sudah melakukan absen pulang hari ini', 'distance' => 0, 'radius' => 0), 409);
        }

        $office = $this->Office_model->getById((int) $attendance['office_id']);
        if (!$office) {
            return $this->json_response(array('success' => false, 'message' => 'Kantor tidak ditemukan', 'distance' => 0, 'radius' => 0), 422);
        }

        $distance = distance_meters($lat, $lng, (float) $office['latitude'], (float) $office['longitude']);
        $radius = (int) $office['check_out_radius'];

        if ($distance > $radius) {
            return $this->json_response(array(
                'success'  => false,
                'message'  => 'Lokasi berada di luar radius kantor (absen pulang)',
                'distance' => round($distance, 2),
                'radius'   => $radius,
            ), 200);
        }

        $photoFilename = $photoBase64 ? save_base64_photo($photoBase64, 'checkout_' . $employee['id']) : null;

        $this->Attendance_model->updateCheckOut($attendanceId, array(
            'check_out_time'      => $timestamp,
            'check_out_photo'     => $photoFilename,
            'check_out_latitude'  => $lat,
            'check_out_longitude' => $lng,
            'check_out_accuracy'  => $accuracy,
            'check_out_distance'  => round($distance, 2),
            'updated_at'          => now_datetime(),
        ));

        $updated = $this->Attendance_model->getById($attendanceId);

        $this->json_response(array(
            'success'    => true,
            'message'    => 'Absensi pulang berhasil',
            'distance'   => round($distance, 2),
            'radius'     => $radius,
            'attendance' => $updated,
        ), 200);
    }

    /** GET /api/attendance/today?employee_id=... (employee_id ignored — always taken from token) */
    public function today()
    {
        $employee = $this->require_auth();
        $this->load->model('Attendance_model');
        $attendance = $this->Attendance_model->getToday((int) $employee['id']);
        $this->json_response($attendance ?: null, 200);
    }

    /** GET /api/attendance/history?employee_id=... (employee_id ignored — always taken from token) */
    public function history()
    {
        $employee = $this->require_auth();
        $this->load->model('Attendance_model');
        $this->json_response($this->Attendance_model->getHistory((int) $employee['id']), 200);
    }
}

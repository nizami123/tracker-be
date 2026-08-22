<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Requests extends MY_Controller
{
    /** POST /api/requests */
    public function create()
    {
        $employee = $this->require_auth();
        $body = $this->json_input();

        $this->load->model('Request_model');

        $type = $body['type'] ?? '';
        $reason = trim($body['reason'] ?? '');
        $attachmentBase64 = $body['attachment_base64'] ?? null;
        $attachmentFilename = $body['attachment_filename'] ?? null;
        $latitude = isset($body['latitude']) ? (float) $body['latitude'] : null;
        $longitude = isset($body['longitude']) ? (float) $body['longitude'] : null;

        if (!$this->Request_model->isValidType($type)) {
            return $this->json_response(array('success' => false, 'message' => 'Jenis pengajuan tidak valid'), 422);
        }
        if (empty($reason)) {
            return $this->json_response(array('success' => false, 'message' => 'Alasan wajib diisi'), 422);
        }
        // Lampiran WAJIB diisi (via pilih file atau kamera di Android) —
        // divalidasi ulang di server, tidak cukup hanya di aplikasi.
        if (empty($attachmentBase64)) {
            return $this->json_response(array('success' => false, 'message' => 'Lampiran wajib diisi'), 422);
        }
        if ($type === 'LEAVE' && (empty($body['start_date']) || empty($body['end_date']))) {
            return $this->json_response(array('success' => false, 'message' => 'Tanggal mulai dan selesai wajib diisi'), 422);
        }
        if ($type !== 'LEAVE' && empty($body['date'])) {
            return $this->json_response(array('success' => false, 'message' => 'Tanggal wajib diisi'), 422);
        }

        $attachmentFile = save_base64_attachment($attachmentBase64, 'request_' . $employee['id'], $attachmentFilename);
        if ($attachmentFile === null) {
            return $this->json_response(array('success' => false, 'message' => 'Lampiran gagal diproses, coba lagi'), 422);
        }

        // Absen Luar Kantor (dinas luar) is auto-approved the instant it's
        // submitted — no admin action needed. Every other type still goes
        // through the normal PENDING -> admin approve/reject flow.
        $isOutsideOffice = $type === 'OUTSIDE_OFFICE';

        // office_id always taken from the employee's own record, never
        // trusted from the client body (spec section 25).
        $id = $this->Request_model->insert(array(
            'employee_id' => $employee['id'],
            'office_id'   => $employee['office_id'],
            'type'        => $type,
            'date'        => $body['date'] ?? null,
            'start_date'  => $body['start_date'] ?? null,
            'end_date'    => $body['end_date'] ?? null,
            'time'        => $body['time'] ?? null,
            'latitude'    => $latitude,
            'longitude'   => $longitude,
            'reason'      => $reason,
            'attachment'  => $attachmentFile,
            'status'      => $isOutsideOffice ? 'APPROVED' : 'PENDING',
            'approved_at' => $isOutsideOffice ? now_datetime() : null,
            'created_at'  => now_datetime(),
        ));

        if ($isOutsideOffice) {
            $this->applyOutsideOfficeCheckIn($employee, $latitude, $longitude, $attachmentBase64);
        }

        $this->json_response($this->Request_model->getById($id), 201);
    }

    /**
     * Absen Luar Kantor already counts as that day's check-in: an employee
     * who submits this in the morning is treated as already checked in, so
     * their next tap on "Absen" in the app goes straight to "Absen Pulang"
     * (the Android Home screen decides that purely from whether today's
     * attendance row already has a check_in_time — see
     * Attendance_model->alreadyCheckedInToday()).
     *
     * Never overwrites an existing check-in for today — an employee may
     * already have checked in normally at the office before submitting
     * this (e.g. checked in, then got sent out on a task later).
     */
    private function applyOutsideOfficeCheckIn(array $employee, ?float $latitude, ?float $longitude, string $attachmentBase64): void
    {
        $this->load->model('Attendance_model');

        if ($this->Attendance_model->alreadyCheckedInToday((int) $employee['id'])) {
            return;
        }

        $photoFilename = save_base64_photo($attachmentBase64, 'outside_office_' . $employee['id']);

        $this->Attendance_model->insertCheckIn(array(
            'employee_id'        => $employee['id'],
            'office_id'          => $employee['office_id'],
            'attendance_date'    => today_date(),
            'check_in_time'      => now_datetime(),
            'check_in_photo'     => $photoFilename,
            'check_in_latitude'  => $latitude,
            'check_in_longitude' => $longitude,
            'check_in_accuracy'  => null,
            'check_in_distance'  => null, // luar kantor — jarak dari kantor tidak relevan
            'status'             => 'PRESENT',
            'created_at'         => now_datetime(),
        ));
    }

    /** GET /api/requests — always scoped to the authenticated employee. */
    public function index()
    {
        $employee = $this->require_auth();
        $this->load->model('Request_model');
        $this->json_response($this->Request_model->getForEmployee((int) $employee['id']), 200);
    }
}

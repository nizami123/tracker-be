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
            'reason'      => $reason,
            'attachment'  => $attachmentFile,
            'status'      => 'PENDING',
            'created_at'  => now_datetime(),
        ));

        $this->json_response($this->Request_model->getById($id), 201);
    }

    /** GET /api/requests — always scoped to the authenticated employee. */
    public function index()
    {
        $employee = $this->require_auth();
        $this->load->model('Request_model');
        $this->json_response($this->Request_model->getForEmployee((int) $employee['id']), 200);
    }
}

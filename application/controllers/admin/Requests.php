<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

class Requests extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_request_model');
        $this->load->model('Admin_office_model');
        $this->load->model('Admin_attendance_model'); // reuse getOptionsForFilter() for the employee dropdown
    }

    /**
     * Both sidebar items "Pengajuan" and "Riwayat Pengajuan" point here.
     * "Pengajuan" links with ?status=PENDING pre-selected (still shows
     * the full filter UI so the admin can broaden it); "Riwayat
     * Pengajuan" opens with no status filter.
     */
    public function index()
    {
        $offices = $this->Admin_office_model->getAll();
        if ($this->officeScope()) {
            $offices = array_values(array_filter($offices, fn($o) => (int) $o['id'] === $this->officeScope()));
        }

        $defaultStatus = $this->input->get('status') ?: '';

        $this->render('admin/requests/index', array(
            'activeMenu'    => $defaultStatus === 'PENDING' ? 'pengajuan' : 'riwayat_pengajuan',
            'pageTitle'     => $defaultStatus === 'PENDING' ? 'Pengajuan' : 'Riwayat Pengajuan',
            'offices'       => $offices,
            'employees'     => $this->Admin_attendance_model->getOptionsForFilter($this->officeScope()),
            'defaultStatus' => $defaultStatus,
        ));
    }

    public function list_data()
    {
        $columnMap = array(
            0 => 'attendance_requests.created_at',
            1 => 'employees.name',
            2 => 'offices.name',
            3 => 'attendance_requests.type',
            5 => 'attendance_requests.status',
        );
        $dt = $this->parseDataTablesRequest($columnMap, 'attendance_requests.created_at');

        $filters = array(
            'date_from'   => $this->input->post('filter_date_from'),
            'date_to'     => $this->input->post('filter_date_to'),
            'office_id'   => $this->input->post('filter_office_id'),
            'employee_id' => $this->input->post('filter_employee_id'),
            'type'        => $this->input->post('filter_type'),
            'status'      => $this->input->post('filter_status'),
        );

        $officeId = $this->officeScope();
        $rows = $this->Admin_request_model->getPage($filters, $officeId, $dt['start'], $dt['length'], $dt['orderCol'], $dt['orderDir']);

        $this->json(array(
            'draw'            => $dt['draw'],
            'recordsTotal'    => $this->Admin_request_model->countAll($officeId),
            'recordsFiltered' => $this->Admin_request_model->countFiltered($filters, $officeId),
            'data'            => $rows,
        ));
    }

    public function detail($id)
    {
        $row = $this->Admin_request_model->getById((int) $id, $this->officeScope());
        if (!$row) return $this->json(array('success' => false, 'message' => 'Pengajuan tidak ditemukan'), 404);
        $this->json(array('success' => true, 'data' => $row));
    }

    public function approve($id)
    {
        $id = (int) $id;
        $row = $this->Admin_request_model->getById($id, $this->officeScope());
        if (!$row) return $this->json(array('success' => false, 'message' => 'Pengajuan tidak ditemukan'), 404);
        if ($row['status'] !== 'PENDING') {
            return $this->json(array('success' => false, 'message' => 'Pengajuan ini sudah diproses sebelumnya'), 409);
        }
        $this->Admin_request_model->approve($id, (int) $this->adminUser['id']);
        $this->json(array('success' => true, 'message' => 'Pengajuan disetujui'));
    }

    public function reject($id)
    {
        $id = (int) $id;
        $reason = trim((string) $this->input->post('reason'));
        if ($reason === '') {
            return $this->json(array('success' => false, 'message' => 'Alasan penolakan wajib diisi'), 422);
        }
        $row = $this->Admin_request_model->getById($id, $this->officeScope());
        if (!$row) return $this->json(array('success' => false, 'message' => 'Pengajuan tidak ditemukan'), 404);
        if ($row['status'] !== 'PENDING') {
            return $this->json(array('success' => false, 'message' => 'Pengajuan ini sudah diproses sebelumnya'), 409);
        }
        $this->Admin_request_model->reject($id, (int) $this->adminUser['id'], $reason);
        $this->json(array('success' => true, 'message' => 'Pengajuan ditolak'));
    }
}

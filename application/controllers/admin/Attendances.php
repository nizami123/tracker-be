<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

class Attendances extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_attendance_model');
        $this->load->model('Admin_office_model');
    }

    public function index()
    {
        $offices = $this->Admin_office_model->getAll();
        if ($this->officeScope()) {
            $offices = array_values(array_filter($offices, fn($o) => (int) $o['id'] === $this->officeScope()));
        }

        $this->render('admin/attendances/index', array(
            'activeMenu' => 'history_absensi',
            'pageTitle'  => 'History Absensi',
            'offices'    => $offices,
            'employees'  => $this->Admin_attendance_model->getOptionsForFilter($this->officeScope()),
            // WAJIB: filter tanggal default ke hari ini.
            'todayDate'  => date('Y-m-d'),
        ));
    }

    /** POST AJAX — DataTables server-side source. */
    public function list_data()
    {
        $columnMap = array(
            0 => 'attendances.attendance_date',
            1 => 'employees.name',
            2 => 'offices.name',
            3 => 'attendances.check_in_time',
            5 => 'attendances.check_out_time',
            7 => 'attendances.status',
        );
        $dt = $this->parseDataTablesRequest($columnMap, 'attendances.attendance_date');

        $filters = array(
            'date'        => $this->input->post('filter_date'),
            'office_id'   => $this->input->post('filter_office_id'),
            'employee_id' => $this->input->post('filter_employee_id'),
            'status'      => $this->input->post('filter_status'),
        );

        $officeId = $this->officeScope();
        $rows = $this->Admin_attendance_model->getPage($filters, $officeId, $dt['start'], $dt['length'], $dt['orderCol'], $dt['orderDir']);

        $this->json(array(
            'draw'            => $dt['draw'],
            'recordsTotal'    => $this->Admin_attendance_model->countAll($officeId),
            'recordsFiltered' => $this->Admin_attendance_model->countFiltered($filters, $officeId),
            'data'            => $rows,
        ));
    }
}

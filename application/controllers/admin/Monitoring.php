<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

class Monitoring extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_monitoring_model');
    }

    public function index()
    {
        $officeId = $this->officeScope();
        $employees = $this->Admin_monitoring_model->getActiveEmployees($officeId);
        $drivers = $this->Admin_monitoring_model->getActiveDrivers($officeId);

        $this->render('admin/monitoring/index', array(
            'activeMenu' => 'monitoring_aktif',
            'pageTitle'  => 'Tracking Aktif',
            'employees'  => $employees,
            'drivers'    => $drivers,
        ));
    }

    /**
     * GET AJAX — lightweight: only current positions + a few fields,
     * called every 30s by the map page. Never returns full polylines.
     */
    public function positions_data()
    {
        $officeId = $this->officeScope();
        $employees = $this->Admin_monitoring_model->getActiveEmployees($officeId);
        $drivers = $this->Admin_monitoring_model->getActiveDrivers($officeId);

        $this->json(array(
            'success'   => true,
            'employees' => $employees,
            'drivers'   => $drivers,
        ));
    }
}

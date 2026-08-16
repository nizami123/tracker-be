<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

/**
 * "Master Kendaraan" — read-only. See ADMIN_README.md: no standalone
 * `vehicles` table exists yet, so this derives a distinct vehicle list
 * from `vehicle_deliveries` history instead of inventing a new table.
 */
class Vehicles extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_vehicle_model');
    }

    public function index()
    {
        $this->render('admin/vehicles/index', array(
            'activeMenu' => 'master_kendaraan',
            'pageTitle'  => 'Master Kendaraan',
        ));
    }

    public function list_data()
    {
        $rows = $this->Admin_vehicle_model->getDistinctVehicles($this->officeScope());
        $this->json(array('data' => $rows));
    }

    public function history($engineNumber)
    {
        $rows = $this->Admin_vehicle_model->getHistoryForVehicle($engineNumber, $this->officeScope());
        $this->json(array('data' => $rows));
    }
}

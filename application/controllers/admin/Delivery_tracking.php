<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

class Delivery_tracking extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_delivery_model');
        $this->load->model('Admin_delivery_tracking_model');
    }

    public function index()
    {
        $this->render('admin/delivery_tracking/index', array(
            'activeMenu' => 'tracking_kendaraan',
            'pageTitle'  => 'Tracking Kendaraan',
            'rows'       => $this->Admin_delivery_tracking_model->getActiveDeliveries($this->officeScope()),
        ));
    }

    public function detail($deliveryId)
    {
        $delivery = $this->Admin_delivery_model->getById((int) $deliveryId, $this->officeScope());
        if (!$delivery) {
            show_404();
            return;
        }

        $this->render('admin/delivery_tracking/detail', array(
            'activeMenu' => 'tracking_kendaraan',
            'pageTitle'  => 'Tracking: ' . $delivery['brand'] . ' ' . $delivery['vehicle_type'],
            'delivery'   => $delivery,
        ));
    }

    public function points_data($deliveryId)
    {
        $delivery = $this->Admin_delivery_model->getById((int) $deliveryId, $this->officeScope());
        if (!$delivery) return $this->json(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);

        $points = $this->Admin_delivery_tracking_model->getTrackingPoints((int) $deliveryId);
        $this->json(array('success' => true, 'data' => $points));
    }

    public function latest_position($deliveryId)
    {
        $delivery = $this->Admin_delivery_model->getById((int) $deliveryId, $this->officeScope());
        if (!$delivery) return $this->json(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);

        $point = $this->Admin_delivery_tracking_model->getLatestPoint((int) $deliveryId);
        $this->json(array(
            'success'   => true,
            'is_active' => $delivery['status'] !== 'COMPLETED',
            'status'    => $delivery['status'],
            'point'     => $point,
        ));
    }
}

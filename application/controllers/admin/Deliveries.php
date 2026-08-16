<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

class Deliveries extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_delivery_model');
        $this->load->model('Admin_delivery_tracking_model');
        $this->load->model('Admin_office_model');
    }

    public function index()
    {
        $this->render('admin/deliveries/index', array(
            'activeMenu' => 'history_pengiriman',
            'pageTitle'  => 'History Pengiriman Kendaraan',
            'offices'    => $this->Admin_office_model->getAll(),
            'drivers'    => $this->Admin_delivery_model->getDriverOptions($this->officeScope()),
            // WAJIB: filter tanggal default ke hari ini.
            'todayDate'  => date('Y-m-d'),
        ));
    }

    public function list_data()
    {
        $columnMap = array(
            0 => 'vehicle_deliveries.created_at',
            1 => 'employees.name',
            2 => 'vehicle_deliveries.brand',
            6 => 'dest.name',
            9 => 'vehicle_deliveries.status',
        );
        $dt = $this->parseDataTablesRequest($columnMap, 'vehicle_deliveries.created_at');

        $filters = array(
            'date_from'              => $this->input->post('filter_date_from'),
            'date_to'                => $this->input->post('filter_date_to'),
            'driver_id'               => $this->input->post('filter_driver_id'),
            'destination_office_id'   => $this->input->post('filter_destination_office_id'),
            'status'                  => $this->input->post('filter_status'),
        );

        $officeId = $this->officeScope();
        $rows = $this->Admin_delivery_model->getPage($filters, $officeId, $dt['start'], $dt['length'], $dt['orderCol'], $dt['orderDir']);

        $this->json(array(
            'draw'            => $dt['draw'],
            'recordsTotal'    => $this->Admin_delivery_model->countAll($officeId),
            'recordsFiltered' => $this->Admin_delivery_model->countFiltered($filters, $officeId),
            'data'            => $rows,
        ));
    }

    public function detail($id)
    {
        $delivery = $this->Admin_delivery_model->getById((int) $id, $this->officeScope());
        if (!$delivery) {
            show_404();
            return;
        }

        $this->render('admin/deliveries/detail', array(
            'activeMenu' => 'history_pengiriman',
            'pageTitle'  => 'Detail Pengiriman',
            'delivery'   => $delivery,
            'timeline'   => $this->buildTimeline($delivery),
        ));
    }

    /**
     * Builds the delivery timeline strictly from columns that actually
     * exist and are actually filled in for this row — no placeholder/
     * dummy steps are ever added (per the spec: "Timeline harus dibuat
     * berdasarkan data yang benar-benar ada").
     */
    private function buildTimeline(array $delivery): array
    {
        $steps = array();

        if (!empty($delivery['pickup_time'])) {
            $steps[] = array('time' => $delivery['pickup_time'], 'label' => 'Foto kendaraan diambil & pengiriman dimulai', 'icon' => 'bi-camera');
        }

        $trackingModel = $this->Admin_delivery_tracking_model;
        $points = $trackingModel->getTrackingPoints((int) $delivery['id']);
        $pointCount = count($points);

        if ($pointCount > 0) {
            $steps[] = array('time' => $points[0]['recorded_at'], 'label' => 'Tracking pertama', 'icon' => 'bi-geo-alt');

            // Show at most a handful of intermediate points to keep the
            // timeline readable for long deliveries — the map (Tracking
            // Kendaraan page) is where the full route is inspected.
            $middle = array_slice($points, 1, -1);
            if (count($middle) > 6) {
                $shown = array_merge(array_slice($middle, 0, 3), array_slice($middle, -3));
                $skipped = count($middle) - 6;
                foreach (array_slice($shown, 0, 3) as $p) {
                    $steps[] = array('time' => $p['recorded_at'], 'label' => 'Tracking diperbarui', 'icon' => 'bi-arrow-repeat');
                }
                $steps[] = array('time' => null, 'label' => "... {$skipped} titik tracking lainnya ...", 'icon' => 'bi-three-dots');
                foreach (array_slice($shown, 3) as $p) {
                    $steps[] = array('time' => $p['recorded_at'], 'label' => 'Tracking diperbarui', 'icon' => 'bi-arrow-repeat');
                }
            } else {
                foreach ($middle as $p) {
                    $steps[] = array('time' => $p['recorded_at'], 'label' => 'Tracking diperbarui', 'icon' => 'bi-arrow-repeat');
                }
            }

            if ($pointCount > 1) {
                $steps[] = array('time' => $points[$pointCount - 1]['recorded_at'], 'label' => 'Titik tracking terakhir sebelum tiba', 'icon' => 'bi-geo-alt-fill');
            }
        }

        if (!empty($delivery['arrival_time'])) {
            $steps[] = array('time' => $delivery['arrival_time'], 'label' => 'Masuk radius kantor tujuan', 'icon' => 'bi-pin-map-fill');
            $steps[] = array('time' => $delivery['arrival_time'], 'label' => 'Foto kendaraan saat tiba', 'icon' => 'bi-camera-fill');
        }

        if ($delivery['status'] === 'COMPLETED' && !empty($delivery['updated_at'])) {
            $steps[] = array('time' => $delivery['updated_at'], 'label' => 'Pengiriman selesai', 'icon' => 'bi-check-circle-fill');
        }

        // Sort chronologically — the array is built roughly in order
        // already, but this keeps it correct even with edge-case timing.
        usort($steps, function ($a, $b) {
            if ($a['time'] === null) return 1;
            if ($b['time'] === null) return -1;
            return strcmp($a['time'], $b['time']);
        });

        return $steps;
    }
}

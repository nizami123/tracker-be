<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Delivery_tracking extends MY_Controller
{
    private function require_driver(): array
    {
        $employee = $this->require_auth();
        if ($employee['role'] !== 'DRIVER') {
            $this->json_response(array('success' => false, 'message' => 'Fitur ini hanya untuk akun Driver'), 403);
            exit;
        }
        return $employee;
    }

    /** POST /api/delivery-tracking/sync — mirrors Tracking::sync(). */
    public function sync()
    {
        $driver = $this->require_driver();
        $body = $this->json_input();
        $points = $body['points'] ?? array();

        if (!is_array($points) || empty($points)) {
            return $this->json_response(array('success' => true, 'synced_ids' => array()), 200);
        }

        $this->load->model('Delivery_model');
        $this->load->model('DeliveryTracking_model');

        $validRows = array();
        $syncedIds = array();
        $checkedDelivery = array();

        foreach ($points as $point) {
            $deliveryId = (int) ($point['delivery_id'] ?? 0);
            $localId = $point['localId'] ?? null;
            if (!$deliveryId || $localId === null) continue;

            if (!isset($checkedDelivery[$deliveryId])) {
                $checkedDelivery[$deliveryId] =
                    $this->Delivery_model->belongsToDriver($deliveryId, (int) $driver['id']);
            }
            if (!$checkedDelivery[$deliveryId]) continue; // not this driver's delivery — skip silently

            $validRows[] = array(
                'delivery_id'   => $deliveryId,
                'driver_id'     => $driver['id'],
                'latitude'      => (float) ($point['latitude'] ?? 0),
                'longitude'     => (float) ($point['longitude'] ?? 0),
                'accuracy'      => $point['accuracy'] ?? null,
                'speed'         => $point['speed'] ?? null,
                'bearing'       => $point['bearing'] ?? null,
                'battery_level' => $point['battery_level'] ?? null,
                'recorded_at'   => $point['recorded_at'] ?? now_datetime(),
            );
            $syncedIds[] = $localId;
        }

        $this->DeliveryTracking_model->insertBatch($validRows);

        $this->json_response(array('success' => true, 'synced_ids' => $syncedIds), 200);
    }

    /** GET /api/delivery-tracking/{delivery_id} */
    public function for_delivery($deliveryId)
    {
        $driver = $this->require_driver();
        $this->load->model('Delivery_model');
        $this->load->model('DeliveryTracking_model');

        if (!$this->Delivery_model->belongsToDriver((int) $deliveryId, (int) $driver['id'])) {
            return $this->json_response(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);
        }

        $this->json_response($this->DeliveryTracking_model->getForDelivery((int) $deliveryId), 200);
    }
}

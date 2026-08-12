<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

/**
 * Fitur Pengiriman Kendaraan — khusus role DRIVER.
 *
 * Sama seperti fitur absensi, role TIDAK boleh dipercaya dari client:
 * setiap endpoint di sini memanggil require_driver() yang membaca role
 * dari employees.role (data server), bukan dari apa pun yang dikirim
 * Android. Karyawan dengan role lain akan ditolak dengan 403 meskipun
 * mereka memodifikasi request secara manual.
 */
class Deliveries extends MY_Controller
{
    private function require_driver(): array
    {
        $employee = $this->require_auth();
        if ($employee['role'] !== 'DRIVER') {
            $this->json_response(array(
                'success' => false,
                'message' => 'Fitur ini hanya untuk akun Driver',
            ), 403);
            exit;
        }
        return $employee;
    }

    /**
     * POST /api/deliveries/start
     * Nomor mesin & nomor rangka WAJIB (kendaraan baru belum punya
     * plat nomor, jadi plat nomor sengaja TIDAK dipakai sebagai
     * identitas kendaraan di sini).
     */
    public function start()
    {
        $driver = $this->require_driver();
        $body = $this->json_input();

        $this->load->model('Delivery_model');
        $this->load->model('Office_model');

        // Satu driver hanya boleh punya satu pengiriman aktif sekaligus.
        $existing = $this->Delivery_model->getActiveForDriver((int) $driver['id']);
        if ($existing) {
            return $this->json_response(array(
                'success' => false,
                'message' => 'Anda masih punya pengiriman yang sedang berjalan',
            ), 409);
        }

        $engineNumber = trim($body['engine_number'] ?? '');
        $chassisNumber = trim($body['chassis_number'] ?? '');
        $brand = trim($body['brand'] ?? '');
        $vehicleType = trim($body['vehicle_type'] ?? '');
        $color = trim($body['color'] ?? '');
        $destinationOfficeId = isset($body['destination_office_id']) ? (int) $body['destination_office_id'] : null;

        if (empty($engineNumber) || empty($chassisNumber) || empty($brand) || empty($vehicleType) || empty($color)) {
            return $this->json_response(array(
                'success' => false,
                'message' => 'Nomor mesin, nomor rangka, merk, tipe, dan warna wajib diisi',
            ), 422);
        }

        $office = $destinationOfficeId ? $this->Office_model->getById($destinationOfficeId) : null;
        if (!$office) {
            return $this->json_response(array('success' => false, 'message' => 'Tujuan pengiriman tidak valid'), 422);
        }

        $photoFilename = !empty($body['pickup_photo_base64'])
            ? save_base64_photo($body['pickup_photo_base64'], 'delivery_pickup_' . $driver['id'])
            : null;

        $id = $this->Delivery_model->insertStart(array(
            'driver_id'              => $driver['id'],
            'engine_number'          => $engineNumber,
            'chassis_number'         => $chassisNumber,
            'brand'                  => $brand,
            'vehicle_type'           => $vehicleType,
            'color'                  => $color,
            'destination_office_id'  => $office['id'],
            'notes'                  => $body['notes'] ?? null,
            'pickup_photo'           => $photoFilename,
            'pickup_time'            => $body['timestamp'] ?? now_datetime(),
            'pickup_latitude'        => $body['pickup_latitude'] ?? null,
            'pickup_longitude'       => $body['pickup_longitude'] ?? null,
            'status'                 => 'IN_PROGRESS',
            'created_at'             => now_datetime(),
        ));

        $this->json_response(array(
            'success'  => true,
            'message'  => 'Pengiriman dimulai',
            'delivery' => $this->Delivery_model->getById($id),
        ), 200);
    }

    /**
     * POST /api/deliveries/complete
     * Body: { delivery_id, arrival_latitude, arrival_longitude, accuracy,
     *         arrival_photo_base64, notes, timestamp }
     *
     * Server re-validates the arrival radius itself (never trusts a
     * client-reported "I've arrived" flag) — same principle as
     * attendance check-in/out.
     */
    public function complete()
    {
        $driver = $this->require_driver();
        $body = $this->json_input();

        $deliveryId = isset($body['delivery_id']) ? (int) $body['delivery_id'] : null;
        $lat = isset($body['arrival_latitude']) ? (float) $body['arrival_latitude'] : null;
        $lng = isset($body['arrival_longitude']) ? (float) $body['arrival_longitude'] : null;
        $accuracy = isset($body['accuracy']) ? (float) $body['accuracy'] : null;
        $timestamp = $body['timestamp'] ?? now_datetime();
        $photoBase64 = $body['arrival_photo_base64'] ?? null;

        if (!$deliveryId || $lat === null || $lng === null) {
            return $this->json_response(array('success' => false, 'message' => 'Data tidak lengkap'), 422);
        }

        $this->load->model('Delivery_model');
        $this->load->model('Office_model');

        $delivery = $this->Delivery_model->getById($deliveryId);
        if (!$delivery || (int) $delivery['driver_id'] !== (int) $driver['id']) {
            return $this->json_response(array('success' => false, 'message' => 'Pengiriman tidak ditemukan'), 404);
        }
        if ($delivery['status'] === 'COMPLETED') {
            return $this->json_response(array('success' => false, 'message' => 'Pengiriman ini sudah selesai'), 409);
        }
        if (empty($photoBase64)) {
            return $this->json_response(array('success' => false, 'message' => 'Foto kendaraan saat tiba wajib diambil'), 422);
        }

        $office = $this->Office_model->getById((int) $delivery['destination_office_id']);
        // Reuses the office's own check-in radius as the "arrival"
        // threshold — no separate delivery-specific radius needed.
        $radius = $office ? (int) $office['check_in_radius'] : 100;
        $distance = $office ? distance_meters($lat, $lng, (float) $office['latitude'], (float) $office['longitude']) : null;

        if ($office && $distance > $radius) {
            return $this->json_response(array(
                'success'  => false,
                'message'  => 'Anda belum berada di area tujuan',
                'distance' => round($distance, 2),
                'radius'   => $radius,
            ), 200);
        }

        $photoFilename = save_base64_photo($photoBase64, 'delivery_arrival_' . $driver['id']);

        $this->Delivery_model->updateStatus($deliveryId, array(
            'arrival_photo'     => $photoFilename,
            'arrival_time'      => $timestamp,
            'arrival_latitude'  => $lat,
            'arrival_longitude' => $lng,
            'arrival_distance'  => $distance !== null ? round($distance, 2) : null,
            'arrival_notes'     => $body['notes'] ?? null,
            'status'            => 'COMPLETED',
            'updated_at'        => now_datetime(),
        ));

        $this->json_response(array(
            'success'  => true,
            'message'  => 'Pengiriman selesai',
            'distance' => $distance !== null ? round($distance, 2) : 0,
            'radius'   => $radius,
            'delivery' => $this->Delivery_model->getById($deliveryId),
        ), 200);
    }

    /** GET /api/deliveries/active — current IN_PROGRESS/ARRIVED delivery for this driver, or null. */
    public function active()
    {
        $driver = $this->require_driver();
        $this->load->model('Delivery_model');
        $delivery = $this->Delivery_model->getActiveForDriver((int) $driver['id']);
        $this->json_response($delivery ?: null, 200);
    }

    /** GET /api/deliveries/history */
    public function history()
    {
        $driver = $this->require_driver();
        $this->load->model('Delivery_model');
        $this->json_response($this->Delivery_model->getHistoryForDriver((int) $driver['id']), 200);
    }
}

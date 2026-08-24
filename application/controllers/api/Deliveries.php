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

        if (empty($engineNumber) || empty($chassisNumber) || empty($brand) || empty($vehicleType) || empty($color)) {
            return $this->json_response(array(
                'success' => false,
                'message' => 'Nomor mesin, nomor rangka, merk, tipe, dan warna wajib diisi',
            ), 422);
        }

        // Tujuan pengiriman: pilih salah satu kantor terdaftar
        // (destination_office_id), ATAU tentukan tujuan bebas (nama +
        // lokasi GPS) sebagai pemberhentian terakhir — dua-duanya
        // disimpan ke kolom destination_* yang sama (disalin dari data
        // kantor kalau dari daftar), supaya validasi kedatangan & respons
        // API tidak perlu tahu bedanya lagi setelah ini.
        $destinationOfficeId = !empty($body['destination_office_id']) ? (int) $body['destination_office_id'] : null;
        $destinationName = trim($body['destination_name'] ?? '');
        $destinationAddress = trim($body['destination_address'] ?? '');
        $destinationLat = isset($body['destination_latitude']) && $body['destination_latitude'] !== ''
            ? (float) $body['destination_latitude'] : null;
        $destinationLng = isset($body['destination_longitude']) && $body['destination_longitude'] !== ''
            ? (float) $body['destination_longitude'] : null;
        $destinationRadius = isset($body['destination_radius']) ? (int) $body['destination_radius'] : null;

        $office = null;
        if ($destinationOfficeId) {
            $office = $this->Office_model->getById($destinationOfficeId);
            if (!$office) {
                return $this->json_response(array('success' => false, 'message' => 'Kantor tujuan tidak valid'), 422);
            }
        } elseif ($destinationLat === null || $destinationLng === null || $destinationName === '') {
            return $this->json_response(array(
                'success' => false,
                'message' => 'Tujuan pengiriman wajib diisi: pilih kantor atau tentukan nama & lokasi tujuan',
            ), 422);
        }

        $photoFilename = !empty($body['pickup_photo_base64'])
            ? save_base64_photo($body['pickup_photo_base64'], 'delivery_pickup_' . $driver['id'])
            : null;

        $id = $this->Delivery_model->insertStart(array(
            'driver_id'               => $driver['id'],
            'engine_number'           => $engineNumber,
            'chassis_number'          => $chassisNumber,
            'brand'                   => $brand,
            'vehicle_type'            => $vehicleType,
            'color'                   => $color,
            'destination_office_id'   => $office['id'] ?? null,
            'destination_name'        => $office['name'] ?? $destinationName,
            'destination_address'     => $office['address'] ?? ($destinationAddress ?: null),
            'destination_latitude'    => $office ? $office['latitude'] : $destinationLat,
            'destination_longitude'   => $office ? $office['longitude'] : $destinationLng,
            'destination_radius'      => $office ? (int) $office['check_in_radius'] : ($destinationRadius ?: 100),
            'notes'                   => $body['notes'] ?? null,
            'pickup_photo'            => $photoFilename,
            'pickup_time'             => $body['timestamp'] ?? now_datetime(),
            'pickup_latitude'         => $body['pickup_latitude'] ?? null,
            'pickup_longitude'        => $body['pickup_longitude'] ?? null,
            'status'                  => 'IN_PROGRESS',
            'created_at'              => now_datetime(),
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

        // Jarak & radius kedatangan divalidasi terhadap destination_latitude/
        // longitude/radius yang tersimpan sejak "Mulai Pengiriman" — berlaku
        // sama persis baik tujuannya kantor terdaftar maupun tujuan bebas
        // yang driver tentukan sendiri, tidak perlu tahu bedanya lagi di sini.
        $radius = (int) ($delivery['destination_radius'] ?: 100);
        $distance = (isset($delivery['destination_latitude']) && isset($delivery['destination_longitude'])
            && $delivery['destination_latitude'] !== null && $delivery['destination_longitude'] !== null)
            ? distance_meters($lat, $lng, (float) $delivery['destination_latitude'], (float) $delivery['destination_longitude'])
            : null;

        if ($distance !== null && $distance > $radius) {
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

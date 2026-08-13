<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class DeliveryTracking_model extends CI_Model
{
    public function insertBatch(array $rows): void
    {
        if (empty($rows)) return;
        // Same INSERT IGNORE pattern as Tracking_model — tolerates a
        // point being re-sent after a flaky network ack without
        // duplicating rows (unique key: delivery_id + recorded_at).
        foreach ($rows as $row) {
            $this->db->query(
                "INSERT IGNORE INTO vehicle_delivery_tracking
                    (delivery_id, driver_id, latitude, longitude,
                     accuracy, speed, bearing, battery_level, recorded_at, server_received_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array(
                    $row['delivery_id'], $row['driver_id'],
                    $row['latitude'], $row['longitude'], $row['accuracy'],
                    $row['speed'], $row['bearing'], $row['battery_level'],
                    $row['recorded_at'], now_datetime(), now_datetime(),
                )
            );
        }
    }

    public function getForDelivery(int $deliveryId): array
    {
        return $this->db->where('delivery_id', $deliveryId)
            ->order_by('recorded_at', 'ASC')
            ->get('vehicle_delivery_tracking')
            ->result_array();
    }
}

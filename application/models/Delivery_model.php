<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Delivery_model extends CI_Model
{
    /** A driver can only have ONE delivery in progress/arrived at a time. */
    public function getActiveForDriver(int $driverId)
    {
        return $this->db->where('driver_id', $driverId)
            ->where_in('status', array('IN_PROGRESS', 'ARRIVED'))
            ->order_by('created_at', 'DESC')
            ->get('vehicle_deliveries')
            ->row_array();
    }

    public function getHistoryForDriver(int $driverId, int $limit = 50): array
    {
        return $this->db->where('driver_id', $driverId)
            ->order_by('created_at', 'DESC')
            ->limit($limit)
            ->get('vehicle_deliveries')
            ->result_array();
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('vehicle_deliveries')->row_array();
    }

    public function insertStart(array $data): int
    {
        $this->db->insert('vehicle_deliveries', $data);
        return (int) $this->db->insert_id();
    }

    public function updateStatus(int $id, array $data): void
    {
        $this->db->where('id', $id)->update('vehicle_deliveries', $data);
    }

    public function belongsToDriver(int $deliveryId, int $driverId): bool
    {
        $row = $this->db->where('id', $deliveryId)
            ->where('driver_id', $driverId)
            ->get('vehicle_deliveries')
            ->row_array();
        return (bool) $row;
    }
}

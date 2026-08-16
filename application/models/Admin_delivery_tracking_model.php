<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_delivery_tracking_model extends CI_Model
{
    public function getTrackingPoints(int $deliveryId): array
    {
        return $this->db->where('delivery_id', $deliveryId)
            ->order_by('recorded_at', 'ASC')
            ->get('vehicle_delivery_tracking')
            ->result_array();
    }

    public function getLatestPoint(int $deliveryId)
    {
        return $this->db->where('delivery_id', $deliveryId)
            ->order_by('recorded_at', 'DESC')
            ->limit(1)
            ->get('vehicle_delivery_tracking')
            ->row_array();
    }

    public function countPoints(int $deliveryId): int
    {
        return (int) $this->db->where('delivery_id', $deliveryId)->count_all_results('vehicle_delivery_tracking');
    }

    /** For the Monitoring Aktif page + "Tracking Kendaraan" active list: deliveries not yet COMPLETED. */
    public function getActiveDeliveries(?int $officeId): array
    {
        $this->db->select("
                vehicle_deliveries.*,
                employees.name as driver_name,
                employees.employee_code as driver_code,
                dest.name as destination_office_name,
                (SELECT COUNT(*) FROM vehicle_delivery_tracking WHERE vehicle_delivery_tracking.delivery_id = vehicle_deliveries.id) as tracking_count,
                (SELECT MAX(recorded_at) FROM vehicle_delivery_tracking WHERE vehicle_delivery_tracking.delivery_id = vehicle_deliveries.id) as last_point_at
            ")
            ->from('vehicle_deliveries')
            ->join('employees', 'employees.id = vehicle_deliveries.driver_id')
            ->join('offices as dest', 'dest.id = vehicle_deliveries.destination_office_id', 'left')
            ->where_in('vehicle_deliveries.status', array('IN_PROGRESS', 'ARRIVED'))
            ->order_by('vehicle_deliveries.created_at', 'DESC');

        if ($officeId) $this->db->where('employees.office_id', $officeId);

        return $this->db->get()->result_array();
    }
}

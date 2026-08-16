<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * There is NO standalone `vehicles` master table in the schema —
 * vehicle identity (engine/chassis number, brand, type, color) is
 * captured per-delivery in `vehicle_deliveries`. This model derives a
 * distinct vehicle listing from that history instead of inventing a
 * new table (see ADMIN_README.md for the tradeoff/decision).
 */
class Admin_vehicle_model extends CI_Model
{
    public function getDistinctVehicles(?int $officeId): array
    {
        $this->db->select('
                vehicle_deliveries.engine_number,
                vehicle_deliveries.chassis_number,
                MAX(vehicle_deliveries.brand) as brand,
                MAX(vehicle_deliveries.vehicle_type) as vehicle_type,
                MAX(vehicle_deliveries.color) as color,
                COUNT(vehicle_deliveries.id) as total_deliveries,
                MAX(vehicle_deliveries.created_at) as last_delivery_at
            ')
            ->from('vehicle_deliveries')
            ->group_by('vehicle_deliveries.engine_number, vehicle_deliveries.chassis_number')
            ->order_by('last_delivery_at', 'DESC');

        if ($officeId) {
            $this->db->join('employees', 'employees.id = vehicle_deliveries.driver_id')
                ->where('employees.office_id', $officeId);
        }

        return $this->db->get()->result_array();
    }

    /** Full delivery history for one specific vehicle (by its engine number). */
    public function getHistoryForVehicle(string $engineNumber, ?int $officeId): array
    {
        $this->db->select('vehicle_deliveries.*, employees.name as driver_name, offices.name as destination_office_name')
            ->from('vehicle_deliveries')
            ->join('employees', 'employees.id = vehicle_deliveries.driver_id')
            ->join('offices', 'offices.id = vehicle_deliveries.destination_office_id', 'left')
            ->where('vehicle_deliveries.engine_number', $engineNumber)
            ->order_by('vehicle_deliveries.created_at', 'DESC');

        if ($officeId) $this->db->where('employees.office_id', $officeId);

        return $this->db->get()->result_array();
    }
}

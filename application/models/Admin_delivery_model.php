<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_delivery_model extends CI_Model
{
    /**
     * $officeId scopes by the DRIVER's home office (employees.office_id)
     * — same convention as the dashboard stat, see Admin_dashboard_model.
     * "Kantor Tujuan" is a separate, independent filter field
     * ($filters['destination_office_id']), not the scoping boundary.
     */
    private function baseQuery(array $filters, ?int $officeId)
    {
        $this->db->select("
                vehicle_deliveries.*,
                employees.name as driver_name,
                employees.employee_code as driver_code,
                dest.name as destination_office_name,
                driver_office.name as driver_office_name
            ")
            ->from('vehicle_deliveries')
            ->join('employees', 'employees.id = vehicle_deliveries.driver_id')
            ->join('offices as dest', 'dest.id = vehicle_deliveries.destination_office_id', 'left')
            ->join('offices as driver_office', 'driver_office.id = employees.office_id', 'left');

        if (!empty($filters['date_from'])) {
            $this->db->where('DATE(vehicle_deliveries.created_at) >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('DATE(vehicle_deliveries.created_at) <=', $filters['date_to']);
        }
        if (!empty($filters['driver_id'])) {
            $this->db->where('vehicle_deliveries.driver_id', (int) $filters['driver_id']);
        }
        if (!empty($filters['destination_office_id'])) {
            $this->db->where('vehicle_deliveries.destination_office_id', (int) $filters['destination_office_id']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('vehicle_deliveries.status', $filters['status']);
        }
        if ($officeId) {
            $this->db->where('employees.office_id', $officeId);
        }

        return $this->db;
    }

    public function countFiltered(array $filters, ?int $officeId): int
    {
        return (int) $this->baseQuery($filters, $officeId)->count_all_results();
    }

    public function countAll(?int $officeId): int
    {
        $this->db->from('vehicle_deliveries');
        if ($officeId) {
            $this->db->join('employees', 'employees.id = vehicle_deliveries.driver_id')->where('employees.office_id', $officeId);
        }
        return (int) $this->db->count_all_results();
    }

    public function getPage(array $filters, ?int $officeId, int $start, int $length, string $orderCol, string $orderDir): array
    {
        $q = $this->baseQuery($filters, $officeId)->order_by($orderCol, $orderDir);
        if ($length > 0) $q->limit($length, $start);
        return $q->get()->result_array();
    }

    public function getById(int $id, ?int $officeId)
    {
        $this->db->select("
                vehicle_deliveries.*,
                employees.name as driver_name,
                employees.employee_code as driver_code,
                employees.phone as driver_phone,
                dest.name as destination_office_name,
                dest.address as destination_office_address,
                dest.latitude as destination_latitude,
                dest.longitude as destination_longitude,
                dest.check_in_radius as destination_radius
            ")
            ->from('vehicle_deliveries')
            ->join('employees', 'employees.id = vehicle_deliveries.driver_id')
            ->join('offices as dest', 'dest.id = vehicle_deliveries.destination_office_id', 'left')
            ->where('vehicle_deliveries.id', $id);
        if ($officeId) $this->db->where('employees.office_id', $officeId);
        return $this->db->get()->row_array();
    }

    public function getDriverOptions(?int $officeId): array
    {
        $q = $this->db->select('id, name, employee_code')->where('role', 'DRIVER')->order_by('name', 'ASC');
        if ($officeId) $q->where('office_id', $officeId);
        return $q->get('employees')->result_array();
    }
}

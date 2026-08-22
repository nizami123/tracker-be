<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Request_model extends CI_Model
{
    private $validTypes = array('LATE', 'CHECK_IN', 'CHECK_OUT', 'LEAVE', 'OUTSIDE_OFFICE');

    public function isValidType(string $type): bool
    {
        return in_array($type, $this->validTypes, true);
    }

    public function insert(array $data): int
    {
        $this->db->insert('attendance_requests', $data);
        return (int) $this->db->insert_id();
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('attendance_requests')->row_array();
    }

    public function getForEmployee(int $employeeId): array
    {
        return $this->db->where('employee_id', $employeeId)
            ->order_by('created_at', 'DESC')
            ->get('attendance_requests')
            ->result_array();
    }
}

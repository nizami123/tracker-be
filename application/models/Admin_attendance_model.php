<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_attendance_model extends CI_Model
{
    /**
     * $filters keys (all optional except where noted):
     *   date, office_id, employee_id, status
     * $officeId: ADMIN_KANTOR scope (null for SUPER_ADMIN = no restriction).
     */
    private function baseQuery(array $filters, ?int $officeId)
    {
        $this->db->select("
                attendances.*,
                employees.name as employee_name,
                employees.employee_code,
                employees.nip,
                offices.name as office_name,
                (SELECT COUNT(*) FROM attendance_tracking WHERE attendance_tracking.attendance_id = attendances.id) as tracking_count
            ")
            ->from('attendances')
            ->join('employees', 'employees.id = attendances.employee_id')
            ->join('offices', 'offices.id = attendances.office_id');

        if (!empty($filters['date'])) {
            $this->db->where('attendances.attendance_date', $filters['date']);
        }
        if (!empty($filters['office_id'])) {
            $this->db->where('attendances.office_id', (int) $filters['office_id']);
        }
        if (!empty($filters['employee_id'])) {
            $this->db->where('attendances.employee_id', (int) $filters['employee_id']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('attendances.status', $filters['status']);
        }
        // ADMIN_KANTOR scope always wins over anything a client sends,
        // even if they somehow post a different office_id.
        if ($officeId) {
            $this->db->where('attendances.office_id', $officeId);
        }

        return $this->db;
    }

    public function countFiltered(array $filters, ?int $officeId): int
    {
        return (int) $this->baseQuery($filters, $officeId)->count_all_results();
    }

    public function countAll(?int $officeId): int
    {
        $q = $this->db->from('attendances');
        if ($officeId) $q->where('office_id', $officeId);
        return (int) $q->count_all_results();
    }

    public function getPage(array $filters, ?int $officeId, int $start, int $length, string $orderCol, string $orderDir): array
    {
        $q = $this->baseQuery($filters, $officeId)
            ->order_by($orderCol, $orderDir);
        if ($length > 0) $q->limit($length, $start);
        return $q->get()->result_array();
    }

    public function getOptionsForFilter(?int $officeId): array
    {
        $q = $this->db->select('id, name, employee_code')->where_in('role', array('EMPLOYEE', 'DRIVER'))->order_by('name', 'ASC');
        if ($officeId) $q->where('office_id', $officeId);
        return $q->get('employees')->result_array();
    }
}

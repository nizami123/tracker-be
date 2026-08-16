<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_request_model extends CI_Model
{
    /**
     * $filters keys: date_from, date_to, office_id, employee_id, type, status.
     * Date range matches BOTH single-date requests (LATE/CHECK_IN/CHECK_OUT,
     * using `date`) and LEAVE requests (using `start_date`/`end_date`) via
     * a proper range-overlap check, not just an exact-date match.
     */
    private function baseQuery(array $filters, ?int $officeId)
    {
        $this->db->select("
                attendance_requests.*,
                employees.name as employee_name,
                employees.employee_code,
                offices.name as office_name,
                approver.name as approved_by_name
            ")
            ->from('attendance_requests')
            ->join('employees', 'employees.id = attendance_requests.employee_id')
            ->join('offices', 'offices.id = attendance_requests.office_id')
            ->join('employees as approver', 'approver.id = attendance_requests.approved_by', 'left');

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $from = $filters['date_from'] ?: '0000-01-01';
            $to = $filters['date_to'] ?: '9999-12-31';
            $this->db->group_start()
                ->group_start()
                    ->where('attendance_requests.type !=', 'LEAVE')
                    ->where('attendance_requests.date >=', $from)
                    ->where('attendance_requests.date <=', $to)
                ->group_end()
                ->or_group_start()
                    ->where('attendance_requests.type', 'LEAVE')
                    ->where('attendance_requests.start_date <=', $to)
                    ->where('attendance_requests.end_date >=', $from)
                ->group_end()
            ->group_end();
        }
        if (!empty($filters['office_id'])) {
            $this->db->where('attendance_requests.office_id', (int) $filters['office_id']);
        }
        if (!empty($filters['employee_id'])) {
            $this->db->where('attendance_requests.employee_id', (int) $filters['employee_id']);
        }
        if (!empty($filters['type'])) {
            $this->db->where('attendance_requests.type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('attendance_requests.status', $filters['status']);
        }
        if ($officeId) {
            $this->db->where('attendance_requests.office_id', $officeId);
        }

        return $this->db;
    }

    public function countFiltered(array $filters, ?int $officeId): int
    {
        return (int) $this->baseQuery($filters, $officeId)->count_all_results();
    }

    public function countAll(?int $officeId): int
    {
        $q = $this->db->from('attendance_requests');
        if ($officeId) $q->where('office_id', $officeId);
        return (int) $q->count_all_results();
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
                attendance_requests.*,
                employees.name as employee_name,
                employees.employee_code,
                employees.nip,
                offices.name as office_name,
                approver.name as approved_by_name
            ")
            ->from('attendance_requests')
            ->join('employees', 'employees.id = attendance_requests.employee_id')
            ->join('offices', 'offices.id = attendance_requests.office_id')
            ->join('employees as approver', 'approver.id = attendance_requests.approved_by', 'left')
            ->where('attendance_requests.id', $id);
        if ($officeId) $this->db->where('attendance_requests.office_id', $officeId);
        return $this->db->get()->row_array();
    }

    public function approve(int $id, int $adminId): void
    {
        $this->db->where('id', $id)->update('attendance_requests', array(
            'status'      => 'APPROVED',
            'approved_by' => $adminId,
            'approved_at' => now_datetime(),
            'rejection_reason' => null,
            'updated_at'  => now_datetime(),
        ));
    }

    public function reject(int $id, int $adminId, string $reason): void
    {
        $this->db->where('id', $id)->update('attendance_requests', array(
            'status'      => 'REJECTED',
            'approved_by' => $adminId,
            'approved_at' => now_datetime(),
            'rejection_reason' => $reason,
            'updated_at'  => now_datetime(),
        ));
    }
}

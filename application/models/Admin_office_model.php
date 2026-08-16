<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_office_model extends CI_Model
{
    public function getAll(): array
    {
        return $this->db->order_by('name', 'ASC')->get('offices')->result_array();
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('offices')->row_array();
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $q = $this->db->where('code', $code);
        if ($exceptId) $q->where('id !=', $exceptId);
        return $q->count_all_results('offices') > 0;
    }

    public function insert(array $data): int
    {
        $data['created_at'] = now_datetime();
        $this->db->insert('offices', $data);
        return (int) $this->db->insert_id();
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = now_datetime();
        $this->db->where('id', $id)->update('offices', $data);
    }

    public function toggleStatus(int $id): void
    {
        $office = $this->getById($id);
        if (!$office) return;
        $newStatus = $office['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $this->update($id, array('status' => $newStatus));
    }

    /** Employees still assigned to this office block a hard delete (FK constraint would fail anyway). */
    public function hasEmployees(int $id): bool
    {
        return $this->db->where('office_id', $id)->count_all_results('employees') > 0;
    }

    public function delete(int $id): bool
    {
        if ($this->hasEmployees($id)) return false;
        $this->db->where('id', $id)->delete('offices');
        return true;
    }
}

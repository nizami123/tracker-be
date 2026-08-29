<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Employee_model extends CI_Model
{
    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('employees')->row_array();
    }

    /** Employee-facing view: never expose the password hash. */
    public function toPublic(array $employee): array
    {
        unset($employee['password']);
        return $employee;
    }

    /** Used by Auth::change_password() after the current password has been verified. */
    public function updatePassword(int $employeeId, string $bcryptHash): void
    {
        $this->db->where('id', $employeeId)->update('employees', array(
            'password'   => $bcryptHash,
            'updated_at' => now_datetime(),
        ));
    }
}

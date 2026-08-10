<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth_model extends CI_Model
{
    private $tokenTtlHours = 24 * 30; // 30 days, similar to a "stay logged in" mobile session

    public function findEmployeeByEmail(string $email)
    {
        return $this->db->where('email', $email)
            ->where('status', 'ACTIVE')
            ->get('employees')
            ->row_array();
    }

    public function createToken(int $employeeId, ?string $deviceId = null): string
    {
        $token = bin2hex(random_bytes(32));
        $this->db->insert('auth_tokens', array(
            'employee_id' => $employeeId,
            'token'       => $token,
            'device_id'   => $deviceId,
            'created_at'  => now_datetime(),
            'expires_at'  => date('Y-m-d H:i:s', strtotime("+{$this->tokenTtlHours} hours")),
        ));
        return $token;
    }

    /** Returns the employee row for a valid, non-expired token, or null. */
    public function getEmployeeByToken(string $token)
    {
        $row = $this->db->select('employees.*')
            ->from('auth_tokens')
            ->join('employees', 'employees.id = auth_tokens.employee_id')
            ->where('auth_tokens.token', $token)
            ->where('auth_tokens.expires_at >=', now_datetime())
            ->where('employees.status', 'ACTIVE')
            ->get()
            ->row_array();

        return $row ?: null;
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Web admin login. Deliberately separate from Auth_model (which issues
 * bearer tokens for the Android app) — the web admin uses CodeIgniter's
 * native PHP session instead, since it's a browser, not a mobile API
 * client. Both still authenticate against the SAME `employees` table
 * and the SAME bcrypt password hashes; no new table is created.
 */
class Admin_auth_model extends CI_Model
{
    /** Only SUPER_ADMIN and ADMIN_KANTOR may log into the web admin. */
    public function findAdminByEmail(string $email)
    {
        return $this->db->where('email', $email)
            ->where('status', 'ACTIVE')
            ->where_in('role', array('SUPER_ADMIN', 'ADMIN_KANTOR'))
            ->get('employees')
            ->row_array();
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('employees')->row_array();
    }
}

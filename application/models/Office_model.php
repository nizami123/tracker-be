<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Office_model extends CI_Model
{
    public function getActive(): array
    {
        return $this->db->where('status', 'ACTIVE')->get('offices')->result_array();
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('offices')->row_array();
    }
}

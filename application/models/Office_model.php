<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Office_model extends CI_Model
{
    public function getActive(): array
    {
        $rows = $this->db->where('status', 'ACTIVE')->get('offices')->result_array();
        foreach ($rows as &$r) {
            $code = normalize_tz_code($r['timezone'] ?? null);
            $r['timezone']       = $code;                  // "WIB" | "WITA" | "WIT" (kolom offices.timezone)
            $r['timezone_id']    = tz_code_to_php($code);  // nama timezone IANA, mis. "Asia/Makassar"
            $r['timezone_label'] = $code;
        }
        unset($r);
        return $rows;
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('offices')->row_array();
    }
}

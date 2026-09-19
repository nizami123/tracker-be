<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Master Hari Libur. office_id NULL = libur nasional (berlaku untuk
 * semua kantor); diisi = khusus kantor tersebut. Dipakai oleh:
 *  - application/controllers/admin/Holidays.php (CRUD)
 *  - Admin_report_model / Reports::attendance_calendar_pdf (menandai
 *    tanggal libur merah + menghitung "Hari Kerja" pada kalender).
 */
class Holiday_model extends CI_Model
{
    /**
     * $officeId = null -> tanpa filter (dipakai SUPER_ADMIN, melihat semua
     * libur nasional + seluruh kantor). $officeId diisi -> hanya libur
     * nasional + libur milik kantor tersebut (dipakai ADMIN_KANTOR).
     */
    public function getAll(?int $officeId = null): array
    {
        $this->db->select('holidays.*, offices.name as office_name')
            ->from('holidays')
            ->join('offices', 'offices.id = holidays.office_id', 'left')
            ->order_by('holidays.holiday_date', 'ASC');

        if ($officeId) {
            $this->db->group_start()
                ->where('holidays.office_id', $officeId)
                ->or_where('holidays.office_id IS NULL', null, false)
                ->group_end();
        }

        return $this->db->get()->result_array();
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('holidays')->row_array();
    }

    /** Cek duplikat pada cakupan yang SAMA (tanggal + office_id persis sama). */
    public function existsOnDate(string $date, ?int $officeId, ?int $exceptId = null): bool
    {
        $q = $this->db->where('holiday_date', $date);
        if ($officeId === null) {
            $q->where('office_id IS NULL', null, false);
        } else {
            $q->where('office_id', $officeId);
        }
        if ($exceptId) $q->where('id !=', $exceptId);
        return $q->count_all_results('holidays') > 0;
    }

    public function insert(array $data): int
    {
        $data['created_at'] = now_datetime();
        $this->db->insert('holidays', $data);
        return (int) $this->db->insert_id();
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = now_datetime();
        $this->db->where('id', $id)->update('holidays', $data);
    }

    public function delete(int $id): void
    {
        $this->db->where('id', $id)->delete('holidays');
    }

    /**
     * tanggal (Y-m-d) => nama libur, gabungan libur nasional (office_id
     * NULL) dan libur milik $officeId, untuk satu rentang tanggal.
     * $officeId di sini WAJIB kantor pegawai yang bersangkutan (bukan
     * hasil filter "ALL" pada halaman laporan) — dipanggil sekali per
     * pegawai saat generate kalender.
     * Jika satu tanggal punya libur nasional & libur kantor sekaligus,
     * nama libur kantor yang ditampilkan (lebih spesifik untuk pegawai
     * tersebut).
     */
    public function getMapInRange(string $dateFrom, string $dateTo, int $officeId): array
    {
        $rows = $this->db->select('holiday_date, name, office_id')
            ->where('holiday_date >=', $dateFrom)
            ->where('holiday_date <=', $dateTo)
            ->group_start()
                ->where('office_id', $officeId)
                ->or_where('office_id IS NULL', null, false)
            ->group_end()
            ->order_by('office_id', 'ASC') // NULL (nasional) dulu, lalu spesifik kantor menimpa
            ->get('holidays')->result_array();

        $map = array();
        foreach ($rows as $r) {
            $map[$r['holiday_date']] = $r['name'];
        }
        return $map;
    }
}

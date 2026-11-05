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
                offices.timezone as office_timezone,
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
                offices.timezone as office_timezone,
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

    /** Zona waktu (WIB/WITA/WIT) kantor pengaju — approved_at/updated_at disimpan di zona yang sama dengan created_at pengajuan. */
    private function requestTimezone(int $id): string
    {
        $row = $this->db->select('offices.timezone')
            ->from('attendance_requests')
            ->join('offices', 'offices.id = attendance_requests.office_id')
            ->where('attendance_requests.id', $id)
            ->get()->row_array();
        return office_timezone($row ?: null);
    }

    public function approve(int $id, int $adminId): void
    {
        $tz = $this->requestTimezone($id);
        $this->db->where('id', $id)->update('attendance_requests', array(
            'status'      => 'APPROVED',
            'approved_by' => $adminId,
            'approved_at' => now_datetime($tz),
            'rejection_reason' => null,
            'updated_at'  => now_datetime($tz),
        ));
    }

    /**
     * Setujui pengajuan DAN terapkan ke tabel absensi.
     *
     * LATE / LEAVE / OUTSIDE_OFFICE: hanya menyetujui (perilaku lama, tidak berubah).
     *
     * CHECK_IN (Pengajuan Masuk) dan CHECK_OUT (Pengajuan Pulang): jam pada pengajuan
     * dicatat ke `attendances` pada tanggal pengajuan —
     *   - sudah ada baris absensi hari itu -> UPDATE jam masuk / jam pulang saja (kolom lain tidak disentuh);
     *   - belum ada baris absensi hari itu -> INSERT baris baru berisi jam tersebut saja;
     *   - jam yang sama sudah terisi       -> TIDAK ditimpa, pengajuan tetap disetujui (ada catatan).
     * Persetujuan dan penulisan absensi satu transaksi: kalau salah satu gagal, dua-duanya dibatalkan,
     * jadi tidak pernah ada pengajuan "disetujui" yang tidak tercatat.
     *
     * Urutan jam dijaga: jam pulang tidak boleh lebih awal dari jam masuk (dan sebaliknya) pada
     * baris yang sama — kalau begitu ACC ditolak dengan pesan. Pengecualian shift malam (lintas
     * tengah malam): jam pulang dini hari dianggap hari berikutnya.
     *
     * @return array{ok:bool,message:string}
     */
    public function approveAndApply(array $request, int $adminId): array
    {
        $id = (int) $request['id'];

        if (!in_array($request['type'], array('CHECK_IN', 'CHECK_OUT'), true)) {
            $this->approve($id, $adminId);
            return array('ok' => true, 'message' => 'Pengajuan disetujui');
        }

        $isIn = ($request['type'] === 'CHECK_IN');
        $label = $isIn ? 'masuk' : 'pulang';

        $date = (string) ($request['date'] ?? '');
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            return array('ok' => false, 'message' => 'Tanggal pada pengajuan tidak valid, tidak bisa dicatat ke absensi.');
        }
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', trim((string) ($request['time'] ?? '')), $m)) {
            return array('ok' => false, 'message' => 'Jam ' . $label . ' pada pengajuan kosong/tidak valid, tidak bisa dicatat ke absensi. Minta karyawan mengajukan ulang dengan jam ' . $label . '.');
        }
        $timePart = sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0);
        $hhmm = substr($timePart, 0, 5);
        $stamp = $date . ' ' . $timePart;

        $employeeId = (int) $request['employee_id'];
        $tz = $this->requestTimezone($id);
        $now = now_datetime($tz);
        $lat = (isset($request['latitude']) && $request['latitude'] !== '') ? $request['latitude'] : null;
        $lng = (isset($request['longitude']) && $request['longitude'] !== '') ? $request['longitude'] : null;
        $note = '';
        $col = $isIn ? 'check_in' : 'check_out';

        $this->db->trans_start();

        // Kunci baris absensi (employee_id, attendance_date unik) agar tidak balapan dengan check-in/out karyawan.
        $att = $this->db->query(
            'SELECT * FROM attendances WHERE employee_id = ? AND attendance_date = ? FOR UPDATE',
            array($employeeId, $date)
        )->row_array();

        if ($att) {
            if (!empty($att[$col . '_time'])) {
                $note = ' Karyawan sudah memiliki jam ' . $label . ' (' . substr($att[$col . '_time'], 11, 5) . ') pada tanggal itu, jadi tidak ditimpa.';
            } else {
                $this->db->where('id', (int) $att['id'])->update('attendances', array(
                    $col . '_time'      => $stamp,
                    $col . '_latitude'  => $lat,
                    $col . '_longitude' => $lng,
                    $col . '_accuracy'  => null,
                    $col . '_distance'  => null, // dari pengajuan, bukan scan di kantor
                    'updated_at'        => $now,
                ));
                $note = ' Jam ' . $label . ' ' . $hhmm . ' dicatat ke absensi.';
            }
        } else {
            $this->db->insert('attendances', array(
                'employee_id'       => $employeeId,
                'office_id'         => (int) $request['office_id'],
                'attendance_date'   => $date,
                $col . '_time'      => $stamp, // sisi lainnya dibiarkan kosong (NULL)
                $col . '_latitude'  => $lat,
                $col . '_longitude' => $lng,
                'status'            => 'PRESENT',
                'created_at'        => $now,
                'updated_at'        => $now,
            ));
            $note = ' Belum ada absensi pada tanggal itu, dibuat baris absensi baru dengan jam ' . $label . ' ' . $hhmm . '.';
        }

        $this->approve($id, $adminId);
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return array('ok' => false, 'message' => 'Gagal menyimpan ke database. Pengajuan belum disetujui, coba lagi.');
        }
        return array('ok' => true, 'message' => 'Pengajuan disetujui.' . $note);
    }

    public function reject(int $id, int $adminId, string $reason): void
    {
        $tz = $this->requestTimezone($id);
        $this->db->where('id', $id)->update('attendance_requests', array(
            'status'      => 'REJECTED',
            'approved_by' => $adminId,
            'approved_at' => now_datetime($tz),
            'rejection_reason' => $reason,
            'updated_at'  => now_datetime($tz),
        ));
    }
}
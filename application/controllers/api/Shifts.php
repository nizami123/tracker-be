<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

/**
 * Jadwal shift karyawan untuk aplikasi Android.
 *
 * Endpoint ini HANYA MEMBACA. Check-in / check-out (Attendance.php)
 * tidak memanggil apa pun dari sini, jadi shift tidak bisa mengubah
 * perilaku absensi yang sudah berjalan — ini murni informasi jadwal
 * yang ditampilkan di Beranda.
 *
 * Karyawan & kantor SELALU diambil dari token (require_auth), tidak
 * pernah dari request, jadi tiap user yang login otomatis mendapat
 * shift & kantornya sendiri.
 */
class Shifts extends MY_Controller
{
    /** Berapa hari ke depan yang ikut dikirim (di luar hari ini). */
    const UPCOMING_DAYS = 7;

    /**
     * GET /api/shifts/my
     *
     * {
     *   "date": "2026-11-05", "timezone": "WIB", "office_id": 2,
     *   "current":  { "date","source","shift_id","shift_code","shift_name","start_time","end_time","crosses_midnight" },
     *   "upcoming": [ { ...sama... }, ... ]
     * }
     *
     * source:
     *   "SHIFT"   = ada jadwal shift (start_time/end_time = jam shift)
     *   "OFF"     = hari libur yang diset admin (start_time/end_time null)
     *   "DEFAULT" = belum dijadwalkan -> jam kerja default 08:00 - 16:30 (perilaku lama)
     *
     * "current" = shift yang sedang berlaku SEKARANG. Untuk shift malam
     * yang melewati tengah malam (mis. 22:00 - 06:00), pukul 02:00 dini
     * hari "current" masih shift malam milik kemarin.
     */
    public function my()
    {
        $employee = $this->require_auth();
        $tz = employee_timezone($employee);
        $officeId = (int) $employee['office_id'];

        $this->load->model('Shift_model');

        $now = new DateTime('now', new DateTimeZone($tz));
        $today = $now->format('Y-m-d');
        $yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');
        $last = (clone $now)->modify('+' . self::UPCOMING_DAYS . ' day')->format('Y-m-d');

        $schedule = array();
        if ($this->Shift_model->tablesReady()) {
            $schedule = $this->Shift_model->getEmployeeSchedule((int) $employee['id'], $officeId, $yesterday, $last);
        }

        // Shift malam kemarin yang masih berjalan sampai pagi ini?
        $current = null;
        if (isset($schedule[$yesterday]) && !$schedule[$yesterday]['off'] && $schedule[$yesterday]['shift']
            && $schedule[$yesterday]['shift']['crosses_midnight']
            && $now->format('H:i') < $schedule[$yesterday]['shift']['end_time']) {
            $current = $this->dayPayload($yesterday, $schedule[$yesterday]);
        }
        if ($current === null) {
            $current = $this->dayPayload($today, $schedule[$today] ?? null);
        }

        $upcoming = array();
        for ($i = 1; $i <= self::UPCOMING_DAYS; $i++) {
            $d = (clone $now)->modify("+{$i} day")->format('Y-m-d');
            $upcoming[] = $this->dayPayload($d, $schedule[$d] ?? null);
        }

        $this->json_response(array(
            'date'      => $today,
            'timezone'  => tz_label($tz),
            'office_id' => $officeId,
            'current'   => $current,
            'upcoming'  => $upcoming,
        ), 200);
    }

    /** $entry = hasil Shift_model::getEmployeeSchedule() untuk tanggal itu, atau null (belum dijadwalkan). */
    private function dayPayload(string $date, ?array $entry): array
    {
        if ($entry === null) {
            return array(
                'date' => $date, 'source' => 'DEFAULT',
                'shift_id' => null, 'shift_code' => null, 'shift_name' => null,
                'start_time' => Shift_model::DEFAULT_START, 'end_time' => Shift_model::DEFAULT_END,
                'crosses_midnight' => false,
            );
        }
        if ($entry['off']) {
            return array(
                'date' => $date, 'source' => 'OFF',
                'shift_id' => null, 'shift_code' => null, 'shift_name' => 'Libur',
                'start_time' => null, 'end_time' => null,
                'crosses_midnight' => false,
            );
        }
        $s = $entry['shift'];
        return array(
            'date' => $date, 'source' => 'SHIFT',
            'shift_id' => $s['id'], 'shift_code' => $s['code'], 'shift_name' => $s['name'],
            'start_time' => $s['start_time'], 'end_time' => $s['end_time'],
            'crosses_midnight' => $s['crosses_midnight'],
        );
    }
}

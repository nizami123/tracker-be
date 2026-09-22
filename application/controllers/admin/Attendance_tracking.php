<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

/**
 * Tracking Karyawan — seluruhnya berbasis employee_id + tanggal pada
 * tabel attendance_tracking. Tidak ada lagi attendance_id maupun mode
 * "pending": karyawan yang belum absen pun tampil selama titik
 * trackingnya sudah masuk, dan absensi tidak memengaruhi halaman ini.
 *
 * URL:
 *   admin/attendance_tracking?date=Y-m-d                  daftar karyawan ber-tracking pada tanggal itu
 *   admin/attendance_tracking/detail/{employee_id}/{date} peta + rute satu karyawan pada satu tanggal
 *   ...points_data/{employee_id}/{date}                   AJAX: seluruh titik (polyline)
 *   ...latest_position/{employee_id}/{date}               AJAX: titik terbaru (polling 30 detik)
 * {date} opsional di semua URL — kosong berarti hari ini.
 */
class Attendance_tracking extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_tracking_model');
    }

    /** Sidebar entry "Tracking Karyawan" — semua karyawan yang punya titik tracking pada tanggal terpilih (default hari ini). */
    public function index()
    {
        $date = $this->resolveDate($this->input->get('date'));

        $this->render('admin/attendance_tracking/index', array(
            'activeMenu'   => 'tracking_karyawan',
            'pageTitle'    => 'Tracking Karyawan',
            'trackingDate' => $date,
            'isToday'      => $date === today_date(),
            'rows'         => $this->Admin_tracking_model->getTrackingList($this->officeScope(), $date),
        ));
    }

    /** Dibuka dari daftar Tracking Karyawan, Tracking Aktif, atau tombol "Lihat Tracking" di History Absensi. */
    public function detail($employeeId, $date = null)
    {
        $date = $this->resolveDate($date);
        $tracking = $this->Admin_tracking_model->getEmployeeDetail((int) $employeeId, $date, $this->officeScope());
        if (!$tracking) {
            show_404();
            return;
        }

        $this->render('admin/attendance_tracking/detail', array(
            'activeMenu' => 'tracking_karyawan',
            'pageTitle'  => 'Tracking: ' . $tracking['employee_name'],
            'tracking'   => $tracking,
            'isToday'    => $date === today_date(),
        ));
    }

    /** GET AJAX — full polyline data for the map, loaded once when the page opens. */
    public function points_data($employeeId, $date = null)
    {
        $date = $this->resolveDate($date);
        if (!$this->Admin_tracking_model->getEmployeeDetail((int) $employeeId, $date, $this->officeScope())) {
            return $this->json(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);
        }

        $this->json(array(
            'success' => true,
            'data'    => $this->Admin_tracking_model->getTrackingPoints((int) $employeeId, $date),
        ));
    }

    /**
     * GET AJAX — realtime polling (every 30s from the browser). Only
     * returns the single newest point, NOT the whole polyline again —
     * keeps the "don't re-fetch everything every 30s" requirement.
     * is_active hanya berarti "tanggalnya masih hari ini" (tracking bisa
     * masih bertambah); tidak ada hubungannya dengan absen pulang.
     */
    public function latest_position($employeeId, $date = null)
    {
        $date = $this->resolveDate($date);
        if (!$this->Admin_tracking_model->getEmployeeDetail((int) $employeeId, $date, $this->officeScope())) {
            return $this->json(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);
        }

        $this->json(array(
            'success'   => true,
            'is_active' => $date === today_date(),
            'point'     => $this->Admin_tracking_model->getLatestPoint((int) $employeeId, $date),
        ));
    }

    /** Kosong -> hari ini (GMT+7). Format salah -> 404, bukan diam-diam diganti. */
    private function resolveDate($date): string
    {
        if ($date === null || $date === '') return today_date();
        if (!valid_date_string($date)) show_404();
        return $date;
    }
}

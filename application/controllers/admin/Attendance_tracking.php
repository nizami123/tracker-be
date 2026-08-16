<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

class Attendance_tracking extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_tracking_model');
    }

    /** Sidebar entry "Tracking Karyawan" — list of currently-active tracking sessions. */
    public function index()
    {
        $this->render('admin/attendance_tracking/index', array(
            'activeMenu' => 'tracking_karyawan',
            'pageTitle'  => 'Tracking Karyawan',
            'rows'       => $this->Admin_tracking_model->getActiveTrackingList($this->officeScope()),
        ));
    }

    /** Opened from History Absensi's "Lihat Tracking" button, or from the active list above. */
    public function detail($attendanceId)
    {
        $attendance = $this->Admin_tracking_model->getAttendanceDetail((int) $attendanceId, $this->officeScope());
        if (!$attendance) {
            show_404();
            return;
        }

        $this->render('admin/attendance_tracking/detail', array(
            'activeMenu'  => 'tracking_karyawan',
            'pageTitle'   => 'Tracking: ' . $attendance['employee_name'],
            'attendance'  => $attendance,
        ));
    }

    /** GET AJAX — full polyline data for the map, loaded once when the page opens. */
    public function points_data($attendanceId)
    {
        $attendance = $this->Admin_tracking_model->getAttendanceDetail((int) $attendanceId, $this->officeScope());
        if (!$attendance) return $this->json(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);

        $points = $this->Admin_tracking_model->getTrackingPoints((int) $attendanceId);
        $this->json(array('success' => true, 'data' => $points));
    }

    /**
     * GET AJAX — realtime polling (every 30s from the browser). Only
     * returns the single newest point, NOT the whole polyline again —
     * keeps the "don't re-fetch everything every 30s" requirement.
     */
    public function latest_position($attendanceId)
    {
        $attendance = $this->Admin_tracking_model->getAttendanceDetail((int) $attendanceId, $this->officeScope());
        if (!$attendance) return $this->json(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);

        $point = $this->Admin_tracking_model->getLatestPoint((int) $attendanceId);
        $this->json(array(
            'success'      => true,
            'is_active'    => empty($attendance['check_out_time']),
            'point'        => $point,
        ));
    }
}

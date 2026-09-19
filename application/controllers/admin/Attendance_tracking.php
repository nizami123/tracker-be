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

    /** Sidebar entry "Tracking Karyawan" — today's tracking, active (checked-in) AND pending (not yet checked in). */
    public function index()
    {
        $this->render('admin/attendance_tracking/index', array(
            'activeMenu' => 'tracking_karyawan',
            'pageTitle'  => 'Tracking Karyawan',
            'rows'       => $this->Admin_tracking_model->getTodayTrackingList($this->officeScope()),
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

    /**
     * Opened from the "Tracking Karyawan" list for an employee who
     * hasn't checked in yet today (pending/pre-check-in tracking — see
     * Admin_tracking_model::getPendingDetail()). Renders the SAME
     * detail template as detail() above; the view/JS tells the two
     * apart via $attendance['is_pending'].
     */
    public function detail_pending($employeeId)
    {
        $attendance = $this->Admin_tracking_model->getPendingDetail((int) $employeeId, $this->officeScope());
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

        // employee_id + attendance_date, not attendance_id — see
        // Admin_tracking_model::getTrackingPoints() docblock: a point can
        // still be attendance_id NULL for an employee who already
        // checked in today (late offline sync), and must still show up.
        $points = $this->Admin_tracking_model->getTrackingPoints((int) $attendance['employee_id'], $attendance['attendance_date']);
        $this->json(array('success' => true, 'data' => $points));
    }

    /** GET AJAX — pending-session counterpart of points_data(), keyed by employee_id instead of attendance_id. */
    public function points_data_pending($employeeId)
    {
        $attendance = $this->Admin_tracking_model->getPendingDetail((int) $employeeId, $this->officeScope());
        if (!$attendance) return $this->json(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);

        $points = $this->Admin_tracking_model->getPendingTrackingPoints((int) $employeeId);
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

        // employee_id + attendance_date, same reasoning as points_data() above.
        $point = $this->Admin_tracking_model->getLatestPoint((int) $attendance['employee_id'], $attendance['attendance_date']);
        $this->json(array(
            'success'      => true,
            'is_active'    => empty($attendance['check_out_time']),
            'point'        => $point,
        ));
    }

    /**
     * GET AJAX — pending-session counterpart of latest_position().
     * is_active is always true here: a pending employee has neither
     * checked in nor out yet, so by definition tracking hasn't stopped
     * — polling only ends (client-side) once the page is reloaded after
     * they actually check in and this URL stops resolving.
     */
    public function latest_position_pending($employeeId)
    {
        $attendance = $this->Admin_tracking_model->getPendingDetail((int) $employeeId, $this->officeScope());
        if (!$attendance) return $this->json(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);

        $point = $this->Admin_tracking_model->getPendingLatestPoint((int) $employeeId);
        $this->json(array(
            'success'      => true,
            'is_active'    => true,
            'point'        => $point,
        ));
    }
}

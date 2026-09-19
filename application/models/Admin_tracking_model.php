<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_tracking_model extends CI_Model
{
    /** Full attendance + employee + office info for the tracking detail header. Office-scope aware. */
    public function getAttendanceDetail(int $attendanceId, ?int $officeId)
    {
        $this->db->select("
                attendances.*,
                employees.name as employee_name,
                employees.employee_code,
                employees.nip,
                offices.name as office_name,
                offices.latitude as office_latitude,
                offices.longitude as office_longitude,
                offices.check_in_radius,
                offices.check_out_radius
            ")
            ->from('attendances')
            ->join('employees', 'employees.id = attendances.employee_id')
            ->join('offices', 'offices.id = attendances.office_id')
            ->where('attendances.id', $attendanceId);

        if ($officeId) $this->db->where('attendances.office_id', $officeId);

        return $this->db->get()->row_array();
    }

    /**
     * Keyed by employee_id + tanggal, NOT attendance_id: a point can be
     * recorded with attendance_id NULL even for an employee who already
     * checked in today — e.g. it was queued offline on the device before
     * check-in and only reached the server (still tagged as "pending")
     * after check-in already ran and reassignPendingPoints() had already
     * fired. Matching on employee_id + tanggal instead of the exact
     * attendance_id picks up those orphaned rows too, so the detail page
     * always shows every point recorded for this employee today,
     * regardless of whether it ever got labeled with this attendance_id.
     */
    public function getTrackingPoints(int $employeeId, string $attendanceDate): array
    {
        return $this->db->where('employee_id', $employeeId)
            ->where('DATE(recorded_at)', $attendanceDate)
            ->order_by('recorded_at', 'ASC')
            ->get('attendance_tracking')
            ->result_array();
    }

    /** Just the newest point — used by the 30s realtime polling endpoint (cheap query, no full reload). */
    public function getLatestPoint(int $employeeId, string $attendanceDate)
    {
        return $this->db->where('employee_id', $employeeId)
            ->where('DATE(recorded_at)', $attendanceDate)
            ->order_by('recorded_at', 'DESC')
            ->limit(1)
            ->get('attendance_tracking')
            ->row_array();
    }

    /**
     * For the "Tracking Karyawan" page: every attendance still
     * checked-in (no check_out yet) — scoped to TODAY's attendance_date
     * only (revisi: "tracking hari ini saja"). Previously this had no
     * date filter at all, so a forgotten check-out from a previous day
     * (see Attendance_model::alreadyCheckedInToday's doc — the
     * check_out_time is simply left empty forever in that case) would
     * keep showing up here indefinitely even though tracking actually
     * stopped at midnight on the client.
     */
    public function getActiveTrackingList(?int $officeId): array
    {
        $this->db->select("
                attendances.*,
                employees.name as employee_name,
                employees.employee_code,
                offices.name as office_name,
                (SELECT COUNT(*) FROM attendance_tracking WHERE attendance_tracking.employee_id = attendances.employee_id AND DATE(attendance_tracking.recorded_at) = attendances.attendance_date) as tracking_count,
                (SELECT MAX(recorded_at) FROM attendance_tracking WHERE attendance_tracking.employee_id = attendances.employee_id AND DATE(attendance_tracking.recorded_at) = attendances.attendance_date) as last_point_at
            ")
            ->from('attendances')
            ->join('employees', 'employees.id = attendances.employee_id')
            ->join('offices', 'offices.id = attendances.office_id')
            ->where('attendances.attendance_date', today_date())
            ->where('attendances.check_in_time IS NOT NULL', null, false)
            ->where('attendances.check_out_time IS NULL', null, false)
            ->order_by('attendances.check_in_time', 'DESC');

        if ($officeId) $this->db->where('attendances.office_id', $officeId);

        return $this->db->get()->result_array();
    }

    /**
     * Revisi: employees already being tracked TODAY but who haven't
     * tapped "Absen Masuk" yet — recorded with attendance_id NULL (see
     * migration_005_pending_location_tracking.sql /
     * TrackingForegroundService's pre-check-in mode on the Android
     * side). These previously had no attendance row at all, so they
     * were completely invisible on this page even though GPS points
     * were already flowing in for them.
     *
     * The NOT EXISTS guard is what satisfies "jangan sampai double
     * data": once an employee checks in, api/Attendance::check_in()
     * calls Tracking_model::reassignPendingPoints(), which UPDATEs
     * every one of their pending rows to the new real attendance_id —
     * so under normal operation there is nothing left here to
     * duplicate against getActiveTrackingList() above. This guard is
     * purely a defensive backstop (e.g. a reassignment that partially
     * failed) so the SAME employee can never appear twice — once here
     * as "pending" and once above as a real checked-in session — no
     * matter what state the data is actually in.
     */
    public function getPendingTrackingList(?int $officeId): array
    {
        $this->db->select("
                employees.id as employee_id,
                employees.name as employee_name,
                employees.employee_code,
                offices.name as office_name,
                COUNT(*) as tracking_count,
                MAX(attendance_tracking.recorded_at) as last_point_at
            ")
            ->from('attendance_tracking')
            ->join('employees', 'employees.id = attendance_tracking.employee_id')
            ->join('offices', 'offices.id = employees.office_id')
            ->where('attendance_tracking.attendance_id IS NULL', null, false)
            ->where('DATE(attendance_tracking.recorded_at)', today_date())
            ->where(
                "NOT EXISTS (SELECT 1 FROM attendances a2 WHERE a2.employee_id = attendance_tracking.employee_id AND a2.attendance_date = '" . $this->db->escape_str(today_date()) . "')",
                null,
                false
            )
            ->group_by('employees.id')
            ->order_by('last_point_at', 'DESC');

        if ($officeId) $this->db->where('employees.office_id', $officeId);

        return $this->db->get()->result_array();
    }

    /**
     * Combines getActiveTrackingList() and getPendingTrackingList()
     * into the single list the "Tracking Karyawan" page renders, each
     * row tagged with is_pending so the view can route to the right
     * detail page (detail/{attendance_id} vs detail_pending/{employee_id}).
     * Active (checked-in) sessions are listed first, then pending ones,
     * each group already ordered by most recent point.
     */
    public function getTodayTrackingList(?int $officeId): array
    {
        $active = array_map(function ($r) {
            $r['is_pending'] = false;
            return $r;
        }, $this->getActiveTrackingList($officeId));

        $pending = array_map(function ($r) {
            $r['is_pending'] = true;
            return $r;
        }, $this->getPendingTrackingList($officeId));

        return array_merge($active, $pending);
    }

    /**
     * Same shape as getAttendanceDetail() (employee_name, employee_code,
     * nip, office_name/lat/lng/radii, attendance_date) but for an
     * employee with no attendance row yet today — everything comes
     * from employees/offices directly instead of a join through
     * attendances. check_in_time/check_out_time are left null and
     * status/id are filled with pending-friendly placeholders so
     * views/admin/attendance_tracking/detail.php can render this
     * exactly like a real attendance without any template changes.
     * Returns null (-> controller 404s) if this employee turns out to
     * already have a real attendance row today — see the guard below.
     */
    public function getPendingDetail(int $employeeId, ?int $officeId)
    {
        $this->db->select("
                employees.id as employee_id,
                employees.name as employee_name,
                employees.employee_code,
                employees.nip,
                offices.name as office_name,
                offices.latitude as office_latitude,
                offices.longitude as office_longitude,
                offices.check_in_radius,
                offices.check_out_radius
            ")
            ->from('employees')
            ->join('offices', 'offices.id = employees.office_id')
            ->where('employees.id', $employeeId);

        if ($officeId) $this->db->where('employees.office_id', $officeId);

        $row = $this->db->get()->row_array();
        if (!$row) return null;

        // Defensive, same guard as getPendingTrackingList()'s NOT EXISTS:
        // if this employee already has a real attendance row today (e.g.
        // a stale/bookmarked URL hit right after they checked in), this
        // is no longer a pending session — refuse to render it as one
        // rather than risk showing the same tracking data under two
        // different pages.
        $alreadyCheckedIn = $this->db->where('employee_id', $employeeId)
            ->where('attendance_date', today_date())
            ->count_all_results('attendances') > 0;
        if ($alreadyCheckedIn) return null;

        $row['id'] = 0;
        $row['attendance_date'] = today_date();
        $row['check_in_time'] = null;
        $row['check_out_time'] = null;
        $row['status'] = 'Menunggu Absen Masuk';
        return $row;
    }

    public function getPendingTrackingPoints(int $employeeId): array
    {
        return $this->db->where('attendance_id IS NULL', null, false)
            ->where('employee_id', $employeeId)
            ->where('DATE(recorded_at)', today_date())
            ->order_by('recorded_at', 'ASC')
            ->get('attendance_tracking')
            ->result_array();
    }

    public function getPendingLatestPoint(int $employeeId)
    {
        return $this->db->where('attendance_id IS NULL', null, false)
            ->where('employee_id', $employeeId)
            ->where('DATE(recorded_at)', today_date())
            ->order_by('recorded_at', 'DESC')
            ->limit(1)
            ->get('attendance_tracking')
            ->row_array();
    }
}

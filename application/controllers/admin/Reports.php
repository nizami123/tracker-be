<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

class Reports extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_report_model');
        $this->load->model('Admin_office_model');
        $this->load->model('Admin_attendance_model');
        $this->load->model('Admin_delivery_model');
        $this->load->model('Holiday_model');
        $this->load->model('Shift_model');
        $this->load->helper('export');
    }

    private function scopedOffices(): array
    {
        $offices = $this->Admin_office_model->getAll();
        if ($this->officeScope()) {
            $offices = array_values(array_filter($offices, fn($o) => (int) $o['id'] === $this->officeScope()));
        }
        return $offices;
    }

    private function filtersFromRequest(): array
    {
        return array(
            'date_from'              => $this->input->get('date_from') ?: date('Y-m-01'),
            'date_to'                => $this->input->get('date_to') ?: date('Y-m-d'),
            'office_id'              => $this->input->get('office_id'),
            'employee_id'            => $this->input->get('employee_id'),
            'driver_id'               => $this->input->get('driver_id'),
            'destination_office_id'   => $this->input->get('destination_office_id'),
            'type'                    => $this->input->get('type'),
            'status'                  => $this->input->get('status'),
        );
    }

    private function calendarMonths(): array
    {
        return array(
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        );
    }

    private function calendarFiltersFromRequest(): array
    {
        $month = (int) ($this->input->get('month') ?: date('n'));
        $year  = (int) ($this->input->get('year') ?: date('Y'));
        if ($month < 1 || $month > 12) $month = (int) date('n');
        if ($year < 2000 || $year > 2100) $year = (int) date('Y');

        return array(
            'month'       => $month,
            'year'        => $year,
            'office_id'   => $this->input->get('office_id'),
            'employee_id' => $this->input->get('employee_id'),
        );
    }

    // ==================== Laporan Absensi ====================

    public function attendance()
    {
        $f = $this->filtersFromRequest();
        $officeId = $f['office_id'] ?: $this->officeScope();
        $report = $this->Admin_report_model->attendanceReport($f['date_from'], $f['date_to'], $officeId, $f['employee_id'] ?: null);
        $summary = $report['summary'];
        $rows = $report['rows'];

        $this->render('admin/reports/attendance', array(
            'activeMenu' => 'laporan_absensi',
            'pageTitle'  => 'Laporan Absensi',
            'offices'    => $this->scopedOffices(),
            'employees'  => $this->Admin_attendance_model->getOptionsForFilter($this->officeScope()),
            'filters'    => $f,
            'summary'    => $summary,
            'rows'       => $rows,
        ));
    }

    public function attendance_export_excel()
    {
        $f = $this->filtersFromRequest();
        $officeId = $f['office_id'] ?: $this->officeScope();
        $rows = $this->Admin_report_model->attendanceReport($f['date_from'], $f['date_to'], $officeId, $f['employee_id'] ?: null)['rows'];

        export_excel(
            'laporan_absensi_' . $f['date_from'] . '_' . $f['date_to'],
            array('Tanggal', 'Karyawan', 'Kode', 'Kantor', 'Jadwal', 'Jam Masuk', 'Jarak Masuk (m)', 'Jam Pulang', 'Jarak Pulang (m)', 'Status'),
            $rows,
            array(
                'attendance_date', 'employee_name', 'employee_code', 'office_name', 'schedule_label',
                fn($r) => $r['check_in_time'] ? substr($r['check_in_time'], 11, 8) . ' ' . office_tz_label($r['office_timezone'] ?? null) : '-',
                fn($r) => $r['check_in_distance'] !== null ? round($r['check_in_distance']) : '-',
                fn($r) => $r['check_out_time'] ? substr($r['check_out_time'], 11, 8) . ' ' . office_tz_label($r['office_timezone'] ?? null) : '-',
                fn($r) => $r['check_out_distance'] !== null ? round($r['check_out_distance']) : '-',
                'status_label',
            )
        );
    }

    public function attendance_export_pdf()
    {
        $f = $this->filtersFromRequest();
        $officeId = $f['office_id'] ?: $this->officeScope();
        $report = $this->Admin_report_model->attendanceReport($f['date_from'], $f['date_to'], $officeId, $f['employee_id'] ?: null);

        $this->load->view('admin/reports/print_attendance', array(
            'filters' => $f, 'summary' => $report['summary'], 'rows' => $report['rows'],
        ));
    }

    // ==================== Kalender Absensi (Export PDF format kalender) ====================

    public function attendance_calendar()
    {
        $f = $this->calendarFiltersFromRequest();

        $this->render('admin/reports/attendance_calendar', array(
            'activeMenu' => 'laporan_kalender_absensi',
            'pageTitle'  => 'Kalender Absensi',
            'offices'    => $this->scopedOffices(),
            'employees'  => $this->Admin_attendance_model->getOptionsForFilter($this->officeScope()),
            'filters'    => $f,
            'months'     => $this->calendarMonths(),
        ));
    }

    public function attendance_calendar_pdf()
    {
        $f = $this->calendarFiltersFromRequest();
        $scope = $this->officeScope();

        $officeId = $f['office_id'] ?: $scope;
        $office = null;
        if ($officeId) {
            $office = $this->db
                ->select('name')
                ->where('id', (int) $officeId)
                ->get('offices')
                ->row_array();
        }
        if ($scope && $officeId && (int) $officeId !== $scope) {
            $officeId = $scope;
        }
        $employeeId = $f['employee_id'] ? (int) $f['employee_id'] : null;

        $employees = $this->Admin_report_model->calendarEmployees($officeId ? (int) $officeId : null, $employeeId);
        if ($scope) {
            $employees = array_values(array_filter($employees, fn($e) => (int) $e['office_id'] === $scope));
        }

        $month = $f['month'];
        $year = $f['year'];
        $dateFrom = sprintf('%04d-%02d-01', $year, $month);
        $daysInMonth = (int) date('t', strtotime($dateFrom));
        $dateTo = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $schedules = $this->Shift_model->getSchedulesForEmployees(array_map(fn($e) => (int) $e['id'], $employees), $dateFrom, $dateTo);
        $today = today_date();

        $pages = array();
        foreach ($employees as $emp) {
            $cells = $this->Admin_report_model->employeeDayStatuses($emp, $dateFrom, $dateTo, $today, $schedules[(int) $emp['id']] ?? array());
            $pages[] = $this->buildEmployeeCalendarPage($emp, $cells);
        }

        $months = $this->calendarMonths();

        $filenameParts = array('rekap_absensi');
        if ($employeeId && count($employees) === 1) {
            $filenameParts[] = preg_replace('/[^a-z0-9]+/i', '_', strtolower($employees[0]['name']));
        }
        if (!empty($office['name'])) {
            $filenameParts[] = preg_replace('/[^a-z0-9]+/i', '_', strtolower($office['name']));
        }
        $filenameParts[] = strtolower($months[$month]);
        $filenameParts[] = (string) $year;

        $this->load->view('admin/reports/print_attendance_calendar', array(
            'pages'      => $pages,
            'monthLabel' => $months[$month],
            'year'       => $year,
            'filename'   => implode('_', $filenameParts),
        ));
    }

    /**
     * Susun data 1 halaman kalender untuk 1 pegawai: grid minggu (Minggu
     * s/d Sabtu) + ringkasan (Hari Kerja / Hadir / Telat / Izin-Cuti /
     * Dinas Luar / Alfa) + legenda jadwal.
     *
     * Status tiap tanggal & jam kerjanya dihitung oleh
     * Admin_report_model::employeeDayStatuses(): pegawai yang punya jadwal
     * shift memakai jam shift itu (Libur dari jadwal = bukan hari kerja);
     * tanpa jadwal memakai jam normal Senin-Jumat 08:00-16:30, Sabtu
     * 08:00-12:30, Minggu & hari libur master = libur.
     */
    private function buildEmployeeCalendarPage(array $emp, array $cells): array
    {
        $weeks = array();
        $week = array_fill(0, 7, null);
        foreach ($cells as $cell) {
            $week[$cell['dow']] = $cell;
            if ($cell['dow'] === 6) {
                $weeks[] = $week;
                $week = array_fill(0, 7, null);
            }
        }
        if (count(array_filter($week, fn($c) => $c !== null)) > 0) {
            $weeks[] = $week;
        }

        // Legenda: kode shift yang dipakai bulan ini + ada/tidaknya hari bertanda jam normal.
        $shiftLegend = array();
        $usesDefault = false;
        foreach ($cells as $c) {
            if ($c['day']['source'] === 'SHIFT') {
                $shiftLegend[$c['shift_code']] = $c['shift_code'] . ' ' . $c['day']['name'] . ' ' . $c['day']['start'] . '-' . $c['day']['end'];
            } elseif ($c['day']['source'] === 'DEFAULT') {
                $usesDefault = true;
            }
        }
        ksort($shiftLegend);

        return array(
            'employee'     => $emp,
            'weeks'        => $weeks,
            'summary'      => $this->Admin_report_model->summarizeDays($cells),
            'shift_legend' => array_values($shiftLegend),
            'uses_default' => $usesDefault,
        );
    }

    // ==================== Laporan Pengajuan ====================

    public function requests()
    {
        $f = $this->filtersFromRequest();
        $officeId = $f['office_id'] ?: $this->officeScope();
        $rows = $this->Admin_report_model->requestRows($f, $officeId);
        $summary = $this->Admin_report_model->requestSummary($rows);

        $this->render('admin/reports/requests', array(
            'activeMenu' => 'laporan_pengajuan',
            'pageTitle'  => 'Laporan Pengajuan',
            'offices'    => $this->scopedOffices(),
            'employees'  => $this->Admin_attendance_model->getOptionsForFilter($this->officeScope()),
            'filters'    => $f,
            'summary'    => $summary,
            'rows'       => $rows,
        ));
    }

    public function requests_export_excel()
    {
        $f = $this->filtersFromRequest();
        $officeId = $f['office_id'] ?: $this->officeScope();
        $rows = $this->Admin_report_model->requestRows($f, $officeId);

        $typeLabels = array('LATE' => 'Terlambat', 'CHECK_IN' => 'Masuk', 'CHECK_OUT' => 'Pulang', 'LEAVE' => 'Cuti/Izin', 'OUTSIDE_OFFICE' => 'Absen Luar Kantor', 'OVERTIME' => 'Lembur');

        export_excel(
            'laporan_pengajuan_' . $f['date_from'] . '_' . $f['date_to'],
            array('Diajukan', 'Karyawan', 'Kantor', 'Jenis', 'Tanggal', 'Status', 'Alasan'),
            $rows,
            array(
                fn($r) => substr($r['created_at'], 0, 16) . ' ' . office_tz_label($r['office_timezone'] ?? null),
                'employee_name', 'office_name',
                fn($r) => $typeLabels[$r['type']] ?? $r['type'],
                fn($r) => $r['type'] === 'LEAVE' ? ($r['start_date'] . ' s/d ' . $r['end_date']) : ($r['date'] . ($r['type'] === 'OVERTIME' ? ' (' . substr((string) $r['time'], 0, 5) . '-' . substr((string) $r['end_time'], 0, 5) . ')' : '')),
                'status', 'reason',
            )
        );
    }

    public function requests_export_pdf()
    {
        $f = $this->filtersFromRequest();
        $officeId = $f['office_id'] ?: $this->officeScope();
        $rows = $this->Admin_report_model->requestRows($f, $officeId);
        $summary = $this->Admin_report_model->requestSummary($rows);

        $this->load->view('admin/reports/print_requests', array(
            'filters' => $f, 'summary' => $summary, 'rows' => $rows,
        ));
    }

    // ==================== Laporan Pengiriman ====================

    public function deliveries()
    {
        $f = $this->filtersFromRequest();
        $rows = $this->Admin_report_model->deliveryRows($f, $this->officeScope());
        $summary = $this->Admin_report_model->deliverySummary($rows);

        $this->render('admin/reports/deliveries', array(
            'activeMenu' => 'laporan_pengiriman',
            'pageTitle'  => 'Laporan Pengiriman',
            'offices'    => $this->scopedOffices(),
            'drivers'    => $this->Admin_delivery_model->getDriverOptions($this->officeScope()),
            'filters'    => $f,
            'summary'    => $summary,
            'rows'       => $rows,
        ));
    }

    public function deliveries_export_excel()
    {
        $f = $this->filtersFromRequest();
        $rows = $this->Admin_report_model->deliveryRows($f, $this->officeScope());

        export_excel(
            'laporan_pengiriman_' . $f['date_from'] . '_' . $f['date_to'],
            array('Tanggal', 'Driver', 'Merk/Tipe', 'No. Mesin', 'No. Rangka', 'Tujuan', 'Mulai', 'Selesai', 'Status'),
            $rows,
            array(
                fn($r) => substr($r['created_at'], 0, 10),
                'driver_name',
                fn($r) => $r['brand'] . ' ' . $r['vehicle_type'],
                'engine_number', 'chassis_number',
                fn($r) => $r['destination_office_name'] ?: $r['destination_name'] ?: '-',
                fn($r) => $r['pickup_time'] ? substr($r['pickup_time'], 11, 8) . ' ' . office_tz_label($r['driver_office_timezone'] ?? null) : '-',
                fn($r) => $r['arrival_time'] ? substr($r['arrival_time'], 11, 8) . ' ' . office_tz_label($r['driver_office_timezone'] ?? null) : '-',
                'status',
            )
        );
    }

    public function deliveries_export_pdf()
    {
        $f = $this->filtersFromRequest();
        $rows = $this->Admin_report_model->deliveryRows($f, $this->officeScope());
        $summary = $this->Admin_report_model->deliverySummary($rows);

        $this->load->view('admin/reports/print_deliveries', array(
            'filters' => $f, 'summary' => $summary, 'rows' => $rows,
        ));
    }
}

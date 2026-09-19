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
        $summary = $this->Admin_report_model->attendanceSummary($f['date_from'], $f['date_to'], $officeId, $f['employee_id'] ?: null);
        $rows = $this->Admin_report_model->attendanceRows($f['date_from'], $f['date_to'], $officeId, $f['employee_id'] ?: null);

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
        $rows = $this->Admin_report_model->attendanceRows($f['date_from'], $f['date_to'], $officeId, $f['employee_id'] ?: null);

        export_excel(
            'laporan_absensi_' . $f['date_from'] . '_' . $f['date_to'],
            array('Tanggal', 'Karyawan', 'Kode', 'Kantor', 'Jam Masuk', 'Jarak Masuk (m)', 'Jam Pulang', 'Jarak Pulang (m)', 'Status'),
            $rows,
            array(
                'attendance_date', 'employee_name', 'employee_code', 'office_name',
                fn($r) => $r['check_in_time'] ? substr($r['check_in_time'], 11, 8) : '-',
                fn($r) => $r['check_in_distance'] !== null ? round($r['check_in_distance']) : '-',
                fn($r) => $r['check_out_time'] ? substr($r['check_out_time'], 11, 8) : '-',
                fn($r) => $r['check_out_distance'] !== null ? round($r['check_out_distance']) : '-',
                'status',
            )
        );
    }

    public function attendance_export_pdf()
    {
        $f = $this->filtersFromRequest();
        $officeId = $f['office_id'] ?: $this->officeScope();
        $rows = $this->Admin_report_model->attendanceRows($f['date_from'], $f['date_to'], $officeId, $f['employee_id'] ?: null);
        $summary = $this->Admin_report_model->attendanceSummary($f['date_from'], $f['date_to'], $officeId, $f['employee_id'] ?: null);

        $this->load->view('admin/reports/print_attendance', array(
            'filters' => $f, 'summary' => $summary, 'rows' => $rows,
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
        // ADMIN_KANTOR tidak bisa diarahkan ke kantor lain lewat query string,
        // sama seperti pola officeScope() di seluruh panel admin ini.
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

        $pages = array();
        foreach ($employees as $emp) {
            $attendanceMap = $this->Admin_report_model->calendarAttendanceMap((int) $emp['id'], $dateFrom, $dateTo);
            $leaveMap = $this->Admin_report_model->calendarLeaveMap((int) $emp['id'], $dateFrom, $dateTo);
            $outsideMap = $this->Admin_report_model->calendarOutsideOfficeMap((int) $emp['id'], $dateFrom, $dateTo);
            $holidayMap = $this->Holiday_model->getMapInRange($dateFrom, $dateTo, (int) $emp['office_id']);

            $pages[] = $this->buildEmployeeCalendarPage($emp, $year, $month, $daysInMonth, $attendanceMap, $leaveMap, $outsideMap, $holidayMap);
        }

        $months = $this->calendarMonths();

        $filenameParts = array('rekap_absensi');
        if ($employeeId && count($employees) === 1) {
            $filenameParts[] = preg_replace('/[^a-z0-9]+/i', '_', strtolower($employees[0]['name']));
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
     * Dinas Luar / Alfa).
     *
     * Prioritas status per tanggal (paling kuat duluan): data absensi
     * NYATA (check-in/out) selalu menang meski jatuh di hari
     * Minggu/libur (mis. driver yang tetap masuk) > pengajuan Izin/Cuti
     * yang disetujui > pengajuan Dinas Luar yang disetujui > hari libur
     * (nasional/kantor, dari master Hari Libur) atau hari Minggu >
     * "Alfa" HANYA untuk hari kerja yang sudah lewat tanpa data apa pun
     * (bukan asumsi, murni turunan dari hitungan tanggal) > belum
     * terjadi (hari ini/masa depan) dibiarkan kosong, tidak dihitung Alfa.
     *
     * "Hari Kerja" & "Alfa" adalah hasil hitung (bukan kolom di
     * database) — sama seperti perkiraan "Tidak Hadir" di
     * attendanceSummary(), ini adalah estimasi berbasis kalender
     * (hari kerja = bukan Minggu & bukan hari libur), bukan jadwal
     * shift per pegawai karena sistem belum punya tabel itu.
     */
    private function buildEmployeeCalendarPage(array $emp, int $year, int $month, int $daysInMonth, array $attendanceMap, array $leaveMap, array $outsideMap, array $holidayMap): array
    {
        $today = today_date();
        $cellsByDate = array();
        $hariKerja = 0; $hadir = 0; $telat = 0; $izinCuti = 0; $dinasLuar = 0; $alfa = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $dow = (int) date('w', strtotime($date)); // 0=Minggu .. 6=Sabtu
            $isSunday = ($dow === 0);
            $isHoliday = isset($holidayMap[$date]);
            if (!$isSunday && !$isHoliday) $hariKerja++;

            $cell = array(
                'date'         => $d,
                'dow'          => $dow,
                'is_sunday'    => $isSunday,
                'is_holiday'   => $isHoliday,
                'holiday_name' => $holidayMap[$date] ?? null,
                'type'         => 'none',
                'time_label'   => null,
            );

            if (isset($attendanceMap[$date])) {
                $a = $attendanceMap[$date];
                $in = $a['check_in_time'] ? substr($a['check_in_time'], 11, 5) : null;
                $out = $a['check_out_time'] ? substr($a['check_out_time'], 11, 5) : null;
                if ($in && $out) $cell['time_label'] = $in . '-' . $out;
                elseif ($in) $cell['time_label'] = $in . '- -';
                elseif ($out) $cell['time_label'] = '- -' . $out;
                else $cell['time_label'] = '-';

                $cell['type'] = ($a['status'] === 'LATE') ? 'telat' : 'hadir';
                if ($cell['type'] === 'telat') $telat++; else $hadir++;
            } elseif (isset($leaveMap[$date])) {
                $cell['type'] = 'izin';
                $izinCuti++;
            } elseif (isset($outsideMap[$date])) {
                $cell['type'] = 'dinas_luar';
                $dinasLuar++;
            } elseif ($isHoliday || $isSunday) {
                $cell['type'] = 'libur';
            } elseif ($date < $today) {
                $cell['type'] = 'alfa';
                $alfa++;
            }

            $cellsByDate[$date] = $cell;
        }

        $weeks = array();
        $week = array_fill(0, 7, null);
        foreach ($cellsByDate as $cell) {
            $week[$cell['dow']] = $cell;
            if ($cell['dow'] === 6) {
                $weeks[] = $week;
                $week = array_fill(0, 7, null);
            }
        }
        if (count(array_filter($week, fn($c) => $c !== null)) > 0) {
            $weeks[] = $week;
        }

        return array(
            'employee' => $emp,
            'weeks'    => $weeks,
            'summary'  => array(
                'hari_kerja' => $hariKerja,
                'hadir'      => $hadir,
                'telat'      => $telat,
                'izin_cuti'  => $izinCuti,
                'dinas_luar' => $dinasLuar,
                'alfa'       => $alfa,
            ),
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

        $typeLabels = array('LATE' => 'Terlambat', 'CHECK_IN' => 'Masuk', 'CHECK_OUT' => 'Pulang', 'LEAVE' => 'Cuti/Izin', 'OUTSIDE_OFFICE' => 'Absen Luar Kantor');

        export_excel(
            'laporan_pengajuan_' . $f['date_from'] . '_' . $f['date_to'],
            array('Diajukan', 'Karyawan', 'Kantor', 'Jenis', 'Tanggal', 'Status', 'Alasan'),
            $rows,
            array(
                fn($r) => substr($r['created_at'], 0, 16),
                'employee_name', 'office_name',
                fn($r) => $typeLabels[$r['type']] ?? $r['type'],
                fn($r) => $r['type'] === 'LEAVE' ? ($r['start_date'] . ' s/d ' . $r['end_date']) : $r['date'],
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
                fn($r) => $r['pickup_time'] ? substr($r['pickup_time'], 11, 8) : '-',
                fn($r) => $r['arrival_time'] ? substr($r['arrival_time'], 11, 8) : '-',
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

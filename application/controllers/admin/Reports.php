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
                'engine_number', 'chassis_number', 'destination_office_name',
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

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

class Dashboard extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_dashboard_model');
    }

    public function index()
    {
        $officeId = $this->officeScope();
        $m = $this->Admin_dashboard_model;

        $stats = array(
            'total_karyawan'      => $m->totalKaryawan($officeId),
            'hadir_hari_ini'      => $m->hadirHariIni($officeId),
            'belum_absen'         => max(0, $m->totalKaryawan($officeId) - $m->hadirHariIni($officeId)),
            'sedang_tracking'     => $m->sedangTracking($officeId),
            'pengajuan_menunggu'  => $m->pengajuanMenunggu($officeId),
            'pengiriman_aktif'    => $m->pengirimanAktif($officeId),
        );

        $this->render('admin/dashboard/index', array(
            'activeMenu' => 'dashboard',
            'pageTitle'  => 'Dashboard',
            'stats'      => $stats,
        ));
    }

    /** GET /admin/dashboard/chart_kehadiran — AJAX, for the "kehadiran beberapa hari terakhir" chart. */
    public function chart_kehadiran()
    {
        $rows = $this->Admin_dashboard_model->kehadiranTerakhir($this->officeScope(), 7);

        // Fill in every day in the range (even zero-attendance days) so
        // the chart's x-axis doesn't skip dates with no data.
        $byDate = array();
        foreach ($rows as $r) $byDate[$r['attendance_date']] = (int) $r['total'];

        $labels = array();
        $values = array();
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('d/m', strtotime($date));
            $values[] = $byDate[$date] ?? 0;
        }

        $this->json(array('labels' => $labels, 'values' => $values));
    }

    /** GET /admin/dashboard/chart_hadir_vs_tidak */
    public function chart_hadir_vs_tidak()
    {
        $officeId = $this->officeScope();
        $hadir = $this->Admin_dashboard_model->hadirHariIni($officeId);
        $total = $this->Admin_dashboard_model->totalKaryawan($officeId);
        $tidakHadir = max(0, $total - $hadir);

        $this->json(array(
            'labels' => array('Hadir', 'Belum/Tidak Hadir'),
            'values' => array($hadir, $tidakHadir),
        ));
    }

    /** GET /admin/dashboard/chart_pengajuan */
    public function chart_pengajuan()
    {
        $rows = $this->Admin_dashboard_model->pengajuanByStatus($this->officeScope());
        $map = array('PENDING' => 0, 'APPROVED' => 0, 'REJECTED' => 0);
        foreach ($rows as $r) $map[$r['status']] = (int) $r['total'];

        $this->json(array(
            'labels' => array('Menunggu', 'Disetujui', 'Ditolak'),
            'values' => array($map['PENDING'], $map['APPROVED'], $map['REJECTED']),
        ));
    }

    /** GET /admin/dashboard/chart_pengiriman */
    public function chart_pengiriman()
    {
        $rows = $this->Admin_dashboard_model->pengirimanByStatus($this->officeScope());
        $map = array('IN_PROGRESS' => 0, 'ARRIVED' => 0, 'COMPLETED' => 0);
        foreach ($rows as $r) $map[$r['status']] = (int) $r['total'];

        $this->json(array(
            'labels' => array('Dalam Perjalanan', 'Sampai Tujuan', 'Selesai'),
            'values' => array($map['IN_PROGRESS'], $map['ARRIVED'], $map['COMPLETED']),
        ));
    }
}

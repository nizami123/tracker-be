<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

/**
 * Master Hari Libur — dipakai untuk menandai tanggal merah di
 * Export PDF Rekap Absensi (Kalender Bulanan) dan perhitungan "Hari
 * Kerja"-nya.
 *
 * SUPER_ADMIN bisa membuat libur nasional (berlaku semua kantor) atau
 * libur khusus satu kantor. ADMIN_KANTOR hanya bisa mengelola libur
 * milik kantornya sendiri, dan tetap bisa MELIHAT libur nasional
 * (tapi tidak bisa mengubah/menghapusnya).
 */
class Holidays extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Holiday_model');
        $this->load->model('Admin_office_model');
    }

    public function index()
    {
        $this->render('admin/holidays/index', array(
            'activeMenu' => 'master_libur',
            'pageTitle'  => 'Hari Libur',
            'offices'    => $this->isSuperAdmin() ? $this->Admin_office_model->getAll() : array(),
        ));
    }

    /** GET AJAX — list untuk DataTables (client-side; jumlah data kecil). */
    public function list_data()
    {
        $rows = $this->Holiday_model->getAll($this->officeScope());
        $this->json(array('data' => $rows));
    }

    public function detail($id)
    {
        $row = $this->Holiday_model->getById((int) $id);
        if (!$row) return $this->json(array('success' => false, 'message' => 'Hari libur tidak ditemukan'), 404);

        $scope = $this->officeScope();
        if ($scope && $row['office_id'] !== null && (int) $row['office_id'] !== $scope) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak memiliki akses ke data ini'), 403);
        }

        $this->json(array('success' => true, 'data' => $row));
    }

    /** POST AJAX — create atau update tergantung ada tidaknya 'id'. */
    public function save()
    {
        $id = (int) $this->input->post('id');
        $date = trim((string) $this->input->post('holiday_date'));
        $name = trim((string) $this->input->post('name'));
        $officeIdInput = $this->input->post('office_id');

        if ($date === '' || $name === '') {
            return $this->json(array('success' => false, 'message' => 'Tanggal dan nama hari libur wajib diisi'), 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            return $this->json(array('success' => false, 'message' => 'Format tanggal tidak valid'), 422);
        }

        $scope = $this->officeScope();

        if ($scope) {
            // ADMIN_KANTOR: selalu terikat ke kantornya sendiri, tidak
            // bisa membuat/mengubah libur nasional atau kantor lain.
            $officeId = $scope;
            if ($id > 0) {
                $existing = $this->Holiday_model->getById($id);
                if (!$existing || $existing['office_id'] === null || (int) $existing['office_id'] !== $scope) {
                    return $this->json(array('success' => false, 'message' => 'Anda hanya dapat mengubah hari libur kantor sendiri'), 403);
                }
            }
        } else {
            // SUPER_ADMIN: boleh pilih kantor tertentu, atau kosongkan
            // untuk libur nasional (berlaku semua kantor).
            $officeId = ($officeIdInput !== null && $officeIdInput !== '') ? (int) $officeIdInput : null;
        }

        if ($this->Holiday_model->existsOnDate($date, $officeId, $id ?: null)) {
            return $this->json(array('success' => false, 'message' => 'Tanggal ini sudah terdaftar sebagai hari libur untuk cakupan yang sama'), 422);
        }

        $data = array(
            'holiday_date' => $date,
            'name'         => $name,
            'office_id'    => $officeId,
        );

        if ($id > 0) {
            $this->Holiday_model->update($id, $data);
        } else {
            $id = $this->Holiday_model->insert($data);
        }

        $this->json(array('success' => true, 'message' => 'Hari libur tersimpan', 'data' => $this->Holiday_model->getById($id)));
    }

    public function delete($id)
    {
        $id = (int) $id;
        $row = $this->Holiday_model->getById($id);
        if (!$row) return $this->json(array('success' => false, 'message' => 'Hari libur tidak ditemukan'), 404);

        $scope = $this->officeScope();
        if ($scope && ($row['office_id'] === null || (int) $row['office_id'] !== $scope)) {
            return $this->json(array('success' => false, 'message' => 'Anda hanya dapat menghapus hari libur kantor sendiri'), 403);
        }

        $this->Holiday_model->delete($id);
        $this->json(array('success' => true, 'message' => 'Hari libur dihapus'));
    }
}

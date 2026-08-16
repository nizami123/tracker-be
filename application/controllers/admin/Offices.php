<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

/**
 * Master Kantor. Full CRUD — offices is genuinely a standalone master
 * table already in the schema (spec section 19 of the original
 * Android/API build), so this is a normal CRUD unlike Kendaraan.
 */
class Offices extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_office_model');
    }

    public function index()
    {
        $this->render('admin/offices/index', array(
            'activeMenu'   => 'master_kantor',
            'pageTitle'    => 'Master Kantor',
        ));
    }

    /** GET AJAX — list for DataTables (client-side; office count is small). */
    public function list_data()
    {
        $offices = $this->Admin_office_model->getAll();
        $this->json(array('data' => $offices));
    }

    public function detail($id)
    {
        $office = $this->Admin_office_model->getById((int) $id);
        if (!$office) return $this->json(array('success' => false, 'message' => 'Kantor tidak ditemukan'), 404);
        $this->json(array('success' => true, 'data' => $office));
    }

    /** POST AJAX — create or update depending on whether 'id' is present. */
    public function save()
    {
        if (!$this->isSuperAdmin()) {
            return $this->json(array('success' => false, 'message' => 'Hanya SUPER_ADMIN yang dapat mengelola Master Kantor'), 403);
        }

        $id = (int) $this->input->post('id');
        $code = trim((string) $this->input->post('code'));
        $name = trim((string) $this->input->post('name'));
        $address = trim((string) $this->input->post('address'));
        $latitude = $this->input->post('latitude');
        $longitude = $this->input->post('longitude');
        $checkInRadius = (int) $this->input->post('check_in_radius');
        $checkOutRadius = (int) $this->input->post('check_out_radius');
        $status = $this->input->post('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';

        if ($code === '' || $name === '' || $latitude === '' || $longitude === '') {
            return $this->json(array('success' => false, 'message' => 'Kode, nama, dan koordinat wajib diisi'), 422);
        }
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return $this->json(array('success' => false, 'message' => 'Koordinat tidak valid'), 422);
        }
        if ($checkInRadius <= 0) $checkInRadius = 50;
        if ($checkOutRadius <= 0) $checkOutRadius = 50;

        if ($this->Admin_office_model->codeExists($code, $id ?: null)) {
            return $this->json(array('success' => false, 'message' => 'Kode kantor sudah dipakai'), 422);
        }

        $data = array(
            'code' => $code,
            'name' => $name,
            'address' => $address,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'check_in_radius' => $checkInRadius,
            'check_out_radius' => $checkOutRadius,
            'status' => $status,
        );

        if ($id > 0) {
            $this->Admin_office_model->update($id, $data);
        } else {
            $id = $this->Admin_office_model->insert($data);
        }

        $this->json(array('success' => true, 'message' => 'Data kantor tersimpan', 'data' => $this->Admin_office_model->getById($id)));
    }

    public function toggle_status($id)
    {
        if (!$this->isSuperAdmin()) {
            return $this->json(array('success' => false, 'message' => 'Hanya SUPER_ADMIN yang dapat mengubah status kantor'), 403);
        }
        $this->Admin_office_model->toggleStatus((int) $id);
        $this->json(array('success' => true));
    }

    public function delete($id)
    {
        if (!$this->isSuperAdmin()) {
            return $this->json(array('success' => false, 'message' => 'Hanya SUPER_ADMIN yang dapat menghapus kantor'), 403);
        }
        $ok = $this->Admin_office_model->delete((int) $id);
        if (!$ok) {
            return $this->json(array('success' => false, 'message' => 'Kantor tidak bisa dihapus karena masih memiliki karyawan. Nonaktifkan saja.'), 409);
        }
        $this->json(array('success' => true, 'message' => 'Kantor dihapus'));
    }
}

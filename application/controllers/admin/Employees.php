<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

/**
 * Master Karyawan — role EMPLOYEE & DRIVER only. Admin accounts
 * (SUPER_ADMIN/ADMIN_KANTOR) are managed separately on the User Admin
 * page (Admin_users.php) even though it's the same `employees` table.
 */
class Employees extends MY_Admin_Controller
{
    private $roles = array('EMPLOYEE', 'DRIVER');

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_employee_model');
        $this->load->model('Admin_office_model');
    }

    public function index()
    {
        $offices = $this->Admin_office_model->getAll();
        if ($this->officeScope()) {
            $offices = array_values(array_filter($offices, function ($o) {
                return (int) $o['id'] === $this->officeScope();
            }));
        }

        $this->render('admin/employees/index', array(
            'activeMenu' => 'master_karyawan',
            'pageTitle'  => 'Master Karyawan',
            'offices'    => $offices,
        ));
    }

    public function list_data()
    {
        $rows = $this->Admin_employee_model->getAll($this->roles, $this->officeScope());
        foreach ($rows as &$r) unset($r['password']); // never leak the hash to the browser
        $this->json(array('data' => $rows));
    }

    public function detail($id)
    {
        $emp = $this->Admin_employee_model->getById((int) $id);
        if (!$emp || !in_array($emp['role'], $this->roles, true)) {
            return $this->json(array('success' => false, 'message' => 'Karyawan tidak ditemukan'), 404);
        }
        // Office-scope check for ADMIN_KANTOR — never trust the id from the URL alone.
        if ($this->officeScope() && (int) $emp['office_id'] !== $this->officeScope()) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak berwenang atas data ini'), 403);
        }
        unset($emp['password']);
        $this->json(array('success' => true, 'data' => $emp));
    }

    public function save()
    {
        $id = (int) $this->input->post('id');
        $employeeCode = trim((string) $this->input->post('employee_code'));
        $nip = trim((string) $this->input->post('nip'));
        $name = trim((string) $this->input->post('name'));
        $email = trim((string) $this->input->post('email'));
        $phone = trim((string) $this->input->post('phone'));
        $password = (string) $this->input->post('password');
        $officeId = (int) $this->input->post('office_id');
        $position = trim((string) $this->input->post('position'));
        $role = $this->input->post('role');
        $status = $this->input->post('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';

        if (!in_array($role, $this->roles, true)) {
            return $this->json(array('success' => false, 'message' => 'Role tidak valid'), 422);
        }
        if ($employeeCode === '' || $name === '' || $officeId <= 0) {
            return $this->json(array('success' => false, 'message' => 'Kode karyawan, nama, dan kantor wajib diisi'), 422);
        }
        // ADMIN_KANTOR can only create/edit employees in their own office.
        if ($this->officeScope() && $officeId !== $this->officeScope()) {
            return $this->json(array('success' => false, 'message' => 'Anda hanya dapat mengelola karyawan kantor Anda sendiri'), 403);
        }
        if ($this->Admin_employee_model->employeeCodeExists($employeeCode, $id ?: null)) {
            return $this->json(array('success' => false, 'message' => 'Kode karyawan sudah dipakai'), 422);
        }
        if ($email !== '' && $this->Admin_employee_model->emailExists($email, $id ?: null)) {
            return $this->json(array('success' => false, 'message' => 'Email sudah dipakai'), 422);
        }

        $data = array(
            'employee_code' => $employeeCode,
            'nip'           => $nip ?: null,
            'name'          => $name,
            'email'         => $email ?: null,
            'phone'         => $phone ?: null,
            'office_id'     => $officeId,
            'position'      => $position ?: null,
            'role'          => $role,
            'status'        => $status,
        );

        if ($id > 0) {
            // Re-check ownership against the EXISTING row before allowing an update.
            $existing = $this->Admin_employee_model->getById($id);
            if (!$existing || ($this->officeScope() && (int) $existing['office_id'] !== $this->officeScope())) {
                return $this->json(array('success' => false, 'message' => 'Anda tidak berwenang mengubah data ini'), 403);
            }
            if ($password !== '') {
                $data['password'] = password_hash($password, PASSWORD_BCRYPT);
            }
            $this->Admin_employee_model->update($id, $data);
        } else {
            if ($password === '') {
                return $this->json(array('success' => false, 'message' => 'Password wajib diisi untuk karyawan baru'), 422);
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
            $id = $this->Admin_employee_model->insert($data);
        }

        $updated = $this->Admin_employee_model->getById($id);
        unset($updated['password']);
        $this->json(array('success' => true, 'message' => 'Data karyawan tersimpan', 'data' => $updated));
    }

    public function toggle_status($id)
    {
        $id = (int) $id;
        $emp = $this->Admin_employee_model->getById($id);
        if (!$emp || ($this->officeScope() && (int) $emp['office_id'] !== $this->officeScope())) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak berwenang atas data ini'), 403);
        }
        $this->Admin_employee_model->toggleStatus($id);
        $this->json(array('success' => true));
    }

    public function delete($id)
    {
        $id = (int) $id;
        $emp = $this->Admin_employee_model->getById($id);
        if (!$emp || ($this->officeScope() && (int) $emp['office_id'] !== $this->officeScope())) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak berwenang atas data ini'), 403);
        }
        $ok = $this->Admin_employee_model->delete($id);
        if (!$ok) {
            return $this->json(array('success' => false, 'message' => 'Karyawan tidak bisa dihapus karena sudah punya riwayat absensi/pengiriman/pengajuan. Nonaktifkan saja.'), 409);
        }
        $this->json(array('success' => true, 'message' => 'Karyawan dihapus'));
    }
}

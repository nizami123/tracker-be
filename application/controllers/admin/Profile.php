<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

class Profile extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_employee_model');
        $this->load->model('Admin_office_model');
    }

    public function index()
    {
        $office = $this->Admin_office_model->getById((int) $this->adminUser['office_id']);

        $this->render('admin/profile/index', array(
            'activeMenu' => 'profil',
            'pageTitle'  => 'Profil',
            'office'     => $office,
        ));
    }

    public function update()
    {
        $name = trim((string) $this->input->post('name'));
        $phone = trim((string) $this->input->post('phone'));

        if ($name === '') {
            return $this->json(array('success' => false, 'message' => 'Nama wajib diisi'), 422);
        }

        $this->Admin_employee_model->update((int) $this->adminUser['id'], array(
            'name'  => $name,
            'phone' => $phone ?: null,
        ));

        // Keep the session's display name in sync immediately.
        $this->session->set_userdata('admin_name', $name);

        $this->json(array('success' => true, 'message' => 'Profil diperbarui'));
    }

    public function change_password()
    {
        $current = (string) $this->input->post('current_password');
        $new = (string) $this->input->post('new_password');
        $confirm = (string) $this->input->post('confirm_password');

        if (!password_verify($current, $this->adminUser['password'])) {
            return $this->json(array('success' => false, 'message' => 'Password lama salah'), 422);
        }
        if (strlen($new) < 6) {
            return $this->json(array('success' => false, 'message' => 'Password baru minimal 6 karakter'), 422);
        }
        if ($new !== $confirm) {
            return $this->json(array('success' => false, 'message' => 'Konfirmasi password tidak cocok'), 422);
        }

        $this->Admin_employee_model->update((int) $this->adminUser['id'], array(
            'password' => password_hash($new, PASSWORD_BCRYPT),
        ));

        $this->json(array('success' => true, 'message' => 'Password berhasil diubah'));
    }
}

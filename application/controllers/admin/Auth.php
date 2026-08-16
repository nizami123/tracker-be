<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Deliberately extends plain CI_Controller, not MY_Admin_Controller —
 * the login page is the one admin page that must be reachable WITHOUT
 * an active session (MY_Admin_Controller's constructor would redirect
 * straight back here in a loop otherwise).
 */
class Auth extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->helper(array('url', 'form'));
        $this->load->model('Admin_auth_model');
    }

    public function login()
    {
        // Already logged in -> skip straight to the dashboard.
        if ($this->session->userdata('admin_id')) {
            redirect('admin/dashboard');
            return;
        }

        $data = array('error' => null);

        if ($this->input->method() === 'post') {
            $email = trim((string) $this->input->post('email'));
            $password = (string) $this->input->post('password');

            $admin = $this->Admin_auth_model->findAdminByEmail($email);

            if (!$admin || !password_verify($password, $admin['password'])) {
                $data['error'] = 'Email atau password salah';
            } else {
                $this->session->set_userdata(array(
                    'admin_id'   => $admin['id'],
                    'admin_name' => $admin['name'],
                    'admin_role' => $admin['role'],
                ));
                redirect('admin/dashboard');
                return;
            }
        }

        $this->load->view('admin/auth/login', $data);
    }

    public function logout()
    {
        $this->session->sess_destroy();
        redirect('admin/auth/login');
    }
}

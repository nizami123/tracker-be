<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Auth extends MY_Controller
{
    public function login()
    {
        $body = $this->json_input();
        $email = trim($body['email'] ?? '');
        $password = (string) ($body['password'] ?? '');
        $deviceId = $body['device_id'] ?? null;

        if (empty($email) || empty($password)) {
            return $this->json_response(array('success' => false, 'message' => 'Email dan password wajib diisi'), 422);
        }

        $this->load->model('Auth_model');
        $employee = $this->Auth_model->findEmployeeByEmail($email);

        if (!$employee || !password_verify($password, $employee['password'])) {
            // Same message for "not found" and "wrong password" on purpose,
            // so the API doesn't leak which registered emails exist.
            return $this->json_response(array('success' => false, 'message' => 'Email atau password salah'), 401);
        }

        $token = $this->Auth_model->createToken((int) $employee['id'], $deviceId);

        $this->load->model('Employee_model');
        $this->json_response(array(
            'token'    => $token,
            'employee' => $this->Employee_model->toPublic($employee),
        ), 200);
    }
}

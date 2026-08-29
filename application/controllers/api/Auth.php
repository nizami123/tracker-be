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

    /**
     * POST /api/auth/change-password
     * Employee-initiated password change (settings screen). Requires
     * the current password to be re-entered (never trust that a valid
     * bearer token alone is enough to change credentials — a device
     * left logged in shouldn't let anyone at it silently lock the real
     * owner out), and re-hashes the new one with the same bcrypt scheme
     * used at login (password_verify/password_hash are a matched pair).
     */
    public function change_password()
    {
        $employee = $this->require_auth();
        $body = $this->json_input();

        $currentPassword = (string) ($body['current_password'] ?? '');
        $newPassword = (string) ($body['new_password'] ?? '');

        if (empty($currentPassword) || empty($newPassword)) {
            return $this->json_response(array('success' => false, 'message' => 'Password lama dan password baru wajib diisi'), 422);
        }

        if (strlen($newPassword) < 6) {
            return $this->json_response(array('success' => false, 'message' => 'Password baru minimal 6 karakter'), 422);
        }

        if (!password_verify($currentPassword, $employee['password'])) {
            return $this->json_response(array('success' => false, 'message' => 'Password lama salah'), 401);
        }

        if (password_verify($newPassword, $employee['password'])) {
            return $this->json_response(array('success' => false, 'message' => 'Password baru tidak boleh sama dengan password lama'), 422);
        }

        $this->load->model('Employee_model');
        $this->Employee_model->updatePassword((int) $employee['id'], password_hash($newPassword, PASSWORD_DEFAULT));

        $this->json_response(array('success' => true, 'message' => 'Password berhasil diubah'), 200);
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
    protected $authEmployee = null;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(array('response', 'distance'));

        // Allow the Android app (and any future admin web app) to call
        // this API from a different origin during development.
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

        if ($this->input->method() === 'options') {
            http_response_code(200);
            exit;
        }
    }

    /** Decoded JSON body of the request, or empty array if none/invalid. */
    protected function json_input(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return array();
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }

    protected function json_response($data, int $httpCode = 200): void
    {
        $this->output
            ->set_status_header($httpCode)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data));
    }

    /**
     * Validates the bearer token and loads the employee row.
     * Ends the request with 401 if missing/invalid/expired.
     */
    protected function require_auth(): array
    {
        $header = $this->input->get_request_header('Authorization', true);
        $token = null;
        if ($header && stripos($header, 'Bearer ') === 0) {
            $token = trim(substr($header, 7));
        }

        if (empty($token)) {
            $this->json_response(array('success' => false, 'message' => 'Token tidak ditemukan'), 401);
            exit;
        }

        $this->load->model('Auth_model');
        $employee = $this->Auth_model->getEmployeeByToken($token);

        if (!$employee) {
            $this->json_response(array('success' => false, 'message' => 'Token tidak valid atau kedaluwarsa'), 401);
            exit;
        }

        $this->authEmployee = $employee;
        return $employee;
    }
}

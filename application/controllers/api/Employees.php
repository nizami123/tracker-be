<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Employees extends MY_Controller
{
    /** GET /api/employees/me — profile of the currently authenticated employee. */
    public function me()
    {
        $employee = $this->require_auth();
        $this->load->model('Employee_model');
        $this->json_response($this->Employee_model->toPublic($employee), 200);
    }
}

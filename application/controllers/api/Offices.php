<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Controller.php';

class Offices extends MY_Controller
{
    /**
     * GET /api/offices
     * Returns every active office. This is the ONLY place office data
     * comes from — the Android app never hardcodes an office (spec
     * section 8/28), so adding a 5th, 10th, 100th office is purely a
     * database change.
     */
    public function index()
    {
        $this->require_auth();
        $this->load->model('Office_model');
        $this->json_response($this->Office_model->getActive(), 200);
    }
}

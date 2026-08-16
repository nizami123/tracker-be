<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base controller for every application/controllers/admin/*.php file.
 *
 * Completely separate from MY_Controller (the JSON API base for the
 * Android app) — this one renders HTML views and authenticates via
 * CodeIgniter's PHP session, not a bearer token. Both read from the
 * same `employees` table though.
 *
 * Authorization is enforced HERE, in the controller layer, not just by
 * hiding sidebar menu items:
 *  - requireLogin() runs on every single admin page load.
 *  - officeScope() returns null (no filter -> sees everything) for
 *    SUPER_ADMIN, or the admin's own office_id for ADMIN_KANTOR. Every
 *    admin model method that lists/filters data takes this value and
 *    applies it as a WHERE clause — so an ADMIN_KANTOR can never see
 *    another office's data no matter what query string / form field
 *    they send (ids from the URL/POST are never trusted directly,
 *    per the project's existing security convention).
 */
class MY_Admin_Controller extends CI_Controller
{
    /** @var array Currently logged-in admin's employees row (SUPER_ADMIN or ADMIN_KANTOR). */
    protected $adminUser = null;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->helper(array('url', 'response', 'distance'));

        $this->requireLogin();
    }

    private function requireLogin(): void
    {
        $adminId = $this->session->userdata('admin_id');
        if (!$adminId) {
            redirect('admin/auth/login');
            return;
        }

        $this->load->model('Admin_auth_model');
        $admin = $this->Admin_auth_model->getById((int) $adminId);

        if (!$admin || !in_array($admin['role'], array('SUPER_ADMIN', 'ADMIN_KANTOR'), true) || $admin['status'] !== 'ACTIVE') {
            $this->session->sess_destroy();
            redirect('admin/auth/login');
            return;
        }

        $this->adminUser = $admin;
    }

    /** null = SUPER_ADMIN (no restriction), int = ADMIN_KANTOR's own office_id only. */
    protected function officeScope(): ?int
    {
        if ($this->adminUser['role'] === 'SUPER_ADMIN') return null;
        return (int) $this->adminUser['office_id'];
    }

    protected function isSuperAdmin(): bool
    {
        return $this->adminUser['role'] === 'SUPER_ADMIN';
    }

    /**
     * Renders an admin view wrapped in the shared sidebar/topbar layout.
     * $view is just the inner content view, e.g. 'admin/dashboard/index'.
     */
    protected function render(string $view, array $data = array()): void
    {
        $data['admin'] = $this->adminUser;
        $data['activeMenu'] = $data['activeMenu'] ?? '';
        // Always available to every view — avoids each view/controller
        // needing to remember to pass these, and avoids ever calling
        // $this->someMethod() from inside a view file (views run inside
        // CI_Loader's scope in CI3, NOT the controller's, so that would
        // fatal-error).
        $data['isSuperAdmin'] = $this->isSuperAdmin();
        $data['officeScopeId'] = $this->officeScope();

        $this->load->view('admin/layout/header', $data);
        $this->load->view('admin/layout/sidebar', $data);
        $this->load->view($view, $data);
        $this->load->view('admin/layout/footer', $data);
    }

    /** For AJAX endpoints (DataTables server-side, map data, polling) — consistent JSON shape. */
    protected function json($data, int $httpCode = 200): void
    {
        $this->output
            ->set_status_header($httpCode)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data));
    }

    /**
     * Parses the standard DataTables server-side request params sent by
     * jQuery DataTables (draw/start/length/order/columns), used by
     * History Absensi, Pengajuan, History Pengiriman etc.
     * $columnMap maps the numeric column index DataTables sends back
     * for sorting to an actual SQL column name (e.g. [0 => 'attendance_date']).
     */
    protected function parseDataTablesRequest(array $columnMap, string $defaultOrderCol = 'id'): array
    {
        $draw = (int) $this->input->post('draw');
        $start = (int) $this->input->post('start');
        $length = (int) $this->input->post('length'); // -1 = "All"

        $orderCol = $defaultOrderCol;
        $orderDir = 'DESC';
        $order = $this->input->post('order');
        if (is_array($order) && isset($order[0]['column'])) {
            $colIndex = (int) $order[0]['column'];
            if (isset($columnMap[$colIndex])) $orderCol = $columnMap[$colIndex];
            $orderDir = (isset($order[0]['dir']) && strtolower($order[0]['dir']) === 'asc') ? 'ASC' : 'DESC';
        }

        return array(
            'draw'      => $draw,
            'start'     => $start,
            'length'    => $length,
            'orderCol'  => $orderCol,
            'orderDir'  => $orderDir,
        );
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

class Requests extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_request_model');
        $this->load->model('Admin_office_model');
        $this->load->model('Admin_attendance_model'); // reuse getOptionsForFilter() for the employee dropdown
    }

    /**
     * Both sidebar items "Pengajuan" and "Riwayat Pengajuan" point here.
     * "Pengajuan" links with ?status=PENDING pre-selected (still shows
     * the full filter UI so the admin can broaden it); "Riwayat
     * Pengajuan" opens with no status filter.
     */
    public function index()
    {
        $offices = $this->Admin_office_model->getAll();
        if ($this->officeScope()) {
            $offices = array_values(array_filter($offices, fn($o) => (int) $o['id'] === $this->officeScope()));
        }

        $defaultStatus = $this->input->get('status') ?: '';

        $this->render('admin/requests/index', array(
            'activeMenu'    => $defaultStatus === 'PENDING' ? 'pengajuan' : 'riwayat_pengajuan',
            'pageTitle'     => $defaultStatus === 'PENDING' ? 'Pengajuan' : 'Riwayat Pengajuan',
            'offices'       => $offices,
            'employees'     => $this->Admin_attendance_model->getOptionsForFilter($this->officeScope()),
            'defaultStatus' => $defaultStatus,
        ));
    }

    public function list_data()
    {
        $columnMap = array(
            0 => 'attendance_requests.created_at',
            1 => 'employees.name',
            2 => 'offices.name',
            3 => 'attendance_requests.type',
            5 => 'attendance_requests.status',
        );
        $dt = $this->parseDataTablesRequest($columnMap, 'attendance_requests.created_at');

        $filters = array(
            'date_from'   => $this->input->post('filter_date_from'),
            'date_to'     => $this->input->post('filter_date_to'),
            'office_id'   => $this->input->post('filter_office_id'),
            'employee_id' => $this->input->post('filter_employee_id'),
            'type'        => $this->input->post('filter_type'),
            'status'      => $this->input->post('filter_status'),
        );

        $officeId = $this->officeScope();
        $rows = $this->Admin_request_model->getPage($filters, $officeId, $dt['start'], $dt['length'], $dt['orderCol'], $dt['orderDir']);

        $this->json(array(
            'draw'            => $dt['draw'],
            'recordsTotal'    => $this->Admin_request_model->countAll($officeId),
            'recordsFiltered' => $this->Admin_request_model->countFiltered($filters, $officeId),
            'data'            => $this->withTzLabel($rows, 'office_timezone'),
        ));
    }

    public function detail($id)
    {
        $row = $this->Admin_request_model->getById((int) $id, $this->officeScope());
        if (!$row) return $this->json(array('success' => false, 'message' => 'Pengajuan tidak ditemukan'), 404);
        $this->json(array('success' => true, 'data' => $this->withTzLabel(array($row), 'office_timezone')[0]));
    }

    public function approve($id)
    {
        $id = (int) $id;
        $row = $this->Admin_request_model->getById($id, $this->officeScope());
        if (!$row) return $this->json(array('success' => false, 'message' => 'Pengajuan tidak ditemukan'), 404);
        if ($row['status'] !== 'PENDING') {
            return $this->json(array('success' => false, 'message' => 'Pengajuan ini sudah diproses sebelumnya'), 409);
        }
        // Lembur: admin boleh mengubah tanggal/jam di form detail sebelum menyetujui
        // (nilai yang dikirim ikut disimpan). Jika tidak dikirim, memakai data pengajuan apa adanya.
        if ($row['type'] === 'OVERTIME' && $this->input->post('time') !== null) {
            $err = $this->applyOvertimeEdit($row);
            if ($err) return $this->json(array('success' => false, 'message' => $err), 422);
        }
        // Pengajuan Pulang (CHECK_OUT) juga ditulis ke absensi: update bila sudah check-in, insert bila belum.
        $result = $this->Admin_request_model->approveAndApply($row, (int) $this->adminUser['id']);
        if (!$result['ok']) {
            return $this->json(array('success' => false, 'message' => $result['message']), 422);
        }
        $this->json(array('success' => true, 'message' => $result['message']));
    }

    /**
     * Validasi + simpan perubahan tanggal/jam lembur dari POST (date, time, end_time).
     * @return string|null pesan error, null bila berhasil.
     */
    private function applyOvertimeEdit(array $row): ?string
    {
        $v = $this->Admin_request_model->validateOvertime(
            $this->input->post('date') ?: $row['date'],
            $this->input->post('time'),
            $this->input->post('end_time')
        );
        if (!$v['ok']) return $v['message'];
        if ($this->Admin_request_model->overtimeOverlaps((int) $row['employee_id'], $v['date'], $v['start'], $v['end'], (int) $row['id'])) {
            return 'Karyawan sudah punya lembur lain yang jamnya bertabrakan pada tanggal itu';
        }
        $this->Admin_request_model->updateOvertime((int) $row['id'], $v['date'], $v['start'], $v['end']);
        return null;
    }

    /** POST admin/requests/update_overtime/{id} — ubah tanggal/jam lembur (PENDING atau APPROVED). */
    public function update_overtime($id)
    {
        $row = $this->Admin_request_model->getById((int) $id, $this->officeScope());
        if (!$row) return $this->json(array('success' => false, 'message' => 'Pengajuan tidak ditemukan'), 404);
        if ($row['type'] !== 'OVERTIME') {
            return $this->json(array('success' => false, 'message' => 'Hanya pengajuan lembur yang bisa diubah jamnya'), 422);
        }
        if ($row['status'] === 'REJECTED') {
            return $this->json(array('success' => false, 'message' => 'Pengajuan yang sudah ditolak tidak bisa diubah'), 409);
        }
        $err = $this->applyOvertimeEdit($row);
        if ($err) return $this->json(array('success' => false, 'message' => $err), 422);
        $this->json(array('success' => true, 'message' => 'Jam lembur diperbarui'));
    }

    /** POST admin/requests/create_overtime — admin menginput lembur karyawan (langsung disetujui). */
    public function create_overtime()
    {
        $employeeId = (int) $this->input->post('employee_id');
        $this->load->model('Admin_employee_model');
        $employee = $employeeId ? $this->Admin_employee_model->getById($employeeId) : null;
        // Karyawan harus berperan EMPLOYEE/DRIVER dan (untuk ADMIN_KANTOR) berada di kantornya sendiri.
        if (!$employee || !in_array($employee['role'], array('EMPLOYEE', 'DRIVER'), true)
            || ($this->officeScope() && (int) $employee['office_id'] !== $this->officeScope())) {
            return $this->json(array('success' => false, 'message' => 'Karyawan tidak ditemukan'), 404);
        }

        $v = $this->Admin_request_model->validateOvertime(
            $this->input->post('date'), $this->input->post('time'), $this->input->post('end_time')
        );
        if (!$v['ok']) return $this->json(array('success' => false, 'message' => $v['message']), 422);

        if ($this->Admin_request_model->overtimeOverlaps($employeeId, $v['date'], $v['start'], $v['end'])) {
            return $this->json(array('success' => false, 'message' => 'Karyawan sudah punya lembur yang jamnya bertabrakan pada tanggal itu'), 409);
        }

        $reason = trim((string) $this->input->post('reason'));
        if ($reason === '') $reason = 'Diinput oleh admin';

        $this->Admin_request_model->createOvertime($employee, $v['date'], $v['start'], $v['end'], $reason, (int) $this->adminUser['id']);
        $this->json(array('success' => true, 'message' => 'Lembur ' . $employee['name'] . ' berhasil dicatat dan langsung disetujui'));
    }

    public function reject($id)
    {
        $id = (int) $id;
        $reason = trim((string) $this->input->post('reason'));
        if ($reason === '') {
            return $this->json(array('success' => false, 'message' => 'Alasan penolakan wajib diisi'), 422);
        }
        $row = $this->Admin_request_model->getById($id, $this->officeScope());
        if (!$row) return $this->json(array('success' => false, 'message' => 'Pengajuan tidak ditemukan'), 404);
        if ($row['status'] !== 'PENDING') {
            return $this->json(array('success' => false, 'message' => 'Pengajuan ini sudah diproses sebelumnya'), 409);
        }
        $this->Admin_request_model->reject($id, (int) $this->adminUser['id'], $reason);
        $this->json(array('success' => true, 'message' => 'Pengajuan ditolak'));
    }
}

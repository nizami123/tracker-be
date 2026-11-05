<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

/**
 * Master Shift. Setiap shift milik SATU kantor (shift hanya berlaku di
 * kantor itu). SUPER_ADMIN mengelola semua kantor; ADMIN_KANTOR hanya
 * shift kantornya sendiri (officeScope()).
 *
 * Halaman ini hanya menyentuh tabel `shifts` — tidak berpengaruh ke
 * absensi yang sedang berjalan.
 */
class Shifts extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Shift_model');
        $this->load->model('Admin_office_model');
    }

    public function index()
    {
        $ready = $this->Shift_model->tablesReady();
        $this->render('admin/shifts/index', array(
            'activeMenu'  => 'master_shift',
            'pageTitle'   => 'Master Shift',
            'tablesReady' => $ready,
            'offices'     => $this->isSuperAdmin() ? $this->Admin_office_model->getAll() : array(),
        ));
    }

    /** GET AJAX — list untuk DataTables (client-side). */
    public function list_data()
    {
        if (!$this->Shift_model->tablesReady()) return $this->json(array('data' => array()));
        $this->json(array('data' => $this->Shift_model->getShifts($this->officeScope())));
    }

    public function detail($id)
    {
        $row = $this->Shift_model->getShiftById((int) $id);
        if (!$row) return $this->json(array('success' => false, 'message' => 'Shift tidak ditemukan'), 404);
        if (!$this->canAccessOffice((int) $row['office_id'])) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak memiliki akses ke data ini'), 403);
        }
        $this->json(array('success' => true, 'data' => $row));
    }

    /** POST AJAX — create atau update tergantung ada tidaknya 'id'. */
    public function save()
    {
        if (!$this->Shift_model->tablesReady()) {
            return $this->json(array('success' => false, 'message' => 'Tabel shift belum dibuat. Jalankan SQL migrasi database/migrations/2026_11_05_shift_management.sql terlebih dahulu.'), 500);
        }

        $id    = (int) $this->input->post('id');
        $code  = strtoupper(trim((string) $this->input->post('code')));
        $name  = trim((string) $this->input->post('name'));
        $start = trim((string) $this->input->post('start_time'));
        $end   = trim((string) $this->input->post('end_time'));
        $status = $this->input->post('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';

        if ($code === '' || $name === '' || $start === '' || $end === '') {
            return $this->json(array('success' => false, 'message' => 'Kode, nama, jam mulai dan jam selesai wajib diisi'), 422);
        }
        if (!preg_match('/^[A-Z0-9_\-]{1,20}$/', $code)) {
            return $this->json(array('success' => false, 'message' => 'Kode hanya boleh huruf/angka/-/_ (maks 20 karakter)'), 422);
        }
        if (mb_strlen($name) > 100) {
            return $this->json(array('success' => false, 'message' => 'Nama shift maksimal 100 karakter'), 422);
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
            return $this->json(array('success' => false, 'message' => 'Format jam harus HH:MM (00:00 - 23:59)'), 422);
        }
        if ($start === $end) {
            return $this->json(array('success' => false, 'message' => 'Jam mulai dan jam selesai tidak boleh sama'), 422);
        }

        if ($id > 0) {
            $existing = $this->Shift_model->getShiftById($id);
            if (!$existing) return $this->json(array('success' => false, 'message' => 'Shift tidak ditemukan'), 404);
            if (!$this->canAccessOffice((int) $existing['office_id'])) {
                return $this->json(array('success' => false, 'message' => 'Anda tidak memiliki akses ke data ini'), 403);
            }
            // Kantor shift tidak boleh dipindah setelah dibuat (jadwal yang sudah ada
            // merujuk shift ini untuk karyawan kantor asalnya).
            $officeId = (int) $existing['office_id'];
        } else {
            $scope = $this->officeScope();
            $officeId = $scope ?: (int) $this->input->post('office_id');
            if ($officeId <= 0 || !$this->Admin_office_model->getById($officeId)) {
                return $this->json(array('success' => false, 'message' => 'Kantor wajib dipilih'), 422);
            }
        }

        if ($this->Shift_model->codeExists($officeId, $code, $id ?: null)) {
            return $this->json(array('success' => false, 'message' => 'Kode shift sudah dipakai di kantor ini'), 422);
        }

        $data = array(
            'code'       => $code,
            'name'       => $name,
            'start_time' => $start . ':00',
            'end_time'   => $end . ':00',
            'status'     => $status,
        );

        if ($id > 0) {
            $this->Shift_model->updateShift($id, $data);
        } else {
            $data['office_id'] = $officeId;
            $id = $this->Shift_model->insertShift($data);
        }

        $this->json(array('success' => true, 'message' => 'Shift tersimpan', 'data' => $this->Shift_model->getShiftById($id)));
    }

    public function delete($id)
    {
        $id = (int) $id;
        $row = $this->Shift_model->getShiftById($id);
        if (!$row) return $this->json(array('success' => false, 'message' => 'Shift tidak ditemukan'), 404);
        if (!$this->canAccessOffice((int) $row['office_id'])) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak memiliki akses ke data ini'), 403);
        }
        if ($this->Shift_model->shiftHasSchedules($id)) {
            return $this->json(array('success' => false, 'message' => 'Shift ini sudah dipakai di jadwal karyawan, tidak bisa dihapus. Nonaktifkan saja.'), 422);
        }
        $this->Shift_model->deleteShift($id);
        $this->json(array('success' => true, 'message' => 'Shift dihapus'));
    }

    /** SUPER_ADMIN boleh semua kantor; ADMIN_KANTOR hanya kantornya. */
    private function canAccessOffice(int $officeId): bool
    {
        $scope = $this->officeScope();
        return $scope === null || $scope === $officeId;
    }
}

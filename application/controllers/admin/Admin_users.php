<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

/**
 * Master User Admin — role SUPER_ADMIN & ADMIN_KANTOR, same
 * `employees` table as everything else (no separate admin_users
 * table). Restricted entirely to SUPER_ADMIN: an ADMIN_KANTOR should
 * not be able to create/edit/see other admin accounts, including
 * their own office's — that's a SUPER_ADMIN-level responsibility.
 *
 * Akses multi-kantor: SUPER_ADMIN menentukan satu atau lebih kantor
 * untuk tiap ADMIN_KANTOR (tabel admin_offices). SUPER_ADMIN sendiri
 * otomatis mengakses semua kantor.
 */
class Admin_users extends MY_Admin_Controller
{
    private $roles = array('SUPER_ADMIN', 'ADMIN_KANTOR');

    public function __construct()
    {
        parent::__construct();
        if (!$this->isSuperAdmin()) {
            show_error('Halaman ini hanya dapat diakses oleh SUPER_ADMIN.', 403, 'Akses Ditolak');
        }
        $this->load->model('Admin_employee_model');
        $this->load->model('Admin_office_model');
        $this->load->model('Admin_auth_model');
    }

    public function index()
    {
        $this->render('admin/admin_users/index', array(
            'activeMenu' => 'master_admin',
            'pageTitle'  => 'Master User Admin',
            'offices'    => $this->Admin_office_model->getAll($this->companyId()),
        ));
    }

    public function list_data()
    {
        $rows = $this->Admin_employee_model->getAll($this->roles, null, $this->companyId());
        $names = $this->Admin_auth_model->getOfficeNamesForAdmins(array_column($rows, 'id'));
        foreach ($rows as &$r) {
            unset($r['password']);
            // SUPER_ADMIN = semua kantor; ADMIN_KANTOR = daftar kantor yang diberikan (fallback: kantor asal).
            $r['office_names'] = $r['role'] === 'SUPER_ADMIN'
                ? array()
                : ($names[(int) $r['id']] ?? ($r['office_name'] ? array($r['office_name']) : array()));
        }
        unset($r);
        $this->json(array('data' => $rows));
    }

    public function detail($id)
    {
        $admin = $this->Admin_employee_model->getById((int) $id, $this->companyId());
        if (!$admin || !in_array($admin['role'], $this->roles, true)) {
            return $this->json(array('success' => false, 'message' => 'Admin tidak ditemukan'), 404);
        }
        unset($admin['password']);
        $ids = $this->Admin_auth_model->getAllOfficeIdsForAdmin((int) $admin['id']);
        if (empty($ids) && $admin['role'] === 'ADMIN_KANTOR') $ids = array((int) $admin['office_id']);
        $admin['office_ids'] = $ids;
        $this->json(array('success' => true, 'data' => $admin));
    }

    public function save()
    {
        $id = (int) $this->input->post('id');
        $employeeCode = trim((string) $this->input->post('employee_code'));
        $name = trim((string) $this->input->post('name'));
        $email = trim((string) $this->input->post('email'));
        $phone = trim((string) $this->input->post('phone'));
        $password = (string) $this->input->post('password');
        $officeId = (int) $this->input->post('office_id');
        $officeIds = array_values(array_unique(array_filter(array_map('intval', (array) $this->input->post('office_ids')))));
        $role = $this->input->post('role');
        $status = $this->input->post('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';

        if (!in_array($role, $this->roles, true)) {
            return $this->json(array('success' => false, 'message' => 'Role tidak valid'), 422);
        }
        // Hanya kantor yang benar-benar ada yang dianggap valid.
        $validIds = array_map('intval', array_column($this->Admin_office_model->getAll($this->companyId()), 'id'));
        $officeIds = array_values(array_intersect($officeIds, $validIds));
        // Kantor asal akun juga harus milik perusahaan ini.
        if ($officeId > 0 && !in_array($officeId, $validIds, true)) $officeId = 0;
        if ($role === 'SUPER_ADMIN' && $officeId <= 0 && !empty($validIds)) $officeId = $validIds[0];
        // Akun yang diedit harus milik perusahaan ini.
        if ($id > 0 && !$this->Admin_employee_model->getById($id, $this->companyId())) {
            return $this->json(array('success' => false, 'message' => 'Admin tidak ditemukan'), 404);
        }

        if ($role === 'ADMIN_KANTOR') {
            if (empty($officeIds)) {
                return $this->json(array('success' => false, 'message' => 'Pilih minimal satu kantor untuk Admin Kantor'), 422);
            }
            // kantor asal akun (kolom employees.office_id) = kantor pertama yang dipilih,
            // kecuali form mengirim kantor asal yang memang termasuk akses.
            if (!in_array($officeId, $officeIds, true)) $officeId = $officeIds[0];
        }
        if ($employeeCode === '' || $name === '' || $email === '' || $officeId <= 0) {
            return $this->json(array('success' => false, 'message' => 'Kode, nama, email, dan kantor wajib diisi'), 422);
        }

        // Prevent a SUPER_ADMIN from locking themselves out by demoting/deactivating their own last admin account.
        if ($id > 0 && $id === (int) $this->adminUser['id'] && ($role !== 'SUPER_ADMIN' || $status !== 'ACTIVE')) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak dapat mengubah role/status akun Anda sendiri dari sini'), 422);
        }

        if ($this->Admin_employee_model->employeeCodeExists($employeeCode, $id ?: null, $this->companyId())) {
            return $this->json(array('success' => false, 'message' => 'Kode admin sudah dipakai'), 422);
        }
        if ($this->Admin_employee_model->emailExists($email, $id ?: null)) {
            return $this->json(array('success' => false, 'message' => 'Email sudah dipakai'), 422);
        }

        $data = array(
            'employee_code' => $employeeCode,
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone ?: null,
            'office_id'     => $officeId,
            'position'      => $role === 'SUPER_ADMIN' ? 'Super Administrator' : 'Admin Kantor',
            'role'          => $role,
            'status'        => $status,
        );

        if ($id > 0) {
            if ($password !== '') $data['password'] = password_hash($password, PASSWORD_BCRYPT);
            $this->Admin_employee_model->update($id, $data);
        } else {
            if ($password === '') {
                return $this->json(array('success' => false, 'message' => 'Password wajib diisi untuk admin baru'), 422);
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
            $data['company_id'] = $this->companyId();
            $id = $this->Admin_employee_model->insert($data);
        }

        // Simpan akses multi-kantor (SUPER_ADMIN: dikosongkan, karena otomatis semua kantor).
        $this->Admin_auth_model->setOfficesForAdmin($id, $role === 'ADMIN_KANTOR' ? $officeIds : array());

        $updated = $this->Admin_employee_model->getById($id);
        unset($updated['password']);
        $this->json(array('success' => true, 'message' => 'Data admin tersimpan', 'data' => $updated));
    }

    public function toggle_status($id)
    {
        $id = (int) $id;
        if ($id === (int) $this->adminUser['id']) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri'), 422);
        }
        if (!$this->Admin_employee_model->getById($id, $this->companyId())) {
            return $this->json(array('success' => false, 'message' => 'Admin tidak ditemukan'), 404);
        }
        $this->Admin_employee_model->toggleStatus($id);
        $this->json(array('success' => true));
    }

    public function delete($id)
    {
        $id = (int) $id;
        if ($id === (int) $this->adminUser['id']) {
            return $this->json(array('success' => false, 'message' => 'Anda tidak dapat menghapus akun Anda sendiri'), 422);
        }
        $target = $this->Admin_employee_model->getById($id, $this->companyId());
        if (!$target || !in_array($target['role'], $this->roles, true)) {
            return $this->json(array('success' => false, 'message' => 'Admin tidak ditemukan'), 404);
        }
        $ok = $this->Admin_employee_model->delete($id);
        if (!$ok) {
            return $this->json(array('success' => false, 'message' => 'Akun ini tidak bisa dihapus karena masih memiliki riwayat aktivitas. Nonaktifkan saja.'), 409);
        }
        $this->json(array('success' => true, 'message' => 'Admin dihapus'));
    }
}

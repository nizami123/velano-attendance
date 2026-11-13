<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base controller for every application/controllers/admin/*.php file.
 *
 * Completely separate from MY_Controller (the JSON API base for the
 * Android app) - this one renders HTML views and authenticates via
 * CodeIgniter's PHP session, not a bearer token. Both read from the
 * same `employees` table though.
 *
 * Authorization is enforced HERE, in the controller layer, not just by
 * hiding sidebar menu items:
 *  - requireLogin() runs on every single admin page load.
 *  - officeScope() returns null (no filter -> sees everything) for
 *    SUPER_ADMIN, or the list of office ids granted to the ADMIN_KANTOR
 *    (one or more, table admin_offices). Every
 *    admin model method that lists/filters data takes this value and
 *    applies it as a WHERE clause - so an ADMIN_KANTOR can never see
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
        $this->load->helper(array('url', 'response', 'distance', 'scope'));

        // Perbaikan: sama seperti MY_Controller - paksa session MySQL ke
        // GMT+7 supaya NOW()/CURDATE() di raw SQL konsisten dengan
        // date_default_timezone_set('Asia/Jakarta') (dijalankan saat
        // helper 'response' di-load barusan) yang dipakai today_date()/
        // now_datetime() dan date() biasa di seluruh panel admin.
        $this->db->query("SET time_zone = '+07:00'");

        $this->requireLogin();
    }

    private function requireLogin(): void
    {
        $adminId = $this->session->userdata('admin_id');
        if (!$adminId) {
            // Super Global Admin punya panel sendiri; tidak boleh membuka panel perusahaan.
            redirect($this->session->userdata('global_admin_id') ? 'platform/companies' : 'admin/auth/login');
            return;
        }

        $this->load->model('Admin_auth_model');
        $this->load->model('Company_model');
        $admin = $this->Admin_auth_model->getById((int) $adminId);

        if (!$admin || !in_array($admin['role'], array('SUPER_ADMIN', 'ADMIN_KANTOR'), true) || $admin['status'] !== 'ACTIVE' || empty($admin['company_id'])) {
            $this->session->sess_destroy();
            redirect('admin/auth/login');
            return;
        }

        // Perusahaan harus aktif & belum kedaluwarsa (dicek di SETIAP request, bukan hanya saat login).
        $company = $this->Company_model->getById((int) $admin['company_id']);
        list($ok, $reason) = $this->Company_model->usability($company);
        if (!$ok) {
            // Hanya keluarkan sesi admin ini (bukan sess_destroy) supaya alasan penolakan tetap tampil di halaman login.
            $this->session->unset_userdata(array('admin_id', 'admin_name', 'admin_role'));
            $this->session->set_flashdata('login_error', $reason);
            redirect('admin/auth/login');
            return;
        }

        $this->adminUser = $admin;
        $this->company = $company;
    }

    /** @var array Perusahaan (tenant) milik admin yang login. */
    protected $company = null;

    /** Id perusahaan admin yang login - SELALU dari session/DB, tidak pernah dari request. */
    protected function companyId(): int
    {
        return (int) $this->adminUser['company_id'];
    }

    /** @var array|null Cache daftar kantor yang boleh diakses admin ADMIN_KANTOR. */
    private $officeIdsCache = null;

    /**
     * int[] = SUPER_ADMIN: semua kantor milik perusahaannya (tabel offices.company_id).
     * int[] = ADMIN_KANTOR: daftar kantor yang diberikan SUPER_ADMIN (tabel
     *         admin_offices - boleh lebih dari satu). Bila tabel belum ada /
     *         belum diisi, jatuh ke kantor asal akun (perilaku lama).
     * Tidak pernah mengembalikan array kosong: bila benar-benar tidak ada
     * kantor, dikembalikan array(0) supaya tidak ada data yang cocok.
     */
    protected function officeScope(): ?array
    {
        if ($this->officeIdsCache === null && $this->adminUser['role'] === 'SUPER_ADMIN') {
            // Super Admin = semua kantor milik PERUSAHAANNYA saja (bukan semua kantor di database).
            // Perusahaan tanpa kantor -> array(0): tidak ada data yang cocok (aman by default).
            $ids = $this->Company_model->officeIds($this->companyId());
            $this->officeIdsCache = !empty($ids) ? $ids : array(0);
        }

        if ($this->officeIdsCache === null) {
            $ids = $this->Admin_auth_model->getOfficeIdsForAdmin((int) $this->adminUser['id']);
            if (empty($ids) && !empty($this->adminUser['office_id'])) {
                $ids = array((int) $this->adminUser['office_id']);
            }
            $this->officeIdsCache = !empty($ids) ? array_values(array_unique(array_map('intval', $ids))) : array(0);
        }
        return $this->officeIdsCache;
    }

    /** Apakah admin yang login boleh mengakses kantor ini? (SUPER_ADMIN: selalu ya) */
    protected function canAccessOffice($officeId): bool
    {
        return scope_allows($this->officeScope(), $officeId);
    }

    /**
     * Filter kantor dari form/query string ($requested, boleh kosong) yang
     * AMAN: hanya dipakai bila masih dalam cakupan admin; selain itu kembali
     * ke seluruh cakupan admin. Mengembalikan int (1 kantor), int[] (semua
     * kantor milik admin) atau null (SUPER_ADMIN tanpa filter).
     */
    protected function resolveOfficeFilter($requested)
    {
        $requested = (int) $requested;
        if ($requested > 0 && $this->canAccessOffice($requested)) return $requested;
        return $this->officeScope();
    }

    /** Daftar kantor (baris offices) yang boleh dilihat admin ini - untuk dropdown filter/form. */
    protected function scopedOffices(): array
    {
        $this->load->model('Admin_office_model');
        $offices = $this->Admin_office_model->getAll($this->companyId());
        $scope = $this->officeScope();
        return array_values(array_filter($offices, function ($o) use ($scope) {
            return in_array((int) $o['id'], $scope, true);
        }));
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
        // Always available to every view - avoids each view/controller
        // needing to remember to pass these, and avoids ever calling
        // $this->someMethod() from inside a view file (views run inside
        // CI_Loader's scope in CI3, NOT the controller's, so that would
        // fatal-error).
        $data['isSuperAdmin'] = $this->isSuperAdmin();
        $data['company'] = $this->company;
        $data['companyDaysLeft'] = $this->Company_model->daysLeft($this->company);
        $data['officeScopeId'] = $this->officeScope();
        $data['officeScopeIds'] = $this->officeScope();
        // true bila admin boleh memilih kantor di form/filter (SUPER_ADMIN atau ADMIN_KANTOR dengan >1 kantor).
        $data['canPickOffice'] = $this->isSuperAdmin() || count((array) $this->officeScope()) > 1;
        $data['adminOfficeCount'] = $this->isSuperAdmin() ? null : count($this->officeScope());

        $this->load->view('admin/layout/header', $data);
        $this->load->view('admin/layout/sidebar', $data);
        $this->load->view($view, $data);
        $this->load->view('admin/layout/footer', $data);
    }

    /** For AJAX endpoints (DataTables server-side, map data, polling) - consistent JSON shape. */
    protected function json($data, int $httpCode = 200): void
    {
        $this->output
            ->set_status_header($httpCode)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data));
    }

    /**
     * Menambahkan kolom `tz_label` ("WIB"/"WITA"/"WIT") ke tiap baris,
     * berdasarkan kolom zona kantor KARYAWAN pada baris itu ($tzKey, mis.
     * `office_timezone` dari offices.timezone) - jam yang tersimpan di
     * database memakai zona karyawan tersebut (lihat response_helper.php).
     */
    protected function withTzLabel(array $rows, string $tzKey = 'office_timezone'): array
    {
        foreach ($rows as &$r) {
            $r['tz_label'] = office_tz_label($r[$tzKey] ?? null);
        }
        unset($r);
        return $rows;
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

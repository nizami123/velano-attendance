<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Basis panel SUPER GLOBAL ADMIN (pemilik layanan Velano).
 *
 *  - Autentikasi memakai tabel `global_admins` (sesi: global_admin_id) - terpisah total dari
 *    panel perusahaan (MY_Admin_Controller, tabel `employees`). Akun perusahaan tidak bisa membuka
 *    panel ini, dan Super Global Admin tidak bisa membuka panel perusahaan.
 *  - Semua request POST wajib membawa token CSRF sesi (header X-CSRF-Token atau field csrf_global).
 */
class MY_Platform_Controller extends CI_Controller
{
    /** @var array Baris global_admins yang sedang login. */
    protected $platformUser = null;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->helper(array('url', 'response'));
        $this->db->query("SET time_zone = '+07:00'");
        $this->load->model('Global_admin_model');
        $this->load->model('Company_model');

        $id = (int) $this->session->userdata('global_admin_id');
        $user = $id ? $this->Global_admin_model->getById($id) : null;
        if (!$user || $user['status'] !== 'ACTIVE') {
            $this->session->unset_userdata(array('global_admin_id', 'global_admin_name', 'csrf_global'));
            if ($this->input->is_ajax_request() || $this->input->method() === 'post') {
                $this->json(array('success' => false, 'message' => 'Sesi berakhir, silakan login ulang'), 401);
                exit;
            }
            redirect('admin/auth/login');
            exit;
        }
        $this->platformUser = $user;

        if (!$this->session->userdata('csrf_global')) {
            $this->session->set_userdata('csrf_global', bin2hex(random_bytes(16)));
        }
        if ($this->input->method() === 'post') {
            $sent = (string) ($this->input->get_request_header('X-CSRF-Token') ?: $this->input->post('csrf_global'));
            if ($sent === '' || !hash_equals((string) $this->session->userdata('csrf_global'), $sent)) {
                $this->json(array('success' => false, 'message' => 'Token keamanan tidak valid, muat ulang halaman'), 403);
                exit;
            }
        }
    }

    protected function csrfToken(): string
    {
        return (string) $this->session->userdata('csrf_global');
    }

    protected function json($data, int $httpCode = 200): void
    {
        $this->output->set_status_header($httpCode)->set_content_type('application/json', 'utf-8')->set_output(json_encode($data));
    }
}

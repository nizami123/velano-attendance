<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Deliberately extends plain CI_Controller, not MY_Admin_Controller -
 * the login page is the one admin page that must be reachable WITHOUT
 * an active session (MY_Admin_Controller's constructor would redirect
 * straight back here in a loop otherwise).
 *
 * Satu halaman login untuk semua peran panel web:
 *   1. Super Global Admin  -> tabel global_admins   -> /platform/companies
 *   2. Super Admin / Admin Kantor perusahaan -> tabel employees -> /admin/dashboard
 * Sesi keduanya dipisah (global_admin_id vs admin_id) supaya tidak pernah tercampur.
 */
class Auth extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->helper(array('url', 'form', 'response'));
        $this->load->model('Admin_auth_model');
        $this->load->model('Global_admin_model');
    }

    public function login()
    {
        // Sudah login -> langsung ke panel yang sesuai.
        if ($this->session->userdata('global_admin_id')) {
            redirect('platform/companies');
            return;
        }
        if ($this->session->userdata('admin_id')) {
            redirect('admin/dashboard');
            return;
        }

        $data = array('error' => $this->session->flashdata('login_error'));

        if ($this->input->method() === 'post') {
            $email = strtolower(trim((string) $this->input->post('email')));
            $password = (string) $this->input->post('password');

            $global = $this->Global_admin_model->findActiveByEmail($email);
            if ($global && password_verify($password, $global['password'])) {
                $this->session->sess_regenerate(TRUE);
                $this->session->unset_userdata(array('admin_id', 'admin_name', 'admin_role'));
                $this->session->set_userdata(array(
                    'global_admin_id'   => (int) $global['id'],
                    'global_admin_name' => $global['name'],
                    'csrf_global'       => bin2hex(random_bytes(16)),
                ));
                $this->Global_admin_model->touchLogin((int) $global['id']);
                redirect('platform/companies');
                return;
            }

            $admin = $this->Admin_auth_model->findAdminByEmail($email);

            if (!$admin || !password_verify($password, $admin['password'])) {
                $data['error'] = 'Email atau password salah';
            } elseif (!$this->companyUsable($admin, $data['error'])) {
                // $data['error'] sudah diisi alasan (ditangguhkan / masa aktif habis)
            } else {
                $this->session->sess_regenerate(TRUE);
                $this->session->unset_userdata(array('global_admin_id', 'global_admin_name', 'csrf_global'));
                $this->session->set_userdata(array(
                    'admin_id'   => $admin['id'],
                    'admin_name' => $admin['name'],
                    'admin_role' => $admin['role'],
                ));
                redirect('admin/dashboard');
                return;
            }
        }

        $this->load->view('admin/auth/login', $data);
    }

    private function companyUsable(array $admin, &$error): bool
    {
        $this->load->model('Company_model');
        list($ok, $reason) = $this->Company_model->usability($this->Company_model->getById((int) $admin['company_id']));
        if (!$ok) $error = $reason;
        return $ok;
    }

    public function logout()
    {
        $this->session->sess_destroy();
        redirect('admin/auth/login');
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pendaftaran mandiri perusahaan baru (coba gratis 14 hari).
 * NONAKTIF secara default: data master perusahaan hanya boleh dibuat oleh Super Global Admin
 * (panel /platform/companies). Aktifkan kembali dengan  $config['allow_self_register'] = TRUE;  di config.php.
 * Bila aktif: membuat perusahaan + 1 kantor awal + akun SUPER_ADMIN, lalu langsung login.
 */
class Register extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->helper(array('url', 'form', 'response'));
        $this->load->model('Company_model');
    }

    public function index()
    {
        if (!$this->config->item('allow_self_register')) {
            $this->output->set_status_header(403);
            $this->load->view('public/register_closed');
            return;
        }

        $data = array('error' => null, 'old' => array());

        if ($this->input->method() === 'post') {
            $in = array(
                'company_name'   => trim((string) $this->input->post('company_name')),
                'admin_name'     => trim((string) $this->input->post('admin_name')),
                'email'          => strtolower(trim((string) $this->input->post('email'))),
                'phone'          => trim((string) $this->input->post('phone')),
                'password'       => (string) $this->input->post('password'),
                'office_name'    => trim((string) $this->input->post('office_name')),
                'office_address' => trim((string) $this->input->post('office_address')),
                'latitude'       => $this->input->post('latitude'),
                'longitude'      => $this->input->post('longitude'),
                'timezone'       => strtoupper(trim((string) $this->input->post('timezone'))),
            );
            $data['old'] = $in;
            unset($data['old']['password']);

            $last = (int) $this->session->userdata('register_last');

            if ($this->input->post('website') !== '' && $this->input->post('website') !== null) {
                show_404(); // honeypot: bot mengisi kolom tersembunyi
            } elseif (time() - $last < 5) {
                $data['error'] = 'Terlalu cepat, coba lagi sebentar.';
            } elseif ($in['company_name'] === '' || $in['admin_name'] === '' || $in['email'] === '') {
                $data['error'] = 'Nama perusahaan, nama Anda, dan email wajib diisi.';
            } elseif (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
                $data['error'] = 'Format email tidak valid.';
            } elseif (strlen($in['password']) < 8) {
                $data['error'] = 'Password minimal 8 karakter.';
            } elseif ($this->db->where('email', $in['email'])->count_all_results('employees') > 0) {
                $data['error'] = 'Email ini sudah terdaftar. Silakan login.';
            } else {
                try {
                    $this->session->set_userdata('register_last', time());
                    list($companyId, $adminId) = $this->Company_model->createWithAdmin($in);
                    $this->session->set_userdata(array(
                        'admin_id'   => $adminId,
                        'admin_name' => $in['admin_name'],
                        'admin_role' => 'SUPER_ADMIN',
                    ));
                    redirect('admin/dashboard');
                    return;
                } catch (Throwable $e) {
                    log_message('error', 'Register gagal: ' . $e->getMessage());
                    $data['error'] = 'Pendaftaran gagal, silakan coba lagi.';
                }
            }
        }

        $this->load->view('public/register', $data);
    }
}

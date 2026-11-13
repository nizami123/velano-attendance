<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Perintah CLI (hanya bisa dijalankan dari terminal server, bukan dari browser).
 * Berguna bila password Super Global Admin lupa:
 *   php index.php tools reset_global_admin_password PasswordBaruMin8 [email]
 */
class Tools extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_cli()) show_404();
        $this->load->database();
        $this->load->helper('response');
    }

    public function reset_global_admin_password($password = '', $email = '')
    {
        if (strlen((string) $password) < 8) {
            echo "Pemakaian: php index.php tools reset_global_admin_password PASSWORD_BARU(min 8 karakter) [EMAIL]\n";
            return;
        }
        $q = $this->db->from('global_admins');
        if ($email !== '') $q->where('email', strtolower(trim((string) $email)));
        $row = $q->order_by('id', 'ASC')->limit(1)->get()->row_array();
        if (!$row) {
            echo "Akun Super Global Admin tidak ditemukan. Import ulang database/schema.sql (tabel global_admins).\n";
            return;
        }
        $this->db->where('id', (int) $row['id'])->update('global_admins', array(
            'password' => password_hash((string) $password, PASSWORD_BCRYPT), 'status' => 'ACTIVE', 'updated_at' => now_datetime(),
        ));
        echo "Password Super Global Admin ({$row['email']}) diganti dan akun diaktifkan.\n";
    }
}

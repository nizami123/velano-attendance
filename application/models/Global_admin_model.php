<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Super Global Admin = pemilik layanan Velano.
 * Disimpan di tabel TERPISAH `global_admins` (bukan di `employees`), dan hanya boleh
 * mengelola data master perusahaan (tabel `companies`) - tidak punya company_id/kantor,
 * sehingga tidak otomatis terhubung ke data absensi perusahaan mana pun.
 */
class Global_admin_model extends CI_Model
{
    public function findActiveByEmail(string $email)
    {
        return $this->db->where('email', strtolower(trim($email)))
            ->where('status', 'ACTIVE')
            ->get('global_admins')
            ->row_array();
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('global_admins')->row_array();
    }

    public function touchLogin(int $id): void
    {
        $this->db->where('id', $id)->update('global_admins', array('last_login_at' => now_datetime()));
    }

    public function emailTaken(string $email, int $exceptId = 0): bool
    {
        $q = $this->db->where('email', strtolower(trim($email)));
        if ($exceptId > 0) $q->where('id !=', $exceptId);
        return $q->count_all_results('global_admins') > 0;
    }

    public function updateProfile(int $id, string $name, string $email): void
    {
        $this->db->where('id', $id)->update('global_admins', array(
            'name'       => $name,
            'email'      => strtolower(trim($email)),
            'updated_at' => now_datetime(),
        ));
    }

    public function updatePassword(int $id, string $plain): void
    {
        $this->db->where('id', $id)->update('global_admins', array(
            'password'   => password_hash($plain, PASSWORD_BCRYPT),
            'updated_at' => now_datetime(),
        ));
    }
}

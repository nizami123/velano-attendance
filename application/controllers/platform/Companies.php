<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Platform_Controller.php';

/**
 * Master Perusahaan (tenant yang berlangganan) - CRUD penuh, HANYA untuk Super Global Admin.
 * Super Admin perusahaan / Admin Kantor tidak punya akses ke controller ini (lihat MY_Platform_Controller).
 */
class Companies extends MY_Platform_Controller
{
    public function index()
    {
        $this->load->model('Demo_request_model');
        $this->load->view('platform/companies', array(
            'me'       => $this->platformUser,
            'plans'    => Company_model::$plans,
            'csrf'     => $this->csrfToken(),
            'new_demo' => $this->Demo_request_model->countNew(),
        ));
    }

    /** GET JSON: semua perusahaan + statistik pemakaian. */
    public function list_data()
    {
        $rows = $this->Company_model->getAllWithStats();
        foreach ($rows as &$r) {
            $r['days_left'] = $this->Company_model->daysLeft($r);
            list($ok, ) = $this->Company_model->usability($r);
            $r['usable'] = $ok;
        }
        unset($r);
        $this->json(array('data' => $rows));
    }

    /** GET JSON: satu perusahaan + Super Admin pemiliknya (untuk form edit). */
    public function detail($id)
    {
        $c = $this->Company_model->getById((int) $id);
        if (!$c) return $this->json(array('success' => false, 'message' => 'Perusahaan tidak ditemukan'), 404);
        $this->json(array('success' => true, 'data' => $c, 'owner' => $this->Company_model->getOwner((int) $c['id'])));
    }

    /** POST: buat (id kosong) atau ubah perusahaan. */
    public function save()
    {
        $id = (int) $this->input->post('id');
        $plan = (string) $this->input->post('plan');
        $name = trim((string) $this->input->post('name'));
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $this->input->post('code')));
        $contactName = trim((string) $this->input->post('contact_name'));
        $contactEmail = strtolower(trim((string) $this->input->post('contact_email')));
        $contactPhone = trim((string) $this->input->post('contact_phone'));
        $maxEmp = (int) $this->input->post('max_employees');
        $validUntil = trim((string) $this->input->post('valid_until'));
        $status = $this->input->post('status') === 'SUSPENDED' ? 'SUSPENDED' : 'ACTIVE';

        if (!isset(Company_model::$plans[$plan])) return $this->json(array('success' => false, 'message' => 'Paket tidak valid'), 422);
        // Paket yang belum dijual (mis. Business - segera hadir) tidak bisa dipilih, kecuali perusahaan itu memang sudah memakainya.
        if (empty(Company_model::$plans[$plan]['available'])) {
            $current = $id > 0 ? $this->Company_model->getById($id) : null;
            if (!$current || $current['plan'] !== $plan) {
                return $this->json(array('success' => false, 'message' => 'Paket ' . Company_model::$plans[$plan]['label'] . ' belum tersedia'), 422);
            }
        }
        if ($maxEmp < 1) return $this->json(array('success' => false, 'message' => 'Batas karyawan minimal 1'), 422);
        if ($name === '') return $this->json(array('success' => false, 'message' => 'Nama perusahaan wajib diisi'), 422);
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->json(array('success' => false, 'message' => 'Format email kontak tidak valid'), 422);
        }
        if ($validUntil !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil)) {
            return $this->json(array('success' => false, 'message' => 'Format tanggal aktif sampai tidak valid'), 422);
        }
        $validUntilSql = $validUntil !== '' ? $validUntil . ' 23:59:59' : null;

        $ownerName = trim((string) $this->input->post('owner_name'));
        $ownerEmail = strtolower(trim((string) $this->input->post('owner_email')));
        $ownerPass = (string) $this->input->post('owner_password');

        if ($id <= 0) {
            // ---- BUAT: perusahaan + 1 kantor awal + 1 Super Admin perusahaan ----
            if ($ownerName === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) || strlen($ownerPass) < 8) {
                return $this->json(array('success' => false, 'message' => 'Nama Super Admin, email valid, dan password (min 8 karakter) wajib diisi'), 422);
            }
            if ($this->Company_model->emailTaken($ownerEmail)) {
                return $this->json(array('success' => false, 'message' => 'Email Super Admin sudah terdaftar'), 422);
            }
            if ($code !== '' && $this->Company_model->codeExists($code)) {
                return $this->json(array('success' => false, 'message' => 'Kode perusahaan sudah dipakai'), 422);
            }
            try {
                list($cid, ) = $this->Company_model->createWithAdmin(array(
                    'company_name' => $name, 'admin_name' => $ownerName, 'email' => $ownerEmail,
                    'phone' => $contactPhone, 'password' => $ownerPass, 'plan' => $plan, 'max_employees' => $maxEmp, 'valid_days' => 0,
                    'office_name' => trim((string) $this->input->post('office_name')) ?: 'Kantor Pusat',
                    'office_address' => '', 'latitude' => null, 'longitude' => null,
                    'timezone' => strtoupper(trim((string) $this->input->post('timezone'))),
                ));
            } catch (Throwable $e) {
                log_message('error', 'Buat perusahaan gagal: ' . $e->getMessage());
                return $this->json(array('success' => false, 'message' => 'Gagal membuat perusahaan'), 500);
            }
            $this->Company_model->update($cid, array(
                'code'          => $code !== '' ? $code : $this->Company_model->makeCode($name),
                'contact_name'  => $contactName !== '' ? $contactName : $ownerName,
                'contact_email' => $contactEmail !== '' ? $contactEmail : $ownerEmail,
                'contact_phone' => $contactPhone !== '' ? $contactPhone : null,
                'plan' => $plan, 'status' => $status, 'max_employees' => $maxEmp,
                'valid_until' => $validUntilSql,
            ));
            return $this->json(array('success' => true, 'message' => 'Perusahaan dibuat', 'id' => $cid));
        }

        // ---- UBAH ----
        $company = $this->Company_model->getById($id);
        if (!$company) return $this->json(array('success' => false, 'message' => 'Perusahaan tidak ditemukan'), 404);
        if ($code === '') $code = $company['code'];
        if ($this->Company_model->codeExists($code, $id)) {
            return $this->json(array('success' => false, 'message' => 'Kode perusahaan sudah dipakai'), 422);
        }

        $owner = $this->Company_model->getOwner($id);
        if ($owner && ($ownerName !== '' || $ownerEmail !== '' || $ownerPass !== '')) {
            $newName = $ownerName !== '' ? $ownerName : $owner['name'];
            $newEmail = $ownerEmail !== '' ? $ownerEmail : strtolower($owner['email']);
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) return $this->json(array('success' => false, 'message' => 'Email Super Admin tidak valid'), 422);
            if ($this->Company_model->emailTaken($newEmail, (int) $owner['id'])) return $this->json(array('success' => false, 'message' => 'Email Super Admin sudah dipakai akun lain'), 422);
            if ($ownerPass !== '' && strlen($ownerPass) < 8) return $this->json(array('success' => false, 'message' => 'Password baru Super Admin minimal 8 karakter'), 422);
            $upd = array('name' => $newName, 'email' => $newEmail, 'updated_at' => now_datetime());
            if ($ownerPass !== '') $upd['password'] = password_hash($ownerPass, PASSWORD_BCRYPT);
            $this->db->where('id', (int) $owner['id'])->where('company_id', $id)->update('employees', $upd);
            if ($ownerPass !== '') $this->db->where('employee_id', (int) $owner['id'])->delete('auth_tokens'); // paksa login ulang di Android
        }

        $this->Company_model->update($id, array(
            'code' => $code, 'name' => $name,
            'contact_name' => $contactName !== '' ? $contactName : null,
            'contact_email' => $contactEmail !== '' ? $contactEmail : null,
            'contact_phone' => $contactPhone !== '' ? $contactPhone : null,
            'plan' => $plan, 'status' => $status, 'max_employees' => $maxEmp,
            'valid_until' => $validUntilSql,
        ));
        $this->json(array('success' => true, 'message' => 'Perusahaan tersimpan', 'id' => $id));
    }

    public function toggle_status($id)
    {
        $c = $this->Company_model->getById((int) $id);
        if (!$c) return $this->json(array('success' => false, 'message' => 'Tidak ditemukan'), 404);
        $this->Company_model->update((int) $id, array('status' => $c['status'] === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE'));
        $this->json(array('success' => true));
    }

    /** Perpanjang N hari (default 30) dari sekarang atau dari akhir masa aktif (mana yang lebih akhir). */
    public function extend($id)
    {
        $c = $this->Company_model->getById((int) $id);
        if (!$c) return $this->json(array('success' => false, 'message' => 'Tidak ditemukan'), 404);
        $days = (int) $this->input->post('days');
        if ($days < 1 || $days > 3650) $days = 30;
        $base = (!empty($c['valid_until']) && strtotime($c['valid_until']) > time()) ? strtotime($c['valid_until']) : time();
        $this->Company_model->update((int) $id, array('valid_until' => date('Y-m-d H:i:s', strtotime("+{$days} days", $base))));
        $this->json(array('success' => true));
    }

    /** Hapus PERMANEN perusahaan + semua datanya. Wajib mengetik kode perusahaan sebagai konfirmasi. */
    public function delete($id)
    {
        $c = $this->Company_model->getById((int) $id);
        if (!$c) return $this->json(array('success' => false, 'message' => 'Perusahaan tidak ditemukan'), 404);
        $confirm = strtoupper(trim((string) $this->input->post('confirm_code')));
        if ($confirm === '' || !hash_equals(strtoupper($c['code']), $confirm)) {
            return $this->json(array('success' => false, 'message' => 'Konfirmasi salah: ketik kode perusahaan ' . $c['code']), 422);
        }
        try {
            $files = $this->Company_model->deleteWithAllData((int) $id);
        } catch (Throwable $e) {
            log_message('error', 'Hapus perusahaan gagal: ' . $e->getMessage());
            return $this->json(array('success' => false, 'message' => 'Gagal menghapus perusahaan'), 500);
        }
        // Bersihkan file lampiran/foto yang tersimpan lokal (fallback). Foto di hosting eksternal tidak ikut terhapus.
        foreach ($files as $f) {
            $name = basename(parse_url((string) $f, PHP_URL_PATH) ?: (string) $f);
            if ($name === '' || $name === '.' || $name === '..') continue;
            foreach (array('attendance_photos', 'request_attachments') as $dir) {
                $path = FCPATH . 'uploads/' . $dir . '/' . $name;
                if (is_file($path)) @unlink($path);
            }
        }
        $this->json(array('success' => true, 'message' => 'Perusahaan dihapus beserta seluruh datanya: ' . $c['name']));
    }

    // ---------------- Akun Super Global Admin sendiri ----------------

    public function account()
    {
        $this->json(array('success' => true, 'data' => array('name' => $this->platformUser['name'], 'email' => $this->platformUser['email'])));
    }

    public function update_account()
    {
        $name = trim((string) $this->input->post('name'));
        $email = strtolower(trim((string) $this->input->post('email')));
        $current = (string) $this->input->post('current_password');
        $new = (string) $this->input->post('new_password');
        $me = $this->platformUser;

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(array('success' => false, 'message' => 'Nama dan email valid wajib diisi'), 422);
        }
        // Perubahan email/password selalu minta password saat ini.
        $needsAuth = ($email !== strtolower($me['email'])) || $new !== '';
        if ($needsAuth && !password_verify($current, $me['password'])) {
            return $this->json(array('success' => false, 'message' => 'Password saat ini salah'), 422);
        }
        if ($new !== '' && strlen($new) < 8) {
            return $this->json(array('success' => false, 'message' => 'Password baru minimal 8 karakter'), 422);
        }
        if ($this->Global_admin_model->emailTaken($email, (int) $me['id']) || $this->db->where('email', $email)->count_all_results('employees') > 0) {
            return $this->json(array('success' => false, 'message' => 'Email sudah dipakai akun lain'), 422);
        }
        $this->Global_admin_model->updateProfile((int) $me['id'], $name, $email);
        if ($new !== '') $this->Global_admin_model->updatePassword((int) $me['id'], $new);
        $this->session->set_userdata('global_admin_name', $name);
        $this->json(array('success' => true, 'message' => 'Akun diperbarui'));
    }
}

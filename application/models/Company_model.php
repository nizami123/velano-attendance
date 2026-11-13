<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Perusahaan (tenant) — data master yang HANYA boleh di-CRUD oleh Super Global Admin (panel /platform/companies).
 * Semua data (kantor, karyawan, admin, libur) memiliki company_id.
 *
 * Status "layak pakai" sebuah perusahaan = status ACTIVE dan (valid_until kosong ATAU belum lewat).
 * Perusahaan yang SUSPENDED / kedaluwarsa tidak bisa login (panel web maupun API Android).
 */
class Company_model extends CI_Model
{
    public static $plans = array(
        'TRIAL'      => array('label' => 'Uji Coba 14 Hari', 'max_employees' => 25,    'max_offices' => 3),
        'STARTER'    => array('label' => 'Starter',          'max_employees' => 50,    'max_offices' => 3),
        'BUSINESS'   => array('label' => 'Business',         'max_employees' => 250,   'max_offices' => 20),
        'ENTERPRISE' => array('label' => 'Enterprise',       'max_employees' => 100000, 'max_offices' => 1000),
    );

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('companies')->row_array();
    }

    /** Cek kelayakan: array(bool ok, string alasan). */
    public function usability($company): array
    {
        if (!$company) return array(false, 'Perusahaan tidak ditemukan.');
        if ($company['status'] !== 'ACTIVE') return array(false, 'Akun perusahaan ditangguhkan. Hubungi administrator Velano.');
        if (!empty($company['valid_until']) && strtotime($company['valid_until']) < time()) {
            return array(false, $company['plan'] === 'TRIAL'
                ? 'Masa uji coba berakhir. Hubungi Velano untuk berlangganan.'
                : 'Masa langganan berakhir. Hubungi Velano untuk memperpanjang.');
        }
        return array(true, '');
    }

    public function daysLeft($company): ?int
    {
        if (empty($company['valid_until'])) return null;
        return (int) floor((strtotime($company['valid_until']) - time()) / 86400);
    }

    /** Id semua kantor milik perusahaan (aktif maupun tidak). */
    public function officeIds(int $companyId): array
    {
        $rows = $this->db->select('id')->where('company_id', $companyId)->get('offices')->result_array();
        return array_map('intval', array_column($rows, 'id'));
    }

    public function countOffices(int $companyId): int
    {
        return (int) $this->db->where('company_id', $companyId)->count_all_results('offices');
    }

    /** Karyawan + driver (non-admin) yang berstatus ACTIVE dihitung ke kuota paket. */
    public function countEmployees(int $companyId): int
    {
        return (int) $this->db->where('company_id', $companyId)
            ->where_in('role', array('EMPLOYEE', 'DRIVER'))
            ->where('status', 'ACTIVE')
            ->count_all_results('employees');
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $q = $this->db->where('code', $code);
        if ($exceptId) $q->where('id !=', $exceptId);
        return $q->count_all_results('companies') > 0;
    }

    /** Kode unik dari nama, mis. "PT Maju Jaya" -> MAJUJAYA, MAJUJAYA2, ... */
    public function makeCode(string $name): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', preg_replace('/^(PT|CV|UD)\s+/i', '', $name)));
        $base = substr($base !== '' ? $base : 'CO', 0, 16);
        $code = $base;
        $i = 2;
        while ($this->codeExists($code)) $code = $base . $i++;
        return $code;
    }

    /**
     * Daftar perusahaan baru + Super Admin pertamanya + satu kantor awal, dalam satu transaksi.
     * $in: company_name, admin_name, email, phone, password, plan(opsional), valid_days(opsional),
     *      office_name, office_address, latitude, longitude
     * @return array(int companyId, int adminId)
     */
    public function createWithAdmin(array $in): array
    {
        $plan = isset(self::$plans[$in['plan'] ?? 'TRIAL']) ? ($in['plan'] ?? 'TRIAL') : 'TRIAL';
        $limits = self::$plans[$plan];
        $days = isset($in['valid_days']) ? (int) $in['valid_days'] : 14;
        $now = now_datetime();

        $this->db->trans_start();

        $this->db->insert('companies', array(
            'code'          => $this->makeCode($in['company_name']),
            'name'          => $in['company_name'],
            'contact_name'  => $in['admin_name'],
            'contact_email' => $in['email'],
            'contact_phone' => $in['phone'] ?: null,
            'plan'          => $plan,
            'status'        => 'ACTIVE',
            'max_employees' => $limits['max_employees'],
            'max_offices'   => $limits['max_offices'],
            'valid_until'   => $days > 0 ? date('Y-m-d H:i:s', strtotime("+{$days} days")) : null,
            'created_at'    => $now,
        ));
        $companyId = (int) $this->db->insert_id();

        $this->db->insert('offices', array(
            'company_id'  => $companyId,
            'code'        => 'PUSAT',
            'name'        => $in['office_name'] ?: 'Kantor Pusat',
            'address'     => $in['office_address'] ?: null,
            // Bila lokasi tidak diisi, dipakai titik Monas Jakarta sebagai placeholder (bisa diubah di Master Kantor).
            'latitude'    => is_numeric($in['latitude'] ?? null) ? (float) $in['latitude'] : -6.1753924,
            'longitude'   => is_numeric($in['longitude'] ?? null) ? (float) $in['longitude'] : 106.8271528,
            'check_in_radius' => 50, 'check_out_radius' => 50, 'tracking_interval_seconds' => 600,
            'timezone'    => in_array($in['timezone'] ?? '', array('WIB', 'WITA', 'WIT'), true) ? $in['timezone'] : 'WIB',
            'status'      => 'ACTIVE',
            'created_at'  => $now,
        ));
        $officeId = (int) $this->db->insert_id();

        $this->db->insert('employees', array(
            'company_id'    => $companyId,
            'employee_code' => 'ADM001',
            'name'          => $in['admin_name'],
            'email'         => $in['email'],
            'phone'         => $in['phone'] ?: null,
            'password'      => password_hash($in['password'], PASSWORD_BCRYPT),
            'office_id'     => $officeId,
            'position'      => 'Super Administrator',
            'role'          => 'SUPER_ADMIN',
            'status'        => 'ACTIVE',
            'created_at'    => $now,
        ));
        $adminId = (int) $this->db->insert_id();

        $this->db->trans_complete();
        if (!$this->db->trans_status()) throw new RuntimeException('Gagal membuat perusahaan');

        return array($companyId, $adminId);
    }

    // ---------------- Super Global Admin (pemilik layanan) ----------------

    public function getAllWithStats(): array
    {
        return $this->db->query(
            "SELECT c.*,
                (SELECT COUNT(*) FROM offices o WHERE o.company_id = c.id) AS office_count,
                (SELECT COUNT(*) FROM employees e WHERE e.company_id = c.id AND e.role IN ('EMPLOYEE','DRIVER') AND e.status = 'ACTIVE') AS employee_count,
                (SELECT e2.email FROM employees e2 WHERE e2.company_id = c.id AND e2.role = 'SUPER_ADMIN' ORDER BY e2.id ASC LIMIT 1) AS owner_email,
                (SELECT e3.name FROM employees e3 WHERE e3.company_id = c.id AND e3.role = 'SUPER_ADMIN' ORDER BY e3.id ASC LIMIT 1) AS owner_name
             FROM companies c ORDER BY c.created_at DESC, c.id DESC"
        )->result_array();
    }

    /** Super Admin pertama (pemilik akun) sebuah perusahaan. */
    public function getOwner(int $companyId)
    {
        return $this->db->select('id, name, email, phone, status')
            ->where('company_id', $companyId)->where('role', 'SUPER_ADMIN')
            ->order_by('id', 'ASC')->limit(1)->get('employees')->row_array();
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = now_datetime();
        $this->db->where('id', $id)->update('companies', $data);
    }

    /** Email dipakai login (panel + Android) -> harus unik di employees DAN global_admins. */
    public function emailTaken(string $email, int $exceptEmployeeId = 0): bool
    {
        $email = strtolower(trim($email));
        $q = $this->db->where('email', $email);
        if ($exceptEmployeeId > 0) $q->where('id !=', $exceptEmployeeId);
        if ($q->count_all_results('employees') > 0) return true;
        return $this->db->where('email', $email)->count_all_results('global_admins') > 0;
    }

    /**
     * Hapus PERMANEN satu perusahaan beserta seluruh datanya (kantor, karyawan, absensi, tracking,
     * pengajuan, shift, libur, pengiriman, token), dalam satu transaksi.
     * @return array nama file lampiran lokal (fallback) yang perlu dihapus dari disk
     */
    public function deleteWithAllData(int $companyId): array
    {
        $emp = "(SELECT id FROM employees WHERE company_id = ?)";
        $off = "(SELECT id FROM offices WHERE company_id = ?)";
        $files = array();

        $rows = $this->db->query("SELECT check_in_photo AS f FROM attendances WHERE employee_id IN $emp AND check_in_photo IS NOT NULL
            UNION ALL SELECT check_out_photo FROM attendances WHERE employee_id IN $emp AND check_out_photo IS NOT NULL
            UNION ALL SELECT pickup_photo FROM vehicle_deliveries WHERE driver_id IN $emp AND pickup_photo IS NOT NULL
            UNION ALL SELECT arrival_photo FROM vehicle_deliveries WHERE driver_id IN $emp AND arrival_photo IS NOT NULL
            UNION ALL SELECT attachment FROM attendance_requests WHERE employee_id IN $emp AND attachment IS NOT NULL
            UNION ALL SELECT file_path FROM attendance_request_attachments WHERE request_id IN (SELECT id FROM attendance_requests WHERE employee_id IN $emp)
            UNION ALL SELECT photo FROM employees WHERE company_id = ? AND photo IS NOT NULL",
            array($companyId, $companyId, $companyId, $companyId, $companyId, $companyId, $companyId))->result_array();
        foreach ($rows as $r) $files[] = (string) $r['f'];

        $this->db->trans_start();
        $steps = array(
            "DELETE FROM vehicle_delivery_tracking WHERE driver_id IN $emp",
            "DELETE FROM vehicle_deliveries WHERE driver_id IN $emp OR destination_office_id IN $off",
            "DELETE FROM attendance_request_attachments WHERE request_id IN (SELECT id FROM attendance_requests WHERE employee_id IN $emp)",
            "DELETE FROM attendance_requests WHERE employee_id IN $emp OR office_id IN $off",
            "DELETE FROM attendance_tracking WHERE employee_id IN $emp OR office_id IN $off",
            "DELETE FROM attendances WHERE employee_id IN $emp OR office_id IN $off",
            "DELETE FROM employee_shift_schedules WHERE employee_id IN $emp",
            "DELETE FROM admin_offices WHERE admin_id IN $emp OR office_id IN $off",
            "DELETE FROM auth_tokens WHERE employee_id IN $emp",
            "DELETE FROM devices WHERE employee_id IN $emp",
            "DELETE FROM shifts WHERE office_id IN $off",
            "DELETE FROM holidays WHERE company_id = ?",
            "DELETE FROM employees WHERE company_id = ?",
            "DELETE FROM offices WHERE company_id = ?",
            "DELETE FROM companies WHERE id = ?",
        );
        foreach ($steps as $sql) {
            $n = substr_count($sql, '?');
            $this->db->query($sql, array_fill(0, $n, $companyId));
        }
        $this->db->trans_complete();
        if (!$this->db->trans_status()) throw new RuntimeException('Gagal menghapus perusahaan');

        return $files;
    }
}

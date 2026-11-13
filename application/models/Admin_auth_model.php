<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Web admin login. Deliberately separate from Auth_model (which issues
 * bearer tokens for the Android app) — the web admin uses CodeIgniter's
 * native PHP session instead, since it's a browser, not a mobile API
 * client. Both still authenticate against the SAME `employees` table
 * and the SAME bcrypt password hashes; no new table is created.
 */
class Admin_auth_model extends CI_Model
{
    /** Panel admin perusahaan: hanya SUPER_ADMIN (per perusahaan) dan ADMIN_KANTOR. Super Global Admin ada di tabel global_admins (Global_admin_model). */
    public function findAdminByEmail(string $email)
    {
        return $this->db->where('email', $email)
            ->where('status', 'ACTIVE')
            ->where_in('role', array('SUPER_ADMIN', 'ADMIN_KANTOR'))
            ->get('employees')
            ->row_array();
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('employees')->row_array();
    }

    // ------------------------------------------------------------
    // Akses multi-kantor (tabel admin_offices)
    // ------------------------------------------------------------

    private function tableReady(): bool
    {
        static $ready = null;
        if ($ready === null) $ready = $this->db->table_exists('admin_offices');
        return $ready;
    }

    /** Id kantor yang diberikan ke seorang ADMIN_KANTOR (kosong bila tabel belum ada). */
    public function getOfficeIdsForAdmin(int $adminId): array
    {
        if (!$this->tableReady()) return array();
        $rows = $this->db->select('admin_offices.office_id')
            ->from('admin_offices')
            ->join('offices', 'offices.id = admin_offices.office_id')
            ->where('admin_offices.admin_id', $adminId)
            ->get()->result_array();
        return array_map('intval', array_column($rows, 'office_id'));
    }

    /** Semua kantor (aktif maupun tidak) milik admin — untuk form edit Super Admin. */
    public function getAllOfficeIdsForAdmin(int $adminId): array
    {
        if (!$this->tableReady()) return array();
        $rows = $this->db->select('office_id')->where('admin_id', $adminId)->get('admin_offices')->result_array();
        return array_map('intval', array_column($rows, 'office_id'));
    }

    /** Ganti seluruh daftar kantor seorang admin (replace). */
    public function setOfficesForAdmin(int $adminId, array $officeIds): void
    {
        if (!$this->tableReady()) return;
        $officeIds = array_values(array_unique(array_filter(array_map('intval', $officeIds))));
        $this->db->where('admin_id', $adminId)->delete('admin_offices');
        foreach ($officeIds as $oid) {
            $this->db->insert('admin_offices', array(
                'admin_id'   => $adminId,
                'office_id'  => $oid,
                'created_at' => now_datetime(),
            ));
        }
    }

    /** Peta admin_id => [nama kantor, ...] untuk tabel Master User Admin. */
    public function getOfficeNamesForAdmins(array $adminIds): array
    {
        $map = array();
        if (!$this->tableReady() || empty($adminIds)) return $map;
        $rows = $this->db->select('admin_offices.admin_id, offices.name')
            ->from('admin_offices')
            ->join('offices', 'offices.id = admin_offices.office_id')
            ->where_in('admin_offices.admin_id', array_map('intval', $adminIds))
            ->order_by('offices.name', 'ASC')
            ->get()->result_array();
        foreach ($rows as $r) $map[(int) $r['admin_id']][] = $r['name'];
        return $map;
    }
}

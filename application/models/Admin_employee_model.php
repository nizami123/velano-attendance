<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_employee_model extends CI_Model
{
    /**
     * $roles filters which accounts this query is for — Karyawan page
     * uses ['EMPLOYEE','DRIVER'], User Admin page uses
     * ['SUPER_ADMIN','ADMIN_KANTOR']. Same table, two different views
     * on it, matching the two separate sidebar menu items.
     */
    public function getAll(array $roles, $officeId, ?int $companyId = null): array
    {
        $this->db->select('employees.*, offices.name as office_name')
            ->from('employees')
            ->join('offices', 'offices.id = employees.office_id', 'left')
            ->where_in('employees.role', $roles)
            ->order_by('employees.name', 'ASC');
        if ($companyId !== null) $this->db->where('employees.company_id', $companyId);
        scope_where($this->db, 'employees.office_id', $officeId);
        return $this->db->get()->result_array();
    }

    public function getById(int $id, ?int $companyId = null)
    {
        $q = $this->db->where('id', $id);
        if ($companyId !== null) $q->where('company_id', $companyId);
        return $q->get('employees')->row_array();
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $q = $this->db->where('email', $email);
        if ($exceptId) $q->where('id !=', $exceptId);
        return $q->count_all_results('employees') > 0;
    }

    public function employeeCodeExists(string $code, ?int $exceptId = null, ?int $companyId = null): bool
    {
        $q = $this->db->where('employee_code', $code);
        if ($companyId !== null) $q->where('company_id', $companyId);
        if ($exceptId) $q->where('id !=', $exceptId);
        return $q->count_all_results('employees') > 0;
    }

    public function insert(array $data): int
    {
        $data['created_at'] = now_datetime();
        $this->db->insert('employees', $data);
        return (int) $this->db->insert_id();
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = now_datetime();
        $this->db->where('id', $id)->update('employees', $data);
    }

    public function toggleStatus(int $id): void
    {
        $emp = $this->getById($id);
        if (!$emp) return;
        $this->update($id, array('status' => $emp['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'));
    }

    /** Blocks hard delete if the employee already has attendance/tracking/delivery/request history (FK-safe, keeps audit trail). */
    public function hasHistory(int $id): bool
    {
        $counts = 0;
        $counts += (int) $this->db->where('employee_id', $id)->count_all_results('attendances');
        $counts += (int) $this->db->where('employee_id', $id)->count_all_results('attendance_tracking');
        $counts += (int) $this->db->where('driver_id', $id)->count_all_results('vehicle_deliveries');
        $counts += (int) $this->db->where('driver_id', $id)->count_all_results('vehicle_delivery_tracking');
        $counts += (int) $this->db->where('employee_id', $id)->count_all_results('attendance_requests');
        return $counts > 0;
    }

    /**
     * Hard delete hanya bila tidak punya riwayat. Token login & perangkat (tabel auth_tokens /
     * devices, FK tanpa cascade) ikut dibersihkan dulu, kalau tidak DELETE gagal diam-diam
     * untuk karyawan yang pernah login di aplikasi. Jadwal shift & akses kantor terhapus otomatis (ON DELETE CASCADE).
     */
    public function delete(int $id): bool
    {
        if ($this->hasHistory($id)) return false;
        $this->db->trans_start();
        $this->db->where('employee_id', $id)->delete('auth_tokens');
        $this->db->where('employee_id', $id)->delete('devices');
        $this->db->where('id', $id)->delete('employees');
        $this->db->trans_complete();
        return $this->db->trans_status() !== false && !$this->getById($id);
    }
}

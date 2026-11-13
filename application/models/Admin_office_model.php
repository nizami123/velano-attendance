<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_office_model extends CI_Model
{
    /** Selalu berikan $companyId dari controller (company_id admin yang login). */
    public function getAll(?int $companyId = null): array
    {
        if ($companyId !== null) $this->db->where('company_id', $companyId);
        return $this->db->order_by('name', 'ASC')->get('offices')->result_array();
    }

    public function getById(int $id, ?int $companyId = null)
    {
        $q = $this->db->where('id', $id);
        if ($companyId !== null) $q->where('company_id', $companyId);
        return $q->get('offices')->row_array();
    }

    public function codeExists(string $code, ?int $exceptId = null, ?int $companyId = null): bool
    {
        $q = $this->db->where('code', $code);
        if ($companyId !== null) $q->where('company_id', $companyId);
        if ($exceptId) $q->where('id !=', $exceptId);
        return $q->count_all_results('offices') > 0;
    }

    public function insert(array $data): int
    {
        $data['created_at'] = now_datetime();
        $this->db->insert('offices', $data);
        return (int) $this->db->insert_id();
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = now_datetime();
        $this->db->where('id', $id)->update('offices', $data);
    }

    public function toggleStatus(int $id): void
    {
        $office = $this->getById($id);
        if (!$office) return;
        $newStatus = $office['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $this->update($id, array('status' => $newStatus));
    }

    /** Employees still assigned to this office block a hard delete (FK constraint would fail anyway). */
    public function hasEmployees(int $id): bool
    {
        return $this->db->where('office_id', $id)->count_all_results('employees') > 0;
    }

    /** Data lain yang merujuk kantor ini lewat FK (shift, libur khusus kantor, absensi, pengajuan, tracking, pengiriman). */
    public function hasLinkedData(int $id): bool
    {
        $checks = array(
            array('shifts', 'office_id'),
            array('holidays', 'office_id'),
            array('attendances', 'office_id'),
            array('attendance_requests', 'office_id'),
            array('attendance_tracking', 'office_id'),
            array('vehicle_deliveries', 'destination_office_id'),
        );
        foreach ($checks as $c) {
            if ($this->db->where($c[1], $id)->count_all_results($c[0]) > 0) return true;
        }
        return false;
    }

    public function delete(int $id): bool
    {
        if ($this->hasEmployees($id) || $this->hasLinkedData($id)) return false;
        $this->db->where('id', $id)->delete('offices');
        return !$this->getById($id);
    }
}

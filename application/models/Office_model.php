<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Office_model extends CI_Model
{
    /** Hanya kantor milik perusahaan karyawan yang login. */
    public function getActive(int $companyId): array
    {
        $rows = $this->db->where('status', 'ACTIVE')->where('company_id', $companyId)->get('offices')->result_array();
        foreach ($rows as &$r) {
            $code = normalize_tz_code($r['timezone'] ?? null);
            $r['timezone']       = $code;                  // "WIB" | "WITA" | "WIT" (kolom offices.timezone)
            $r['timezone_id']    = tz_code_to_php($code);  // nama timezone IANA, mis. "Asia/Makassar"
            $r['timezone_label'] = $code;
        }
        unset($r);
        return $rows;
    }

    public function getById(int $id, ?int $companyId = null)
    {
        $q = $this->db->where('id', $id);
        if ($companyId !== null) $q->where('company_id', $companyId);
        return $q->get('offices')->row_array();
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Permintaan demo / uji coba dari landing page (tabel `demo_requests`).
 * Diisi pengunjung lewat /trial, dikelola Super Global Admin di /platform/demo_requests.
 */
class Demo_request_model extends CI_Model
{
    public static $statuses = array(
        'NEW'       => 'Baru',
        'CONTACTED' => 'Sudah dihubungi',
        'CONVERTED' => 'Jadi pelanggan',
        'REJECTED'  => 'Tidak lanjut',
    );

    public static $employeeRanges = array('1-10', '11-25', '26-50', '51-100', '101-250', '250+');

    public function create(array $d): int
    {
        $this->db->insert('demo_requests', array(
            'name'           => $d['name'],
            'company_name'   => $d['company_name'],
            'phone'          => $d['phone'],
            'email'          => $d['email'] !== '' ? $d['email'] : null,
            'employee_count' => $d['employee_count'] !== '' ? $d['employee_count'] : null,
            'message'        => $d['message'] !== '' ? $d['message'] : null,
            'status'         => 'NEW',
            'wa_sent'        => 0,
            'ip_address'     => $d['ip_address'] ?? null,
            'user_agent'     => isset($d['user_agent']) ? mb_substr((string) $d['user_agent'], 0, 255) : null,
            'created_at'     => now_datetime(),
        ));
        return (int) $this->db->insert_id();
    }

    public function markWaSent(int $id, bool $ok): void
    {
        $this->db->where('id', $id)->update('demo_requests', array('wa_sent' => $ok ? 1 : 0));
    }

    /** Jumlah permintaan dari IP ini dalam N menit terakhir (anti-spam). */
    public function countByIpSince(string $ip, int $minutes): int
    {
        return (int) $this->db->where('ip_address', $ip)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - $minutes * 60))
            ->count_all_results('demo_requests');
    }

    /** Apakah nomor ini sudah mengirim permintaan dalam N menit terakhir (cegah dobel klik/kirim). */
    public function phoneRecentlySubmitted(string $phone, int $minutes): bool
    {
        return $this->db->where('phone', $phone)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - $minutes * 60))
            ->count_all_results('demo_requests') > 0;
    }

    public function getAll(): array
    {
        return $this->db->select('d.*, g.name AS handled_by_name')
            ->from('demo_requests d')
            ->join('global_admins g', 'g.id = d.handled_by', 'left')
            ->order_by('d.created_at', 'DESC')
            ->order_by('d.id', 'DESC')
            ->limit(1000)
            ->get()->result_array();
    }

    public function getById(int $id)
    {
        return $this->db->where('id', $id)->get('demo_requests')->row_array();
    }

    public function updateFollowUp(int $id, string $status, string $note, int $adminId): void
    {
        $this->db->where('id', $id)->update('demo_requests', array(
            'status'     => $status,
            'admin_note' => $note !== '' ? $note : null,
            'handled_by' => $adminId,
            'handled_at' => now_datetime(),
            'updated_at' => now_datetime(),
        ));
    }

    public function delete(int $id): void
    {
        $this->db->where('id', $id)->delete('demo_requests');
    }

    public function countNew(): int
    {
        return (int) $this->db->where('status', 'NEW')->count_all_results('demo_requests');
    }
}

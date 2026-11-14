<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Form permintaan demo / uji coba (tujuan tombol "Minta Uji Coba" di landing page).
 *
 * Alur: pengunjung isi form -> data disimpan ke tabel `demo_requests` -> notifikasi WA langsung
 * dikirim ke nomor admin (Fonnte, lihat helpers/wa_helper.php) -> Super Global Admin menindaklanjuti
 * dari panel /platform/demo_requests.
 */
class Trial extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session'); // database baru dibuka saat form dikirim (GET tidak butuh DB)
        $this->load->helper(array('url', 'form', 'response', 'wa'));
        $this->load->model('Demo_request_model');
    }

    public function index()
    {
        $data = array('error' => null, 'old' => array(), 'done' => false, 'ranges' => Demo_request_model::$employeeRanges);

        // Halaman "terima kasih" (Post/Redirect/Get, supaya refresh tidak mengirim ulang form).
        if ($this->session->flashdata('trial_ok')) {
            $data['done'] = true;
            $this->load->view('public/trial', $data);
            return;
        }

        if ($this->input->method() === 'post') {
            $this->load->database();
            $in = array(
                'name'           => trim((string) $this->input->post('name')),
                'company_name'   => trim((string) $this->input->post('company_name')),
                'phone'          => trim((string) $this->input->post('phone')),
                'email'          => strtolower(trim((string) $this->input->post('email'))),
                'employee_count' => trim((string) $this->input->post('employee_count')),
                'message'        => trim((string) $this->input->post('message')),
            );
            $data['old'] = $in;

            $phone = $this->normalizePhone($in['phone']);
            $sent  = (string) $this->input->post('csrf_trial');
            $token = (string) $this->session->userdata('csrf_trial');
            $last  = (int) $this->session->userdata('trial_last');
            $ip    = (string) $this->input->ip_address();

            if ((string) $this->input->post('website') !== '') {
                show_404(); // honeypot: bot mengisi kolom tersembunyi
            } elseif ($token === '' || !hash_equals($token, $sent)) {
                $data['error'] = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
            } elseif (time() - $last < 5) {
                $data['error'] = 'Terlalu cepat, coba lagi sebentar.';
            } elseif ($in['name'] === '' || $in['company_name'] === '' || $in['phone'] === '') {
                $data['error'] = 'Nama, nama perusahaan, dan nomor WhatsApp wajib diisi.';
            } elseif ($phone === null) {
                $data['error'] = 'Nomor WhatsApp tidak valid. Contoh: 081234567890';
            } elseif ($in['email'] !== '' && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
                $data['error'] = 'Format email tidak valid.';
            } elseif ($in['employee_count'] !== '' && !in_array($in['employee_count'], Demo_request_model::$employeeRanges, true)) {
                $data['error'] = 'Pilihan jumlah karyawan tidak valid.';
            } elseif ($this->Demo_request_model->countByIpSince($ip, 60) >= 5) {
                $data['error'] = 'Terlalu banyak permintaan dari jaringan Anda. Silakan coba lagi nanti.';
            } else {
                $this->session->set_userdata('trial_last', time());

                // Nomor yang sama baru saja mengirim (mis. klik dua kali): anggap sukses, jangan simpan/kirim WA lagi.
                if (!$this->Demo_request_model->phoneRecentlySubmitted($phone, 10)) {
                    try {
                        $id = $this->Demo_request_model->create(array(
                            'name'           => mb_substr($in['name'], 0, 150),
                            'company_name'   => mb_substr($in['company_name'], 0, 200),
                            'phone'          => $phone,
                            'email'          => mb_substr($in['email'], 0, 150),
                            'employee_count' => $in['employee_count'],
                            'message'        => mb_substr($in['message'], 0, 1000),
                            'ip_address'     => $ip,
                            'user_agent'     => (string) $this->input->user_agent(),
                        ));
                    } catch (Throwable $e) {
                        log_message('error', 'Simpan permintaan demo gagal: ' . $e->getMessage());
                        $data['error'] = 'Maaf, permintaan gagal disimpan. Silakan coba lagi.';
                        $id = 0;
                    }

                    if ($id > 0) {
                        // Notifikasi WA langsung ke admin. Gagal kirim WA tidak membatalkan data yang sudah tersimpan.
                        $ok = kirimWA($this->buildMessage($id, $in, $phone));
                        $this->Demo_request_model->markWaSent($id, $ok);
                    }
                }

                if ($data['error'] === null) {
                    $this->session->set_flashdata('trial_ok', 1);
                    redirect('trial');
                    return;
                }
            }
        }

        if (!$this->session->userdata('csrf_trial')) {
            $this->session->set_userdata('csrf_trial', bin2hex(random_bytes(16)));
        }
        $data['csrf'] = (string) $this->session->userdata('csrf_trial');
        $this->load->view('public/trial', $data);
    }

    /** Pesan WA untuk admin. */
    private function buildMessage(int $id, array $in, string $phone): string
    {
        $lines = array(
            '*Permintaan Demo / Uji Coba Velano* (#' . $id . ')',
            '',
            'Nama: ' . $in['name'],
            'Perusahaan: ' . $in['company_name'],
            'WhatsApp: ' . $phone . ' (wa.me/' . $phone . ')',
            'Email: ' . ($in['email'] !== '' ? $in['email'] : '-'),
            'Jumlah karyawan: ' . ($in['employee_count'] !== '' ? $in['employee_count'] : '-'),
        );
        if ($in['message'] !== '') $lines[] = 'Pesan: ' . mb_substr($in['message'], 0, 500);
        $lines[] = '';
        $lines[] = 'Waktu: ' . now_datetime();
        $lines[] = 'Kelola: ' . site_url('platform/demo_requests');
        return implode("\n", $lines);
    }

    /** Ubah 08xx / +62xx / 62xx menjadi 628xx. NULL bila bukan nomor yang masuk akal (9-15 digit). */
    private function normalizePhone(string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', $raw);
        if ($d === '') return null;
        if (strpos($d, '00') === 0) $d = substr($d, 2);
        if (strpos($d, '0') === 0) $d = '62' . substr($d, 1);
        elseif (strpos($d, '8') === 0) $d = '62' . $d;
        $len = strlen($d);
        return ($len >= 9 && $len <= 15) ? $d : null;
    }
}

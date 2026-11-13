<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('kirimWA')) {
    /**
     * Kirim pesan WhatsApp lewat Fonnte.
     * Token & nomor tujuan default dibaca dari application/config/fonnte.php.
     * Tidak pernah melempar error: kegagalan hanya ditulis ke log dan mengembalikan FALSE,
     * sehingga proses utama (mis. menyimpan form) tidak ikut gagal.
     *
     * @param string      $pesan  isi pesan
     * @param string|null $target nomor tujuan (628xxxx); null = nomor admin di config
     */
    function kirimWA(string $pesan, ?string $target = null): bool
    {
        $CI =& get_instance();
        $CI->config->load('fonnte', TRUE, TRUE);
        $token  = (string) $CI->config->item('fonnte_token', 'fonnte');
        $target = $target !== null && $target !== '' ? $target : (string) $CI->config->item('fonnte_target', 'fonnte');

        if ($token === '' || $target === '') {
            log_message('error', 'kirimWA: token/nomor tujuan Fonnte belum diisi (config/fonnte.php)');
            return false;
        }
        if (!function_exists('curl_init')) {
            log_message('error', 'kirimWA: ekstensi PHP cURL tidak aktif');
            return false;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL            => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => array(
                'target'      => $target,
                'message'     => $pesan,
                'countryCode' => '62',
            ),
            CURLOPT_HTTPHEADER     => array('Authorization: ' . $token),
        ));
        $res  = curl_exec($curl);
        $err  = curl_error($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($res === false) {
            log_message('error', 'kirimWA gagal (cURL): ' . $err);
            return false;
        }
        $json = json_decode((string) $res, true);
        $ok = $code >= 200 && $code < 300 && is_array($json) && !empty($json['status']);
        if (!$ok) log_message('error', 'kirimWA ditolak Fonnte (HTTP ' . $code . '): ' . substr((string) $res, 0, 300));
        return $ok;
    }
}

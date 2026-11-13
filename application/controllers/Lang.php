<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Ganti bahasa panel web: /lang/en  atau  /lang/id  (disimpan di cookie 1 tahun lalu kembali ke halaman sebelumnya). */
class Lang extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
    }

    public function set($code = 'id')
    {
        $code = $code === 'en' ? 'en' : 'id';
        setcookie('lang', $code, array('expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax'));
        $back = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
        $host = parse_url($back, PHP_URL_HOST);
        $mine = parse_url(base_url(), PHP_URL_HOST);
        // Hanya kembali ke halaman di domain yang sama (cegah open redirect).
        header('Location: ' . (($back !== '' && $host === $mine) ? $back : site_url('admin/auth/login')));
        exit;
    }
}

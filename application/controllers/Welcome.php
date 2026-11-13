<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Halaman utama (/) = landing page Velano (landing/index.html).
 * Sebelumnya menampilkan halaman bawaan CodeIgniter, sehingga landing page tidak pernah terlihat
 * kecuali pengunjung membuka /landing/ secara manual.
 */
class Welcome extends CI_Controller
{
    public function index()
    {
        $file = FCPATH . 'landing/index.html';
        if (!is_file($file)) {
            show_404();
            return;
        }
        $this->output
            ->set_content_type('text/html', 'utf-8')
            ->set_output(file_get_contents($file));
    }
}

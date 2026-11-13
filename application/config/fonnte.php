<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Konfigurasi WhatsApp gateway Fonnte (https://fonnte.com).
 * Dipakai oleh helper kirimWA() (application/helpers/wa_helper.php).
 *
 * Token boleh diisi lewat environment variable FONNTE_TOKEN (lebih aman),
 * atau langsung di bawah ini. JANGAN commit token asli ke repository publik.
 */
$config['fonnte_token']  = getenv('FONNTE_TOKEN') ?: 'YcNnR3CyxYxPMxYb5siR';

// Nomor WA admin penerima notifikasi permintaan demo (format 628xxxx).
$config['fonnte_target'] = getenv('FONNTE_TARGET') ?: '6281249710657';

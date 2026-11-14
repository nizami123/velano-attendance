<!DOCTYPE html>
<html lang="<?= app_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran - Velano</title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/admin/img/logo.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin.css') ?>">
</head>
<body>
<div class="at-login-wrap">
    <div class="at-login-card text-center">
        <div class="text-end mb-1"><?php $this->load->view('admin/layout/lang_switch'); ?></div>
        <img src="<?= base_url('assets/admin/img/logo-full.png') ?>" alt="Velano" style="width:230px;max-width:100%;" class="mb-3">
        <h5 class="fw-bold">Akun perusahaan dibuat oleh admin Velano</h5>
        <p class="text-gray small">Pendaftaran mandiri tidak tersedia. Hubungi tim Velano untuk berlangganan atau meminta uji coba &mdash; akun perusahaan Anda akan dibuatkan dan dikirimkan.</p>
        <a href="<?= site_url('admin/auth/login') ?>" class="btn btn-at-primary w-100 fw-bold">Ke Halaman Login</a>
    </div>
</div>
</body>
</html>

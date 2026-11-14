<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minta Demo / Uji Coba - Velano</title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/admin/img/favicon-64.png') ?>">
    <link rel="preload" as="image" href="<?= base_url('assets/admin/img/logo-sm.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/public/css/trial.css') ?>?v=1">
</head>
<body>
<div class="at-login-wrap">
    <div class="at-login-card" style="max-width:520px;">
        <div class="text-center">
            <div class="at-login-logo"><img src="<?= base_url('assets/admin/img/logo-sm.png') ?>" alt="Velano" width="150" height="100" decoding="async"></div>

        <?php if (!empty($done)): ?>
            <h5 class="fw-bold mb-2 mt-2">Terima kasih!</h5>
            <p class="text-gray small mb-3">Permintaan demo Anda sudah kami terima. Tim Velano akan menghubungi Anda lewat WhatsApp secepatnya.</p>
            <a href="<?= base_url() ?>" class="btn btn-at-primary w-100 fw-bold py-2">KEMBALI KE BERANDA</a>
        </div>

        <?php else: ?>
            <h5 class="fw-bold mb-0">Minta Demo / Uji Coba Velano</h5>
            <p class="text-gray small mb-3">Isi data singkat di bawah, tim kami akan menghubungi Anda lewat WhatsApp.</p>
        </div>
        <?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= html_escape($error) ?></div><?php endif; ?>
        <form method="post" action="<?= site_url('trial') ?>" autocomplete="on">
            <input type="hidden" name="csrf_trial" value="<?= html_escape($csrf) ?>">
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true">

            <div class="mb-2"><input class="form-control" name="name" placeholder="Nama Anda *" maxlength="150" value="<?= html_escape($old['name'] ?? '') ?>" required></div>
            <div class="mb-2"><input class="form-control" name="company_name" placeholder="Nama perusahaan *" maxlength="200" value="<?= html_escape($old['company_name'] ?? '') ?>" required></div>
            <div class="mb-2"><input class="form-control" type="tel" name="phone" inputmode="tel" placeholder="No. WhatsApp * (mis. 081234567890)" maxlength="25" value="<?= html_escape($old['phone'] ?? '') ?>" required></div>
            <div class="mb-2"><input class="form-control" type="email" name="email" placeholder="Email kerja (opsional)" maxlength="150" value="<?= html_escape($old['email'] ?? '') ?>"></div>
            <div class="mb-2">
                <select class="form-select" name="employee_count">
                    <option value="">Jumlah karyawan (opsional)</option>
                    <?php foreach ($ranges as $r): ?>
                        <option value="<?= html_escape($r) ?>" <?= ($old['employee_count'] ?? '') === $r ? 'selected' : '' ?>><?= html_escape($r) ?> karyawan</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><textarea class="form-control" name="message" rows="3" maxlength="1000" placeholder="Kebutuhan / pertanyaan Anda (opsional)"><?= html_escape($old['message'] ?? '') ?></textarea></div>
            <button type="submit" class="btn btn-at-primary w-100 fw-bold py-2">KIRIM PERMINTAAN</button>
        </form>
        <p class="text-center text-gray small mt-3 mb-0"><a href="<?= base_url() ?>" class="text-decoration-none">&larr; Kembali ke beranda</a></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

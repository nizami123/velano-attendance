<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coba Gratis 7 Hari - Velano</title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/admin/img/logo.png') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin.css') ?>">
</head>
<body>
<div class="at-login-wrap">
    <div class="at-login-card" style="max-width:520px;">
        <div class="text-center">
            <div class="at-login-logo"><img src="<?= base_url('assets/admin/img/logo.png') ?>" alt="Velano" style="width:100%;height:100%;object-fit:contain;"></div>
            <h5 class="fw-bold mb-0">Coba Velano Gratis 7 Hari</h5>
            <p class="text-gray small mb-3">Tanpa kartu kredit. Siap dipakai dalam 2 menit.</p>
        </div>
        <?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= html_escape($error) ?></div><?php endif; ?>
        <form method="post" action="<?= site_url('daftar') ?>">
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true">
            <div class="fw-bold small text-gray mb-2">PERUSAHAAN & AKUN ADMIN</div>
            <div class="mb-2"><input class="form-control" name="company_name" placeholder="Nama perusahaan *" value="<?= html_escape($old['company_name'] ?? '') ?>" required></div>
            <div class="row g-2 mb-2">
                <div class="col-sm-6"><input class="form-control" name="admin_name" placeholder="Nama Anda *" value="<?= html_escape($old['admin_name'] ?? '') ?>" required></div>
                <div class="col-sm-6"><input class="form-control" name="phone" placeholder="No. WhatsApp" value="<?= html_escape($old['phone'] ?? '') ?>"></div>
            </div>
            <div class="mb-2"><input type="email" class="form-control" name="email" placeholder="Email kerja * (untuk login)" value="<?= html_escape($old['email'] ?? '') ?>" required></div>
            <div class="mb-3"><input type="password" class="form-control" name="password" placeholder="Password (min. 8 karakter) *" minlength="8" required></div>

            <div class="fw-bold small text-gray mb-2">KANTOR PERTAMA <span class="fw-normal">(bisa diubah nanti)</span></div>
            <div class="mb-2"><input class="form-control" name="office_name" placeholder="Nama kantor, mis. Kantor Pusat" value="<?= html_escape($old['office_name'] ?? '') ?>"></div>
            <div class="mb-2"><input class="form-control" name="office_address" placeholder="Alamat kantor" value="<?= html_escape($old['office_address'] ?? '') ?>"></div>
            <div class="row g-2 mb-2">
                <div class="col-5"><input class="form-control" name="latitude" id="lat" placeholder="Latitude" value="<?= html_escape($old['latitude'] ?? '') ?>"></div>
                <div class="col-5"><input class="form-control" name="longitude" id="lng" placeholder="Longitude" value="<?= html_escape($old['longitude'] ?? '') ?>"></div>
                <div class="col-2"><button type="button" class="btn btn-outline-secondary w-100" id="geo" title="Pakai lokasi saya"><i class="bi bi-geo-alt"></i></button></div>
            </div>
            <select name="timezone" class="form-select mb-3">
                <option value="WIB">WIB (Jakarta)</option><option value="WITA">WITA (Makassar)</option><option value="WIT">WIT (Jayapura)</option>
            </select>
            <button type="submit" class="btn btn-at-primary w-100 fw-bold py-2">MULAI COBA GRATIS</button>
        </form>
        <p class="text-center text-gray small mt-3 mb-0">Sudah punya akun? <a href="<?= site_url('admin/auth/login') ?>" class="fw-bold text-decoration-none">Masuk</a></p>
    </div>
</div>
<script>
document.getElementById('geo').addEventListener('click', function () {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(function (p) {
        document.getElementById('lat').value = p.coords.latitude.toFixed(7);
        document.getElementById('lng').value = p.coords.longitude.toFixed(7);
    });
});
</script>
</body>
</html>

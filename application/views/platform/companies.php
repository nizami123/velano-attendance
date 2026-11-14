<!DOCTYPE html>
<html lang="<?= app_lang() ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Master Perusahaan | Velano Super Global Admin</title>
<link rel="icon" type="image/png" href="<?= base_url('assets/admin/img/logo.png') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/admin/css/admin.css') ?>">
</head>
<body style="background:var(--at-light-gray);">
<nav class="navbar bg-white border-bottom px-3 sticky-top">
  <span class="d-flex align-items-center">
    <img src="<?= base_url('assets/admin/img/logo-full.png') ?>" style="height:36px" class="me-3" alt="Velano">
    <span class="badge rounded-pill text-bg-success">Super Global Admin</span>
  </span>
  <span class="small d-flex align-items-center gap-3">
    <?php $this->load->view('admin/layout/lang_switch'); ?>
    <a href="<?= site_url('platform/demo_requests') ?>" class="btn btn-sm btn-light position-relative"><i class="bi bi-inbox me-1"></i>Permintaan Demo<?php if (!empty($new_demo)): ?> <span class="badge rounded-pill bg-danger"><?= (int) $new_demo ?></span><?php endif; ?></a>
    <span class="text-gray d-none d-sm-inline"><i class="bi bi-person-circle me-1"></i><span id="meName"><?= html_escape($me['name']) ?></span></span>
    <button class="btn btn-sm btn-light" onclick="openAccount()"><i class="bi bi-key me-1"></i>Akun Saya</button>
    <a href="<?= site_url('admin/auth/logout') ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
  </span>
</nav>

<div class="container-xl py-4">
  <div class="row g-3 mb-3" id="stats"></div>

  <div class="at-card p-3">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
      <h6 class="fw-bold mb-0"><i class="bi bi-buildings me-2"></i>Master Perusahaan (Berlangganan)</h6>
      <div class="d-flex gap-2">
        <input type="search" id="q" class="form-control form-control-sm" placeholder="Cari nama / kode / email..." style="min-width:220px">
        <button class="btn btn-at-primary btn-sm text-nowrap" onclick="openForm()"><i class="bi bi-plus-lg me-1"></i>Perusahaan Baru</button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table align-middle small mb-0">
        <thead><tr><th>Perusahaan</th><th>Super Admin</th><th>Paket</th><th>Kantor</th><th>Karyawan</th><th>Masa Aktif</th><th>Status</th><th></th></tr></thead>
        <tbody id="rows"><tr><td colspan="8" class="text-center text-gray py-4">Memuat...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Form perusahaan -->
<div class="modal fade" id="m" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" id="f" autocomplete="off">
  <div class="modal-header"><h6 class="modal-title fw-bold" id="mt">Perusahaan</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="id" id="id">
    <div class="row g-2">
      <div class="col-md-8"><label class="form-label small fw-bold">Nama perusahaan *</label><input class="form-control" name="name" required></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Kode</label><input class="form-control" name="code" placeholder="otomatis" maxlength="50"></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Nama kontak</label><input class="form-control" name="contact_name"></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Email kontak</label><input class="form-control" type="email" name="contact_email"></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Telepon / WA</label><input class="form-control" name="contact_phone"></div>
    </div>
    <hr>
    <div class="row g-2">
      <div class="col-md-4"><label class="form-label small fw-bold">Paket</label>
        <select class="form-select" name="plan" id="plan"><?php foreach ($plans as $k => $p): ?><option value="<?= $k ?>"><?= html_escape($p['label']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Maks. karyawan <span class="text-gray fw-normal">(sesuai yang dibayar)</span></label><input class="form-control" type="number" min="1" name="max_employees" id="me" required></div>
      <div class="col-md-6"><label class="form-label small fw-bold">Aktif sampai <span class="text-gray fw-normal">(kosong = tanpa batas)</span></label><input class="form-control" type="date" name="valid_until" id="vu"></div>
      <div class="col-md-6"><label class="form-label small fw-bold">Status</label>
        <select class="form-select" name="status" id="status"><option value="ACTIVE">Aktif</option><option value="SUSPENDED">Ditangguhkan</option></select></div>
    </div>
    <hr>
    <div class="fw-bold small mb-2"><i class="bi bi-person-badge me-1"></i>Super Admin perusahaan <span class="text-gray fw-normal" id="ownerHint"></span></div>
    <div class="row g-2">
      <div class="col-md-4"><label class="form-label small fw-bold">Nama</label><input class="form-control" name="owner_name" id="on"></div>
      <div class="col-md-4"><label class="form-label small fw-bold">Email login</label><input class="form-control" type="email" name="owner_email" id="oe"></div>
      <div class="col-md-4"><label class="form-label small fw-bold" id="opl">Password</label><input class="form-control" type="text" name="owner_password" id="op" autocomplete="new-password" placeholder="min. 8 karakter"></div>
      <div class="col-md-8 newOnly"><label class="form-label small fw-bold">Nama kantor pertama</label><input class="form-control" name="office_name" placeholder="Kantor Pusat"></div>
      <div class="col-md-4 newOnly"><label class="form-label small fw-bold">Zona waktu</label><select class="form-select" name="timezone"><option>WIB</option><option>WITA</option><option>WIT</option></select></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-at-primary">Simpan</button></div>
</form></div></div>

<!-- Konfirmasi hapus -->
<div class="modal fade" id="md" tabindex="-1"><div class="modal-dialog"><form class="modal-content" id="fd">
  <div class="modal-header"><h6 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Hapus Perusahaan</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body small">
    <p class="mb-2">Semua data <b id="dName"></b> (kantor, karyawan, absensi, tracking, pengajuan, pengiriman) akan <b>dihapus permanen</b> dan tidak bisa dikembalikan.</p>
    <p class="mb-2">Untuk melanjutkan, ketik kode perusahaan: <code id="dCode" class="fs-6"></code></p>
    <input type="hidden" id="dId"><input class="form-control" id="dConfirm" placeholder="Ketik kode di sini" autocomplete="off">
    <p class="text-gray mt-2 mb-0">Ingin sementara saja? Gunakan tombol <b>Tangguhkan</b> - data tetap aman.</p>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger">Hapus Permanen</button></div>
</form></div></div>

<!-- Akun saya -->
<div class="modal fade" id="ma" tabindex="-1"><div class="modal-dialog"><form class="modal-content" id="fa" autocomplete="off">
  <div class="modal-header"><h6 class="modal-title fw-bold">Akun Super Global Admin</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label small fw-bold">Nama</label><input class="form-control mb-2" name="name" id="an" required>
    <label class="form-label small fw-bold">Email login</label><input class="form-control mb-2" type="email" name="email" id="ae" required>
    <hr>
    <label class="form-label small fw-bold">Password baru <span class="text-gray fw-normal">(kosongkan bila tidak diganti)</span></label><input class="form-control mb-2" type="password" name="new_password" autocomplete="new-password" placeholder="min. 8 karakter">
    <label class="form-label small fw-bold">Password saat ini <span class="text-gray fw-normal">(wajib bila ganti email / password)</span></label><input class="form-control" type="password" name="current_password" autocomplete="current-password">
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-at-primary">Simpan</button></div>
</form></div></div>

<div class="toast-container position-fixed bottom-0 end-0 p-3"><div id="toast" class="toast align-items-center text-bg-dark border-0" role="status"><div class="d-flex"><div class="toast-body" id="toastBody"></div></div></div></div>

<script src="<?= base_url('assets/vendor/bootstrap.bundle.min.js') ?>"></script>
<script>
const CSRF = <?= json_encode($csrf) ?>;
const U = p => '<?= site_url('platform/companies') ?>/' + p;
const modal = new bootstrap.Modal('#m'), delModal = new bootstrap.Modal('#md'), accModal = new bootstrap.Modal('#ma');
let data = [];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const $ = id => document.getElementById(id);
function toast(msg) { $('toastBody').textContent = msg; bootstrap.Toast.getOrCreateInstance($('toast'), {delay: 3500}).show(); }
async function post(path, body) {
  const fd = body instanceof FormData ? body : new FormData();
  const res = await fetch(U(path), {method: 'POST', body: fd, headers: {'X-CSRF-Token': CSRF}});
  let j = {}; try { j = await res.json(); } catch (e) { j = {success: false, message: 'Respon server tidak valid'}; }
  if (res.status === 401) { location.href = '<?= site_url('admin/auth/login') ?>'; }
  return j;
}
function render() {
  const q = $('q').value.trim().toLowerCase();
  const list = data.filter(c => !q || [c.name, c.code, c.owner_email, c.contact_email].some(v => String(v ?? '').toLowerCase().includes(q)));
  $('rows').innerHTML = list.map(c => {
    const left = c.valid_until ? (c.days_left < 0 ? '<span class="text-danger">Habis</span>' : c.days_left + ' hari lagi') : 'Tanpa batas';
    const st = c.usable ? '<span class="badge bg-success">Aktif</span>' : (c.status === 'SUSPENDED' ? '<span class="badge bg-danger">Ditangguhkan</span>' : '<span class="badge bg-warning text-dark">Kedaluwarsa</span>');
    return `<tr><td><b>${esc(c.name)}</b><div class="text-gray">${esc(c.code)} · dibuat ${esc((c.created_at || '').slice(0,10))}</div></td>
      <td>${esc(c.owner_name)}<div class="text-gray">${esc(c.owner_email)}</div></td><td>${esc(c.plan)}</td>
      <td>${c.office_count}</td><td>${c.employee_count}/${c.max_employees}</td><td>${left}</td><td>${st}</td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-light" title="Edit" data-a="edit" data-id="${c.id}"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-sm btn-light" title="Perpanjang 30 hari" data-a="extend" data-id="${c.id}">+30 hr</button>
        <button class="btn btn-sm btn-light" data-a="toggle_status" data-id="${c.id}">${c.status === 'ACTIVE' ? 'Tangguhkan' : 'Aktifkan'}</button>
        <button class="btn btn-sm btn-outline-danger" title="Hapus" data-a="del" data-id="${c.id}"><i class="bi bi-trash"></i></button></td></tr>`;
  }).join('') || '<tr><td colspan="8" class="text-center text-gray py-4">' + (data.length ? 'Tidak ada yang cocok' : 'Belum ada perusahaan') + '</td></tr>';
}
async function load() {
  const r = await fetch(U('list_data'));
  if (r.status === 401) { location.href = '<?= site_url('admin/auth/login') ?>'; return; }
  data = (await r.json()).data;
  const usable = data.filter(c => c.usable).length, trial = data.filter(c => c.plan === 'TRIAL' && c.usable).length;
  const emp = data.reduce((a, c) => a + +c.employee_count, 0);
  $('stats').innerHTML = [['Perusahaan', data.length], ['Aktif', usable], ['Masa uji coba', trial], ['Karyawan aktif', emp]]
    .map(([l, v]) => `<div class="col-6 col-md-3"><div class="at-card p-3"><div class="text-gray small">${l}</div><div class="fs-4 fw-bold">${v}</div></div></div>`).join('');
  render();
}
$('q').addEventListener('input', render);
$('rows').addEventListener('click', async e => {
  const b = e.target.closest('button[data-a]'); if (!b) return;
  const id = +b.dataset.id, a = b.dataset.a;
  if (a === 'edit') return openForm(id);
  if (a === 'del') return openDelete(id);
  const r = await post(a + '/' + id, new FormData());
  toast(r.success ? 'Berhasil' : (r.message || 'Gagal')); load();
});
async function openForm(id) {
  const f = $('f'); f.reset(); $('id').value = '';
  const isNew = !id;
  document.querySelectorAll('.newOnly').forEach(el => el.style.display = isNew ? '' : 'none');
  $('mt').textContent = isNew ? 'Perusahaan Baru' : 'Edit Perusahaan';
  $('on').required = $('oe').required = $('op').required = isNew;
  $('opl').textContent = isNew ? 'Password awal *' : 'Reset password (opsional)';
  $('ownerHint').textContent = isNew ? '- akun ini dipakai admin perusahaan untuk login' : '- kosongkan bila tidak diubah';
  if (isNew) { f.plan.value = 'TRIAL'; f.max_employees.value = 25; f.status.value = 'ACTIVE'; modal.show(); return; }
  const r = await (await fetch(U('detail/' + id))).json();
  if (!r.success) return toast(r.message || 'Gagal memuat');
  const c = r.data, o = r.owner || {};
  $('id').value = c.id;
  f.name.value = c.name; f.code.value = c.code; f.contact_name.value = c.contact_name || ''; f.contact_email.value = c.contact_email || ''; f.contact_phone.value = c.contact_phone || '';
  f.plan.value = c.plan; f.max_employees.value = c.max_employees; f.status.value = c.status;
  f.valid_until.value = c.valid_until ? c.valid_until.slice(0, 10) : '';
  f.owner_name.value = o.name || ''; f.owner_email.value = o.email || '';
  modal.show();
}
$('f').addEventListener('submit', async e => {
  e.preventDefault();
  const r = await post('save', new FormData(e.target));
  if (!r.success) return toast(r.message || 'Gagal menyimpan');
  modal.hide(); toast(r.message); load();
});
function openDelete(id) {
  const c = data.find(x => x.id == id); if (!c) return;
  $('dId').value = c.id; $('dName').textContent = c.name; $('dCode').textContent = c.code; $('dConfirm').value = ''; delModal.show();
}
$('fd').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(); fd.append('confirm_code', $('dConfirm').value);
  const r = await post('delete/' + $('dId').value, fd);
  toast(r.message || (r.success ? 'Dihapus' : 'Gagal')); if (r.success) { delModal.hide(); load(); }
});
async function openAccount() {
  const r = await (await fetch(U('account'))).json();
  $('fa').reset(); $('an').value = r.data.name; $('ae').value = r.data.email; accModal.show();
}
$('fa').addEventListener('submit', async e => {
  e.preventDefault();
  const r = await post('update_account', new FormData(e.target));
  toast(r.message || 'Gagal'); if (r.success) { $('meName').textContent = $('an').value; accModal.hide(); }
});
load();
</script>
</body></html>

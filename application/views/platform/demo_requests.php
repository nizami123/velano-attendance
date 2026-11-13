<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Permintaan Demo | Velano Super Global Admin</title>
<link rel="icon" type="image/png" href="<?= base_url('assets/admin/img/logo.png') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= base_url('assets/admin/css/admin.css') ?>">
</head>
<body style="background:var(--at-light-gray);">
<nav class="navbar bg-white border-bottom px-3 sticky-top">
  <span class="d-flex align-items-center">
    <img src="<?= base_url('assets/admin/img/logo-full.png') ?>" style="height:36px" class="me-3" alt="Velano">
    <span class="badge rounded-pill text-bg-success">Super Global Admin</span>
  </span>
  <span class="small d-flex align-items-center gap-3">
    <a href="<?= site_url('platform/companies') ?>" class="btn btn-sm btn-light"><i class="bi bi-buildings me-1"></i>Perusahaan</a>
    <span class="text-gray d-none d-sm-inline"><i class="bi bi-person-circle me-1"></i><?= html_escape($me['name']) ?></span>
    <a href="<?= site_url('admin/auth/logout') ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
  </span>
</nav>

<div class="container-xl py-4">
  <div class="row g-3 mb-3" id="stats"></div>

  <div class="at-card p-3">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
      <h6 class="fw-bold mb-0"><i class="bi bi-inbox me-2"></i>Permintaan Demo / Uji Coba</h6>
      <div class="d-flex gap-2 flex-wrap">
        <select id="fs" class="form-select form-select-sm" style="width:auto">
          <option value="">Semua status</option>
          <?php foreach ($statuses as $k => $label): ?><option value="<?= $k ?>"><?= html_escape($label) ?></option><?php endforeach; ?>
        </select>
        <input type="search" id="q" class="form-control form-control-sm" placeholder="Cari nama / perusahaan / nomor..." style="min-width:220px">
        <button class="btn btn-light btn-sm" onclick="load()" title="Muat ulang"><i class="bi bi-arrow-clockwise"></i></button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table align-middle small mb-0">
        <thead><tr><th>Masuk</th><th>Pemohon</th><th>Kontak</th><th>Karyawan</th><th>Pesan</th><th>Status</th><th></th></tr></thead>
        <tbody id="rows"><tr><td colspan="7" class="text-center text-gray py-4">Memuat...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Tindak lanjut -->
<div class="modal fade" id="m" tabindex="-1"><div class="modal-dialog"><form class="modal-content" id="f">
  <div class="modal-header"><h6 class="modal-title fw-bold">Tindak Lanjut</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body small">
    <input type="hidden" id="id">
    <div id="info" class="mb-3"></div>
    <label class="form-label fw-bold">Status</label>
    <select class="form-select mb-2" id="status"><?php foreach ($statuses as $k => $label): ?><option value="<?= $k ?>"><?= html_escape($label) ?></option><?php endforeach; ?></select>
    <label class="form-label fw-bold">Catatan</label>
    <textarea class="form-control" id="note" rows="3" maxlength="2000" placeholder="mis. sudah dihubungi, jadwal demo Senin 10.00"></textarea>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-at-primary">Simpan</button></div>
</form></div></div>

<div class="toast-container position-fixed bottom-0 end-0 p-3"><div id="toast" class="toast align-items-center text-bg-dark border-0" role="status"><div class="d-flex"><div class="toast-body" id="toastBody"></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = <?= json_encode($csrf) ?>;
const STATUSES = <?= json_encode($statuses) ?>;
const BADGE = {NEW: 'bg-danger', CONTACTED: 'bg-primary', CONVERTED: 'bg-success', REJECTED: 'bg-secondary'};
const U = p => '<?= site_url('platform/demo_requests') ?>/' + p;
const modal = new bootstrap.Modal('#m');
let data = [];
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function toast(msg) { $('toastBody').textContent = msg; bootstrap.Toast.getOrCreateInstance($('toast'), {delay: 3000}).show(); }
async function post(path, body) {
  const res = await fetch(U(path), {method: 'POST', body: body || new FormData(), headers: {'X-CSRF-Token': CSRF}});
  let j = {}; try { j = await res.json(); } catch (e) { j = {success: false, message: 'Respon server tidak valid'}; }
  if (res.status === 401) location.href = '<?= site_url('admin/auth/login') ?>';
  return j;
}
const waLink = (r, text) => 'https://wa.me/' + r.phone + '?text=' + encodeURIComponent(text || ('Halo ' + r.name + ', kami dari Velano menindaklanjuti permintaan demo untuk ' + r.company_name + '.'));
function render() {
  const q = $('q').value.trim().toLowerCase(), fs = $('fs').value;
  const list = data.filter(r => (!fs || r.status === fs) &&
    (!q || [r.name, r.company_name, r.phone, r.email].some(v => String(v ?? '').toLowerCase().includes(q))));
  $('rows').innerHTML = list.map(r => `<tr>
    <td class="text-nowrap">${esc((r.created_at || '').slice(0, 16))}${r.wa_sent == 1 ? '' : '<div class="text-danger" title="Notifikasi WA ke admin gagal terkirim"><i class="bi bi-exclamation-triangle"></i> WA gagal</div>'}</td>
    <td><b>${esc(r.name)}</b><div class="text-gray">${esc(r.company_name)}</div></td>
    <td><a href="${waLink(r)}" target="_blank" rel="noopener" class="text-decoration-none"><i class="bi bi-whatsapp text-success me-1"></i>${esc(r.phone)}</a>${r.email ? `<div class="text-gray">${esc(r.email)}</div>` : ''}</td>
    <td>${esc(r.employee_count || '-')}</td>
    <td style="max-width:260px"><div class="text-truncate" title="${esc(r.message)}">${esc(r.message || '-')}</div>${r.admin_note ? `<div class="text-gray text-truncate" title="${esc(r.admin_note)}"><i class="bi bi-sticky me-1"></i>${esc(r.admin_note)}</div>` : ''}</td>
    <td><span class="badge ${BADGE[r.status] || 'bg-secondary'}">${esc(STATUSES[r.status] || r.status)}</span>${r.handled_by_name ? `<div class="text-gray">${esc(r.handled_by_name)}</div>` : ''}</td>
    <td class="text-end text-nowrap">
      <a class="btn btn-sm btn-light" target="_blank" rel="noopener" title="Chat WhatsApp" href="${waLink(r)}"><i class="bi bi-whatsapp"></i></a>
      <button class="btn btn-sm btn-light" title="Tindak lanjut" data-a="edit" data-id="${r.id}"><i class="bi bi-pencil"></i></button>
      <button class="btn btn-sm btn-outline-danger" title="Hapus" data-a="del" data-id="${r.id}"><i class="bi bi-trash"></i></button></td></tr>`).join('')
    || `<tr><td colspan="7" class="text-center text-gray py-4">${data.length ? 'Tidak ada yang cocok' : 'Belum ada permintaan masuk'}</td></tr>`;
}
async function load() {
  const r = await fetch(U('list_data'));
  if (r.status === 401) { location.href = '<?= site_url('admin/auth/login') ?>'; return; }
  data = (await r.json()).data;
  const n = s => data.filter(x => x.status === s).length;
  $('stats').innerHTML = [['Total masuk', data.length], ['Baru (belum dihubungi)', n('NEW')], ['Sudah dihubungi', n('CONTACTED')], ['Jadi pelanggan', n('CONVERTED')]]
    .map(([l, v]) => `<div class="col-6 col-md-3"><div class="at-card p-3"><div class="text-gray small">${l}</div><div class="fs-4 fw-bold">${v}</div></div></div>`).join('');
  render();
}
$('q').addEventListener('input', render);
$('fs').addEventListener('change', render);
$('rows').addEventListener('click', async e => {
  const b = e.target.closest('button[data-a]'); if (!b) return;
  const r = data.find(x => x.id == b.dataset.id); if (!r) return;
  if (b.dataset.a === 'edit') {
    $('id').value = r.id; $('status').value = r.status; $('note').value = r.admin_note || '';
    $('info').innerHTML = `<div><b>${esc(r.name)}</b> - ${esc(r.company_name)}</div><div class="text-gray">${esc(r.phone)}${r.email ? ' · ' + esc(r.email) : ''} · ${esc(r.employee_count || '-')} karyawan</div>${r.message ? `<div class="mt-2 p-2 bg-light rounded" style="white-space:pre-wrap">${esc(r.message)}</div>` : ''}`;
    modal.show();
  } else if (b.dataset.a === 'del') {
    if (!confirm('Hapus permintaan dari ' + r.name + ' (' + r.company_name + ')?')) return;
    const j = await post('delete/' + r.id); toast(j.message || 'Gagal'); if (j.success) load();
  }
});
$('f').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(); fd.append('status', $('status').value); fd.append('admin_note', $('note').value);
  const j = await post('update/' + $('id').value, fd);
  toast(j.message || 'Gagal'); if (j.success) { modal.hide(); load(); }
});
load();
</script>
</body></html>

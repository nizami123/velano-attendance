<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Master User Admin</h4>
        <p class="text-gray small mb-0">Kelola akun Super Admin (semua kantor) dan Admin Kantor (satu atau lebih kantor).</p>
    </div>
    <button class="btn btn-at-primary" id="btnAddAdmin"><i class="bi bi-plus-lg me-1"></i>Tambah Admin</button>
</div>

<div class="at-card">
    <div class="table-responsive">
        <table class="table table-at table-hover align-middle w-100" id="tblAdmins">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Akses Kantor</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="adminModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="adminForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminModalTitle">Tambah Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="adminFormError" class="alert alert-danger py-2 small d-none"></div>
                    <input type="hidden" name="id" id="adminId">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Kode *</label>
                            <input type="text" class="form-control" name="employee_code" id="adminCode" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Nama Lengkap *</label>
                            <input type="text" class="form-control" name="name" id="adminName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Email *</label>
                            <input type="email" class="form-control" name="email" id="adminEmail" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">No. Telepon</label>
                            <input type="text" class="form-control" name="phone" id="adminPhone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Role *</label>
                            <select class="form-select" name="role" id="adminRole" required>
                                <option value="ADMIN_KANTOR">Admin Kantor</option>
                                <option value="SUPER_ADMIN">Super Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="wrapMainOffice">
                            <label class="form-label small fw-bold">Kantor Asal *</label>
                            <select class="form-select" name="office_id" id="adminOffice"></select>
                            <div class="form-text">Super Admin otomatis mengakses <strong>semua kantor</strong>; kantor ini hanya menentukan asal akun.</div>
                        </div>
                        <div class="col-12 d-none" id="wrapOfficeAccess">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label small fw-bold mb-0">Akses Kantor * <span class="text-gray fw-normal">(boleh lebih dari satu)</span></label>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="adminOfficeAll">
                                    <label class="form-check-label small" for="adminOfficeAll">Pilih semua</label>
                                </div>
                            </div>
                            <div class="border rounded p-2" id="adminOfficeList" style="max-height:200px;overflow:auto;"></div>
                            <div class="form-text">Admin ini hanya dapat melihat dan mengelola data kantor yang dicentang.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Status</label>
                            <select class="form-select" name="status" id="adminStatus">
                                <option value="ACTIVE">Aktif</option>
                                <option value="INACTIVE">Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Password <span id="adminPasswordHint" class="text-gray fw-normal">(kosongkan jika tidak ingin mengubah)</span></label>
                            <input type="password" class="form-control" name="password" id="adminPassword" placeholder="Minimal 6 karakter">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-at-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const offices = <?= json_encode($offices) ?>;
    const officeSelect = document.getElementById('adminOffice');
    const officeList = document.getElementById('adminOfficeList');
    const roleSelect = document.getElementById('adminRole');
    const wrapMain = document.getElementById('wrapMainOffice');
    const wrapAccess = document.getElementById('wrapOfficeAccess');
    const checkAll = document.getElementById('adminOfficeAll');

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    offices.forEach(o => {
        const opt = document.createElement('option');
        opt.value = o.id; opt.textContent = o.name;
        officeSelect.appendChild(opt);

        const row = document.createElement('div');
        row.className = 'form-check';
        row.innerHTML = '<input class="form-check-input office-check" type="checkbox" name="office_ids[]" id="oc' + o.id + '" value="' + o.id + '">' +
            '<label class="form-check-label" for="oc' + o.id + '">' + esc(o.name) + ' <span class="text-gray small">(' + esc(o.code) + ')</span></label>';
        officeList.appendChild(row);
    });

    function syncRoleUi() {
        const isAdminKantor = roleSelect.value === 'ADMIN_KANTOR';
        wrapAccess.classList.toggle('d-none', !isAdminKantor);
        wrapMain.classList.toggle('d-none', isAdminKantor);   // ADMIN_KANTOR: kantor asal = kantor pertama yang dicentang
        officeSelect.required = !isAdminKantor;
    }
    function setCheckedOffices(ids) {
        const set = new Set((ids || []).map(String));
        document.querySelectorAll('.office-check').forEach(c => { c.checked = set.has(c.value); });
        checkAll.checked = offices.length > 0 && document.querySelectorAll('.office-check:checked').length === offices.length;
    }
    roleSelect.addEventListener('change', syncRoleUi);
    checkAll.addEventListener('change', function () {
        document.querySelectorAll('.office-check').forEach(c => { c.checked = checkAll.checked; });
    });
    officeList.addEventListener('change', function () {
        checkAll.checked = document.querySelectorAll('.office-check:checked').length === offices.length;
    });

    const adminModal = new bootstrap.Modal(document.getElementById('adminModal'));

    const table = $('#tblAdmins').DataTable({
        ajax: { url: ADMIN_BASE_URL + 'admin/admin_users/list_data', dataSrc: 'data' },
        columns: [
            { data: 'employee_code' },
            { data: 'name' },
            { data: 'email' },
            {
                data: null,
                render: r => {
                    if (r.role === 'SUPER_ADMIN') return '<span class="badge-at badge-at-green">Semua kantor</span>';
                    const names = r.office_names || [];
                    if (!names.length) return '-';
                    return names.map(n => '<span class="badge-at badge-at-gray me-1">' + esc(n) + '</span>').join('');
                }
            },
            { data: 'role', render: r => `<span class="badge-at ${r === 'SUPER_ADMIN' ? 'badge-at-blue' : 'badge-at-gray'}">${r}</span>` },
            { data: 'status', render: s => `<span class="badge-at ${s === 'ACTIVE' ? 'badge-at-green' : 'badge-at-gray'}">${s === 'ACTIVE' ? 'Aktif' : 'Nonaktif'}</span>` },
            {
                data: null, orderable: false, className: 'text-end',
                render: row => `
                    <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="${row.id}"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-warning btn-toggle" data-id="${row.id}"><i class="bi bi-power"></i></button>
                    <button class="btn btn-sm btn-outline-danger btn-delete" data-id="${row.id}"><i class="bi bi-trash"></i></button>
                `
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.11/i18n/id.json' }
    });

    document.getElementById('btnAddAdmin').addEventListener('click', function () {
        document.getElementById('adminForm').reset();
        document.getElementById('adminId').value = '';
        setCheckedOffices([]);
        syncRoleUi();
        document.getElementById('adminModalTitle').textContent = 'Tambah Admin';
        document.getElementById('adminPasswordHint').classList.add('d-none');
        document.getElementById('adminFormError').classList.add('d-none');
        adminModal.show();
    });

    $('#tblAdmins tbody').on('click', '.btn-edit', function () {
        const id = $(this).data('id');
        fetch(ADMIN_BASE_URL + 'admin/admin_users/detail/' + id).then(r => r.json()).then(res => {
            if (!res.success) { alert(res.message); return; }
            const a = res.data;
            document.getElementById('adminForm').reset();
            document.getElementById('adminId').value = a.id;
            document.getElementById('adminCode').value = a.employee_code;
            document.getElementById('adminName').value = a.name;
            document.getElementById('adminEmail').value = a.email || '';
            document.getElementById('adminPhone').value = a.phone || '';
            document.getElementById('adminRole').value = a.role;
            document.getElementById('adminOffice').value = a.office_id;
            setCheckedOffices(a.office_ids || []);
            syncRoleUi();
            document.getElementById('adminStatus').value = a.status;
            document.getElementById('adminModalTitle').textContent = 'Edit Admin';
            document.getElementById('adminPasswordHint').classList.remove('d-none');
            document.getElementById('adminFormError').classList.add('d-none');
            adminModal.show();
        });
    });

    $('#tblAdmins tbody').on('click', '.btn-toggle', function () {
        const id = $(this).data('id');
        if (!confirm('Ubah status admin ini?')) return;
        fetch(ADMIN_BASE_URL + 'admin/admin_users/toggle_status/' + id, { method: 'POST' })
            .then(r => r.json()).then(res => { if (res.success) table.ajax.reload(null, false); else alert(res.message); });
    });

    $('#tblAdmins tbody').on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        if (!confirm('Hapus admin ini?')) return;
        fetch(ADMIN_BASE_URL + 'admin/admin_users/delete/' + id, { method: 'POST' })
            .then(r => r.json()).then(res => {
                if (res.success) table.ajax.reload(null, false);
                else alert(res.message);
            });
    });

    document.getElementById('adminForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch(ADMIN_BASE_URL + 'admin/admin_users/save', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.success) { adminModal.hide(); table.ajax.reload(null, false); }
                else {
                    const err = document.getElementById('adminFormError');
                    err.textContent = res.message;
                    err.classList.remove('d-none');
                }
            });
    });
});
</script>

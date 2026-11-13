<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Admin_Controller.php';

/**
 * Master Kantor. Full CRUD - offices is genuinely a standalone master
 * table already in the schema (spec section 19 of the original
 * Android/API build), so this is a normal CRUD unlike Kendaraan.
 */
class Offices extends MY_Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Admin_office_model');
    }

    public function index()
    {
        $this->render('admin/offices/index', array(
            'activeMenu'   => 'master_kantor',
            'pageTitle'    => 'Master Kantor',
        ));
    }

    /** GET AJAX - list for DataTables (client-side; office count is small). */
    public function list_data()
    {
        // SUPER_ADMIN: semua kantor; ADMIN_KANTOR: hanya kantor yang menjadi aksesnya.
        $this->json(array('data' => $this->scopedOffices()));
    }

    public function detail($id)
    {
        $office = $this->Admin_office_model->getById((int) $id, $this->companyId());
        if (!$office || !$this->canAccessOffice($office['id'])) return $this->json(array('success' => false, 'message' => 'Kantor tidak ditemukan'), 404);
        $this->json(array('success' => true, 'data' => $office));
    }

    /** POST AJAX - create or update depending on whether 'id' is present. */
    public function save()
    {
        if (!$this->isSuperAdmin()) {
            return $this->json(array('success' => false, 'message' => 'Hanya SUPER_ADMIN yang dapat mengelola Master Kantor'), 403);
        }

        $id = (int) $this->input->post('id');
        $code = trim((string) $this->input->post('code'));
        $name = trim((string) $this->input->post('name'));
        $address = trim((string) $this->input->post('address'));
        $latitude = $this->input->post('latitude');
        $longitude = $this->input->post('longitude');
        $checkInRadius = (int) $this->input->post('check_in_radius');
        $checkOutRadius = (int) $this->input->post('check_out_radius');
        // Interval tracking GPS khusus kantor ini. Form mengirim dalam
        // MENIT (lebih mudah diisi admin); disimpan ke DB dalam DETIK
        // karena itu yang langsung dipakai Android tanpa konversi lagi.
        $trackingIntervalMinutes = $this->input->post('tracking_interval_minutes');
        $status = $this->input->post('status') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';
        // Zona waktu kantor: WIB / WITA / WIT. Semua karyawan & driver yang
        // terdaftar di kantor ini otomatis memakai zona ini (lihat response_helper.php).
        $timezone = strtoupper(trim((string) $this->input->post('timezone')));

        if ($code === '' || $name === '' || $latitude === '' || $longitude === '') {
            return $this->json(array('success' => false, 'message' => 'Kode, nama, dan koordinat wajib diisi'), 422);
        }
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return $this->json(array('success' => false, 'message' => 'Koordinat tidak valid'), 422);
        }
        if (!in_array($timezone, tz_codes(), true)) {
            return $this->json(array('success' => false, 'message' => 'Zona waktu harus WIB, WITA, atau WIT'), 422);
        }
        if ($checkInRadius <= 0) $checkInRadius = 50;
        if ($checkOutRadius <= 0) $checkOutRadius = 50;

        if ($trackingIntervalMinutes === '' || $trackingIntervalMinutes === null || !is_numeric($trackingIntervalMinutes) || (int) $trackingIntervalMinutes <= 0) {
            $trackingIntervalSeconds = 600; // default 10 menit, sama seperti perilaku lama
        } else {
            $trackingIntervalSeconds = (int) $trackingIntervalMinutes * 60;
        }

        if ($this->Admin_office_model->codeExists($code, $id ?: null, $this->companyId())) {
            return $this->json(array('success' => false, 'message' => 'Kode kantor sudah dipakai'), 422);
        }
        // Kantor yang diedit harus milik perusahaan ini (id dari form tidak dipercaya).
        if ($id > 0 && !$this->Admin_office_model->getById($id, $this->companyId())) {
            return $this->json(array('success' => false, 'message' => 'Kantor tidak ditemukan'), 404);
        }
        // Kuota paket
        $this->load->model('Company_model');
        if ($id <= 0 && $this->Company_model->countOffices($this->companyId()) >= (int) $this->company['max_offices']) {
            return $this->json(array('success' => false, 'message' => 'Kuota kantor paket Anda (' . (int) $this->company['max_offices'] . ') sudah penuh. Upgrade paket untuk menambah kantor.'), 422);
        }

        $data = array(
            'code' => $code,
            'name' => $name,
            'address' => $address,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'check_in_radius' => $checkInRadius,
            'check_out_radius' => $checkOutRadius,
            'tracking_interval_seconds' => $trackingIntervalSeconds,
            'timezone' => $timezone,
            'status' => $status,
        );

        if ($id > 0) {
            $this->Admin_office_model->update($id, $data);
        } else {
            $data['company_id'] = $this->companyId();
            $id = $this->Admin_office_model->insert($data);
        }

        $this->json(array('success' => true, 'message' => 'Data kantor tersimpan', 'data' => $this->Admin_office_model->getById($id, $this->companyId())));
    }

    public function toggle_status($id)
    {
        if (!$this->isSuperAdmin()) {
            return $this->json(array('success' => false, 'message' => 'Hanya SUPER_ADMIN yang dapat mengubah status kantor'), 403);
        }
        if (!$this->Admin_office_model->getById((int) $id, $this->companyId())) {
            return $this->json(array('success' => false, 'message' => 'Kantor tidak ditemukan'), 404);
        }
        $this->Admin_office_model->toggleStatus((int) $id);
        $this->json(array('success' => true));
    }

    public function delete($id)
    {
        if (!$this->isSuperAdmin()) {
            return $this->json(array('success' => false, 'message' => 'Hanya SUPER_ADMIN yang dapat menghapus kantor'), 403);
        }
        if (!$this->Admin_office_model->getById((int) $id, $this->companyId())) {
            return $this->json(array('success' => false, 'message' => 'Kantor tidak ditemukan'), 404);
        }
        $ok = $this->Admin_office_model->delete((int) $id);
        if (!$ok) {
            return $this->json(array('success' => false, 'message' => 'Kantor tidak bisa dihapus karena masih punya karyawan, shift, hari libur, atau riwayat absensi. Nonaktifkan saja.'), 409);
        }
        $this->json(array('success' => true, 'message' => 'Kantor dihapus'));
    }
}

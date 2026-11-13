<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Platform_Controller.php';

/**
 * Permintaan demo / uji coba yang masuk dari landing page (form /trial).
 * HANYA untuk Super Global Admin (lihat MY_Platform_Controller).
 */
class Demo_requests extends MY_Platform_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Demo_request_model');
    }

    public function index()
    {
        $this->load->view('platform/demo_requests', array(
            'me'       => $this->platformUser,
            'statuses' => Demo_request_model::$statuses,
            'csrf'     => $this->csrfToken(),
        ));
    }

    /** GET JSON: semua permintaan (terbaru dulu). */
    public function list_data()
    {
        $this->json(array('data' => $this->Demo_request_model->getAll()));
    }

    /** POST: ubah status + catatan tindak lanjut. */
    public function update($id)
    {
        $row = $this->Demo_request_model->getById((int) $id);
        if (!$row) return $this->json(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);

        $status = (string) $this->input->post('status');
        $note   = mb_substr(trim((string) $this->input->post('admin_note')), 0, 2000);
        if (!isset(Demo_request_model::$statuses[$status])) {
            return $this->json(array('success' => false, 'message' => 'Status tidak valid'), 422);
        }
        $this->Demo_request_model->updateFollowUp((int) $id, $status, $note, (int) $this->platformUser['id']);
        $this->json(array('success' => true, 'message' => 'Tersimpan'));
    }

    /** POST: hapus permintaan. */
    public function delete($id)
    {
        if (!$this->Demo_request_model->getById((int) $id)) {
            return $this->json(array('success' => false, 'message' => 'Data tidak ditemukan'), 404);
        }
        $this->Demo_request_model->delete((int) $id);
        $this->json(array('success' => true, 'message' => 'Dihapus'));
    }
}

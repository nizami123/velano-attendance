<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'helpers/i18n_helper.php';

/**
 * Menerjemahkan seluruh output (HTML, JSON, ekspor teks) ke English bila bahasa aktif = en.
 * Bahasa Indonesia (default) tidak diproses sama sekali.
 */
class MY_Output extends CI_Output
{
    public function _display($output = '')
    {
        if (Velano_I18n::lang() === 'en') {
            $type = '';
            foreach ($this->headers as $h) {
                if (stripos($h[0], 'content-type') === 0) $type = strtolower($h[0]);
            }
            $isJson = strpos($type, 'json') !== false;
            $isText = $type === '' || strpos($type, 'html') !== false || strpos($type, 'excel') !== false
                || strpos($type, 'text/') !== false || strpos($type, 'csv') !== false;

            if ($output === '') {
                $this->final_output = $isJson ? Velano_I18n::json((string) $this->final_output)
                    : ($isText ? Velano_I18n::html((string) $this->final_output) : $this->final_output);
            } else {
                $output = $isJson ? Velano_I18n::json((string) $output) : ($isText ? Velano_I18n::html((string) $output) : $output);
            }
        }
        parent::_display($output);
    }
}

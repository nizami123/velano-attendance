<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dua bahasa (Indonesia = bahasa sumber, English = terjemahan).
 *
 * Bahasa aktif:
 *   - Panel web  : cookie `lang` ("id" / "en"), diganti lewat  /lang/en  dan  /lang/id
 *   - API Android: header Accept-Language (en / id) atau parameter ?lang=
 *
 * Penerjemahan dilakukan pada OUTPUT (lihat core/MY_Output.php) memakai kamus
 * application/language/en_ui.php:
 *   - 'whole'  : label pendek, hanya diganti bila SELURUH teks node/atribut/string JS sama persis
 *                (supaya nama kantor / data pengguna yang kebetulan mengandung kata itu tidak ikut berubah)
 *   - 'phrase' : kalimat/frasa panjang, diganti di mana pun muncul.
 * Data yang diinput pengguna (nama, alamat, dll.) tidak pernah diterjemahkan.
 */
class Velano_I18n
{
    private static $dict = null;

    public static function lang(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $isApi = (bool) preg_match('#(^|/)api(/|$)#', $path);

        if ($isApi) {
            $q = isset($_GET['lang']) ? strtolower((string) $_GET['lang']) : '';
            if ($q === 'en' || $q === 'id') return $q;
            $h = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? strtolower(trim((string) $_SERVER['HTTP_ACCEPT_LANGUAGE'])) : '';
            return strpos($h, 'en') === 0 ? 'en' : 'id';
        }
        $c = isset($_COOKIE['lang']) ? strtolower((string) $_COOKIE['lang']) : 'id';
        return $c === 'en' ? 'en' : 'id';
    }

    private static function dict(): array
    {
        if (self::$dict === null) {
            $f = APPPATH . 'language/en_ui.php';
            self::$dict = is_file($f) ? (array) include $f : array('whole' => array(), 'phrase' => array(), 'flex' => array());
            self::$dict += array('whole' => array(), 'phrase' => array(), 'flex' => array());
        }
        return self::$dict;
    }

    /** Terjemahkan satu string pendek (mis. nilai JSON / pesan). */
    public static function str(string $s): string
    {
        if (self::lang() !== 'en' || $s === '') return $s;
        return self::translatePlain($s);
    }

    /** Hanya label utuh (untuk sel data ekspor): nilai bebas pengguna tidak disentuh. */
    public static function label(string $s): string
    {
        if (self::lang() !== 'en') return $s;
        $d = self::dict(); $core = trim($s);
        return ($core !== '' && isset($d['whole'][$core])) ? str_replace($core, $d['whole'][$core], $s) : $s;
    }

    private static function translatePlain(string $s): string
    {
        $d = self::dict();
        $core = trim($s);
        if ($core !== '' && isset($d['whole'][$core])) {
            return str_replace($core, $d['whole'][$core], $s);
        }
        $out = self::flex($s, $d);
        return $d['phrase'] ? strtr($out, $d['phrase']) : $out;
    }

    private static function flex(string $s, array $d): string
    {
        if (empty($d['flex'])) return $s;
        foreach ($d['flex'] as $regex => $en) {
            $r = @preg_replace($regex, $en, $s);
            if ($r !== null) $s = $r;   // regex rusak tidak boleh mengosongkan halaman
        }
        return $s;
    }

    /** Terjemahkan dokumen HTML (atau teks hasil ekspor). */
    public static function html(string $html): string
    {
        if (self::lang() !== 'en' || $html === '') return $html;
        $d = self::dict();
        $whole = $d['whole'];

        $swap = function ($m) use ($whole) {
            $core = trim($m[2]);
            return ($core !== '' && isset($whole[$core])) ? $m[1] . str_replace($core, $whole[$core], $m[2]) . $m[3] : $m[0];
        };
        // 1) frasa panjang di mana pun
        $html = self::flex($html, $d);               // frasa lintas-baris dulu (bisa memuat frasa pendek di dalamnya)
        if ($d['phrase']) $html = strtr($html, $d['phrase']);
        // 2) <title> : "Judul | Velano Admin"
        $html = preg_replace_callback('#(<title>)(.*?)(</title>)#s', function ($m) use ($whole) {
            $parts = preg_split('/( [|\\-] )/', $m[2], -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($parts as &$p) { $c = trim($p); if ($c !== '' && isset($whole[$c])) $p = str_replace($c, $whole[$c], $p); }
            return $m[1] . implode('', $parts) . $m[3];
        }, $html);
        // 3) node teks utuh
        $html = preg_replace_callback('/(>)([^<>]+)(<)/', $swap, $html);
        // 4) atribut
        $html = preg_replace_callback('/(\b(?:placeholder|title|alt|aria-label|data-title|label)=")([^"]*)(")/', $swap, $html);
        // 5) string literal JS (' " `) yang isinya sama persis dengan satu kunci
        $html = preg_replace_callback('/([\'"`])((?:\\\\.|(?!\1)[^\\\\\n]){1,200})(\1)/', function ($m) use ($whole) {
            $core = trim($m[2]);
            return ($core !== '' && isset($whole[$core])) ? $m[1] . str_replace($core, $whole[$core], $m[2]) . $m[3] : $m[0];
        }, $html);
        return $html;
    }

    /** Terjemahkan JSON: pesan/label kalimat, nilai label utuh, dan potongan HTML di dalam nilai. */
    public static function json(string $json): string
    {
        if (self::lang() !== 'en' || $json === '') return $json;
        $data = json_decode($json, true);
        if (!is_array($data)) return $json;
        $walk = function ($v) use (&$walk) {
            if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = $walk($x); return $v; }
            if (!is_string($v) || $v === '') return $v;
            if (strpos($v, '<') !== false && strpos($v, '>') !== false) return Velano_I18n::html($v);
            return Velano_I18n::str($v);
        };
        $out = json_encode($walk($data));
        return $out === false ? $json : $out;
    }
}

if (!function_exists('t')) {
    /** Terjemahkan teks Indonesia ke bahasa aktif (dipakai di controller/model bila perlu). */
    function t(string $idText): string { return Velano_I18n::str($idText); }
}
if (!function_exists('app_lang')) {
    function app_lang(): string { return Velano_I18n::lang(); }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Permintaan ke API (/api/...) dijawab JSON, bukan halaman HTML.
$__path = (string) parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
if (preg_match('#/api(/|$)#', $__path)) {
	if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array('success' => false, 'message' => 'Endpoint tidak ditemukan'));
	return;
}

// Helper url belum tentu termuat saat 404 terjadi (mis. controller tidak ada), jadi ambil dari config.
$__base = load_class('Config', 'core')->base_url();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>404 - Halaman Tidak Ditemukan | Velano</title>
<link rel="icon" type="image/png" href="<?php echo $__base; ?>assets/admin/img/logo.png">
<style>
:root {
	--bg: #f4f7f5; --card: #ffffff; --text: #1f2a24; --muted: #6b7a72;
	--brand: #048F38; --brand-dark: #037a30; --line: #e2e9e5;
}
@media (prefers-color-scheme: dark) {
	:root { --bg: #0f1713; --card: #17211c; --text: #e8f0ea; --muted: #9baba1; --line: #263229; }
}
* { box-sizing: border-box; }
html, body { height: 100%; margin: 0; }
body {
	display: flex; align-items: center; justify-content: center; padding: 24px;
	background: var(--bg); color: var(--text);
	font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
.card {
	width: 100%; max-width: 460px; text-align: center; padding: 40px 32px;
	background: var(--card); border: 1px solid var(--line); border-radius: 16px;
	box-shadow: 0 8px 30px rgba(0, 0, 0, .06);
}
.logo { height: 40px; width: auto; margin-bottom: 20px; }
.code { font-size: 84px; line-height: 1; font-weight: 800; letter-spacing: -2px; color: var(--brand); margin: 0; }
h1 { font-size: 20px; margin: 12px 0 8px; }
p { margin: 0 0 24px; color: var(--muted); font-size: 15px; }
.actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
.btn {
	display: inline-block; padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 14px;
	text-decoration: none; cursor: pointer; border: 1px solid var(--brand); font-family: inherit;
}
.btn-primary { background: var(--brand); color: #fff; }
.btn-primary:hover { background: var(--brand-dark); border-color: var(--brand-dark); }
.btn-ghost { background: transparent; color: var(--brand); }
.btn-ghost:hover { background: rgba(4, 143, 56, .08); }
</style>
</head>
<body>
<main class="card">
	<img class="logo" src="<?php echo $__base; ?>assets/admin/img/logo-full.png" alt="Velano">
	<p class="code" aria-hidden="true">404</p>
	<h1>Halaman tidak ditemukan</h1>
	<p>Maaf, halaman yang Anda cari tidak ada</p>
	<div class="actions">
		<a class="btn btn-primary" href="<?php echo $__base; ?>">Kembali ke Beranda</a>
		<button type="button" class="btn btn-ghost" onclick="history.length > 1 ? history.back() : (location.href='<?php echo $__base; ?>')">Halaman Sebelumnya</button>
	</div>
</main>
</body>
</html>
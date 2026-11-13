# Velano — versi PWA (iPhone)

Port dari aplikasi Android: login, absen masuk/pulang (kamera + GPS + radius), tracking lokasi,
riwayat + rute, pengajuan (dengan lampiran), profil/pengaturan/ubah password, dan pengiriman kendaraan (driver).
Tanpa build step: file statis (HTML/CSS/JS).

## Pasang di server
1. Upload SELURUH isi folder ini ke hosting **HTTPS** (wajib untuk GPS, kamera, service worker).
   Contoh: `https://GANTI-DENGAN-DOMAIN-API-ANDA/app/`
2. Atur `API_BASE` di `js/config.js` (default `https://GANTI-DENGAN-DOMAIN-API-ANDA/`).
   - Jika PWA di domain yang SAMA dengan API: tidak perlu CORS.
   - Jika domain BEDA: backend CI3 harus mengirim header CORS
     (`Access-Control-Allow-Origin`, `-Headers: Authorization, Content-Type`, `-Methods: GET, POST, OPTIONS`)
     dan menjawab request OPTIONS dengan 200.
3. Di iPhone: buka URL di **Safari** → Bagikan → **Tambah ke Layar Utama** → buka dari ikon.

## Coba tanpa server
Di layar login buka "Pengaturan lanjutan" → aktifkan **Mode demo**.
Akun: `ahmad@example.com` (karyawan) / `sopir@example.com` (driver), password `demo`.

## Batasan iOS (tidak bisa diatasi oleh PWA)
- **Tracking tidak jalan di latar belakang.** Titik hanya dicatat saat aplikasi terbuka & layar menyala
  (aplikasi meminta Wake Lock bila iOS mengizinkan). Saat dibuka kembali, titik langsung dicatat dan disinkronkan.
  Titik disimpan offline dulu (IndexedDB) lalu dikirim ke `api/tracking/sync`.
- Level baterai tidak tersedia di iOS (dikirim `null`).
- Peta memakai OpenStreetMap (bukan Google Maps), tanpa API key.
- Izin lokasi/kamera bisa ditanya ulang oleh iOS; jika ditolak: Pengaturan › Safari / Privasi.
- Jika kamera langsung gagal, tombol "Ambil Foto" membuka kamera bawaan iPhone.

## Multi-bahasa (Indonesia | English)
Pilih bahasa lewat tombol **ID | EN** di layar login atau **Profil › Pengaturan › Bahasa** (tersimpan di perangkat).
- Teks di kode ditulis dalam bahasa Indonesia dan dibungkus `t('...')` (`js/i18n.js`); terjemahan Inggris ada di
  `js/lang/en.js` (kunci = teks Indonesia, `{nama}` = placeholder). Teks yang belum ada di kamus tampil apa adanya (Indonesia).
- Nama hari/bulan, sapaan, durasi (`8j 35m` / `8h 35m`) mengikuti bahasa aktif. Data dari pengguna/admin (nama, alamat, dll.) tidak diterjemahkan.
- Aplikasi mengirim header `Accept-Language` (`id`/`en`) ke API, jadi pesan dari backend ikut berbahasa sama
  (backend menerjemahkan lewat `application/language/en_ui.php`, sama seperti panel admin). Pesan server lama yang masih Indonesia
  diterjemahkan di sisi aplikasi bila ada di kamus.
- Menambah teks baru: tulis `t('Teks Indonesia')` lalu tambahkan pasangannya di `js/lang/en.js`.
  Menambah bahasa: salin `js/lang/en.js` → `js/lang/xx.js`, daftarkan di `LANGS` & `DICTS` pada `js/i18n.js`.
- Tidak ikut berubah: `manifest.webmanifest` (nama/deskripsi saat dipasang ke layar utama masih Indonesia karena manifest statis).

## Struktur
`index.html`, `manifest.webmanifest`, `sw.js` (offline shell), `icons/`, `css/app.css`,
`js/` (api, repo, tracker, camera, map, mock, i18n, lang/en, screens/*).

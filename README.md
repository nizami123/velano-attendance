# Velano - Backend (CodeIgniter 3 + MySQL/MariaDB)

Backend REST API + panel admin web untuk aplikasi Android Velano. Melakukan
**validasi lokasi absensi di server** (bukan hanya di Android) sesuai
spesifikasi, dan menyimpan semua data ke MySQL/MariaDB.

## 1. Yang perlu Anda siapkan sendiri

Folder ini **bukan** instalasi CodeIgniter 3 yang lengkap - hanya
file `application/` (controller, model, config, helper) yang perlu
ditaruh di atas instalasi CI3 asli. Ini karena core framework CI3
(folder `system/`) sebaiknya diunduh langsung dari sumber resmi, bukan
ditulis ulang manual.

Langkah instalasi:

1. Download CodeIgniter 3 dari https://codeigniter.com/download
   (atau `composer create-project codeigniter4/appstarter` **tidak** -
   pastikan ambil **CodeIgniter 3.x**, bukan 4, karena strukturnya beda).
2. Ekstrak, Anda akan dapat folder `system/`, `application/`, `index.php`, dll.
3. **Timpa** folder `application/` bawaan dengan folder `application/` dari
   paket ini (atau salin isi `config/`, `controllers/api/`, `core/`,
   `helpers/`, `models/` satu per satu).
4. Salin juga `.htaccess` ke root project.
5. Buka `application/config/config.php` bawaan CI3, lalu terapkan
   perubahan yang tercantum di `CONFIG_CHANGES.php` (paket ini).
6. Buat database MySQL/MariaDB baru, lalu import:
   ```
   mysql -u root -p employee_tracker < database/schema.sql
   ```
   (buat database `employee_tracker` dulu jika belum ada:
   `CREATE DATABASE employee_tracker CHARACTER SET utf8mb4;`)
7. Sesuaikan kredensial di `application/config/database.php`.
8. Pastikan folder `uploads/attendance_photos/` bisa ditulis web server
   (`chmod 755` atau `775` di Linux).
9. Jalankan dengan Apache/Nginx (XAMPP/Laragon paling gampang untuk lokal),
   arahkan ke folder ini sebagai document root / virtual host.

## 1b. Peran & hak akses

| Peran                            | Tabel                                          | Login                                       | Yang boleh dilihat / dikelola                                                                                                                                         |
| -------------------------------- | ---------------------------------------------- | ------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Super Global Admin**           | `global_admins` (terpisah, 1 baris awal)       | `/admin/auth/login` → `/platform/companies` | **Satu-satunya yang CRUD Master Perusahaan** (nama, kode, kontak, paket, batas karyawan, masa aktif, status, Super Admin-nya). Tidak melihat data absensi perusahaan mana pun. |
| **Super Admin** (per perusahaan) | `employees` (`SUPER_ADMIN`)                    | `/admin/auth/login` → `/admin/dashboard`    | Semua kantor, admin, karyawan, shift, libur, laporan **milik perusahaannya saja**. Tidak bisa mengubah data perusahaan (paket/batas karyawan/masa aktif).                      |
| **Admin Kantor**                 | `employees` (`ADMIN_KANTOR`) + `admin_offices` | sama                                        | Hanya kantor yang diberikan Super Admin (boleh >1). Tidak bisa Master Kantor / User Admin.                                                                            |
| Karyawan / Driver                | `employees`                                    | Aplikasi Android / PWA                      | Hanya datanya sendiri.                                                                                                                                                |

**Akun awal Super Global Admin** (dibuat oleh `schema.sql`):

| Email                   | Password          |
| ----------------------- | ----------------- |
| `superglobal@velano.id` | `SuperGlobal@123` |

**Segera ganti** lewat tombol **Akun Saya** di panel. Lupa password? Jalankan di server:
`php index.php tools reset_global_admin_password PasswordBaruMin8`

**Alur berlangganan:** Super Global Admin → _Perusahaan Baru_ (isi nama perusahaan, paket, batas karyawan, masa aktif, dan akun Super Admin perusahaan)
→ kirim email/password itu ke pelanggan → Super Admin perusahaan membuat kantor, Admin Kantor, dan karyawan sendiri.
Aksi lain: edit, perpanjang +30 hari, tangguhkan/aktifkan, reset password Super Admin, **hapus permanen** (wajib mengetik kode perusahaan).
Perusahaan yang ditangguhkan / kedaluwarsa otomatis tidak bisa login (web & Android) dan token lamanya ditolak.

**Isolasi data:** `company_id` selalu diambil dari sesi/token di server, tidak pernah dari input form. Semua aksi lihat/ubah/hapus
memeriksa kepemilikan perusahaan, lalu cakupan kantor untuk Admin Kantor. Panel Super Global Admin memakai sesi terpisah
(`global_admin_id`) dan token CSRF pada setiap POST. Email unik secara global (dipakai login).
Pendaftaran mandiri `/daftar` **dinonaktifkan** (`$config['allow_self_register'] = FALSE` di `config.php`).

## 1c. Dua bahasa (Indonesia / English)

- **Panel web** (Super Global Admin, Super Admin, Admin Kantor): tombol **ID | EN** di pojok atas (dan di halaman login). Pilihan disimpan di cookie `lang`
  (`/lang/en`, `/lang/id`). Yang ikut berganti: semua halaman, pesan JSON, ekspor Excel, dan halaman cetak/PDF.
- **Aplikasi Android**: pilih di **Pengaturan > Bahasa** atau tombol **ID | EN** di layar login. Default mengikuti bahasa perangkat (English bila perangkat berbahasa Inggris,
  selain itu Indonesia). Aplikasi mengirim header `Accept-Language` sehingga **pesan dari API** juga berbahasa sama.
  Teks aplikasi ada di `res/values/strings.xml` (Indonesia) dan `res/values-en/strings.xml` (English); teks di kode memakai `L.s(R.string.xxx)`.
- **Cara kerja web**: bahasa sumber = Indonesia. Saat bahasa = English, output diterjemahkan oleh `application/core/MY_Output.php` memakai kamus
  `application/language/en_ui.php` (`whole` = label utuh, `phrase` = kalimat). Data yang diinput pengguna (nama, alamat, nama kantor, dll.) **tidak** diterjemahkan.
- **Menambah teks baru**: tulis teks Indonesia di view/controller seperti biasa, lalu tambahkan pasangannya di `application/language/en_ui.php`
  (hindari tanda kutip lurus `'` `"` dan `&` pada sisi English; pakai `’ “ ”` dan kata "and"). Untuk kalimat yang disusun dari variabel PHP, bungkus bagian tetapnya dengan `t('teks')`.
- Belum diterjemahkan: **PWA** (`/app`) dan **landing page** - keduanya tetap berbahasa Indonesia.

## 2. Database & data demo

Hanya dua file di `database/`:

1. **`schema.sql`** - semua tabel + 1 akun Super Global Admin. Import ke database kosong.
2. **`data_demo.sql`** _(opsional)_ - dua perusahaan contoh (aman diimpor ulang; hanya kode `DEMO` & `DEMO2` yang dibuat ulang):
   - **PT Demo Maju Jaya** (`DEMO`): 3 kantor (Surabaya, Sidoarjo, Makassar/WITA), shift & jadwal, 14 karyawan + 1 driver, absensi 2 minggu,
     tracking hari ini, pengajuan, hari libur, 2 pengiriman.
   - **CV Contoh Sejahtera** (`DEMO2`): 1 kantor, 2 karyawan - untuk membuktikan data antar perusahaan tidak saling terlihat.

Password semua akun demo: **`demo123`**

| Email                                                           | Peran                                      |
| --------------------------------------------------------------- | ------------------------------------------ |
| `admin@example.com`                                             | Super Admin PT Demo                        |
| `citra@example.com`                                             | Admin Kantor PT Demo (Sidoarjo + Surabaya) |
| `bos@contoh.id`                                                 | Super Admin CV Contoh                      |
| `ahmad@example.com`, `budi@example.com`, `deni@example.com` ... | Karyawan PT Demo                           |
| `sopir@example.com`                                             | Driver PT Demo                             |
| `rina@contoh.id`, `tono@contoh.id`                              | Karyawan CV Contoh                         |

Mode demo PWA (tanpa server) memakai akun terpisah: `ahmad@example.com` / `sopir@example.com`, password `demo`.

## 3. Menguji API dengan curl

```bash
# Login
curl -X POST http://192.168.1.100/employee-tracker-backend/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ahmad@example.com","password":"demo123"}'

# -> simpan "token" dari respons, lalu:

# Lihat daftar kantor
curl http://192.168.1.100/employee-tracker-backend/api/offices \
  -H "Authorization: Bearer TOKEN_DISINI"

# Absen masuk (lokasi TEPAT di titik Kantor Surabaya -> harus sukses)
curl -X POST http://192.168.1.100/employee-tracker-backend/api/attendance/check-in \
  -H "Authorization: Bearer TOKEN_DISINI" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 3,
    "latitude": -7.2575,
    "longitude": 112.7521,
    "accuracy": 8.5,
    "timestamp": "2026-08-10 08:01:20",
    "photo_base64": ""
  }'

# Absen masuk dari lokasi jauh (>50m) -> harus DITOLAK (success:false)
curl -X POST http://192.168.1.100/employee-tracker-backend/api/attendance/check-in \
  -H "Authorization: Bearer TOKEN_DISINI" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 3,
    "latitude": -7.30,
    "longitude": 112.80,
    "accuracy": 8.5,
    "timestamp": "2026-08-10 08:01:20",
    "photo_base64": ""
  }'
```

## 4. Daftar endpoint (harus sama persis dengan `ApiService.kt` di Android)

| Method | Path                            | Auth | Keterangan                                      |
| ------ | ------------------------------- | :--: | ----------------------------------------------- |
| POST   | `/api/auth/login`               |  -   | `{email, password}` -> `{token, employee}`      |
| GET    | `/api/offices`                  |  ✔   | Daftar kantor aktif                             |
| GET    | `/api/employees/me`             |  ✔   | Profil karyawan yang login                      |
| POST   | `/api/attendance/check-in`      |  ✔   | Validasi lokasi di server, simpan absensi       |
| POST   | `/api/attendance/check-out`     |  ✔   | Idem, pakai `check_out_radius`                  |
| GET    | `/api/attendance/today`         |  ✔   | Status absen hari ini                           |
| GET    | `/api/attendance/history`       |  ✔   | Riwayat absensi                                 |
| POST   | `/api/tracking/sync`            |  ✔   | Kirim batch titik lokasi (setiap 10 menit)      |
| GET    | `/api/tracking?date=YYYY-MM-DD` |  ✔   | Titik tracking milik sendiri pada satu tanggal  |
| GET    | `/api/shifts/my`                |  ✔   | Shift hari ini + 7 hari ke depan                |
| POST   | `/api/auth/change-password`     |  ✔   | Ubah password sendiri                           |
| POST   | `/api/requests`                 |  ✔   | Kirim pengajuan (LATE/CHECK_IN/CHECK_OUT/LEAVE) |
| GET    | `/api/requests`                 |  ✔   | Riwayat pengajuan milik sendiri                 |

Semua endpoint ber-`✔` memerlukan header:

```
Authorization: Bearer <token dari login>
```

## 5. Keamanan yang sudah diterapkan (sesuai spec bagian 25)

- **office_id tidak pernah dipercaya dari client.** Setiap endpoint
  mengambil `office_id` dari data karyawan yang sudah terautentikasi
  di server (`employees.office_id`), bukan dari body/query request.
- **Kepemilikan data selalu dicek ulang.** Check-out dan sinkronisasi
  tracking memverifikasi `attendance.employee_id` cocok dengan token
  yang sedang login sebelum menulis/membaca apa pun.
- **Jarak & radius selalu dihitung ulang di server** (`distance_helper.php`,
  fungsi Haversine) - hasil perhitungan di Android hanya untuk
  tampilan UI, keputusan sebenarnya selalu dari sini.
- Password disimpan sebagai hash bcrypt (`password_hash`/`password_verify`),
  bukan plaintext.
- Token bearer disimpan di tabel `auth_tokens` dengan masa berlaku
  (default 30 hari), bukan token statis/API-key.

## 6. Menyambungkan Android app ke backend ini

Di project Android:

1. Buka `app/src/main/java/com/employeetracker/app/util/Constants.kt`,
   ubah `USE_MOCK_API` menjadi `false`.
2. Buka `app/build.gradle.kts`, ubah `BASE_URL` ke alamat backend Anda,
   contoh: `"http://192.168.1.100/employee-tracker-backend/"`
   (harus diakhiri `/`, dan pakai IP LAN, bukan `localhost`, kalau
   diuji dari HP fisik/emulator).
3. Sync Gradle & jalankan ulang. Tidak ada kode UI/ViewModel yang perlu
   diubah karena `MockApiService` dan Retrofit sama-sama memenuhi
   interface `ApiService` yang sama.

## 7. Yang belum termasuk (menyusul)

- Web admin (Master Kantor, Master Karyawan, approve/reject pengajuan,
  dashboard tracking) - akan dibuat setelah alur karyawan ini stabil.
- Refresh-token / logout endpoint (saat ini token cukup lama masa
  berlakunya untuk kebutuhan mobile).
- Rate limiting pada endpoint login.

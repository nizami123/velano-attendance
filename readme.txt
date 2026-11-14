## 1b. Peran & hak akses

| Peran                            | Tabel                                          | Login                                       | Yang boleh dilihat / dikelola                                                                                                                                                  |
| -------------------------------- | ---------------------------------------------- | ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Super Global Admin**           | `global_admins` (terpisah, 1 baris awal)       | `/admin/auth/login` → `/platform/companies` | **Satu-satunya yang CRUD Master Perusahaan** (nama, kode, kontak, paket, batas karyawan, masa aktif, status, Super Admin-nya). Tidak melihat data absensi perusahaan mana pun. |
| **Super Admin** (per perusahaan) | `employees` (`SUPER_ADMIN`)                    | `/admin/auth/login` → `/admin/dashboard`    | Semua kantor, admin, karyawan, shift, libur, laporan **milik perusahaannya saja**. Tidak bisa mengubah data perusahaan (paket/batas karyawan/masa aktif).                      |
| **Admin Kantor**                 | `employees` (`ADMIN_KANTOR`) + `admin_offices` | sama                                        | Hanya kantor yang diberikan Super Admin (boleh >1). Tidak bisa Master Kantor / User Admin.                                                                                     |
| Karyawan / Driver                | `employees`                                    | Aplikasi Android / PWA                      | Hanya datanya sendiri.                                                                                                                                                         |

**Akun awal Super Global Admin** (dibuat oleh `schema.sql`):

| Email                   | Password          |
| ----------------------- | ----------------- |
| `superglobal@velano.id` | `SuperGlobal@123` |


| Email                                                           | Peran                                      |
| --------------------------------------------------------------- | ------------------------------------------ |
| `admin@example.com`                                             | Super Admin PT Demo                        |
| `citra@example.com`                                             | Admin Kantor PT Demo (Sidoarjo + Surabaya) |
| `bos@contoh.id`                                                 | Super Admin CV Contoh                      |
| `ahmad@example.com`, `budi@example.com`, `deni@example.com` ... | Karyawan PT Demo                           |
| `sopir@example.com`                                             | Driver PT Demo                             |
| `rina@contoh.id`, `tono@contoh.id`                              | Karyawan CV Contoh                         |

Mode demo PWA (tanpa server) memakai akun terpisah: `ahmad@example.com` / `sopir@example.com`, password `demo`.

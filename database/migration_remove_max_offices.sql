-- ============================================================
-- MIGRASI: hapus batas jumlah kantor per perusahaan.
-- Kantor tidak lagi dibatasi. Batas karyawan (max_employees) TETAP ada
-- dan diinput per perusahaan oleh Super Global Admin.
-- Jalankan sekali pada database yang sudah berjalan.
-- ============================================================
ALTER TABLE `companies`
  DROP COLUMN `max_offices`;

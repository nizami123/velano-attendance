-- =====================================================================
-- VELANO — DATA DEMO
-- Membuat DUA perusahaan contoh supaya pembatasan data per peran/perusahaan bisa dicoba:
--   1) PT Demo Maju Jaya   (kode DEMO)  : 3 kantor, 14 karyawan + 1 driver, absensi, shift, pengajuan, pengiriman
--   2) CV Contoh Sejahtera (kode DEMO2) : 1 kantor, 2 karyawan  -> data TIDAK boleh terlihat oleh admin PT Demo
--
-- Cara pakai: import SETELAH schema.sql  (mysql -u USER -p DB < data_demo.sql, atau phpMyAdmin > Import).
-- Aman dijalankan ULANG: hanya perusahaan berkode DEMO / DEMO2 yang dihapus & dibuat ulang (tanggal segar,
-- relatif terhadap hari ini). Perusahaan lain & akun Super Global Admin tidak disentuh.
--
-- Password semua akun demo:  demo123
--   Super Global Admin (dari schema.sql) superglobal@velano.id / SuperGlobal@123  -> /admin/auth/login -> Master Perusahaan
--   admin@example.com   Super Admin PT Demo (semua kantor PT Demo)   -> /admin/auth/login
--   citra@example.com   Admin Kantor (Sidoarjo + Surabaya)           -> /admin/auth/login
--   bos@contoh.id       Super Admin CV Contoh (perusahaan lain)      -> /admin/auth/login
--   ahmad@example.com   Karyawan (Surabaya)    sopir@example.com  Driver (Surabaya)   -> aplikasi Android / PWA
--   (+ karyawan lain: budi, deni, eka, fajar, gita, hendra, indah, joko, kartika, lukman, maya @example.com;
--      rina@contoh.id & tono@contoh.id di CV Contoh)
-- Catatan: email harus unik global. Bila salah satu email di atas sudah dipakai data nyata, ganti dulu di file ini.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- ---------- 1. Hapus data demo lama (kalau ada): hanya perusahaan berkode DEMO / DEMO2 ----------
DROP TEMPORARY TABLE IF EXISTS tmp_demo_co;
CREATE TEMPORARY TABLE tmp_demo_co AS SELECT id FROM companies WHERE code IN ('DEMO', 'DEMO2');

DELETE FROM vehicle_delivery_tracking WHERE driver_id IN (SELECT id FROM employees WHERE company_id IN (SELECT id FROM tmp_demo_co));
DELETE FROM vehicle_deliveries        WHERE driver_id IN (SELECT id FROM employees WHERE company_id IN (SELECT id FROM tmp_demo_co));
DELETE FROM attendance_request_attachments WHERE request_id IN (SELECT id FROM attendance_requests WHERE employee_id IN (SELECT id FROM employees WHERE company_id IN (SELECT id FROM tmp_demo_co)));
DELETE FROM attendance_requests       WHERE employee_id IN (SELECT id FROM employees WHERE company_id IN (SELECT id FROM tmp_demo_co));
DELETE FROM attendance_tracking       WHERE employee_id IN (SELECT id FROM employees WHERE company_id IN (SELECT id FROM tmp_demo_co));
DELETE FROM attendances               WHERE employee_id IN (SELECT id FROM employees WHERE company_id IN (SELECT id FROM tmp_demo_co));
DELETE FROM employee_shift_schedules  WHERE employee_id IN (SELECT id FROM employees WHERE company_id IN (SELECT id FROM tmp_demo_co));
DELETE FROM admin_offices             WHERE admin_id IN (SELECT id FROM employees WHERE company_id IN (SELECT id FROM tmp_demo_co));
DELETE FROM auth_tokens               WHERE employee_id IN (SELECT id FROM employees WHERE company_id IN (SELECT id FROM tmp_demo_co));
DELETE FROM devices                   WHERE employee_id IN (SELECT id FROM employees WHERE company_id IN (SELECT id FROM tmp_demo_co));
DELETE FROM shifts                    WHERE office_id IN (SELECT id FROM offices WHERE company_id IN (SELECT id FROM tmp_demo_co));
DELETE FROM holidays                  WHERE company_id IN (SELECT id FROM tmp_demo_co);
DELETE FROM employees                 WHERE company_id IN (SELECT id FROM tmp_demo_co);
DELETE FROM offices                   WHERE company_id IN (SELECT id FROM tmp_demo_co);
DELETE FROM companies                 WHERE id IN (SELECT id FROM tmp_demo_co);
DROP TEMPORARY TABLE IF EXISTS tmp_demo_co;

-- ---------- 2. Perusahaan (paket TRIAL, aktif 30 hari dari sekarang) ----------
INSERT INTO companies (code, name, contact_name, contact_email, contact_phone, plan, status, max_employees, max_offices, valid_until, created_at)
VALUES ('DEMO', 'PT Demo Maju Jaya', 'Admin Demo', 'admin@example.com', '081234567890', 'TRIAL', 'ACTIVE', 25, 3, NOW() + INTERVAL 30 DAY, NOW());
SET @c := LAST_INSERT_ID();

-- ---------- 3. Kantor (Surabaya & Sidoarjo = WIB, Makassar = WITA) ----------
INSERT INTO offices (company_id, code, name, address, latitude, longitude, check_in_radius, check_out_radius, tracking_interval_seconds, status, created_at, timezone) VALUES
(@c, 'SBY', 'Kantor Pusat Surabaya', 'Jl. Tunjungan No. 1, Surabaya', -7.2575000, 112.7521000, 50, 50, 600, 'ACTIVE', NOW(), 'WIB');
SET @o_sby := LAST_INSERT_ID();
INSERT INTO offices (company_id, code, name, address, latitude, longitude, check_in_radius, check_out_radius, tracking_interval_seconds, status, created_at, timezone) VALUES
(@c, 'SDA', 'Cabang Sidoarjo', 'Jl. Pahlawan No. 10, Sidoarjo', -7.4478000, 112.7183000, 75, 75, 600, 'ACTIVE', NOW(), 'WIB');
SET @o_sda := LAST_INSERT_ID();
INSERT INTO offices (company_id, code, name, address, latitude, longitude, check_in_radius, check_out_radius, tracking_interval_seconds, status, created_at, timezone) VALUES
(@c, 'MKS', 'Cabang Makassar', 'Jl. Sultan Hasanuddin No. 5, Makassar', -5.1477000, 119.4327000, 100, 100, 600, 'ACTIVE', NOW(), 'WITA');
SET @o_mks := LAST_INSERT_ID();

-- ---------- 4. Akun (password semua: demo123) ----------
SET @pw := '$2y$10$9DU1hoz9wZjy7oRFOt4FpepGRpY4YclodL6WkRG1u31JAP.tCeacS';

INSERT INTO employees (company_id, employee_code, name, email, phone, password, office_id, position, role, status, created_at) VALUES
(@c, 'ADM001', 'Admin Demo',      'admin@example.com',  '081234567890', @pw, @o_sby, 'Super Administrator', 'SUPER_ADMIN',  'ACTIVE', NOW()),
(@c, 'ADM002', 'Citra Dewi',      'citra@example.com',  '081234567891', @pw, @o_sda, 'Admin Kantor',        'ADMIN_KANTOR', 'ACTIVE', NOW()),
(@c, 'EMP001', 'Ahmad Kurniawan', 'ahmad@example.com',  '081234567892', @pw, @o_sby, 'Staff Operasional',   'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'EMP002', 'Budi Santoso',    'budi@example.com',   '081234567893', @pw, @o_sby, 'Staff Gudang',        'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'EMP003', 'Deni Pratama',    'deni@example.com',   '081234567894', @pw, @o_sda, 'Staff Penjualan',     'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'EMP004', 'Eka Putri',       'eka@example.com',    '081234567895', @pw, @o_sda, 'Staff Administrasi',  'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'EMP005', 'Fajar Nugroho',   'fajar@example.com',  '081234567896', @pw, @o_sda, 'Teknisi',             'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'EMP006', 'Gita Lestari',    'gita@example.com',   '081234567897', @pw, @o_sby, 'Customer Service',    'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'EMP007', 'Hendra Wijaya',   'hendra@example.com', '081234567898', @pw, @o_sby, 'Supervisor',          'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'EMP008', 'Indah Permata',   'indah@example.com',  '081234567899', @pw, @o_mks, 'Staff Penjualan',     'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'EMP009', 'Joko Susilo',     'joko@example.com',   '081234567800', @pw, @o_mks, 'Staff Gudang',        'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'EMP010', 'Kartika Sari',    'kartika@example.com','081234567801', @pw, @o_mks, 'Staff Administrasi',  'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'EMP011', 'Lukman Hakim',    'lukman@example.com', '081234567802', @pw, @o_sby, 'Staff Keuangan',      'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'EMP012', 'Maya Anggraini',  'maya@example.com',   '081234567803', @pw, @o_sda, 'Staff Marketing',     'EMPLOYEE',     'ACTIVE', NOW()),
(@c, 'DRV001', 'Rudi Hartono',    'sopir@example.com',  '081234567804', @pw, @o_sby, 'Driver Pengiriman',   'DRIVER',       'ACTIVE', NOW());

SET @admin  := (SELECT id FROM employees WHERE company_id = @c AND employee_code = 'ADM001');
SET @citra  := (SELECT id FROM employees WHERE company_id = @c AND employee_code = 'ADM002');
SET @ahmad  := (SELECT id FROM employees WHERE company_id = @c AND employee_code = 'EMP001');
SET @budi   := (SELECT id FROM employees WHERE company_id = @c AND employee_code = 'EMP002');
SET @deni   := (SELECT id FROM employees WHERE company_id = @c AND employee_code = 'EMP003');
SET @eka    := (SELECT id FROM employees WHERE company_id = @c AND employee_code = 'EMP004');
SET @fajar  := (SELECT id FROM employees WHERE company_id = @c AND employee_code = 'EMP005');
SET @driver := (SELECT id FROM employees WHERE company_id = @c AND employee_code = 'DRV001');

-- Admin Kantor Citra memegang kantor Sidoarjo (dan Surabaya, contoh akses multi-kantor)
INSERT INTO admin_offices (admin_id, office_id, created_at) VALUES (@citra, @o_sda, NOW()), (@citra, @o_sby, NOW());

-- ---------- 5. Master shift (per kantor) ----------
INSERT INTO shifts (office_id, code, name, start_time, end_time, status, created_at)
SELECT o.id, s.code, s.name, s.start_time, s.end_time, 'ACTIVE', NOW()
FROM offices o
CROSS JOIN (
  SELECT 'N' AS code, 'Normal' AS name, '08:00:00' AS start_time, '16:30:00' AS end_time
  UNION ALL SELECT 'P', 'Pagi',  '07:00:00', '15:00:00'
  UNION ALL SELECT 'S', 'Siang', '15:00:00', '23:00:00'
  UNION ALL SELECT 'M', 'Malam', '23:00:00', '07:00:00'
) s
WHERE o.company_id = @c;

-- ---------- 6. Jadwal shift: 7 hari ke belakang s.d. 14 hari ke depan ----------
-- Senin-Jumat: shift Normal (karyawan dengan id kelipatan 3 memakai shift Pagi mulai hari ini). Sabtu & Minggu: Libur.
INSERT INTO employee_shift_schedules (employee_id, schedule_date, shift_id, created_by, created_at)
SELECT e.id,
       d.dt,
       CASE WHEN WEEKDAY(d.dt) >= 5 THEN NULL
            ELSE (SELECT sh.id FROM shifts sh
                  WHERE sh.office_id = e.office_id
                    AND sh.code = IF(d.dt >= CURDATE() AND MOD(e.id, 3) = 0, 'P', 'N')) END,
       @admin, NOW()
FROM employees e
CROSS JOIN (
  SELECT CURDATE() - INTERVAL 7 DAY + INTERVAL (a.i + b.i * 10) DAY AS dt
  FROM (SELECT 0 i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
  CROSS JOIN (SELECT 0 i UNION ALL SELECT 1 UNION ALL SELECT 2) b
  WHERE a.i + b.i * 10 <= 21
) d
WHERE e.company_id = @c AND e.role IN ('EMPLOYEE', 'DRIVER');

-- ---------- 7. Absensi 14 hari terakhir (hari kerja saja; sebagian terlambat / tidak hadir) ----------
INSERT INTO attendances
  (employee_id, office_id, attendance_date, check_in_time, check_in_latitude, check_in_longitude, check_in_accuracy, check_in_distance,
   check_out_time, check_out_latitude, check_out_longitude, check_out_accuracy, check_out_distance, status, created_at, updated_at)
SELECT e.id, o.id, x.dt,
       TIMESTAMP(x.dt, SEC_TO_TIME(7 * 3600 + 45 * 60 + MOD(e.id * 37 + x.n * 53, 40) * 60)),
       o.latitude  + (CAST(MOD(e.id + x.n, 7) AS SIGNED) - 3) * 0.00003,
       o.longitude + (CAST(MOD(e.id * 3 + x.n, 7) AS SIGNED) - 3) * 0.00003,
       6 + MOD(e.id + x.n, 9),
       4 + MOD(e.id * 5 + x.n * 3, 30),
       IF(x.n = 0, NULL, TIMESTAMP(x.dt, SEC_TO_TIME(16 * 3600 + 30 * 60 + MOD(e.id * 11 + x.n * 7, 30) * 60))),
       IF(x.n = 0, NULL, o.latitude  + (CAST(MOD(e.id + x.n, 5) AS SIGNED) - 2) * 0.00003),
       IF(x.n = 0, NULL, o.longitude + (CAST(MOD(e.id + x.n, 5) AS SIGNED) - 2) * 0.00003),
       IF(x.n = 0, NULL, 7 + MOD(e.id + x.n, 8)),
       IF(x.n = 0, NULL, 5 + MOD(e.id * 2 + x.n, 25)),
       'PRESENT',
       TIMESTAMP(x.dt, '07:45:00'),
       IF(x.n = 0, NULL, TIMESTAMP(x.dt, '16:45:00'))
FROM employees e
JOIN offices o ON o.id = e.office_id
JOIN (
  SELECT CURDATE() - INTERVAL n DAY AS dt, n
  FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
        UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13) nums
) x ON 1 = 1
WHERE e.company_id = @c
  AND e.role IN ('EMPLOYEE', 'DRIVER')
  AND WEEKDAY(x.dt) < 5
  AND MOD(e.id * 7 + x.n * 3, 11) <> 0                   -- sesekali tidak hadir
  AND (x.n > 0 OR CURTIME() > '08:30:00');               -- hari ini: hanya bila sudah lewat 08:30

-- ---------- 8. Titik tracking hari ini (beberapa karyawan, tiap 10 menit sejak 08:00) ----------
INSERT INTO attendance_tracking (employee_id, office_id, latitude, longitude, accuracy, speed, bearing, battery_level, recorded_at, server_received_at, created_at)
SELECT e.id, o.id,
       o.latitude  + (t.n - 2) * 0.00004 + MOD(e.id, 3) * 0.00002,
       o.longitude + (t.n - 2) * 0.00005,
       8 + MOD(t.n, 5), 0, NULL, 90 - t.n * 3,
       TIMESTAMP(CURDATE(), SEC_TO_TIME(8 * 3600 + t.n * 600)),
       TIMESTAMP(CURDATE(), SEC_TO_TIME(8 * 3600 + t.n * 600 + 5)),
       TIMESTAMP(CURDATE(), SEC_TO_TIME(8 * 3600 + t.n * 600 + 5))
FROM employees e
JOIN offices o ON o.id = e.office_id
JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7) t ON 1 = 1
WHERE e.company_id = @c AND e.role = 'EMPLOYEE' AND MOD(e.id, 3) = 0
  AND TIMESTAMP(CURDATE(), SEC_TO_TIME(8 * 3600 + t.n * 600)) <= NOW();

-- ---------- 9. Pengajuan (berbagai jenis & status) ----------
INSERT INTO attendance_requests (employee_id, office_id, type, date, start_date, end_date, time, end_time, reason, status, approved_by, approved_at, rejection_reason, created_at) VALUES
(@ahmad, @o_sby, 'LEAVE',          NULL,                     CURDATE() + INTERVAL 3 DAY, CURDATE() + INTERVAL 4 DAY, NULL, NULL, 'Acara keluarga di luar kota',          'PENDING',  NULL,   NULL,                          NULL,                      NOW() - INTERVAL 1 DAY),
(@budi,  @o_sby, 'LATE',           CURDATE() - INTERVAL 2 DAY, NULL, NULL, '08:30:00', NULL,       'Ban motor bocor di jalan',                'APPROVED', @admin, NOW() - INTERVAL 2 DAY, NULL,                      NOW() - INTERVAL 2 DAY),
(@deni,  @o_sda, 'OVERTIME',       CURDATE() - INTERVAL 3 DAY, NULL, NULL, '17:00:00', '20:00:00', 'Menyelesaikan laporan akhir bulan',       'APPROVED', @citra, NOW() - INTERVAL 3 DAY, NULL,                      NOW() - INTERVAL 3 DAY),
(@eka,   @o_sda, 'CHECK_OUT',      CURDATE() - INTERVAL 4 DAY, NULL, NULL, '16:30:00', NULL,       'Lupa absen pulang',                       'PENDING',  NULL,   NULL,                          NULL,                      NOW() - INTERVAL 4 DAY),
(@fajar, @o_sda, 'OUTSIDE_OFFICE', CURDATE() - INTERVAL 1 DAY, NULL, NULL, '09:00:00', NULL,       'Instalasi di lokasi pelanggan',           'APPROVED', NULL,   NOW() - INTERVAL 1 DAY, NULL,                      NOW() - INTERVAL 1 DAY),
(@ahmad, @o_sby, 'LEAVE',          NULL,                     CURDATE() - INTERVAL 9 DAY, CURDATE() - INTERVAL 8 DAY, NULL, NULL, 'Keperluan pribadi',                      'REJECTED', @admin, NOW() - INTERVAL 10 DAY, 'Bertepatan dengan jadwal audit', NOW() - INTERVAL 11 DAY);

-- ---------- 10. Hari libur (berlaku semua kantor perusahaan) ----------
INSERT INTO holidays (company_id, holiday_date, name, office_id, created_at) VALUES
(@c, '2026-12-25', 'Hari Raya Natal',      NULL, NOW()),
(@c, '2026-12-31', 'Cuti Bersama Akhir Tahun', NULL, NOW()),
(@c, '2027-01-01', 'Tahun Baru Masehi',    NULL, NOW());

-- ---------- 11. Pengiriman kendaraan (driver Rudi): 1 selesai + 1 sedang berjalan ----------
INSERT INTO vehicle_deliveries
  (driver_id, engine_number, chassis_number, brand, vehicle_type, color, destination_office_id, destination_name, destination_address,
   destination_latitude, destination_longitude, destination_radius, notes,
   pickup_time, pickup_latitude, pickup_longitude,
   arrival_time, arrival_latitude, arrival_longitude, arrival_distance, arrival_notes, status, created_at, updated_at)
VALUES
(@driver, 'MSN-2026-0001', 'MRK-2026-0001', 'Honda', 'Vario 160', 'Hitam', @o_sda, 'Cabang Sidoarjo', 'Jl. Pahlawan No. 10, Sidoarjo',
 -7.4478000, 112.7183000, 75, 'Pengiriman unit pameran',
 TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '09:00:00'), -7.2575000, 112.7521000,
 TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '10:35:00'), -7.4479000, 112.7184000, 18.50, 'Unit diterima dalam kondisi baik', 'COMPLETED',
 TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '09:00:00'), TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '10:35:00'));

INSERT INTO vehicle_deliveries
  (driver_id, engine_number, chassis_number, brand, vehicle_type, color, destination_office_id, destination_name, destination_address,
   destination_latitude, destination_longitude, destination_radius, notes, pickup_time, pickup_latitude, pickup_longitude, status, created_at)
VALUES
(@driver, 'MSN-2026-0002', 'MRK-2026-0002', 'Yamaha', 'NMAX 155', 'Abu-abu', @o_sda, 'Cabang Sidoarjo', 'Jl. Pahlawan No. 10, Sidoarjo',
 -7.4478000, 112.7183000, 75, 'Stok cabang', NOW() - INTERVAL 50 MINUTE, -7.2575000, 112.7521000, 'IN_PROGRESS', NOW() - INTERVAL 50 MINUTE);
SET @dlv := LAST_INSERT_ID();

-- Titik rute: 5 titik dari Surabaya menuju Sidoarjo, tiap 10 menit sampai sekarang
INSERT INTO vehicle_delivery_tracking (delivery_id, driver_id, latitude, longitude, accuracy, speed, bearing, battery_level, recorded_at, server_received_at, created_at)
SELECT @dlv, @driver,
       -7.2575000 + (-0.1903000) * (t.n / 5), 112.7521000 + (-0.0338000) * (t.n / 5),
       9, 8.5, 190, 80 - t.n * 2,
       NOW() - INTERVAL (50 - t.n * 10) MINUTE, NOW() - INTERVAL (50 - t.n * 10) MINUTE, NOW() - INTERVAL (50 - t.n * 10) MINUTE
FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4) t;

-- ---------- 12. Perusahaan kedua (DEMO2) — untuk menguji isolasi data antar perusahaan ----------
INSERT INTO companies (code, name, contact_name, contact_email, contact_phone, plan, status, max_employees, max_offices, valid_until, created_at)
VALUES ('DEMO2', 'CV Contoh Sejahtera', 'Bos Contoh', 'bos@contoh.id', '081300000001', 'STARTER', 'ACTIVE', 50, 3, NOW() + INTERVAL 365 DAY, NOW());
SET @c2 := LAST_INSERT_ID();

INSERT INTO offices (company_id, code, name, address, latitude, longitude, check_in_radius, check_out_radius, tracking_interval_seconds, status, created_at, timezone) VALUES
(@c2, 'MLG', 'Kantor Malang', 'Jl. Ijen No. 3, Malang', -7.9666000, 112.6326000, 60, 60, 600, 'ACTIVE', NOW(), 'WIB');
SET @o_mlg := LAST_INSERT_ID();

INSERT INTO employees (company_id, employee_code, name, email, phone, password, office_id, position, role, status, created_at) VALUES
(@c2, 'ADM001', 'Bos Contoh',  'bos@contoh.id',  '081300000001', @pw, @o_mlg, 'Super Administrator', 'SUPER_ADMIN', 'ACTIVE', NOW()),
(@c2, 'EMP001', 'Rina Marlina', 'rina@contoh.id', '081300000002', @pw, @o_mlg, 'Staff Administrasi',  'EMPLOYEE',    'ACTIVE', NOW()),
(@c2, 'EMP002', 'Tono Wibowo',  'tono@contoh.id', '081300000003', @pw, @o_mlg, 'Staff Gudang',        'EMPLOYEE',    'ACTIVE', NOW());
SET @rina := (SELECT id FROM employees WHERE company_id = @c2 AND employee_code = 'EMP001');
SET @tono := (SELECT id FROM employees WHERE company_id = @c2 AND employee_code = 'EMP002');
SET @bos  := (SELECT id FROM employees WHERE company_id = @c2 AND employee_code = 'ADM001');

INSERT INTO shifts (office_id, code, name, start_time, end_time, status, created_at) VALUES
(@o_mlg, 'N', 'Normal', '08:00:00', '16:30:00', 'ACTIVE', NOW());

INSERT INTO attendances (employee_id, office_id, attendance_date, check_in_time, check_in_latitude, check_in_longitude, check_in_accuracy, check_in_distance,
                         check_out_time, check_out_latitude, check_out_longitude, check_out_accuracy, check_out_distance, status, created_at, updated_at)
SELECT e.id, @o_mlg, CURDATE() - INTERVAL n.n DAY, TIMESTAMP(CURDATE() - INTERVAL n.n DAY, '07:55:00'), -7.9666000, 112.6326000, 8, 5,
       TIMESTAMP(CURDATE() - INTERVAL n.n DAY, '16:35:00'), -7.9666000, 112.6326000, 8, 5, 'PRESENT',
       TIMESTAMP(CURDATE() - INTERVAL n.n DAY, '07:55:00'), TIMESTAMP(CURDATE() - INTERVAL n.n DAY, '16:35:00')
FROM employees e
JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3) n ON 1 = 1
WHERE e.company_id = @c2 AND e.role = 'EMPLOYEE' AND WEEKDAY(CURDATE() - INTERVAL n.n DAY) < 5;

INSERT INTO attendance_requests (employee_id, office_id, type, date, start_date, end_date, time, end_time, reason, status, created_at) VALUES
(@rina, @o_mlg, 'LEAVE', NULL, CURDATE() + INTERVAL 2 DAY, CURDATE() + INTERVAL 2 DAY, NULL, NULL, 'Urusan keluarga (data CV Contoh)', 'PENDING', NOW() - INTERVAL 1 DAY);

INSERT INTO holidays (company_id, holiday_date, name, office_id, created_at) VALUES
(@c2, CURDATE() + INTERVAL 20 DAY, 'Libur Internal CV Contoh', NULL, NOW());

-- Selesai. Ringkasan:
SELECT 'Demo siap' AS info, @c AS company_id, @c2 AS company2_id,
       (SELECT COUNT(*) FROM employees   WHERE company_id = @c) AS akun,
       (SELECT COUNT(*) FROM attendances WHERE employee_id IN (SELECT id FROM employees WHERE company_id = @c)) AS absensi,
       (SELECT COUNT(*) FROM employee_shift_schedules WHERE employee_id IN (SELECT id FROM employees WHERE company_id = @c)) AS jadwal_shift;

-- =====================================================================
-- MIGRASI: tabel permintaan demo / uji coba (dari form di landing page)
-- Jalankan SEKALI pada database yang sudah berjalan.
-- (Instalasi baru tidak perlu: tabel ini sudah ada di schema.sql)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `demo_requests` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `phone` varchar(30) NOT NULL COMMENT 'format 628xxxx',
  `email` varchar(150) DEFAULT NULL,
  `employee_count` varchar(30) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('NEW','CONTACTED','CONVERTED','REJECTED') NOT NULL DEFAULT 'NEW',
  `admin_note` text DEFAULT NULL,
  `handled_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'global_admins.id yang terakhir menindaklanjuti',
  `handled_at` datetime DEFAULT NULL,
  `wa_sent` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = notifikasi WA ke admin berhasil terkirim',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_demo_status_created` (`status`,`created_at`),
  KEY `idx_demo_phone_created` (`phone`,`created_at`),
  KEY `idx_demo_ip_created` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

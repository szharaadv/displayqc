-- Modul Foreman: role foreman, tim (users.foreman_id), absensi harian, overtime.
-- Jalankan sekali di server: mysql displayqc < config/foreman.sql
-- Aman dijalankan ulang (pakai IF NOT EXISTS — sintaks MariaDB).
--
-- users.foreman_id   -> anggota tim milik foreman mana (NULL = belum punya tim).
-- attendance         -> 1 baris per orang per tanggal (status harian saja).
-- overtime           -> 1 baris per orang per jadwal OT. Pembagian rata dihitung
--                       dari SUM(durasi_menit) & COUNT(*) per bulan, status <> 'batal'.

ALTER TABLE `users`
  MODIFY `role` enum('admin','operator','qc','foreman') NOT NULL;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `foreman_id` int(11) DEFAULT NULL AFTER `role`,
  ADD KEY IF NOT EXISTS `idx_users_foreman` (`foreman_id`);

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('hadir','terlambat','izin','sakit','cuti','alpha') NOT NULL DEFAULT 'hadir',
  `keterangan` varchar(150) NOT NULL DEFAULT '',
  `dicatat_oleh` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_tgl_user` (`tanggal`, `user_id`),
  KEY `idx_attendance_user` (`user_id`, `tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `overtime` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `user_id` int(11) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `durasi_menit` int(11) NOT NULL DEFAULT 0,
  `keterangan` varchar(150) NOT NULL DEFAULT '',
  `alasan_lewati` varchar(200) NOT NULL DEFAULT '',
  `status` enum('rencana','selesai','batal') NOT NULL DEFAULT 'rencana',
  `dibuat_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_overtime_tgl` (`tanggal`),
  KEY `idx_overtime_user` (`user_id`, `tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

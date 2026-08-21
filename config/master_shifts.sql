-- Tabel definisi shift — dibaca oleh config/shift.php.
-- Jalankan sekali di server: mysql displayqc < config/master_shifts.sql
--
-- hari_tipe : 'weekday' (Senin-Kamis), 'friday', 'saturday', 'sunday' (Minggu)
-- shift_no  : 1, 2, 3
-- jam_selesai <= jam_mulai berarti shift melewati tengah malam (shift 3).
-- istirahat_menit : lama istirahat (menit) yang dipotong dari durasi shift.
-- efektif_menit   : durasi kerja efektif = (jam_selesai - jam_mulai) - istirahat.
--                   Dipakai sebagai pembagi operation ratio. NILAI INI TURUNAN,
--                   dihitung otomatis di shift_setting_action.php dari istirahat.

CREATE TABLE IF NOT EXISTS `master_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hari_tipe` varchar(10) NOT NULL,
  `shift_no` tinyint(4) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `istirahat_menit` int(11) NOT NULL DEFAULT 0,
  `efektif_menit` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hari_shift` (`hari_tipe`, `shift_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tambah kolom istirahat_menit untuk tabel lama yang belum punya (idempotent).
ALTER TABLE `master_shifts`
  ADD COLUMN IF NOT EXISTS `istirahat_menit` int(11) NOT NULL DEFAULT 0 AFTER `jam_selesai`;

-- Nilai awal. istirahat_menit = durasi shift dikurangi efektif_menit lama,
-- supaya angka operation ratio tidak berubah saat migrasi. Sesuaikan lewat
-- halaman admin (Setting Shift) sesuai jam istirahat riil tiap shift.
INSERT INTO `master_shifts` (`hari_tipe`, `shift_no`, `jam_mulai`, `jam_selesai`, `istirahat_menit`, `efektif_menit`) VALUES
('weekday', 1, '06:30:00', '15:15:00', 45, 480),
('weekday', 2, '15:15:00', '23:00:00', 15, 450),
('weekday', 3, '23:00:00', '06:30:00', 45, 405),
('friday',  1, '06:30:00', '14:45:00',  0, 495),
('friday',  2, '14:45:00', '22:45:00',  0, 480),
('friday',  3, '22:45:00', '06:30:00',  0, 465),
('saturday',1, '06:30:00', '14:15:00',  0, 465),
('saturday',2, '14:15:00', '21:45:00',  0, 450),
('saturday',3, '21:45:00', '05:15:00',  0, 450),
('sunday',  1, '06:30:00', '15:15:00', 45, 480),
('sunday',  2, '15:15:00', '23:00:00', 15, 450),
('sunday',  3, '23:00:00', '06:30:00', 45, 405)
ON DUPLICATE KEY UPDATE
  `jam_mulai` = VALUES(`jam_mulai`),
  `jam_selesai` = VALUES(`jam_selesai`),
  `istirahat_menit` = VALUES(`istirahat_menit`),
  `efektif_menit` = VALUES(`efektif_menit`);

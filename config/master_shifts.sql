-- Tabel definisi shift — dibaca oleh config/shift.php.
-- Jalankan sekali di server: mysql displayqc < config/master_shifts.sql
--
-- hari_tipe : 'weekday' (Senin-Kamis & Minggu), 'friday', 'saturday'
-- shift_no  : 1, 2, 3
-- jam_selesai <= jam_mulai berarti shift melewati tengah malam (shift 3).
-- efektif_menit : durasi kerja efektif (span dikurangi istirahat), dipakai
--                 sebagai pembagi operation ratio.

CREATE TABLE IF NOT EXISTS `master_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hari_tipe` varchar(10) NOT NULL,
  `shift_no` tinyint(4) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `efektif_menit` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hari_shift` (`hari_tipe`, `shift_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Nilai awal = nilai yang selama ini hardcoded di config/shift.php.
INSERT INTO `master_shifts` (`hari_tipe`, `shift_no`, `jam_mulai`, `jam_selesai`, `efektif_menit`) VALUES
('weekday', 1, '06:30:00', '15:15:00', 480),
('weekday', 2, '15:15:00', '23:00:00', 450),
('weekday', 3, '23:00:00', '06:30:00', 405),
('friday',  1, '06:30:00', '14:45:00', 495),
('friday',  2, '14:45:00', '22:45:00', 480),
('friday',  3, '22:45:00', '06:30:00', 465),
('saturday',1, '06:30:00', '14:15:00', 465),
('saturday',2, '14:15:00', '21:45:00', 450),
('saturday',3, '21:45:00', '05:15:00', 450)
ON DUPLICATE KEY UPDATE
  `jam_mulai` = VALUES(`jam_mulai`),
  `jam_selesai` = VALUES(`jam_selesai`),
  `efektif_menit` = VALUES(`efektif_menit`);

-- Kalender kerja YADIN — dibaca oleh config/calendar.php.
-- Jalankan sekali di server: mysql displayqc < config/master_calendar.sql
--
-- Tabel ini HANYA menyimpan tanggal PENGECUALIAN. Hari normal diturunkan:
--   Senin–Jumat = hari kerja, Sabtu–Minggu = libur.
--   tipe = 'holiday'  -> libur (tidak dikerjakan) walau jatuh di hari kerja.
--   tipe = 'overtime' -> akhir pekan yang TETAP dikerjakan (lembur).
--
-- Seed di bawah = data kalender 2026 yang sudah dipakai (32 holiday + 6 overtime).
-- Semua tanggal bisa disunting lewat halaman admin (admin/kalender.php).

CREATE TABLE IF NOT EXISTS `master_calendar` (
  `tanggal` date NOT NULL,
  `tipe` enum('holiday','overtime') NOT NULL,
  `label` varchar(100) NOT NULL DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- === Libur (holiday) 2026 ===
INSERT INTO `master_calendar` (`tanggal`, `tipe`, `label`) VALUES
('2026-01-01', 'holiday', 'New Year 2026'),
('2026-01-02', 'holiday', 'YADIN Special Holiday'),
('2026-01-16', 'holiday', 'Isra Mi''raj'),
('2026-02-16', 'holiday', 'Replaced Holiday'),
('2026-02-17', 'holiday', 'Chinese New Year'),
('2026-03-19', 'holiday', 'Nyepi'),
('2026-03-20', 'holiday', 'YADIN Collective Leave'),
('2026-03-21', 'holiday', 'Iedul Fitri'),
('2026-03-22', 'holiday', 'Iedul Fitri'),
('2026-03-23', 'holiday', 'YADIN Collective Leave'),
('2026-03-24', 'holiday', 'YADIN Collective Leave'),
('2026-03-25', 'holiday', 'YADIN Collective Leave'),
('2026-04-03', 'holiday', 'Good Friday'),
('2026-04-05', 'holiday', 'Easter Day'),
('2026-05-01', 'holiday', 'International Labor Day'),
('2026-05-14', 'holiday', 'Ascension Day of Christ'),
('2026-05-15', 'holiday', 'Replaced Holiday'),
('2026-05-27', 'holiday', 'Idul Adha'),
('2026-05-31', 'holiday', 'Waisak'),
('2026-06-01', 'holiday', 'Pancasila Day'),
('2026-06-15', 'holiday', 'Replaced Holiday'),
('2026-06-16', 'holiday', 'Islamic New Year'),
('2026-07-03', 'holiday', 'YADIN Special Holiday'),
('2026-08-17', 'holiday', 'Independence Day of Indonesia'),
('2026-08-24', 'holiday', 'Replaced Holiday'),
('2026-08-25', 'holiday', 'Prophet Birthday Muhammad SAW'),
('2026-09-04', 'holiday', 'YADIN Special Holiday'),
('2026-12-25', 'holiday', 'Christmas'),
('2026-12-28', 'holiday', 'YADIN Collective Leave'),
('2026-12-29', 'holiday', 'YADIN Collective Leave'),
('2026-12-30', 'holiday', 'Replaced Holiday'),
('2026-12-31', 'holiday', 'Replaced Holiday')
ON DUPLICATE KEY UPDATE `tipe` = VALUES(`tipe`), `label` = VALUES(`label`);

-- === Lembur akhir pekan (overtime) 2026 — Sabtu yang tetap dikerjakan ===
INSERT INTO `master_calendar` (`tanggal`, `tipe`, `label`) VALUES
('2026-02-07', 'overtime', 'Working Day'),
('2026-05-09', 'overtime', 'Working Day'),
('2026-05-23', 'overtime', 'Working Day'),
('2026-06-20', 'overtime', 'Working Day'),
('2026-08-29', 'overtime', 'Working Day'),
('2026-12-05', 'overtime', 'Working Day')
ON DUPLICATE KEY UPDATE `tipe` = VALUES(`tipe`), `label` = VALUES(`label`);

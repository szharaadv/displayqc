-- Tabel definisi shift — dibaca oleh config/shift.php.
-- Jalankan sekali: mysql displayqc < config/master_shifts.sql  (atau import via phpMyAdmin)
--
-- PENTING: skrip ini MEMBUAT ULANG tabel (DROP + CREATE) lalu mengisi 12 baris
-- definisi shift (4 tipe hari × 3 shift). Dipakai untuk SETUP AWAL. Kalau jam
-- shift sudah Anda sesuaikan lewat halaman admin (Setting Shift), JANGAN
-- dijalankan ulang — akan mengembalikan ke nilai default di bawah.
--
-- Dibuat ulang (bukan ALTER) supaya bebas dari sisa skema lama: kolom `id`
-- tanpa auto-increment (penyebab error #1364 di strict mode) dan tabel tanpa
-- kunci unik (bikin ON DUPLICATE KEY tidak berfungsi). Kunci sebenarnya =
-- (hari_tipe, shift_no); kolom `id` lama memang tidak dipakai aplikasi.
--
-- hari_tipe : 'weekday' (Senin-Kamis), 'friday', 'saturday', 'sunday' (Minggu)
-- shift_no  : 1, 2, 3
-- jam_selesai <= jam_mulai berarti shift lewat tengah malam (shift 3).
-- istirahat_menit : lama istirahat (menit) yang dipotong dari durasi shift.
-- efektif_menit   : (jam_selesai - jam_mulai) - istirahat. Pembagi operation ratio;
--                   nilai turunan, dihitung otomatis di shift_setting_action.php.

DROP TABLE IF EXISTS `master_shifts`;

CREATE TABLE `master_shifts` (
  `hari_tipe` varchar(10) NOT NULL,
  `shift_no` tinyint(4) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `istirahat_menit` int(11) NOT NULL DEFAULT 0,
  `efektif_menit` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`hari_tipe`, `shift_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Nilai awal. istirahat_menit weekday/sunday = 45/15/45 supaya efektif = nilai
-- lama (480/450/405). Jumat & Sabtu istirahat 0 (efektif = durasi penuh) —
-- sesuaikan lewat halaman admin sesuai jam istirahat riil.
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
('sunday',  3, '23:00:00', '06:30:00', 45, 405);

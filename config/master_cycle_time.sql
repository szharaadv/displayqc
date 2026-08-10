-- Tabel standar cycle time per kategori & mesin QC — dipakai admin/cycle_time.php.
-- Jalankan HANYA jika tabel ini belum ada di produksi (cek: buka menu Cycle Time;
-- kalau error "table doesn't exist", jalankan file ini):
--   mysql displayqc < config/master_cycle_time.sql
--
-- Aman diulang: CREATE TABLE IF NOT EXISTS tidak menimpa tabel/data yang sudah ada.
-- Tidak ada data awal — standar cycle time diisi sendiri lewat halaman Cycle Time.

CREATE TABLE IF NOT EXISTS `master_cycle_time` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(100) NOT NULL,
  `qc_machine` enum('CMM','RONDCOM','ROUGHNESS','CONTOUR','PROFIL PROJECTOR','MANUAL','HARDNESS CHECK') NOT NULL,
  `standard_detik` int(11) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cat_machine` (`category`,`qc_machine`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

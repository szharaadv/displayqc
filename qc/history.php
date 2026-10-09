<?php
// Halaman History sudah digabung ke Main Display → Done (pilih tanggal di sana).
// File ini dipertahankan hanya supaya bookmark lama tetap mengarah ke tempat yang benar.
$tgl = isset($_GET['tanggal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tanggal']) ? '&tgl=' . $_GET['tanggal'] : '';
header('Location: main_display.php?section=done' . $tgl);
exit;

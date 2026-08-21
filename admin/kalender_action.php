<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: kalender.php");
    exit;
}

$tanggal = trim($_POST['tanggal'] ?? '');
$tipe    = trim($_POST['tipe'] ?? '');           // normal | holiday | overtime
$label   = trim($_POST['label'] ?? '');

// Validasi tanggal (YYYY-MM-DD)
$d = DateTime::createFromFormat('Y-m-d', $tanggal);
if (!$d || $d->format('Y-m-d') !== $tanggal) {
    header("Location: kalender.php?err=" . urlencode('Tanggal tidak valid.'));
    exit;
}

$tahun = (int)$d->format('Y');

if ($tipe === 'normal') {
    // 'normal' = kembalikan ke perilaku default (hapus baris pengecualian).
    $t = mysqli_real_escape_string($conn, $tanggal);
    if (!mysqli_query($conn, "DELETE FROM `master_calendar` WHERE `tanggal` = '$t'")) {
        header("Location: kalender.php?y=$tahun&err=" . urlencode('Gagal menghapus: ' . mysqli_error($conn)));
        exit;
    }
    header("Location: kalender.php?y=$tahun&ok=" . urlencode('Tanggal dikembalikan ke normal.'));
    exit;
}

if ($tipe !== 'holiday' && $tipe !== 'overtime') {
    header("Location: kalender.php?y=$tahun&err=" . urlencode('Tipe tidak valid.'));
    exit;
}

if ($label === '') {
    $label = ($tipe === 'holiday') ? 'Libur' : 'Lembur';
}
if (mb_strlen($label) > 100) {
    $label = mb_substr($label, 0, 100);
}

$t = mysqli_real_escape_string($conn, $tanggal);
$tp = mysqli_real_escape_string($conn, $tipe);
$lb = mysqli_real_escape_string($conn, $label);

$sql = "INSERT INTO `master_calendar` (`tanggal`, `tipe`, `label`)
        VALUES ('$t', '$tp', '$lb')
        ON DUPLICATE KEY UPDATE `tipe` = VALUES(`tipe`), `label` = VALUES(`label`)";
if (!mysqli_query($conn, $sql)) {
    header("Location: kalender.php?y=$tahun&err=" . urlencode('Gagal menyimpan: ' . mysqli_error($conn)));
    exit;
}

header("Location: kalender.php?y=$tahun&ok=" . urlencode('Kalender berhasil diperbarui.'));
exit;

<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/csrf.php';
include '../config/foreman.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

$ctx = fmKonteks($conn);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: absensi.php");
    exit;
}
csrfVerify();

$tanggal = fmTanggal($_POST['tgl'] ?? null, '');
$balik   = 'absensi.php?tgl=' . $tanggal . fmParam($ctx);
if ($tanggal === '') fmBalik('absensi.php', 'err', 'Invalid date.');

// Hanya anggota tim dalam konteks ini yang boleh diisi — id lain diabaikan.
$anggota = fmAnggota($conn, $ctx['foreman_id']);
$status  = $_POST['status'] ?? [];
$ket     = $_POST['ket'] ?? [];
$oleh    = (int)$_SESSION['id'];

$n = 0;
foreach ($anggota as $uid => $_) {
    $st = $status[$uid] ?? '';
    if (!isset(FM_STATUS_ABSEN[$st])) continue;
    $k = mysqli_real_escape_string($conn, mb_substr(trim((string)($ket[$uid] ?? '')), 0, 150));
    mysqli_query($conn, "INSERT INTO attendance (tanggal, user_id, status, keterangan, dicatat_oleh)
                         VALUES ('$tanggal', $uid, '$st', '$k', $oleh)
                         ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), dicatat_oleh = VALUES(dicatat_oleh)");
    $n++;
}

fmBalik($balik, 'ok', "Attendance saved for $n people on " . fmLabelTanggal($tanggal) . '.');

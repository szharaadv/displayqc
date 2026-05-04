<?php
session_start();
include '../config/koneksi.php';
/** @var mysqli $conn */

$nik              = trim($_POST['nik']              ?? '');
$password_lama    = trim($_POST['password_lama']    ?? '');
$password_baru    = trim($_POST['password_baru']    ?? '');
$password_konfirm = trim($_POST['password_konfirmasi'] ?? '');

// Validasi kosong
if (empty($password_baru)) {
    header("Location: ganti_password.php?error=4");
    exit;
}

// Validasi konfirmasi
if ($password_baru !== $password_konfirm) {
    header("Location: ganti_password.php?error=3");
    exit;
}

$nik_esc = mysqli_real_escape_string($conn, $nik);

// Cek NIK ada
$query = mysqli_query($conn, "SELECT * FROM users WHERE nik='$nik_esc' AND status=1 LIMIT 1");
$user  = mysqli_fetch_assoc($query);

if (!$user) {
    header("Location: ganti_password.php?error=1");
    exit;
}

// Cek password lama
if ($password_lama !== $user['password']) {
    header("Location: ganti_password.php?error=2");
    exit;
}

// Update password
$pass_baru_esc = mysqli_real_escape_string($conn, $password_baru);
mysqli_query($conn, "UPDATE users SET password='$pass_baru_esc' WHERE nik='$nik_esc'");

header("Location: ganti_password.php?success=1");
exit;
?>
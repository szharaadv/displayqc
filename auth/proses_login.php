<?php
session_start();
include '../config/koneksi.php';
/** @var mysqli $conn */

$nik      = trim($_POST['nik']      ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($nik) || empty($password)) {
    header("Location: login.php?error=1");
    exit;
}

$nik_esc = mysqli_real_escape_string($conn, $nik);
$query   = mysqli_query($conn, "SELECT * FROM users WHERE nik='$nik_esc' AND status=1 LIMIT 1");
$user    = mysqli_fetch_assoc($query);

if ($user && $password === $user['password']) {
    session_regenerate_id(true);
    $_SESSION['id']   = $user['id'];
    $_SESSION['nik']  = $user['nik'];
    $_SESSION['nama'] = $user['nama'];
    $_SESSION['role'] = $user['role'];

    if ($user['role'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../menu.php");
    }
    exit;
} else {
    header("Location: login.php?error=1");
    exit;
}
?>
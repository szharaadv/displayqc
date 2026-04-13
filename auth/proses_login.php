<?php
session_start();
include '../config/koneksi.php';

$nik      = $_POST['nik']      ?? '';
$password = $_POST['password'] ?? '';

if (empty($nik) || empty($password)) {
    header("Location: login.php?error=1");
    exit;
}

$nik_int = (int)$nik;

$query = mysqli_query($conn, "SELECT * FROM users WHERE nik=$nik_int AND status=1");
$user  = mysqli_fetch_assoc($query);

if ($user && $password === $user['password']) {
    session_regenerate_id(true);

    $_SESSION['id']   = $user['id'];
    $_SESSION['nik']  = $user['nik'];
    $_SESSION['role'] = $user['role'];

    header("Location: ../menu.php");
    exit;
} else {
    header("Location: login.php?error=1");
    exit;
}
?>
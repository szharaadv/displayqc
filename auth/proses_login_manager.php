<?php
session_start();
include '../config/koneksi.php';

$nik      = trim($_POST['nik']);
$password = mysqli_real_escape_string($conn, trim($_POST['password']));

$query = mysqli_query($conn, "
    SELECT * FROM users 
    WHERE nik = '$nik'
      AND password = '$password' 
      AND role = 'admin'
      AND status = 1
    LIMIT 1
");

if (mysqli_num_rows($query) === 1) {
    $user = mysqli_fetch_assoc($query);
    session_regenerate_id(true);
    $_SESSION['id']   = $user['id'];
    $_SESSION['nik']  = $user['nik'];
    $_SESSION['nama'] = $user['nama'];
    $_SESSION['role'] = $user['role'];

    header("Location: ../admin/dashboard.php");
    exit;
} else {
    header("Location: login_manager.php?error=1");
    exit;
}
?>
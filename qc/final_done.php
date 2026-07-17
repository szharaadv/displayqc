<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/csrf.php';
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'qc') {
    header("Location: display.php");
    exit;
}

csrfVerify();

if (!isset($_POST['id'])) {
    header("Location: main_display.php?section=done");
    exit;
}

$order_id = (int) $_POST['id'];

mysqli_query($conn, "
    UPDATE sampling_orders
    SET status = 'done'
    WHERE id = '$order_id'
");

header("Location: main_display.php?section=done&final_done=1");
exit;
?>
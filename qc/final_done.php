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

// Tutup order = mesin yang masih jalan / di-pause ikut selesai. Dulu step itu
// tertinggal "in_progress" tanpa end_time, sehingga waktunya hilang dari ratio.
mysqli_query($conn, "
    UPDATE sampling_process_steps
    SET status = 'done', end_time = NOW()
    WHERE order_id = $order_id AND status = 'in_progress'
");
$terakhir = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT id, status FROM sampling_process_steps
    WHERE order_id = $order_id ORDER BY id DESC LIMIT 1
"));
if ($terakhir && $terakhir['status'] === 'paused') {
    mysqli_query($conn, "UPDATE sampling_process_steps SET status = 'done' WHERE id = " . (int)$terakhir['id']);
}

mysqli_query($conn, "
    UPDATE sampling_orders
    SET status = 'done'
    WHERE id = '$order_id'
");

header("Location: main_display.php?section=done&final_done=1");
exit;
?>
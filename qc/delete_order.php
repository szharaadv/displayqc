<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['qc', 'operator'])) {
    header("Location: display.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: display.php");
    exit;
}

$order_id = (int) $_GET['id'];

$cek = mysqli_query($conn, "SELECT * FROM sampling_orders WHERE id = '$order_id'");
if (mysqli_num_rows($cek) == 0) {
    header("Location: display.php?delete_error=1");
    exit;
}

$order = mysqli_fetch_assoc($cek);

/* hanya boleh hapus order waiting */
if ($order['status'] !== 'waiting') {
    header("Location: display.php?delete_error=1");
    exit;
}

/* hapus step kalau ada */
mysqli_query($conn, "DELETE FROM sampling_process_steps WHERE order_id = '$order_id'");

/* hapus order utama */
$hapus = mysqli_query($conn, "DELETE FROM sampling_orders WHERE id = '$order_id'");

if ($hapus) {
    header("Location: display.php?delete_success=1");
    exit;
} else {
    header("Location: display.php?delete_error=1");
    exit;
}
?>
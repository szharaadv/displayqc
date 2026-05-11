<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
/** @var mysqli $conn */

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Hanya role qc yang boleh akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'qc') {
    header("Location: ../auth/login.php");
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id  = (int)$_SESSION['id'];

if ($order_id <= 0) {
    header("Location: main_display.php?section=job&delete_error=1");
    exit;
}

// Pastikan order ini milik user yang login (created_by = user ini)
$cek = mysqli_query($conn, "
    SELECT id, status FROM sampling_orders
    WHERE id = $order_id AND created_by = $user_id
    LIMIT 1
");

if (!$cek || mysqli_num_rows($cek) === 0) {
    // Order bukan milik user ini, atau tidak ditemukan
    header("Location: main_display.php?section=job&delete_error=1");
    exit;
}

// Hapus dulu semua process steps terkait order ini
mysqli_query($conn, "DELETE FROM sampling_process_steps WHERE order_id = $order_id");

// Lalu hapus ordernya
$hapus = mysqli_query($conn, "DELETE FROM sampling_orders WHERE id = $order_id AND created_by = $user_id");

if ($hapus) {
    header("Location: main_display.php?section=job&delete_success=1");
} else {
    header("Location: main_display.php?section=job&delete_error=1");
}
exit;
?>  
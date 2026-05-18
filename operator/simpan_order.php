<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
/** @var mysqli $conn */

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$category   = mysqli_real_escape_string($conn, $_POST['category']);
$part_id    = (int) $_POST['part_id'];
$line_id    = (int) $_POST['line_id'];
$machine_id = (int) $_POST['machine_id'];
$qty        = (int) $_POST['qty'];
$created_by = (int) $_SESSION['id'];

// Deteksi shift_date supaya order masuk ke tanggal shift yang benar
$now_h   = (int)date('H');
$now_m   = (int)date('i');
$now_tot = $now_h * 60 + $now_m;
$now_hari = (int)date('N');

if ($now_hari === 7 && $now_tot < 315) {
    $shift_date_order = date('Y-m-d', strtotime('-1 day'));
} elseif ($now_hari === 6 && $now_tot < 390) {
    $shift_date_order = date('Y-m-d', strtotime('-1 day'));
} elseif ($now_tot < 390) {
    $shift_date_order = date('Y-m-d', strtotime('-1 day'));
} else {
    $shift_date_order = date('Y-m-d');
}

$cekPart    = mysqli_query($conn, "SELECT * FROM master_parts WHERE id='$part_id' AND category='$category'");
$cekLine    = mysqli_query($conn, "SELECT * FROM master_lines WHERE id='$line_id' AND category='$category'");
$cekMachine = mysqli_query($conn, "SELECT * FROM master_machines WHERE id='$machine_id' AND category='$category'");

if (
    mysqli_num_rows($cekPart) == 0 ||
    mysqli_num_rows($cekLine) == 0 ||
    mysqli_num_rows($cekMachine) == 0
) {
    die("Data category, part, line, atau machine tidak sesuai.");
}

$order_code = 'ORD-' . date('YmdHis');

$sql = "INSERT INTO sampling_orders (order_code, category, part_id, line_id, machine_id, qty, created_by, status, created_at)
        VALUES ('$order_code', '$category', '$part_id', '$line_id', '$machine_id', '$qty', '$created_by', 'waiting', '$shift_date_order 00:00:00')";
        
if (mysqli_query($conn, $sql)) {
    header("Location: ../qc/display.php?success=1&order_code=" . urlencode($order_code));
    exit;
} else {
    echo "Gagal simpan order: " . mysqli_error($conn);
}
?>
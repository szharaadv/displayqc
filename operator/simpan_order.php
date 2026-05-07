<?php
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

$cekPart = mysqli_query($conn, "SELECT * FROM master_parts WHERE id='$part_id' AND category='$category'");
$cekLine = mysqli_query($conn, "SELECT * FROM master_lines WHERE id='$line_id' AND category='$category'");
$cekMachine = mysqli_query($conn, "SELECT * FROM master_machines WHERE id='$machine_id' AND category='$category'");

if (
    mysqli_num_rows($cekPart) == 0 ||
    mysqli_num_rows($cekLine) == 0 ||
    mysqli_num_rows($cekMachine) == 0
) {
    die("Data category, part, line, atau machine tidak sesuai.");
}

$order_code = 'ORD-' . date('YmdHis');

$sql = "INSERT INTO sampling_orders (order_code, category, part_id, line_id, machine_id, qty, created_by, status)
        VALUES ('$order_code', '$category', '$part_id', '$line_id', '$machine_id', '$qty', '$created_by', 'waiting')";

if (mysqli_query($conn, $sql)) {
    header("Location: ../qc/display.php?success=1&order_code=" . urlencode($order_code));
    exit;
} else {
    echo "Gagal simpan order: " . mysqli_error($conn);
}
?>
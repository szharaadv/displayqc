<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'qc') {
    header("Location: display.php");
    exit;
}

$order_id   = isset($_POST['order_id'])   ? (int) $_POST['order_id']                                        : 0;
$nik        = isset($_POST['nik'])        ? (int) trim($_POST['nik'])                                        : 0;
$password   = isset($_POST['password'])   ? mysqli_real_escape_string($conn, trim($_POST['password']))       : '';
$qc_machine = isset($_POST['qc_machine']) ? mysqli_real_escape_string($conn, trim($_POST['qc_machine']))     : '';

$allowed_machines = ['CMM', 'RONDCOM', 'ROUGHNESS', 'CONTOUR', 'PROFIL PROJECTOR', 'MANUAL', 'HARDNESS CHECK'];

if ($order_id <= 0) {
    header("Location: main_display.php?section=job&error_order=1");
    exit;
}

if (!in_array($qc_machine, $allowed_machines, true)) {
    header("Location: main_display.php?section=job&error_machine=1");
    exit;
}

// ✅ NIK pakai INT (tanpa quotes) supaya cocok dengan tipe kolom DB
$userQuery = mysqli_query($conn, "
    SELECT *
    FROM users
    WHERE nik = $nik
      AND password = '$password'
      AND role = 'qc'
      AND status = 1
    LIMIT 1
");

if (!$userQuery || mysqli_num_rows($userQuery) === 0) {
    header("Location: main_display.php?section=job&error_qc=1");
    exit;
}

$user       = mysqli_fetch_assoc($userQuery);
$qc_user_id = (int) $user['id'];

$orderQuery = mysqli_query($conn, "
    SELECT *
    FROM sampling_orders
    WHERE id = $order_id
    LIMIT 1
");

if (!$orderQuery || mysqli_num_rows($orderQuery) === 0) {
    header("Location: main_display.php?section=job&error_order=1");
    exit;
}

$order = mysqli_fetch_assoc($orderQuery);

if ($order['status'] === 'done') {
    header("Location: main_display.php?section=done");
    exit;
}

$cekMesin = mysqli_query($conn, "
    SELECT *
    FROM sampling_process_steps
    WHERE order_id = $order_id
      AND qc_machine = '$qc_machine'
    ORDER BY id DESC
    LIMIT 1
");

if ($cekMesin && mysqli_num_rows($cekMesin) > 0) {
    $step = mysqli_fetch_assoc($cekMesin);

    if ($step['status'] === 'done') {
        header("Location: main_display.php?section=job&machine_done=1");
        exit;
    }

    if ($step['status'] === 'in_progress') {
        header("Location: main_display.php?section=progress&already_progress=1");
        exit;
    }
}

$insert = mysqli_query($conn, "
    INSERT INTO sampling_process_steps (
        order_id,
        qc_machine,
        qc_user_id,
        status,
        start_time
    ) VALUES (
        $order_id,
        '$qc_machine',
        $qc_user_id,
        'in_progress',
        NOW()
    )
");

if (!$insert) {
    header("Location: main_display.php?section=job&insert_error=1");
    exit;
}

$updateOrder = mysqli_query($conn, "
    UPDATE sampling_orders
    SET status = 'in_progress'
    WHERE id = $order_id
");

if (!$updateOrder) {
    header("Location: main_display.php?section=job&insert_error=1");
    exit;
}

header("Location: main_display.php?section=progress&process_success=1");
exit;
?>
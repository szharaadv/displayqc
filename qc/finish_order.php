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

if (!isset($_GET['id'])) {
    header("Location: main_display.php?section=progress");
    exit;
}

$order_id = (int) $_GET['id'];

/* step aktif terakhir */
$stepQuery = mysqli_query($conn, "
    SELECT * FROM sampling_process_steps
    WHERE order_id = '$order_id' AND status = 'in_progress'
    ORDER BY id DESC
    LIMIT 1
");

if (mysqli_num_rows($stepQuery) == 0) {
    header("Location: main_display.php?section=progress");
    exit;
}

$step = mysqli_fetch_assoc($stepQuery);
$step_id = (int) $step['id'];

mysqli_query($conn, "
    UPDATE sampling_process_steps
    SET status = 'done',
        end_time = NOW()
    WHERE id = '$step_id'
");

/* cek apakah masih ada step in_progress lain */
$cekProgress = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM sampling_process_steps
    WHERE order_id = '$order_id' AND status = 'in_progress'
");
$progressData = mysqli_fetch_assoc($cekProgress);

if ((int)$progressData['total'] > 0) {
    mysqli_query($conn, "
        UPDATE sampling_orders
        SET status = 'in_progress'
        WHERE id = '$order_id'
    ");
} else {
    mysqli_query($conn, "
        UPDATE sampling_orders
        SET status = 'partial_done'
        WHERE id = '$order_id'
    ");
}

header("Location: main_display.php?section=progress&finish_success=1");
exit;
?>
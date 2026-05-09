<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$order_id  = isset($_GET['id'])     ? (int)$_GET['id']  : 0;
$is_resume = isset($_GET['resume']) && $_GET['resume'] == '1';
$user_id   = (int)$_SESSION['id'];

if ($order_id <= 0) {
    header("Location: main_display.php?section=progress&error_order=1");
    exit;
}

if ($is_resume) {
    // ── RESUME ──────────────────────────────────────────────────────────────
    // Ambil step terakhir yang paused — untuk tahu mesin apa yang dilanjutkan
    $cek = mysqli_query($conn, "
        SELECT id, qc_machine FROM sampling_process_steps
        WHERE order_id = $order_id
          AND status = 'paused'
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$cek || mysqli_num_rows($cek) === 0) {
        header("Location: main_display.php?section=progress&error_order=1");
        exit;
    }

    $step       = mysqli_fetch_assoc($cek);
    $qc_machine = mysqli_real_escape_string($conn, $step['qc_machine']);

    // Buat row baru — mesin sama, start_time = sekarang
    mysqli_query($conn, "
        INSERT INTO sampling_process_steps (order_id, qc_machine, qc_user_id, status, start_time)
        VALUES ($order_id, '$qc_machine', $user_id, 'in_progress', NOW())
    ");

    // Update order status jadi in_progress
    mysqli_query($conn, "
        UPDATE sampling_orders
        SET status = 'in_progress'
        WHERE id = $order_id
    ");

    header("Location: main_display.php?section=progress&resume_success=1");
    exit;

} else {
    // ── PAUSE ────────────────────────────────────────────────────────────────
    $cek = mysqli_query($conn, "
        SELECT id FROM sampling_process_steps
        WHERE order_id = $order_id
          AND status = 'in_progress'
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$cek || mysqli_num_rows($cek) === 0) {
        header("Location: main_display.php?section=progress&error_order=1");
        exit;
    }

    $step    = mysqli_fetch_assoc($cek);
    $step_id = (int)$step['id'];

    // Update status jadi paused + isi end_time
    mysqli_query($conn, "
        UPDATE sampling_process_steps
        SET status = 'paused', end_time = NOW()
        WHERE id = $step_id
    ");

    // Update order status jadi partial_done
    mysqli_query($conn, "
        UPDATE sampling_orders
        SET status = 'partial_done'
        WHERE id = $order_id
    ");

    header("Location: main_display.php?section=progress&pause_success=1");
    exit;
}
?>
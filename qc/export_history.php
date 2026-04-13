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

$tanggal_dari = isset($_GET['tanggal_dari']) && $_GET['tanggal_dari'] !== ''
    ? $_GET['tanggal_dari']
    : date('Y-m-d');

$tanggal_sampai = isset($_GET['tanggal_sampai']) && $_GET['tanggal_sampai'] !== ''
    ? $_GET['tanggal_sampai']
    : date('Y-m-d');

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$whereStatus = "";
if ($status_filter === 'waiting') {
    $whereStatus = " AND so.status = 'waiting' ";
} elseif ($status_filter === 'in_progress') {
    $whereStatus = " AND so.status IN ('in_progress','partial_done') ";
} elseif ($status_filter === 'done') {
    $whereStatus = " AND so.status = 'done' ";
}

$query = mysqli_query($conn, "
    SELECT
        so.order_code,
        so.category,
        so.status,
        so.created_at,
        mp.part_no,
        mp.part_name,
        ml.catalog_line,
        mm.machine_jig_catalog,

        MAX(CASE WHEN sps.qc_machine = 'CMM'       AND sps.status = 'done' THEN 1 ELSE 0 END) AS cmm_done,
        MAX(CASE WHEN sps.qc_machine = 'RUNCOM'    AND sps.status = 'done' THEN 1 ELSE 0 END) AS runcom_done,
        MAX(CASE WHEN sps.qc_machine = 'ROUGHNESS' AND sps.status = 'done' THEN 1 ELSE 0 END) AS roughness_done,
        MAX(CASE WHEN sps.qc_machine = 'CONTOURE'  AND sps.status = 'done' THEN 1 ELSE 0 END) AS contoure_done,
        MAX(CASE WHEN sps.qc_machine = 'PROFIL'    AND sps.status = 'done' THEN 1 ELSE 0 END) AS profil_done,
        MAX(CASE WHEN sps.qc_machine = 'MANUAL'    AND sps.status = 'done' THEN 1 ELSE 0 END) AS manual_done,

        MIN(sps.start_time) AS latest_start_time,
        MAX(sps.end_time)   AS latest_end_time,
        MAX(u.nama)         AS qc_nama
    FROM sampling_orders so
    JOIN master_parts    mp  ON so.part_id    = mp.id
    JOIN master_lines    ml  ON so.line_id    = ml.id
    JOIN master_machines mm  ON so.machine_id = mm.id
    LEFT JOIN sampling_process_steps sps ON so.id = sps.order_id
    LEFT JOIN users u ON sps.qc_user_id = u.id
    WHERE DATE(so.created_at) BETWEEN '$tanggal_dari' AND '$tanggal_sampai'
    $whereStatus
    GROUP BY
        so.id, so.order_code, so.category, so.status, so.created_at,
        mp.part_no, mp.part_name, ml.catalog_line, mm.machine_jig_catalog
    ORDER BY so.created_at DESC
");

$filename = 'History_QC_' . $tanggal_dari . '_sd_' . $tanggal_sampai . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$output = fopen('php://output', 'w');

// BOM UTF-8 biar Excel gak mojibake
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Info header laporan
fputcsv($output, ['LAPORAN HISTORY QC SAMPLING']);
fputcsv($output, [
    'Periode: ' . date('d M Y', strtotime($tanggal_dari)) . ' s/d ' . date('d M Y', strtotime($tanggal_sampai)),
    'Diekspor: ' . date('d M Y H:i')
]);
fputcsv($output, []);

// Header kolom
fputcsv($output, [
    'No.',
    'Order Code',
    'Category',
    'Part No',
    'Part Name',
    'Line',
    'Machine / Jig',
    'QC Staff',
    'CMM',
    'RUNCOM',
    'ROUGHNESS',
    'CONTOURE',
    'PROFIL',
    'MANUAL',
    'Start',
    'Finish',
    'Durasi (HH:MM:SS)',
    'Status',
    'Created At',
]);

$no = 1;
while ($row = mysqli_fetch_assoc($query)) {

    // Durasi
    $durText = '-';
    if (!empty($row['latest_start_time']) && !empty($row['latest_end_time'])) {
        $secs    = max(0, strtotime($row['latest_end_time']) - strtotime($row['latest_start_time']));
        $durText = sprintf('%02d:%02d:%02d', floor($secs / 3600), floor(($secs % 3600) / 60), $secs % 60);
    }

    // Status label
    $st = $row['status'];
    if ($st === 'waiting')                                   $stLabel = 'Order Request';
    elseif ($st === 'in_progress' || $st === 'partial_done') $stLabel = 'In Progress';
    elseif ($st === 'done')                                  $stLabel = 'Done';
    else                                                     $stLabel = strtoupper(str_replace('_', ' ', $st));

    fputcsv($output, [
        $no,
        $row['order_code'],
        $row['category'],
        $row['part_no'],
        $row['part_name'],
        $row['catalog_line'],
        $row['machine_jig_catalog'],
        $row['qc_nama'] ?: '-',
        (int)$row['cmm_done']      === 1 ? 'DONE' : '-',
        (int)$row['runcom_done']   === 1 ? 'DONE' : '-',
        (int)$row['roughness_done']=== 1 ? 'DONE' : '-',
        (int)$row['contoure_done'] === 1 ? 'DONE' : '-',
        (int)$row['profil_done']   === 1 ? 'DONE' : '-',
        (int)$row['manual_done']   === 1 ? 'DONE' : '-',
        $row['latest_start_time'] ?: '-',
        $row['latest_end_time']   ?: '-',
        $durText,
        $stLabel,
        $row['created_at'],
    ]);

    $no++;
}

fputcsv($output, []);
fputcsv($output, ['Total: ' . ($no - 1) . ' order']);

fclose($output);
exit;
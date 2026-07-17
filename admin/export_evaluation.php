<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/shift.php';
include '../config/xlsx.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$sel_month = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : (int)date('m');
$sel_year  = isset($_GET['year'])  && $_GET['year']  !== '' ? (int)$_GET['year']  : (int)date('Y');
$sel_nik   = isset($_GET['nik'])   ? $_GET['nik'] : 'all';

$month_pad = str_pad($sel_month, 2, '0', STR_PAD_LEFT);
$date_from = "{$sel_year}-{$month_pad}-01";
$date_to   = date('Y-m-t', strtotime($date_from));

$month_names  = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$period_label = $month_names[$sel_month] . ' ' . $sel_year;

$whereNik = '';
if ($sel_nik !== 'all') {
    $nik_esc  = mysqli_real_escape_string($conn, $sel_nik);
    $whereNik = " AND u.nik = '$nik_esc' ";
}

// ── Daftar staff QC (kolom-kolom rekap) ───────────────────────────────────────
$staffList = [];
$staffQuery = mysqli_query($conn, "SELECT id, nik, nama FROM users WHERE role = 'qc' $whereNik ORDER BY nama ASC");
while ($s = mysqli_fetch_assoc($staffQuery)) $staffList[] = $s;

// ── Matrix step harian: [tgl][uid] = jumlah step done ────────────────────────
$stepQuery = mysqli_query($conn, "
    SELECT DATE(sps.start_time) AS tgl, u.id AS uid, COUNT(*) AS jumlah
    FROM sampling_process_steps sps
    JOIN users u ON sps.qc_user_id = u.id
    WHERE u.role = 'qc'
      AND DATE(sps.start_time) BETWEEN '$date_from' AND '$date_to'
      AND sps.status = 'done'
      $whereNik
    GROUP BY DATE(sps.start_time), u.id
");
$stepMatrix = [];
while ($r = mysqli_fetch_assoc($stepQuery)) {
    $stepMatrix[$r['tgl']][$r['uid']] = (int)$r['jumlah'];
}

// ── Matrix operation ratio per shift: [shift_date|shift_nama][uid] = ratio ───
$ratioQuery = mysqli_query($conn, "
    SELECT
        u.id AS uid,
        sps.start_time,
        TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) AS durasi
    FROM sampling_process_steps sps
    JOIN users u ON sps.qc_user_id = u.id
    WHERE u.role = 'qc'
      AND DATE(sps.start_time) BETWEEN '$date_from' AND '$date_to'
      AND sps.status IN ('done', 'paused')
      AND sps.end_time IS NOT NULL
      $whereNik
    ORDER BY sps.start_time ASC
");
$ratioAccum = []; // [key][uid] = ['tgl'=>, 'shift'=>, 'total_detik'=>, 'work_sec'=>]
while ($r = mysqli_fetch_assoc($ratioQuery)) {
    $shift = qcShift($r['start_time']);
    $key   = $shift['shift_date'] . '|' . $shift['nama'];
    $uid   = $r['uid'];
    if (!isset($ratioAccum[$key][$uid])) {
        $ratioAccum[$key][$uid] = [
            'tgl'         => $shift['shift_date'],
            'shift'       => $shift['nama'],
            'total_detik' => 0,
            'work_sec'    => $shift['detik'],
        ];
    }
    $ratioAccum[$key][$uid]['total_detik'] += (int)$r['durasi'];
}
ksort($ratioAccum);

// ── Summary per staff (total order, step, per mesin) ──────────────────────────
$summaryQuery = mysqli_query($conn, "
    SELECT
        u.id, u.nama, u.nik,
        COUNT(DISTINCT sps.order_id) AS total_order,
        COUNT(sps.id) AS total_step,
        SUM(CASE WHEN sps.qc_machine = 'CMM'              AND sps.status = 'done' THEN 1 ELSE 0 END) AS cmm_count,
        SUM(CASE WHEN sps.qc_machine = 'RONDCOM'          AND sps.status = 'done' THEN 1 ELSE 0 END) AS rondcom_count,
        SUM(CASE WHEN sps.qc_machine = 'ROUGHNESS'        AND sps.status = 'done' THEN 1 ELSE 0 END) AS roughness_count,
        SUM(CASE WHEN sps.qc_machine = 'CONTOUR'          AND sps.status = 'done' THEN 1 ELSE 0 END) AS contour_count,
        SUM(CASE WHEN sps.qc_machine = 'PROFIL PROJECTOR' AND sps.status = 'done' THEN 1 ELSE 0 END) AS profil_count,
        SUM(CASE WHEN sps.qc_machine = 'MANUAL'           AND sps.status = 'done' THEN 1 ELSE 0 END) AS manual_count,
        SUM(CASE WHEN sps.qc_machine = 'HARDNESS CHECK'   AND sps.status = 'done' THEN 1 ELSE 0 END) AS hardness_count
    FROM users u
    LEFT JOIN sampling_process_steps sps ON sps.qc_user_id = u.id
        AND DATE(sps.created_at) BETWEEN '$date_from' AND '$date_to'
        AND sps.status = 'done'
    WHERE u.role = 'qc' $whereNik
    GROUP BY u.id, u.nama, u.nik
    ORDER BY total_step DESC
");
$summary_data = [];
while ($row = mysqli_fetch_assoc($summaryQuery)) $summary_data[] = $row;

// ── Sheet 1: Rekap Step Harian ────────────────────────────────────────────────
$sheetStep = [];
$sheetStep[] = [xb('LAPORAN REKAP QC BULANAN — ' . strtoupper($period_label))];
$sheetStep[] = ['Diekspor: ' . date('d M Y H:i')];
$sheetStep[] = [];

$headerRow = [xb('Tanggal')];
foreach ($staffList as $s) $headerRow[] = xb($s['nama']);
$headerRow[] = xb('Total');
$sheetStep[] = $headerRow;

$totalPerStaff = array_fill_keys(array_column($staffList, 'id'), 0);
$grandTotal    = 0;

$cursor = strtotime($date_from);
$endTs  = strtotime($date_to);
while ($cursor <= $endTs) {
    $tgl     = date('Y-m-d', $cursor);
    $row     = [$tgl];
    $rowSum  = 0;
    foreach ($staffList as $s) {
        $jumlah = $stepMatrix[$tgl][$s['id']] ?? 0;
        $row[]  = $jumlah;
        $totalPerStaff[$s['id']] += $jumlah;
        $rowSum += $jumlah;
    }
    $row[] = $rowSum;
    $grandTotal += $rowSum;
    $sheetStep[] = $row;
    $cursor = strtotime('+1 day', $cursor);
}

$totalRow = [xb('Total')];
foreach ($staffList as $s) $totalRow[] = xb($totalPerStaff[$s['id']]);
$totalRow[] = xb($grandTotal);
$sheetStep[] = $totalRow;

// ── Sheet 2: Operation Ratio per Shift ────────────────────────────────────────
$sheetRatio = [];
$sheetRatio[] = [xb('OPERATION RATIO (%) PER SHIFT — ' . strtoupper($period_label))];
$sheetRatio[] = [];

$headerRatio = [xb('Tanggal'), xb('Shift')];
foreach ($staffList as $s) $headerRatio[] = xb($s['nama']);
$sheetRatio[] = $headerRatio;

foreach ($ratioAccum as $key => $perUid) {
    $first = reset($perUid);
    $row   = [$first['tgl'], $first['shift']];
    foreach ($staffList as $s) {
        if (isset($perUid[$s['id']])) {
            $d = $perUid[$s['id']];
            $row[] = min(100, round(($d['total_detik'] / $d['work_sec']) * 100, 1));
        } else {
            $row[] = '';
        }
    }
    $sheetRatio[] = $row;
}

// ── Sheet 3: Summary Bulanan ───────────────────────────────────────────────────
$sheetSummary = [];
$sheetSummary[] = [xb('SUMMARY QC BULANAN — ' . strtoupper($period_label))];
$sheetSummary[] = [];
$sheetSummary[] = [
    xb('Nama'), xb('NIK'), xb('Total Order'), xb('Total Step'),
    xb('CMM'), xb('RONDCOM'), xb('ROUGHNESS'), xb('CONTOUR'),
    xb('PROFIL PROJECTOR'), xb('MANUAL'), xb('HARDNESS CHECK'),
];
foreach ($summary_data as $s) {
    $sheetSummary[] = [
        $s['nama'], $s['nik'], (int)$s['total_order'], (int)$s['total_step'],
        (int)$s['cmm_count'], (int)$s['rondcom_count'], (int)$s['roughness_count'], (int)$s['contour_count'],
        (int)$s['profil_count'], (int)$s['manual_count'], (int)$s['hardness_count'],
    ];
}

$filename = 'Laporan_QC_' . $month_names[$sel_month] . '_' . $sel_year . '.xlsx';

xlsxKirim($filename, [
    'Rekap Step Harian' => $sheetStep,
    'Operation Ratio'   => $sheetRatio,
    'Summary Bulanan'   => $sheetSummary,
]);

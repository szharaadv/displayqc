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

function evalStatus(float $r): string {
    if ($r >= 80) return 'Produktif';
    if ($r >= 50) return 'Normal';
    return 'Perlu Perhatian';
}

// ── Step count per shift per staff: [shift_date|shift_nama][uid] = jumlah ────
$stepQuery = mysqli_query($conn, "
    SELECT u.id AS uid, sps.start_time
    FROM sampling_process_steps sps
    JOIN users u ON sps.qc_user_id = u.id
    WHERE u.role = 'qc'
      AND DATE(sps.start_time) BETWEEN '$date_from' AND '$date_to'
      AND sps.status = 'done'
      $whereNik
");
$stepByShift = []; // [key][uid] = jumlah
while ($r = mysqli_fetch_assoc($stepQuery)) {
    $shift = qcShift($r['start_time']);
    $key   = $shift['shift_date'] . '|' . $shift['nama'];
    $uid   = $r['uid'];
    $stepByShift[$key][$uid] = ($stepByShift[$key][$uid] ?? 0) + 1;
}

// ── Waktu aktif & ratio per shift per staff: [shift_date|shift_nama][uid] ────
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
$ratioByShift = []; // [key][uid] = ['tgl'=>, 'shift'=>, 'total_detik'=>, 'work_sec'=>]
while ($r = mysqli_fetch_assoc($ratioQuery)) {
    $shift = qcShift($r['start_time']);
    $key   = $shift['shift_date'] . '|' . $shift['nama'];
    $uid   = $r['uid'];
    if (!isset($ratioByShift[$key][$uid])) {
        $ratioByShift[$key][$uid] = [
            'tgl'         => $shift['shift_date'],
            'shift'       => $shift['nama'],
            'total_detik' => 0,
            'work_sec'    => $shift['detik'],
        ];
    }
    $ratioByShift[$key][$uid]['total_detik'] += (int)$r['durasi'];
}
ksort($ratioByShift);

// ── Rata-rata ratio per staff (dari semua shift-nya bulan ini) ───────────────
$ratioSumByUid   = [];
$ratioCountByUid = [];
foreach ($ratioByShift as $perUid) {
    foreach ($perUid as $uid => $d) {
        $ratio = min(100, round(($d['total_detik'] / $d['work_sec']) * 100, 1));
        $ratioSumByUid[$uid]   = ($ratioSumByUid[$uid]   ?? 0) + $ratio;
        $ratioCountByUid[$uid] = ($ratioCountByUid[$uid] ?? 0) + 1;
    }
}

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

// ── Sheet 1: Ringkasan per Staff (1 baris/staff, buat evaluasi cepat) ────────
$sheetRingkasan = [];
$sheetRingkasan[] = [xb('LAPORAN EVALUASI QC BULANAN — ' . strtoupper($period_label))];
$sheetRingkasan[] = ['Diekspor: ' . date('d M Y H:i')];
$sheetRingkasan[] = [];
$sheetRingkasan[] = [
    xb('No'), xb('Nama'), xb('NIK'), xb('Total Order'), xb('Total Step'),
    xb('Rata-rata Ratio (%)'), xb('Status Evaluasi'),
    xb('CMM'), xb('RONDCOM'), xb('ROUGHNESS'), xb('CONTOUR'),
    xb('PROFIL PROJECTOR'), xb('MANUAL'), xb('HARDNESS CHECK'),
];

$no = 1;
foreach ($summary_data as $s) {
    $uid       = $s['id'];
    $avg_ratio = isset($ratioSumByUid[$uid]) && $ratioCountByUid[$uid] > 0
        ? round($ratioSumByUid[$uid] / $ratioCountByUid[$uid], 1)
        : 0;

    $sheetRingkasan[] = [
        $no++, $s['nama'], $s['nik'], (int)$s['total_order'], (int)$s['total_step'],
        $avg_ratio, evalStatus($avg_ratio),
        (int)$s['cmm_count'], (int)$s['rondcom_count'], (int)$s['roughness_count'], (int)$s['contour_count'],
        (int)$s['profil_count'], (int)$s['manual_count'], (int)$s['hardness_count'],
    ];
}

// ── Sheet 2: Detail Harian per Staff (list vertikal, urut nama lalu tanggal) ──
$sheetDetail = [];
$sheetDetail[] = [xb('DETAIL HARIAN QC — ' . strtoupper($period_label))];
$sheetDetail[] = [];
$sheetDetail[] = [
    xb('Nama'), xb('NIK'), xb('Tanggal'), xb('Shift'),
    xb('Total Step'), xb('Waktu Aktif (jam)'), xb('Operation Ratio (%)'), xb('Status'),
];

$detailRows = [];
foreach ($ratioByShift as $key => $perUid) {
    foreach ($perUid as $uid => $d) {
        $ratio  = min(100, round(($d['total_detik'] / $d['work_sec']) * 100, 1));
        $jamAktif = round($d['total_detik'] / 3600, 2);
        $detailRows[] = [
            'uid'    => $uid,
            'tgl'    => $d['tgl'],
            'shift'  => $d['shift'],
            'step'   => $stepByShift[$key][$uid] ?? 0,
            'jam'    => $jamAktif,
            'ratio'  => $ratio,
        ];
    }
}

$namaByUid = [];
foreach ($summary_data as $s) $namaByUid[$s['id']] = ['nama' => $s['nama'], 'nik' => $s['nik']];

usort($detailRows, function ($a, $b) use ($namaByUid) {
    $namaA = $namaByUid[$a['uid']]['nama'] ?? '';
    $namaB = $namaByUid[$b['uid']]['nama'] ?? '';
    return $namaA <=> $namaB ?: $a['tgl'] <=> $b['tgl'];
});

foreach ($detailRows as $d) {
    $info = $namaByUid[$d['uid']] ?? ['nama' => '-', 'nik' => '-'];
    $sheetDetail[] = [
        $info['nama'], $info['nik'], $d['tgl'], $d['shift'],
        $d['step'], $d['jam'], $d['ratio'], evalStatus($d['ratio']),
    ];
}

$filename = 'Laporan_Evaluasi_QC_' . $month_names[$sel_month] . '_' . $sel_year . '.xlsx';

xlsxKirim($filename, [
    'Ringkasan per Staff' => $sheetRingkasan,
    'Detail Harian'       => $sheetDetail,
]);

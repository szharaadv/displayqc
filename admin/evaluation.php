<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$sel_month = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : (int)date('m');
$sel_year  = isset($_GET['year'])  && $_GET['year']  !== '' ? (int)$_GET['year']  : (int)date('Y');
$sel_nik   = isset($_GET['nik'])   ? $_GET['nik'] : 'all';

$month_pad  = str_pad($sel_month, 2, '0', STR_PAD_LEFT);
$date_from  = "{$sel_year}-{$month_pad}-01";
$date_to    = date('Y-m-t', strtotime($date_from));

$staffList = [];
$staffQuery = mysqli_query($conn, "SELECT id, nik, nama FROM users WHERE role = 'qc' ORDER BY nama ASC");
while ($s = mysqli_fetch_assoc($staffQuery)) $staffList[] = $s;

$whereNik = '';
if ($sel_nik !== 'all') {
    $nik_esc  = mysqli_real_escape_string($conn, $sel_nik);
    $whereNik = " AND u.nik = '$nik_esc' ";
}

// ── Summary per staff ────────────────────────────────────────────────────────
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

// ── Operation Ratio ──────────────────────────────────────────────────────────
function getShift(string $start_time): array {
    $h    = (int)date('H', strtotime($start_time));
    $m    = (int)date('i', strtotime($start_time));
    $tot  = $h * 60 + $m;
    $hari = (int)date('N', strtotime($start_time));

    if ($hari === 5) {
        if ($tot >= 390 && $tot < 885)       return ['nama' => 'Shift 1', 'detik' => 29700];
        elseif ($tot >= 885 && $tot < 1365)  return ['nama' => 'Shift 2', 'detik' => 28800];
        else                                  return ['nama' => 'Shift 3', 'detik' => 27900];
    } elseif ($hari === 6) {
        if ($tot >= 390 && $tot < 855)       return ['nama' => 'Shift 1', 'detik' => 27900];
        elseif ($tot >= 855 && $tot < 1305)  return ['nama' => 'Shift 2', 'detik' => 27000];
        else                                  return ['nama' => 'Shift 3', 'detik' => 27000];
    } else {
        if ($tot >= 390 && $tot < 915)       return ['nama' => 'Shift 1', 'detik' => 28800];
        elseif ($tot >= 915 && $tot < 1380)  return ['nama' => 'Shift 2', 'detik' => 27000];
        else                                  return ['nama' => 'Shift 3', 'detik' => 24300];
    }
}

$ratio_data = [];
if ($sel_nik !== 'all') {
    $nik_esc2 = mysqli_real_escape_string($conn, $sel_nik);
    $ratioQ   = mysqli_query($conn, "
        SELECT
            sps.start_time,
            TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) AS durasi
        FROM sampling_process_steps sps
        JOIN users u ON sps.qc_user_id = u.id
        WHERE u.nik = '$nik_esc2'
          AND DATE(sps.start_time) BETWEEN '$date_from' AND '$date_to'
          AND sps.status IN ('done', 'paused')
          AND sps.end_time IS NOT NULL
        ORDER BY sps.start_time ASC
    ");

    $grouped = [];
    while ($r = mysqli_fetch_assoc($ratioQ)) {
        $tgl   = date('Y-m-d', strtotime($r['start_time']));
        $shift = getShift($r['start_time']);
        $key   = $tgl . '|' . $shift['nama'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'tgl'         => $tgl,
                'shift_nama'  => $shift['nama'],
                'total_detik' => 0,
                'work_sec'    => $shift['detik'],
            ];
        }
        $grouped[$key]['total_detik'] += (int)$r['durasi'];
    }

    foreach ($grouped as $g) {
        $ratio_data[] = [
            'tgl'         => $g['tgl'],
            'shift_nama'  => $g['shift_nama'],
            'total_detik' => $g['total_detik'],
            'ratio'       => min(100, round(($g['total_detik'] / $g['work_sec']) * 100, 1)),
        ];
    }

} else {
    $ratioAllQ = mysqli_query($conn, "
        SELECT
            u.id, u.nama, u.nik,
            sps.start_time,
            TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) AS durasi
        FROM sampling_process_steps sps
        JOIN users u ON sps.qc_user_id = u.id
        WHERE DATE(sps.start_time) BETWEEN '$date_from' AND '$date_to'
          AND sps.status IN ('done', 'paused')
          AND sps.end_time IS NOT NULL
          $whereNik
        ORDER BY u.nama ASC, sps.start_time ASC
    ");

    $ratio_by_staff = [];
    while ($r = mysqli_fetch_assoc($ratioAllQ)) {
        $uid   = $r['id'];
        $tgl   = date('Y-m-d', strtotime($r['start_time']));
        $shift = getShift($r['start_time']);
        $key   = $tgl . '|' . $shift['nama'];

        if (!isset($ratio_by_staff[$uid]['days'][$key])) {
        $ratio_by_staff[$uid]['days'][$key] = [
            'tgl'         => $tgl,
            'shift_nama'  => $shift['nama'],
            'total_detik' => 0,
            'work_sec'    => $shift['detik'],
        ];
    }
        $ratio_by_staff[$uid]['days'][$key]['total_detik'] += (int)$r['durasi'];
    }

    foreach ($ratio_by_staff as &$s) {
        $days_arr = [];
        foreach ($s['days'] as $d) {
            $days_arr[] = [
                'tgl'         => $d['tgl'],
                'shift_nama'  => $d['shift_nama'],
                'total_detik' => $d['total_detik'],
                'ratio'       => min(100, round(($d['total_detik'] / $d['work_sec']) * 100, 1)),
            ];
        }
        $s['days']      = array_values($days_arr);
        $s['avg_ratio'] = count($days_arr) > 0
            ? round(array_sum(array_column($days_arr, 'ratio')) / count($days_arr), 1)
            : 0;
    }
    unset($s);
    $ratio_data = array_values($ratio_by_staff);
}
// ─────────────────────────────────────────────────────────────────────────────

// ── Cycle Time per Order ─────────────────────────────────────────────────────
$cycleQuery = mysqli_query($conn, "
    SELECT
        so.order_code,
        so.id AS order_id,
        mp.part_name,
        mp.part_no,
        u.nama AS qc_nama,
        u.nik  AS qc_nik,
        DATE(MIN(sps.start_time)) AS tgl,
        MIN(sps.start_time) AS mulai,
        MAX(sps.end_time)   AS selesai,
        TIMESTAMPDIFF(SECOND, MIN(sps.start_time), MAX(sps.end_time)) AS cycle_time_detik,
        SUM(TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time))      AS aktif_detik
    FROM sampling_orders so
    JOIN sampling_process_steps sps ON sps.order_id = so.id
    JOIN master_parts mp ON so.part_id = mp.id
    JOIN users u ON sps.qc_user_id = u.id
    WHERE so.status = 'done'
      AND DATE(sps.start_time) BETWEEN '$date_from' AND '$date_to'
      AND sps.status IN ('done', 'paused')
      AND sps.end_time IS NOT NULL
      $whereNik
    GROUP BY so.id, so.order_code, mp.part_name, mp.part_no, u.nama, u.nik
    ORDER BY mulai DESC
");
$cycle_rows = [];
while ($row = mysqli_fetch_assoc($cycleQuery)) $cycle_rows[] = $row;

// ── Detail Step List ─────────────────────────────────────────────────────────
$detailQuery = mysqli_query($conn, "
    SELECT
        sps.id,
        DATE(sps.start_time) AS tgl,
        u.nama AS qc_nama,
        u.nik  AS qc_nik,
        so.order_code,
        mp.part_name,
        mp.part_no,
        sps.qc_machine,
        sps.start_time,
        sps.end_time,
        TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) AS durasi_detik
    FROM sampling_process_steps sps
    JOIN users u ON sps.qc_user_id = u.id
    JOIN sampling_orders so ON sps.order_id = so.id
    JOIN master_parts mp ON so.part_id = mp.id
    WHERE DATE(sps.start_time) BETWEEN '$date_from' AND '$date_to'
      AND sps.status = 'done'
      AND sps.end_time IS NOT NULL
      $whereNik
    ORDER BY sps.start_time DESC
");
$detail_rows = [];
while ($row = mysqli_fetch_assoc($detailQuery)) $detail_rows[] = $row;

$total_step_all  = array_sum(array_column($summary_data, 'total_step'));
$total_order_all = array_sum(array_column($summary_data, 'total_order'));
$active_staff    = count(array_filter($summary_data, fn($s) => $s['total_step'] > 0));
$top_staff       = !empty($summary_data) ? $summary_data[0]['nama'] : '-';

$month_names = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$period_label = $month_names[$sel_month] . ' ' . $sel_year;

function ratioClass(float $r): string {
    if ($r >= 80) return 'high';
    if ($r >= 50) return 'mid';
    return 'low';
}
function ratioLabel(float $r): string {
    if ($r >= 80) return '🟢 Produktif';
    if ($r >= 50) return '🟡 Normal';
    return '🔴 Perhatian';
}

function fmtTime(int $sec): string {
    $h = floor($sec / 3600);
    $m = floor(($sec % 3600) / 60);
    $s = $sec % 60;
    if ($h > 0) return "{$h}j {$m}m {$s}s";
    if ($m > 0) return "{$m}m {$s}s";
    return "{$s}s";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation — QC Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --red:       #CC0000;
            --red-dark:  #A30000;
            --red-soft:  #fff0f0;
            --red-mid:   #ffd5d5;
            --bg:        #f4f5f7;
            --surface:   #ffffff;
            --surface2:  #f9fafb;
            --border:    rgba(0,0,0,0.07);
            --text:      #111827;
            --text2:     #6b7280;
            --text3:     #9ca3af;
            --green:     #059669;
            --green-soft:#d1fae5;
            --blue:      #1d4ed8;
            --blue-soft: #dbeafe;
            --sidebar-w: 220px;
            --radius:    12px;
            --radius-sm: 8px;
            --shadow:    0 1px 4px rgba(0,0,0,0.07);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.09);
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

        .sidebar { width: var(--sidebar-w); background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 200; }
        .sidebar-logo { padding: 24px 20px 20px; border-bottom: 1px solid var(--border); }
        .sidebar-logo-badge { display: inline-flex; align-items: center; gap: 8px; }
        .logo-icon { width: 32px; height: 32px; background: var(--red); border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .logo-icon svg { width: 18px; height: 18px; fill: #fff; }
        .logo-text { display: flex; flex-direction: column; }
        .logo-name { font-size: 13px; font-weight: 700; color: var(--text); letter-spacing: 0.02em; }
        .logo-sub  { font-size: 10px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.08em; }
        .sidebar-nav { padding: 16px 12px; flex: 1; }
        .nav-label { font-size: 10px; font-weight: 600; color: var(--text3); text-transform: uppercase; letter-spacing: 0.08em; padding: 0 8px; margin-bottom: 8px; margin-top: 16px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; color: var(--text2); text-decoration: none; transition: background 0.12s, color 0.12s; cursor: pointer; margin-bottom: 2px; }
        .nav-item:hover { background: var(--surface2); color: var(--text); }
        .nav-item.active { background: var(--red-soft); color: var(--red); }
        .nav-item svg { width: 16px; height: 16px; flex-shrink: 0; }
        .nav-item.active svg { stroke: var(--red); }
        .sidebar-footer { padding: 16px 12px; border-top: 1px solid var(--border); }
        .user-card { display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: var(--radius-sm); background: var(--surface2); }
        .user-avatar { width: 32px; height: 32px; background: var(--red); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0; }
        .user-info { flex: 1; min-width: 0; }
        .user-name { font-size: 12px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 10px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.06em; }
        .btn-logout-sm { font-size: 10px; padding: 4px 8px; border-radius: 6px; border: 1px solid var(--red-mid); background: var(--red-soft); color: var(--red); text-decoration: none; font-weight: 600; white-space: nowrap; }
        .btn-logout-sm:hover { background: var(--red-mid); }

        .main { margin-left: var(--sidebar-w); flex: 1; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 28px; height: 56px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .topbar-title { font-size: 15px; font-weight: 700; color: var(--text); }
        .topbar-date  { font-size: 12px; color: var(--text3); font-family: 'JetBrains Mono', monospace; }
        .content { padding: 24px 28px; height: calc(100vh - 56px); overflow-y: auto; }

        .filter-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); padding: 18px 22px; margin-bottom: 22px; display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; box-shadow: var(--shadow); }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-label { font-size: 10px; font-weight: 600; color: var(--text3); text-transform: uppercase; letter-spacing: 0.07em; }
        .filter-input { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; padding: 7px 11px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface2); color: var(--text); outline: none; min-width: 140px; }
        .filter-input:focus { border-color: var(--red); }
        .btn-filter { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; font-weight: 600; padding: 8px 20px; border-radius: var(--radius-sm); border: none; background: var(--red); color: #fff; cursor: pointer; }
        .btn-filter:hover { background: var(--red-dark); }

        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px; }
        .summary-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); padding: 20px; position: relative; overflow: hidden; box-shadow: var(--shadow); }
        .summary-card.accent { background: var(--red); border-color: var(--red); }
        .summary-card-bar { position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--red); }
        .summary-card.accent .summary-card-bar { background: rgba(255,255,255,0.3); }
        .summary-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
        .summary-icon svg { width: 18px; height: 18px; }
        .icon-white { background: rgba(255,255,255,0.2); } .icon-white svg { stroke: #fff; }
        .icon-green { background: var(--green-soft); }     .icon-green svg { stroke: var(--green); }
        .icon-blue  { background: var(--blue-soft); }      .icon-blue  svg { stroke: var(--blue); }
        .icon-gray  { background: var(--surface2); }       .icon-gray  svg { stroke: var(--text2); }
        .summary-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text3); margin-bottom: 4px; }
        .summary-card.accent .summary-label { color: rgba(255,255,255,0.75); }
        .summary-value { font-size: 28px; font-weight: 700; color: var(--text); line-height: 1; }
        .summary-card.accent .summary-value { color: #fff; }
        .summary-sub { font-size: 11px; color: var(--text3); margin-top: 6px; }
        .summary-card.accent .summary-sub { color: rgba(255,255,255,0.6); }

        .section-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .section-head-line { width: 3px; height: 16px; background: var(--red); border-radius: 2px; }
        .section-head-title { font-size: 13px; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: 0.05em; }

        .ratio-section { margin-bottom: 28px; }
        .ratio-bar-wrap { display: flex; align-items: center; gap: 8px; }
        .ratio-bar-bg { flex: 1; height: 6px; background: var(--surface2); border-radius: 99px; overflow: hidden; }
        .ratio-bar-fill { height: 100%; border-radius: 99px; transition: width 0.4s; }
        .ratio-high { background: var(--green); } .ratio-mid { background: #f59e0b; } .ratio-low { background: var(--red); }
        .ratio-val { font-size: 11px; font-weight: 700; font-family: 'JetBrains Mono', monospace; min-width: 38px; text-align: right; }
        .ratio-val.high { color: var(--green); } .ratio-val.mid { color: #f59e0b; } .ratio-val.low { color: var(--red); }
        .ratio-badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
        .ratio-badge.high { background: var(--green-soft); color: var(--green); }
        .ratio-badge.mid  { background: #fef3c7; color: #b45309; }
        .ratio-badge.low  { background: var(--red-soft); color: var(--red); }
        .ratio-staff-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); padding: 16px; box-shadow: var(--shadow); }
        .ratio-staff-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 10px; }
        .ratio-staff-name { font-size: 13px; font-weight: 700; color: var(--text); }
        .ratio-staff-nik  { font-size: 10px; color: var(--text3); font-family: 'JetBrains Mono', monospace; }
        .ratio-day-row { display: flex; align-items: center; gap: 8px; padding: 5px 0; border-bottom: 1px solid var(--border); }
        .ratio-day-row:last-child { border-bottom: none; }
        .ratio-day-label { font-size: 10px; color: var(--text3); font-family: 'JetBrains Mono', monospace; min-width: 80px; }
        .ratio-grid-all { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }

        .table-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow); margin-bottom: 22px; }
        .table-head-bar { display: flex; align-items: center; gap: 10px; padding: 16px 20px; border-bottom: 1px solid var(--border); justify-content: space-between; }
        .dash-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .dash-table th { padding: 11px 14px; background: var(--surface2); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text3); text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .dash-table td { padding: 11px 14px; border-bottom: 1px solid var(--border); color: var(--text); }
        .dash-table tr:last-child td { border-bottom: none; }
        .dash-table tr:hover td { background: #fafafa; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
        .pill-green { background: var(--green-soft); color: var(--green); }
        .pill-red   { background: var(--red-soft);   color: var(--red); }
        .pill-gray  { background: var(--surface2);   color: var(--text3); }
        .pill-blue  { background: var(--blue-soft);  color: var(--blue); }

        .detail-section { margin-bottom: 28px; }
        .detail-empty { padding: 32px; text-align: center; color: var(--text3); font-size: 13px; }
        .mesin-badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; background: var(--blue-soft); color: var(--blue); }
        .mesin-badge.cmm      { background: #ede9fe; color: #7c3aed; }
        .mesin-badge.rondcom  { background: #fef3c7; color: #b45309; }
        .mesin-badge.roughness{ background: var(--green-soft); color: var(--green); }
        .mesin-badge.contour  { background: #fce7f3; color: #be185d; }
        .mesin-badge.profil   { background: var(--blue-soft); color: var(--blue); }
        .mesin-badge.manual   { background: var(--surface2); color: var(--text2); }
        .mesin-badge.hardness { background: #ffedd5; color: #c2410c; }

        .cycle-efficiency-bar { height: 4px; border-radius: 99px; background: var(--surface2); overflow: hidden; margin-top: 4px; }
        .cycle-efficiency-fill { height: 100%; border-radius: 99px; background: var(--green); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-badge">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            </div>
            <div class="logo-text">
                <span class="logo-name">QC Display</span>
                <span class="logo-sub">Yanmar · Manager</span>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Menu</div>
        <a class="nav-item" href="dashboard.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a class="nav-item active" href="evaluation.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Evaluation
        </a>
        <a class="nav-item" href="../qc/history.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
            History QC
        </a>
        <a class="nav-item" href="../menu.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            Main Menu
        </a>
        <a class="nav-item" href="cycle_time.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
            Cycle Time
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?php echo isset($_SESSION['nama']) ? strtoupper(substr($_SESSION['nama'], 0, 2)) : 'AD'; ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Admin'; ?></div>
                <div class="user-role">Manager</div>
            </div>
            <a href="../auth/logout.php" class="btn-logout-sm">Logout</a>
        </div>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-title">Evaluation — <?php echo $period_label; ?></div>
        <div class="topbar-date"><?php echo date('D, d M Y'); ?></div>
    </div>

    <div class="content">

        <!-- Filter -->
        <form method="GET" class="filter-card">
            <div class="filter-group">
                <label class="filter-label">Bulan</label>
                <select name="month" class="filter-input">
                    <?php foreach ($month_names as $num => $name):
                        if ($num === 0) continue; ?>
                    <option value="<?php echo $num; ?>" <?php echo $sel_month === $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Tahun</label>
                <select name="year" class="filter-input">
                    <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $sel_year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Staff QC</label>
                <select name="nik" class="filter-input">
                    <option value="all" <?php echo $sel_nik === 'all' ? 'selected' : ''; ?>>Semua Staff</option>
                    <?php foreach ($staffList as $s): ?>
                    <option value="<?php echo $s['nik']; ?>" <?php echo $sel_nik === $s['nik'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['nama']); ?> (<?php echo $s['nik']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-filter">Tampilkan</button>
        </form>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card accent">
                <div class="summary-card-bar"></div>
                <div class="summary-icon icon-white"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
                <div class="summary-label">Total Step</div>
                <div class="summary-value"><?php echo $total_step_all; ?></div>
                <div class="summary-sub"><?php echo $period_label; ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-bar"></div>
                <div class="summary-icon icon-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></div>
                <div class="summary-label">Total Order</div>
                <div class="summary-value"><?php echo $total_order_all; ?></div>
                <div class="summary-sub">Selesai</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-bar"></div>
                <div class="summary-icon icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
                <div class="summary-label">Staff Aktif</div>
                <div class="summary-value"><?php echo $active_staff; ?></div>
                <div class="summary-sub">dari <?php echo count($summary_data); ?> staff</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-bar"></div>
                <div class="summary-icon icon-gray"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></div>
                <div class="summary-label">Top Performer</div>
                <div class="summary-value" style="font-size:16px;padding-top:6px;line-height:1.3;"><?php echo htmlspecialchars($top_staff); ?></div>
                <div class="summary-sub">Step terbanyak</div>
            </div>
        </div>

        <!-- Operation Ratio -->
        <div class="ratio-section">
            <div class="section-head">
                <div class="section-head-line"></div>
                <div class="section-head-title">Operation Ratio</div>
                <span style="font-size:11px;color:var(--text3);margin-left:8px;">Basis 8 jam kerja/hari &nbsp;|&nbsp;
                    <span style="color:var(--green);font-weight:700;">≥80% Produktif</span> &nbsp;
                    <span style="color:#f59e0b;font-weight:700;">50–79% Normal</span> &nbsp;
                    <span style="color:var(--red);font-weight:700;">&lt;50% Perlu Perhatian</span>
                </span>
            </div>

            <?php if ($sel_nik !== 'all'): ?>
                <?php if (empty($ratio_data)): ?>
                    <p style="color:var(--text3);font-size:13px;margin-bottom:22px;">Belum ada data operation ratio pada periode ini.</p>
                <?php else: ?>
                <div class="table-card">
                    <div class="table-head-bar">
                        <div class="section-head-line"></div>
                        <div class="section-head-title" style="margin:0;"><?php echo htmlspecialchars($summary_data[0]['nama'] ?? ''); ?> — Ratio per Hari</div>
                    </div>
                    <table class="dash-table">
                        <thead><tr><th>Tanggal</th><th>Waktu Aktif</th><th style="min-width:200px;">Operation Ratio</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($ratio_data as $rd):
                                $cls = ratioClass($rd['ratio']);
                                $jam = floor($rd['total_detik'] / 3600);
                                $mnt = floor(($rd['total_detik'] % 3600) / 60);
                            ?>
                            <tr>
                                <td class="mono"><?php echo $rd['tgl']; ?></td>
                                <td class="mono"><?php echo "{$jam}j {$mnt}m"; ?></td>
                                <td>
                                    <div class="ratio-bar-wrap">
                                        <div class="ratio-bar-bg"><div class="ratio-bar-fill ratio-<?php echo $cls; ?>" style="width:<?php echo $rd['ratio']; ?>%"></div></div>
                                        <span class="ratio-val <?php echo $cls; ?>"><?php echo $rd['ratio']; ?>%</span>
                                    </div>
                                </td>
                                <td><span class="ratio-badge <?php echo $cls; ?>"><?php echo ratioLabel($rd['ratio']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <?php if (empty($ratio_data)): ?>
                    <p style="color:var(--text3);font-size:13px;margin-bottom:22px;">Belum ada data operation ratio pada periode ini.</p>
                <?php else: ?>
                <div class="ratio-grid-all" style="margin-bottom:22px;">
                    <?php foreach ($ratio_data as $rs):
                        $avg_cls = ratioClass($rs['avg_ratio']);
                    ?>
                    <div class="ratio-staff-card">
                        <div class="ratio-staff-header">
                            <div>
                                <div class="ratio-staff-name"><?php echo htmlspecialchars($rs['nama']); ?></div>
                                <div class="ratio-staff-nik"><?php echo $rs['nik']; ?></div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:20px;font-weight:700;color:var(--<?php echo $avg_cls === 'high' ? 'green' : ($avg_cls === 'mid' ? 'text' : 'red'); ?>);"><?php echo $rs['avg_ratio']; ?>%</div>
                                <div style="font-size:10px;color:var(--text3);">avg ratio</div>
                            </div>
                        </div>
                        <div class="ratio-bar-wrap" style="margin-bottom:12px;">
                            <div class="ratio-bar-bg" style="height:10px;"><div class="ratio-bar-fill ratio-<?php echo $avg_cls; ?>" style="width:<?php echo $rs['avg_ratio']; ?>%"></div></div>
                        </div>
                        <?php
            // Group days by shift
            $days_by_shift = ['Shift 1' => [], 'Shift 2' => [], 'Shift 3' => []];
            foreach ($rs['days'] as $d) {
                $sn = $d['shift_nama'] ?? 'Shift 1';
                $days_by_shift[$sn][] = $d;
            }
            ?>
            <?php foreach ($days_by_shift as $shift_label => $shift_days): ?>
                <?php if (empty($shift_days)) continue; ?>
                <div style="margin: 10px 0 4px;">
                    <span style="font-size:10px;font-weight:700;color:var(--text2);text-transform:uppercase;
                        background:var(--surface2);border:1px solid var(--border);
                        border-radius:5px;padding:2px 8px;letter-spacing:0.05em;">
                        📋 <?php echo $shift_label; ?>
                    </span>
                </div>
                <?php foreach ($shift_days as $d):
                    $dcls = ratioClass($d['ratio']);
                    $djam = floor($d['total_detik'] / 3600);
                    $dmnt = floor(($d['total_detik'] % 3600) / 60);
                ?>
                <div class="ratio-day-row">
                    <span class="ratio-day-label"><?php echo $d['tgl']; ?></span>
                    <div class="ratio-bar-wrap" style="flex:1;">
                        <div class="ratio-bar-bg">
                            <div class="ratio-bar-fill ratio-<?php echo $dcls; ?>" style="width:<?php echo $d['ratio']; ?>%"></div>
                        </div>
                        <span class="ratio-val <?php echo $dcls; ?>"><?php echo $d['ratio']; ?>%</span>
                    </div>
                    <span style="font-size:10px;color:var(--text3);min-width:48px;text-align:right;">
                        <?php echo "{$djam}j{$dmnt}m"; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Summary per Staff -->
        <div class="table-card" style="max-height: 350px; overflow-y: auto;">
            <div class="table-head-bar" style="position: sticky; top: 0; z-index: 10; background: var(--surface);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="section-head-line"></div>
                    <div class="section-head-title" style="margin:0;">Summary per Staff</div>
                </div>
                <span style="font-size:11px;color:var(--text3);"><?php echo $period_label; ?></span>
            </div>
            <table class="dash-table">
                <thead style="position: sticky; top: 57px; z-index: 9;">
                    <tr>
                        <th>Nama</th><th>NIK</th><th>Order</th><th>Step</th>
                        <th>CMM</th><th>RONDCOM</th><th>ROUGHNESS</th><th>CONTOUR</th>
                        <th>PROFIL</th><th>MANUAL</th><th>HARDNESS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary_data as $s): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($s['nama']); ?></strong></td>
                        <td class="mono"><?php echo $s['nik']; ?></td>
                        <td><span class="pill <?php echo $s['total_order'] > 0 ? 'pill-green' : 'pill-gray'; ?>"><?php echo $s['total_order']; ?></span></td>
                        <td><span class="pill <?php echo $s['total_step'] > 0 ? 'pill-red' : 'pill-gray'; ?>"><?php echo $s['total_step']; ?></span></td>
                        <td class="mono"><?php echo $s['cmm_count']; ?></td>
                        <td class="mono"><?php echo $s['rondcom_count']; ?></td>
                        <td class="mono"><?php echo $s['roughness_count']; ?></td>
                        <td class="mono"><?php echo $s['contour_count']; ?></td>
                        <td class="mono"><?php echo $s['profil_count']; ?></td>
                        <td class="mono"><?php echo $s['manual_count']; ?></td>
                        <td class="mono"><?php echo $s['hardness_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Cycle Time per Order -->
        <div class="detail-section">
            <div class="section-head">
                <div class="section-head-line"></div>
                <div class="section-head-title">Cycle Time per Order</div>
                <span style="font-size:11px;color:var(--text3);margin-left:8px;"><?php echo count($cycle_rows); ?> order selesai</span>
            </div>
            <div class="table-card" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($cycle_rows)): ?>
                    <div class="detail-empty">Belum ada data cycle time pada periode ini.</div>
                <?php else: ?>
                <table class="dash-table">
                    <thead style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Staff</th>
                            <th>Order Code</th>
                            <th>Part Name</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th>Cycle Time</th>
                            <th>Waktu Aktif</th>
                            <th>Efisiensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cycle_rows as $i => $cr):
                            $cycle = (int)$cr['cycle_time_detik'];
                            $aktif = (int)$cr['aktif_detik'];
                            $efisiensi = $cycle > 0 ? min(100, round(($aktif / $cycle) * 100)) : 0;
                            $ef_color = $efisiensi >= 80 ? 'var(--green)' : ($efisiensi >= 50 ? '#f59e0b' : 'var(--red)');
                        ?>
                        <tr>
                            <td class="mono" style="color:var(--text3);"><?php echo $i + 1; ?></td>
                            <td class="mono"><?php echo $cr['tgl']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($cr['qc_nama']); ?></strong>
                                <div style="font-size:10px;color:var(--text3);"><?php echo $cr['qc_nik']; ?></div>
                            </td>
                            <td class="mono" style="font-size:11px;"><?php echo htmlspecialchars($cr['order_code']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($cr['part_name']); ?>
                                <div style="font-size:10px;color:var(--text3);"><?php echo htmlspecialchars($cr['part_no']); ?></div>
                            </td>
                            <td class="mono" style="font-size:11px;"><?php echo date('H:i', strtotime($cr['mulai'])); ?></td>
                            <td class="mono" style="font-size:11px;"><?php echo date('H:i', strtotime($cr['selesai'])); ?></td>
                            <td class="mono"><span class="pill pill-blue"><?php echo fmtTime($cycle); ?></span></td>
                            <td class="mono"><?php echo fmtTime($aktif); ?></td>
                            <td>
                                <div style="font-size:11px;font-weight:700;color:<?php echo $ef_color; ?>"><?php echo $efisiensi; ?>%</div>
                                <div class="cycle-efficiency-bar">
                                    <div class="cycle-efficiency-fill" style="width:<?php echo $efisiensi; ?>%;background:<?php echo $ef_color; ?>;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detail Step List -->
        <div class="detail-section">
            <div class="section-head">
                <div class="section-head-line"></div>
                <div class="section-head-title">Detail Step</div>
                <span style="font-size:11px;color:var(--text3);margin-left:8px;"><?php echo count($detail_rows); ?> records</span>
            </div>
            <div class="table-card" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                <?php if (empty($detail_rows)): ?>
                    <div class="detail-empty">Belum ada data step pada periode ini.</div>
                <?php else: ?>
                <table class="dash-table">
                    <thead style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th>#</th><th>Tanggal</th><th>Staff</th><th>Order Code</th>
                            <th>Part Name</th><th>Part No</th><th>Mesin QC</th>
                            <th>Start</th><th>End</th><th>Durasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail_rows as $i => $d):
                            $dur   = (int)$d['durasi_detik'];
                            $dur_m = floor($dur / 60);
                            $dur_s = $dur % 60;
                            $mesin_key = strtolower(str_replace([' ', '/'], '', $d['qc_machine']));
                            if (str_contains($mesin_key, 'cmm'))       $mk = 'cmm';
                            elseif (str_contains($mesin_key, 'rond'))  $mk = 'rondcom';
                            elseif (str_contains($mesin_key, 'rough')) $mk = 'roughness';
                            elseif (str_contains($mesin_key, 'cont'))  $mk = 'contour';
                            elseif (str_contains($mesin_key, 'prof'))  $mk = 'profil';
                            elseif (str_contains($mesin_key, 'man'))   $mk = 'manual';
                            elseif (str_contains($mesin_key, 'hard'))  $mk = 'hardness';
                            else $mk = 'manual';
                        ?>
                        <tr>
                            <td class="mono" style="color:var(--text3);"><?php echo $i + 1; ?></td>
                            <td class="mono"><?php echo $d['tgl']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($d['qc_nama']); ?></strong>
                                <div style="font-size:10px;color:var(--text3);"><?php echo $d['qc_nik']; ?></div>
                            </td>
                            <td class="mono" style="font-size:11px;"><?php echo htmlspecialchars($d['order_code']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($d['part_name']); ?>
                                <div style="font-size:10px;color:var(--text3);"><?php echo htmlspecialchars($d['part_no']); ?></div>
                            </td>
                            <td class="mono" style="font-size:10px;color:var(--text3);"><?php echo htmlspecialchars($d['part_no']); ?></td>
                            <td><span class="mesin-badge <?php echo $mk; ?>"><?php echo htmlspecialchars($d['qc_machine']); ?></span></td>
                            <td class="mono" style="font-size:11px;"><?php echo date('H:i', strtotime($d['start_time'])); ?></td>
                            <td class="mono" style="font-size:11px;"><?php echo date('H:i', strtotime($d['end_time'])); ?></td>
                            <td class="mono"><?php echo "{$dur_m}m {$dur_s}s"; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    let scrollSpeed = 0.3;
    let scrolling   = true;
    let scrollPos   = 0;

    function autoScroll() {
        if (!scrolling) return;
        const content = document.querySelector('.content');
        if (!content) return;
        scrollPos += scrollSpeed;
        if (scrollPos + content.clientHeight >= content.scrollHeight - 5) {
            scrollPos = 0;
        }
        content.scrollTop = scrollPos;
        requestAnimationFrame(autoScroll);
    }

    const contentEl = document.querySelector('.content');
    if (contentEl) {
        scrollPos = contentEl.scrollTop;
        contentEl.addEventListener('mouseenter', () => scrolling = false);
        contentEl.addEventListener('mouseleave', () => {
            scrolling = true;
            autoScroll();
        });
        autoScroll();
    }
</script>
</body>
</html>
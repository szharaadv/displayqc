<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
/** @var mysqli $conn */

mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login_manager.php");
    exit;
}

// ── Deteksi shift date ────────────────────────────────────────────────────────
$now_h   = (int)date('H');
$now_m   = (int)date('i');
$now_tot = $now_h * 60 + $now_m;

if ($now_tot < 390) {
    $date_from = date('Y-m-d', strtotime('-1 day'));
    $date_to   = date('Y-m-d', strtotime('-1 day'));
} else {
    $date_from = date('Y-m-d');
    $date_to   = date('Y-m-d');
}
// ─────────────────────────────────────────────────────────────────────────────

$selected_nik = isset($_GET['nik']) ? $_GET['nik'] : 'all';

$staffList = [];
$staffQuery = mysqli_query($conn, "SELECT id, nik, nama FROM users WHERE role = 'qc' AND status = 1 ORDER BY nama ASC");
while ($s = mysqli_fetch_assoc($staffQuery)) $staffList[] = $s;

$whereNik = "";
if ($selected_nik !== 'all') {
    $nik_esc  = mysqli_real_escape_string($conn, $selected_nik);
    $whereNik = " AND u.nik = '$nik_esc' ";
}

$query = mysqli_query($conn, "
    SELECT
        u.id, u.nama, u.nik,
        COUNT(DISTINCT sps.order_id) AS total_order,
        COUNT(sps.id) AS total_step,
        AVG(TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time)) AS avg_duration,
        SUM(CASE WHEN sps.qc_machine = 'CMM'              AND sps.status = 'done' THEN 1 ELSE 0 END) AS cmm_count,
        SUM(CASE WHEN sps.qc_machine = 'RONDCOM'          AND sps.status = 'done' THEN 1 ELSE 0 END) AS rondcom_count,
        SUM(CASE WHEN sps.qc_machine = 'ROUGHNESS'        AND sps.status = 'done' THEN 1 ELSE 0 END) AS roughness_count,
        SUM(CASE WHEN sps.qc_machine = 'CONTOUR'          AND sps.status = 'done' THEN 1 ELSE 0 END) AS contour_count,
        SUM(CASE WHEN sps.qc_machine = 'PROFIL PROJECTOR' AND sps.status = 'done' THEN 1 ELSE 0 END) AS profil_count,
        SUM(CASE WHEN sps.qc_machine = 'MANUAL'           AND sps.status = 'done' THEN 1 ELSE 0 END) AS manual_count,
        SUM(CASE WHEN sps.qc_machine = 'HARDNESS CHECK'   AND sps.status = 'done' THEN 1 ELSE 0 END) AS hardness_count
    FROM users u
    LEFT JOIN sampling_process_steps sps ON sps.qc_user_id = u.id
        AND DATE(sps.start_time) BETWEEN '$date_from' AND '$date_to'
        AND sps.status = 'done'
    WHERE u.role = 'qc' AND u.status = 1
    GROUP BY u.id, u.nama, u.nik
    ORDER BY total_step DESC
");
$staff_data = [];
while ($row = mysqli_fetch_assoc($query)) $staff_data[] = $row;

$daily_data = [];
if ($selected_nik !== 'all') {
    $nik_esc2   = mysqli_real_escape_string($conn, $selected_nik);
    $dailyQuery = mysqli_query($conn, "
        SELECT DATE(sps.start_time) AS tgl,
               COUNT(DISTINCT sps.order_id) AS total_order,
               COUNT(sps.id) AS total_step
        FROM sampling_process_steps sps
        JOIN users u ON sps.qc_user_id = u.id
        WHERE u.nik = '$nik_esc2'
          AND DATE(sps.start_time) BETWEEN '$date_from' AND '$date_to'
          AND sps.status = 'done'
        GROUP BY DATE(sps.start_time)
        ORDER BY tgl ASC
    ");
    while ($d = mysqli_fetch_assoc($dailyQuery)) $daily_data[] = $d;
}

// ── Shift Definition ─────────────────────────────────────────────────────────
function getShift(string $start_time): array {
    $h   = (int)date('H', strtotime($start_time));
    $m   = (int)date('i', strtotime($start_time));
    $tot = $h * 60 + $m;

    if ($tot >= 390 && $tot < 915) {
        return ['nama' => 'Shift 1', 'detik' => 28800];
    } elseif ($tot >= 915 && $tot < 1380) {
        return ['nama' => 'Shift 2', 'detik' => 27000];
    } else {
        return ['nama' => 'Shift 3', 'detik' => 24300];
    }
}

// ── Operation Ratio Queries ───────────────────────────────────────────────────
$ratio_daily_data = [];

if ($selected_nik !== 'all') {
    $nik_esc3 = mysqli_real_escape_string($conn, $selected_nik);
    $ratioQ   = mysqli_query($conn, "
        SELECT
            sps.start_time,
            TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) AS durasi
        FROM sampling_process_steps sps
        JOIN users u ON sps.qc_user_id = u.id
        WHERE u.nik = '$nik_esc3'
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
                'work_sec'    => $shift['detik'],
                'total_detik' => 0,
            ];
        }
        $grouped[$key]['total_detik'] += (int)$r['durasi'];
    }

    foreach ($grouped as $g) {
        $ratio = min(100, round(($g['total_detik'] / $g['work_sec']) * 100, 1));
        $ratio_daily_data[] = [
            'tgl'         => $g['tgl'],
            'shift_nama'  => $g['shift_nama'],
            'total_detik' => $g['total_detik'],
            'ratio'       => $ratio,
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

        if (!isset($ratio_by_staff[$uid])) {
            $ratio_by_staff[$uid] = ['nama' => $r['nama'], 'nik' => $r['nik'], 'days' => []];
        }
        if (!isset($ratio_by_staff[$uid]['days'][$key])) {
            $ratio_by_staff[$uid]['days'][$key] = [
                'tgl'         => $tgl,
                'shift_nama'  => $shift['nama'],
                'work_sec'    => $shift['detik'],
                'total_detik' => 0,
            ];
        }
        $ratio_by_staff[$uid]['days'][$key]['total_detik'] += (int)$r['durasi'];
    }

    foreach ($ratio_by_staff as $uid => &$s) {
        $days_arr = [];
        foreach ($s['days'] as $d) {
            $ratio      = min(100, round(($d['total_detik'] / $d['work_sec']) * 100, 1));
            $days_arr[] = array_merge($d, ['ratio' => $ratio]);
        }
        $s['days']      = array_values($days_arr);
        $s['avg_ratio'] = count($days_arr) > 0
            ? round(array_sum(array_column($days_arr, 'ratio')) / count($days_arr), 1)
            : 0;
    }
    unset($s);
    $ratio_daily_data = array_values($ratio_by_staff);
}
// ─────────────────────────────────────────────────────────────────────────────

$total_all_step  = array_sum(array_column($staff_data, 'total_step'));
$total_all_order = array_sum(array_column($staff_data, 'total_order'));
$top_staff       = !empty($staff_data) ? $staff_data[0]['nama'] : '-';
$active_staff    = count(array_filter($staff_data, fn($s) => $s['total_step'] > 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard — QC Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .btn-logout-sm { font-size: 10px; padding: 4px 8px; border-radius: 6px; border: 1px solid var(--red-mid); background: var(--red-soft); color: var(--red); text-decoration: none; font-weight: 600; white-space: nowrap; transition: background 0.12s; }
        .btn-logout-sm:hover { background: var(--red-mid); }
        .main { margin-left: var(--sidebar-w); flex: 1; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 28px; height: 56px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .topbar-title { font-size: 15px; font-weight: 700; color: var(--text); }
        .content { padding: 24px 28px; height: calc(100vh - 56px); overflow-y: auto; overflow-x: hidden; }
        .filter-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); padding: 18px 22px; margin-bottom: 22px; display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; box-shadow: var(--shadow); }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-label { font-size: 10px; font-weight: 600; color: var(--text3); text-transform: uppercase; letter-spacing: 0.07em; }
        .filter-input { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; padding: 7px 11px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface2); color: var(--text); outline: none; transition: border-color 0.15s; min-width: 150px; }
        .filter-input:focus { border-color: var(--red); }
        .btn-filter { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; font-weight: 600; padding: 8px 20px; border-radius: var(--radius-sm); border: none; background: var(--red); color: #fff; cursor: pointer; transition: background 0.15s; }
        .btn-filter:hover { background: var(--red-dark); }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px; }
        .summary-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); padding: 18px 20px; box-shadow: var(--shadow); position: relative; overflow: hidden; transition: transform 0.15s; }
        .summary-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .summary-card.accent { background: var(--red); border-color: var(--red-dark); }
        .summary-card.accent .summary-label { color: rgba(255,255,255,0.7); }
        .summary-card.accent .summary-value { color: #fff; }
        .summary-card.accent .summary-sub   { color: rgba(255,255,255,0.6); }
        .summary-card-bar { position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--red); }
        .summary-card.accent .summary-card-bar { background: rgba(255,255,255,0.3); }
        .summary-icon { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
        .icon-red   { background: var(--red-soft); }
        .icon-green { background: var(--green-soft); }
        .icon-blue  { background: var(--blue-soft); }
        .icon-white { background: rgba(255,255,255,0.2); }
        .summary-icon svg { width: 18px; height: 18px; }
        .summary-label { font-size: 11px; color: var(--text3); font-weight: 500; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em; }
        .summary-value { font-size: 28px; font-weight: 700; color: var(--text); line-height: 1; margin-bottom: 4px; }
        .summary-sub   { font-size: 11px; color: var(--text3); }
        .section-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .section-head-line { width: 3px; height: 16px; background: var(--red); border-radius: 2px; }
        .section-head-title { font-size: 13px; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: 0.05em; }
        .staff-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .staff-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); padding: 16px 18px; box-shadow: var(--shadow); transition: transform 0.15s, box-shadow 0.15s; }
        .staff-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .staff-top { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .staff-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--red); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0; }
        .staff-avatar.zero { background: #e5e7eb; color: var(--text3); }
        .staff-name { font-size: 13px; font-weight: 700; color: var(--text); line-height: 1.2; }
        .staff-nik  { font-size: 10px; color: var(--text3); font-family: 'JetBrains Mono', monospace; }
        .staff-divider { height: 1px; background: var(--border); margin: 10px 0; }
        .staff-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 10px; }
        .stat-box { background: var(--surface2); border-radius: 7px; padding: 7px 10px; text-align: center; }
        .stat-box-val { font-size: 18px; font-weight: 700; color: var(--red); line-height: 1; }
        .stat-box-val.zero { color: var(--text3); }
        .stat-box-label { font-size: 9px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.05em; margin-top: 2px; }
        .staff-mesin-row { display: flex; justify-content: space-between; align-items: center; font-size: 11px; padding: 2px 0; }
        .staff-mesin-label { color: var(--text2); }
        .staff-mesin-val   { font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 500; color: var(--text); }
        .staff-mesin-val.has { color: var(--red); }
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 22px; }
        .chart-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); padding: 20px 22px; box-shadow: var(--shadow); }
        .chart-card.full { grid-column: 1 / -1; }
        .chart-title { font-size: 13px; font-weight: 700; color: var(--text); margin-bottom: 18px; }
        .table-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow); margin-bottom: 28px; }
        .table-head-bar { padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
        .dash-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .dash-table th { padding: 9px 14px; text-align: left; font-size: 10px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: 0.06em; background: var(--surface2); border-bottom: 1px solid var(--border); }
        .dash-table td { padding: 11px 14px; border-bottom: 1px solid var(--border); color: var(--text); }
        .dash-table tr:last-child td { border-bottom: none; }
        .dash-table tr:hover td { background: #fafafa; }
        .mono { font-family: 'JetBrains Mono', monospace; font-size: 11px; }
        .ratio-section { margin-bottom: 28px; }
        .ratio-table-wrap { overflow-x: auto; }
        .ratio-bar-wrap { display: flex; align-items: center; gap: 8px; min-width: 120px; }
        .ratio-bar-bg { flex: 1; height: 8px; background: #f3f4f6; border-radius: 99px; overflow: hidden; min-width: 60px; }
        .ratio-bar-fill { height: 100%; border-radius: 99px; transition: width 0.6s cubic-bezier(.4,0,.2,1); }
        .ratio-high { background: var(--green); } .ratio-mid { background: #f59e0b; } .ratio-low { background: var(--red); }
        .ratio-val { font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600; min-width: 38px; text-align: right; }
        .ratio-val.high { color: var(--green); } .ratio-val.mid { color: #f59e0b; } .ratio-val.low { color: var(--red); }
        .ratio-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; }
        .ratio-badge.high { background: var(--green-soft); color: var(--green); }
        .ratio-badge.mid  { background: #fef3c7; color: #b45309; }
        .ratio-badge.low  { background: var(--red-soft); color: var(--red); }
        .ratio-staff-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); padding: 16px 18px; box-shadow: var(--shadow); }
        .ratio-staff-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .ratio-staff-name { font-size: 13px; font-weight: 700; color: var(--text); }
        .ratio-staff-nik  { font-size: 10px; color: var(--text3); font-family: 'JetBrains Mono', monospace; }
        .ratio-day-row { display: flex; align-items: center; gap: 6px; padding: 5px 0; border-bottom: 1px solid var(--border); font-size: 12px; }
        .ratio-day-row:last-child { border-bottom: none; }
        .ratio-grid-all { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; }
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
        <a class="nav-item active" href="dashboard.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a class="nav-item" href="evaluation.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Evaluation
        </a>
        <a class="nav-item" href="cycle_time.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
            Cycle Time
        </a>
        <a class="nav-item" href="../qc/history.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
            History QC
        </a>
        <a class="nav-item" href="../menu.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            Main Menu
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
        <span class="topbar-title">Operating Ratio Dashboard</span>
        <span class="topbar-date" style="font-size:15px;font-weight:700;color:var(--text);"><?php echo date('l, d F Y', strtotime($date_from)); ?></span>
    </div>

    <div class="content">

        <form method="GET" class="filter-card">
            <div class="filter-group">
                <label class="filter-label">Staff QC</label>
                <select name="nik" class="filter-input">
                    <option value="all" <?php echo $selected_nik === 'all' ? 'selected' : ''; ?>>Semua Staff</option>
                    <?php foreach ($staffList as $s): ?>
                    <option value="<?php echo $s['nik']; ?>" <?php echo $selected_nik === $s['nik'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['nama']); ?> (<?php echo $s['nik']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-filter">Tampilkan</button>
        </form>

        <div class="summary-grid">
            <div class="summary-card accent">
                <div class="summary-card-bar"></div>
                <div class="summary-icon icon-white">
                    <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="2"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>
                </div>
                <div class="summary-label">Total Step</div>
                <div class="summary-value counter" data-target="<?php echo $total_all_step; ?>">0</div>
                <div class="summary-sub">Hari ini</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-bar"></div>
                <div class="summary-icon icon-green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                </div>
                <div class="summary-label">Total Order</div>
                <div class="summary-value counter" data-target="<?php echo $total_all_order; ?>">0</div>
                <div class="summary-sub">Selesai</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-bar"></div>
                <div class="summary-icon icon-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
                <div class="summary-label">Staff Aktif</div>
                <div class="summary-value counter" data-target="<?php echo $active_staff; ?>">0</div>
                <div class="summary-sub">dari <?php echo count($staff_data); ?> staff</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-bar"></div>
                <div class="summary-icon icon-red">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                </div>
                <div class="summary-label">Top Performer</div>
                <div class="summary-value" style="font-size:16px;padding-top:6px;line-height:1.3;"><?php echo htmlspecialchars($top_staff); ?></div>
                <div class="summary-sub">Step terbanyak</div>
            </div>
        </div>

        <div class="ratio-section">
            <div class="section-head">
                <div class="section-head-line"></div>
                <div class="section-head-title">Operation Ratio</div>
                <?php
                if ($now_tot >= 390 && $now_tot < 915) {
                    $shift_aktif = 'Shift 1 (06:30-15:14) | Efektif 8 jam';
                } elseif ($now_tot >= 915 && $now_tot < 1380) {
                    $shift_aktif = 'Shift 2 (15:15-22:59) | Efektif 7,5 jam';
                } else {
                    $shift_aktif = 'Shift 3 (23:00-06:29) | Efektif 6,75 jam';
                }
                ?>
                <span style="font-size:11px;color:var(--text3);margin-left:8px;">
                    Shift Aktif: <strong><?php echo $shift_aktif; ?></strong> &nbsp;|&nbsp;
                    <span style="color:var(--green);font-weight:700;">≥80% Produktif</span> &nbsp;
                    <span style="color:#f59e0b;font-weight:700;">50–79% Normal</span> &nbsp;
                    <span style="color:var(--red);font-weight:700;">&lt;50% Perlu Perhatian</span>
                </span>
            </div>

            <?php
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
            ?>

            <?php if ($selected_nik !== 'all'): ?>
                <?php if (empty($ratio_daily_data)): ?>
                    <p style="color:var(--text3);font-size:13px;">Belum ada data operation ratio pada periode ini.</p>
                <?php else: ?>
                <div class="table-card ratio-table-wrap">
                    <div class="table-head-bar">
                        <div class="section-head-line"></div>
                        <div class="section-head-title" style="margin:0;"><?php echo htmlspecialchars($staff_data[0]['nama'] ?? ''); ?> — Ratio per Shift</div>
                    </div>
                    <table class="dash-table">
                        <thead>
                            <tr><th>Tanggal</th><th>Shift</th><th>Waktu Aktif</th><th style="min-width:200px;">Operation Ratio</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ratio_daily_data as $rd):
                                $cls = ratioClass($rd['ratio']);
                                $jam = floor($rd['total_detik'] / 3600);
                                $mnt = floor(($rd['total_detik'] % 3600) / 60);
                            ?>
                            <tr>
                                <td class="mono"><?php echo $rd['tgl']; ?></td>
                                <td><span style="font-size:11px;font-weight:600;color:var(--text2);"><?php echo $rd['shift_nama']; ?></span></td>
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
                <?php if (empty($ratio_daily_data)): ?>
                    <p style="color:var(--text3);font-size:13px;">Belum ada data operation ratio pada periode ini.</p>
                <?php else: ?>
                <div class="ratio-grid-all">
                    <?php foreach ($ratio_daily_data as $rs):
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
                        <?php foreach ($rs['days'] as $d):
                            $dcls = ratioClass($d['ratio']);
                            $djam = floor($d['total_detik'] / 3600);
                            $dmnt = floor(($d['total_detik'] % 3600) / 60);
                        ?>
                        <div class="ratio-day-row">
                            <span style="font-size:10px;color:var(--text2);font-family:'JetBrains Mono',monospace;min-width:80px;"><?php echo $d['tgl']; ?></span>
                            <span style="font-size:10px;color:var(--text3);min-width:44px;"><?php echo $d['shift_nama']; ?></span>
                            <div class="ratio-bar-bg" style="flex:1;height:8px;"><div class="ratio-bar-fill ratio-<?php echo $dcls; ?>" style="width:<?php echo $d['ratio']; ?>%"></div></div>
                            <span class="ratio-val <?php echo $dcls; ?>" style="min-width:42px;"><?php echo $d['ratio']; ?>%</span>
                            <span style="font-size:10px;color:var(--text3);min-width:44px;text-align:right;"><?php echo "{$djam}j{$dmnt}m"; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="section-head">
            <div class="section-head-line"></div>
            <div class="section-head-title">Detail per Staff</div>
        </div>
        <div class="staff-grid">
            <?php foreach ($staff_data as $staff):
                $initials = strtoupper(substr($staff['nama'], 0, 2));
                $isZero = $staff['total_step'] == 0;
            ?>
            <div class="staff-card">
                <div class="staff-top">
                    <div class="staff-avatar <?php echo $isZero ? 'zero' : ''; ?>"><?php echo $initials; ?></div>
                    <div>
                        <div class="staff-name"><?php echo htmlspecialchars($staff['nama']); ?></div>
                        <div class="staff-nik"><?php echo $staff['nik']; ?></div>
                    </div>
                </div>
                <div class="staff-stats">
                    <div class="stat-box">
                        <div class="stat-box-val <?php echo $staff['total_order'] == 0 ? 'zero' : ''; ?>"><?php echo $staff['total_order']; ?></div>
                        <div class="stat-box-label">Order</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-box-val <?php echo $staff['total_step'] == 0 ? 'zero' : ''; ?>"><?php echo $staff['total_step']; ?></div>
                        <div class="stat-box-label">Step</div>
                    </div>
                </div>
                <div class="staff-divider"></div>
                <?php
                $mesin_list = [
                    'CMM'          => $staff['cmm_count'],
                    'RONDCOM'      => $staff['rondcom_count'],
                    'ROUGHNESS'    => $staff['roughness_count'],
                    'CONTOUR'      => $staff['contour_count'],
                    'PROFIL PROJ.' => $staff['profil_count'],
                    'MANUAL'       => $staff['manual_count'],
                    'HARDNESS'     => $staff['hardness_count'],
                ];
                foreach ($mesin_list as $ml => $mv): ?>
                <div class="staff-mesin-row">
                    <span class="staff-mesin-label"><?php echo $ml; ?></span>
                    <span class="staff-mesin-val <?php echo $mv > 0 ? 'has' : ''; ?>"><?php echo $mv; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($selected_nik === 'all'): ?>
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">Total Step per Staff</div>
                <canvas id="chartStep" height="120"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-title">Total Order per Staff</div>
                <canvas id="chartOrder" height="120"></canvas>
            </div>
        </div>
        <?php else: ?>
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">Step per Mesin — <?php echo htmlspecialchars($staff_data[0]['nama'] ?? ''); ?></div>
                <canvas id="chartMesin" height="120"></canvas>
            </div>
            <div class="chart-card full">
                <div class="chart-title">Aktivitas Harian — <?php echo htmlspecialchars($staff_data[0]['nama'] ?? ''); ?></div>
                <canvas id="chartHarian" height="80"></canvas>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
document.querySelectorAll('.counter').forEach(el => {
    const target = parseInt(el.dataset.target);
    if (target === 0) { el.textContent = '0'; return; }
    let current = 0;
    const step = Math.ceil(target / 40);
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = current;
        if (current >= target) clearInterval(timer);
    }, 20);
});

const chartOpts = {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, color: '#9ca3af', font: { family: 'Plus Jakarta Sans', size: 11 } }, grid: { color: 'rgba(0,0,0,0.04)' } },
        x: { ticks: { color: '#6b7280', font: { family: 'Plus Jakarta Sans', size: 11 } }, grid: { display: false } }
    }
};

<?php if ($selected_nik === 'all'): ?>
const staffNames  = <?php echo json_encode(array_column($staff_data, 'nama')); ?>;
const totalSteps  = <?php echo json_encode(array_map('intval', array_column($staff_data, 'total_step'))); ?>;
const totalOrders = <?php echo json_encode(array_map('intval', array_column($staff_data, 'total_order'))); ?>;
new Chart(document.getElementById('chartStep'), {
    type: 'bar',
    data: { labels: staffNames, datasets: [{ data: totalSteps, backgroundColor: 'rgba(204,0,0,0.75)', borderColor: '#CC0000', borderWidth: 1, borderRadius: 5 }] },
    options: chartOpts
});
new Chart(document.getElementById('chartOrder'), {
    type: 'bar',
    data: { labels: staffNames, datasets: [{ data: totalOrders, backgroundColor: 'rgba(5,150,105,0.7)', borderColor: '#059669', borderWidth: 1, borderRadius: 5 }] },
    options: chartOpts
});
<?php else: ?>
const mesinLabels = ['CMM','RONDCOM','ROUGHNESS','CONTOUR','PROFIL PROJ.','MANUAL','HARDNESS'];
const mesinData   = [
    <?php echo (int)($staff_data[0]['cmm_count']      ?? 0); ?>,
    <?php echo (int)($staff_data[0]['rondcom_count']   ?? 0); ?>,
    <?php echo (int)($staff_data[0]['roughness_count'] ?? 0); ?>,
    <?php echo (int)($staff_data[0]['contour_count']   ?? 0); ?>,
    <?php echo (int)($staff_data[0]['profil_count']    ?? 0); ?>,
    <?php echo (int)($staff_data[0]['manual_count']    ?? 0); ?>,
    <?php echo (int)($staff_data[0]['hardness_count']  ?? 0); ?>
];
new Chart(document.getElementById('chartMesin'), {
    type: 'bar',
    data: { labels: mesinLabels, datasets: [{ data: mesinData, backgroundColor: 'rgba(204,0,0,0.75)', borderColor: '#CC0000', borderWidth: 1, borderRadius: 5 }] },
    options: chartOpts
});
const hariLabels = <?php echo json_encode(array_column($daily_data, 'tgl')); ?>;
const hariSteps  = <?php echo json_encode(array_map('intval', array_column($daily_data, 'total_step'))); ?>;
const hariOrders = <?php echo json_encode(array_map('intval', array_column($daily_data, 'total_order'))); ?>;
new Chart(document.getElementById('chartHarian'), {
    type: 'line',
    data: {
        labels: hariLabels,
        datasets: [
            { label: 'Total Step',  data: hariSteps,  borderColor: '#CC0000', backgroundColor: 'rgba(204,0,0,0.07)', tension: 0.4, fill: true, pointRadius: 4, pointBackgroundColor: '#CC0000' },
            { label: 'Total Order', data: hariOrders, borderColor: '#059669', backgroundColor: 'rgba(5,150,105,0.07)', tension: 0.4, fill: true, pointRadius: 4, pointBackgroundColor: '#059669' }
        ]
    },
    options: { ...chartOpts, plugins: { legend: { display: true, labels: { color: '#6b7280', font: { family: 'Plus Jakarta Sans', size: 12 } } } } }
});
<?php endif; ?>

setTimeout(() => { window.location.reload(); }, 30000);

let scrollSpeed = 0.3;
let scrolling   = true;
let scrollPos   = 0;

function autoScroll() {
    if (!scrolling) return;
    const content = document.querySelector('.content');
    if (!content) return;
    scrollPos += scrollSpeed;
    if (scrollPos + content.clientHeight >= content.scrollHeight - 5) scrollPos = 0;
    content.scrollTop = scrollPos;
    requestAnimationFrame(autoScroll);
}

const contentEl = document.querySelector('.content');
if (contentEl) {
    scrollPos = contentEl.scrollTop;
    contentEl.addEventListener('mouseenter', () => scrolling = false);
    contentEl.addEventListener('mouseleave', () => { scrolling = true; autoScroll(); });
    autoScroll();
}
</script>
</body>
</html>
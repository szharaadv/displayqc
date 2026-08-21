<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/shift.php';
include '../config/ratio_ui.php';
/** @var mysqli $conn */

mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Shift yang sedang berjalan — batasnya dari config/shift.php
$shift_kini    = qcShift();
$hari_ini_kerja = $shift_kini['shift_date'];

// Manager bisa memilih hari kerja lain lewat ?tanggal=YYYY-MM-DD. Default = hari
// kerja berjalan. Satu "hari kerja" mencakup Shift 1, 2, dan 3 (shift 3 lewat
// tengah malam), jadi memilih tanggal kemarin memunculkan shift 3 semalam.
$date_from = (isset($_GET['tanggal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tanggal']))
    ? $_GET['tanggal']
    : $hari_ini_kerja;
$date_to   = $date_from;
$lihat_hari_ini = ($date_from === $hari_ini_kerja);

// Rentang penuh hari kerja $date_from: dari shift 1 mulai sampai shift 3 selesai
// (selesainya sudah lewat tengah malam). Dipakai untuk memfilter step, supaya
// bagian shift 3 yang jatuh setelah tengah malam tidak terbuang dan ekor shift 3
// hari sebelumnya tidak ikut terhitung.
$shift_hari_ini = qcShiftPadaHari(strtotime($date_from));
$window_mulai   = date('Y-m-d H:i:s', $shift_hari_ini[0]['mulai_ts']);
$window_selesai = date('Y-m-d H:i:s', $shift_hari_ini[count($shift_hari_ini) - 1]['selesai_ts']);

// KHUSUS Operation Ratio: window dimulai lebih awal, dari shift 3 hari SEBELUMNYA
// (yang berjalan semalam sampai pagi ini), supaya shift 3 lintas-tengah-malam
// tetap tampil sebagai kartu "Lanjutan" di hari ini. Ini TIDAK dipakai untuk
// statistik lain (step/order/mesin) — hanya untuk query ratio. Grup shift 3
// semalam otomatis punya shift_date = kemarin (dari qcShift), jadi tetap
// terhitung sekali di tanggalnya sendiri, tidak double.
$shift_kemarin        = qcShiftPadaHari(strtotime('-1 day', strtotime($date_from)));
$window_ratio_mulai   = date('Y-m-d H:i:s', $shift_kemarin[count($shift_kemarin) - 1]['mulai_ts']);

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
        AND sps.start_time >= '$window_mulai'
        AND sps.start_time <  '$window_selesai'
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
          AND sps.start_time >= '$window_mulai'
          AND sps.start_time <  '$window_selesai'
          AND sps.status = 'done'
        GROUP BY DATE(sps.start_time)
        ORDER BY tgl ASC
    ");
    while ($d = mysqli_fetch_assoc($dailyQuery)) $daily_data[] = $d;
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
          AND sps.start_time >= '$window_ratio_mulai'
          AND sps.start_time <  '$window_selesai'
          AND sps.status IN ('done', 'paused')
          AND sps.end_time IS NOT NULL
        ORDER BY sps.start_time ASC
    ");

    $grouped = [];
    while ($r = mysqli_fetch_assoc($ratioQ)) {
        $shift = qcShift($r['start_time']);
        $key   = $shift['shift_date'] . '|' . $shift['nama'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'tgl'         => $shift['shift_date'],
                'shift_nama'  => $shift['nama'],
                'work_sec'    => $shift['detik'],
                'mulai_ts'    => $shift['mulai_ts'],
                'selesai_ts'  => $shift['selesai_ts'],
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
            'work_sec'    => $g['work_sec'],
            'mulai_ts'    => $g['mulai_ts'],
            'selesai_ts'  => $g['selesai_ts'],
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
        WHERE sps.start_time >= '$window_ratio_mulai'
          AND sps.start_time <  '$window_selesai'
          AND sps.status IN ('done', 'paused')
          AND sps.end_time IS NOT NULL
          $whereNik
        ORDER BY u.nama ASC, sps.start_time ASC
    ");

    $ratio_by_staff = [];
    while ($r = mysqli_fetch_assoc($ratioAllQ)) {
        $uid   = $r['id'];
        $shift = qcShift($r['start_time']);
        $key   = $shift['shift_date'] . '|' . $shift['nama'];

        if (!isset($ratio_by_staff[$uid])) {
            $ratio_by_staff[$uid] = ['nama' => $r['nama'], 'nik' => $r['nik'], 'days' => []];
        }
        if (!isset($ratio_by_staff[$uid]['days'][$key])) {
            $ratio_by_staff[$uid]['days'][$key] = [
                'tgl'         => $shift['shift_date'],
                'shift_nama'  => $shift['nama'],
                'work_sec'    => $shift['detik'],
                'mulai_ts'    => $shift['mulai_ts'],
                'selesai_ts'  => $shift['selesai_ts'],
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
    usort($ratio_daily_data, fn($a, $b) => $b['avg_ratio'] <=> $a['avg_ratio']);
}

// ── Detail step per staff (untuk panel yang bisa dibuka di kartu ratio) ────────
// Rincian tiap order yang dikerjakan: kategori/part, mesin, jam mulai-selesai.
$detail_by_nik = [];
$detailQ = mysqli_query($conn, "
    SELECT
        u.nik,
        so.order_code,
        so.category,
        mp.part_no,
        mp.part_name,
        ml.catalog_line,
        mm.machine_jig_catalog,
        sps.qc_machine,
        sps.start_time,
        sps.end_time,
        TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) AS durasi
    FROM sampling_process_steps sps
    JOIN users u                 ON sps.qc_user_id = u.id
    JOIN sampling_orders so      ON sps.order_id   = so.id
    LEFT JOIN master_parts mp    ON so.part_id      = mp.id
    LEFT JOIN master_lines ml    ON so.line_id      = ml.id
    LEFT JOIN master_machines mm ON so.machine_id   = mm.id
    WHERE sps.start_time >= '$window_ratio_mulai'
      AND sps.start_time <  '$window_selesai'
      AND sps.status IN ('done', 'paused')
      AND sps.end_time IS NOT NULL
      $whereNik
    ORDER BY u.nik, sps.start_time ASC
");
while ($d = mysqli_fetch_assoc($detailQ)) {
    $detail_by_nik[$d['nik']][] = $d;
}
// ─────────────────────────────────────────────────────────────────────────────

$total_all_step  = array_sum(array_column($staff_data, 'total_step'));
$total_all_order = array_sum(array_column($staff_data, 'total_order'));
$top_staff       = !empty($staff_data) ? $staff_data[0]['nama'] : '-';

// Hanya staff yang benar-benar mengerjakan order pada hari ini — kartu & chart
// per-staff dibangun dari daftar ini, jadi yang 0 order/step tidak muncul.
$staff_aktif  = array_values(array_filter($staff_data, fn($s) => (int)$s['total_step'] > 0));
$active_staff = count($staff_aktif);
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
        .ratio-grid-all { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; align-items: start; }
        /* Kartu ratio bisa diklik untuk membuka pop-up detail */
        .ratio-staff-card.expandable { cursor: pointer; transition: box-shadow .15s, transform .15s; }
        .ratio-staff-card.expandable:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .ratio-toggle { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; color: var(--red); margin-top: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
        .ratio-toggle svg { width: 12px; height: 12px; }
        .ratio-detail { display: none; } /* sumber isi modal, tidak ditampilkan inline */
        .ratio-detail-empty { font-size: 12px; color: var(--text3); padding: 8px 0; }

        /* Pop-up detail dengan latar blur */
        .detail-overlay { display: none; position: fixed; inset: 0; z-index: 600; background: rgba(17,24,39,0.45); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); align-items: center; justify-content: center; padding: 24px; }
        .detail-overlay.show { display: flex; }
        .detail-box { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow-md); width: 100%; max-width: 520px; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden; animation: detailPop .16s ease-out; }
        @keyframes detailPop { from { opacity: 0; transform: scale(.96) translateY(6px); } to { opacity: 1; transform: none; } }
        .detail-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 18px 20px; border-bottom: 1px solid var(--border); }
        .detail-head-name { font-size: 16px; font-weight: 700; color: var(--text); }
        .detail-head-sub { font-size: 11px; color: var(--text3); font-family: 'JetBrains Mono', monospace; margin-top: 2px; }
        .detail-close { background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; width: 30px; height: 30px; cursor: pointer; font-size: 16px; color: var(--text2); line-height: 1; flex-shrink: 0; }
        .detail-close:hover { background: var(--red-soft); color: var(--red); border-color: var(--red-mid); }
        .detail-body { padding: 8px 20px 18px; overflow-y: auto; }
        .ratio-detail-item { padding: 7px 0; border-bottom: 1px solid var(--border); }
        .ratio-detail-item:last-child { border-bottom: none; }
        .ratio-detail-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .ratio-detail-time { font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600; color: var(--text); }
        .ratio-detail-dur { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: var(--red); }
        .ratio-detail-part { font-size: 12px; font-weight: 600; color: var(--text); margin-top: 2px; }
        .ratio-detail-meta { font-size: 10px; color: var(--text3); margin-top: 1px; }
        .ratio-cat { display: inline-block; padding: 1px 6px; border-radius: 20px; font-size: 9px; font-weight: 700; background: var(--surface2); color: var(--text2); border: 1px solid var(--border); margin-right: 4px; vertical-align: middle; }
    </style>
</head>
<body>

<?php $active_nav = 'dashboard'; include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <span class="topbar-title">Operating Ratio Dashboard</span>
        <span class="topbar-date" style="font-size:15px;font-weight:700;color:var(--text);"><?php echo date('l, d F Y', strtotime($date_from)); ?></span>
    </div>

    <div class="content">

        <form method="GET" class="filter-card">
            <div class="filter-group">
                <label class="filter-label">Hari Kerja</label>
                <input type="date" name="tanggal" class="filter-input" value="<?php echo htmlspecialchars($date_from); ?>" max="<?php echo $hari_ini_kerja; ?>">
            </div>
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
            <?php if (!$lihat_hari_ini): ?>
                <a class="btn-filter" style="background:var(--text2);text-decoration:none;display:inline-flex;align-items:center;" href="dashboard.php">Hari Ini</a>
            <?php endif; ?>
            <a class="btn-filter" style="background:var(--green);text-decoration:none;display:inline-flex;align-items:center;"
               href="export_evaluation.php?month=<?php echo (int)date('m'); ?>&year=<?php echo (int)date('Y'); ?>&nik=<?php echo urlencode($selected_nik); ?>">
                ⬇ Export Laporan Bulanan (.xlsx)
            </a>
        </form>

        <div class="summary-grid">
            <div class="summary-card accent">
                <div class="summary-card-bar"></div>
                <div class="summary-icon icon-white">
                    <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="2"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>
                </div>
                <div class="summary-label">Total Step</div>
                <div class="summary-value counter" data-target="<?php echo $total_all_step; ?>">0</div>
                <div class="summary-sub"><?php echo $lihat_hari_ini ? 'Hari ini' : date('d M Y', strtotime($date_from)); ?></div>
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
                <?php $shift_aktif = qcLabelShift($shift_kini); ?>
                <span style="font-size:11px;color:var(--text3);margin-left:8px;">
                    Shift Aktif: <strong><?php echo $shift_aktif; ?></strong> &nbsp;|&nbsp;
                    <span style="color:var(--green);font-weight:700;">≥80% Produktif</span> &nbsp;
                    <span style="color:#f59e0b;font-weight:700;">50–79% Normal</span> &nbsp;
                    <span style="color:var(--red);font-weight:700;">&lt;50% Perlu Perhatian</span>
                </span>
            </div>
            <?php echo qcRatioUiStyle(); ?>
            <style>
            /* Penanda shift 3 lintas-tengah-malam yang "menyambung" dari hari sebelumnya. */
            .ratio-lanjut-badge { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700; color:#2563eb; background:#fff; border:1px solid #c7dcff; border-radius:20px; padding:3px 8px; margin-left:8px; vertical-align:middle; white-space:nowrap; }
            .ratio-lanjut-src { font-size:10px; color:#2563eb; font-family:'JetBrains Mono',monospace; margin-top:3px; }
            tr.row-lanjut td { background:#eff5ff; }
            .day-lanjut-tag { font-size:9px; font-weight:700; color:#2563eb; border:1px solid #c7dcff; background:#e6efff; border-radius:5px; padding:1px 5px; margin-left:4px; white-space:nowrap; }
            </style>

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
                                $is_lanjut = ($rd['tgl'] !== $date_from);
                            ?>
                            <tr class="<?php echo $is_lanjut ? 'row-lanjut' : ''; ?>">
                                <td class="mono"><?php echo $rd['tgl']; ?></td>
                                <td>
                                    <span style="font-size:11px;font-weight:600;color:var(--text2);"><?php echo $rd['shift_nama']; ?></span>
                                    <?php if ($is_lanjut): ?><span class="ratio-lanjut-badge">⏱ Lanjutan</span><div class="ratio-lanjut-src">dari <?php echo date('d M', strtotime($rd['tgl'])); ?> · <?php echo date('H:i', (int)$rd['mulai_ts']); ?>–<?php echo date('H:i', (int)$rd['selesai_ts']); ?></div><?php endif; ?>
                                </td>
                                <td class="mono"><?php echo "{$jam}j {$mnt}m"; ?></td>
                                <td>
                                    <div class="ratio-bar-wrap">
                                        <div class="ratio-bar-bg"><div class="ratio-bar-fill ratio-<?php echo $cls; ?>" style="width:<?php echo $rd['ratio']; ?>%"></div></div>
                                        <span class="ratio-val <?php echo $cls; ?>"><?php echo $rd['ratio']; ?>%</span>
                                        <?php echo qcRatioHelp((int)$rd['mulai_ts'], (int)$rd['selesai_ts'], (int)$rd['work_sec']); ?>
                                    </div>
                                    <?php echo qcRatioFraction((int)$rd['total_detik'], (int)$rd['work_sec']); ?>
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
                        $detail  = $detail_by_nik[$rs['nik']] ?? [];
                        $jml_step = count($detail);
                    ?>
                    <div class="ratio-staff-card expandable" onclick="openDetail(this)">
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
                            $d_efektif   = (int)($d['work_sec'] ?? 0);
                            $d_dur_menit = (int)round((($d['selesai_ts'] ?? 0) - ($d['mulai_ts'] ?? 0)) / 60);
                            $d_ist       = max(0, $d_dur_menit - (int)round($d_efektif / 60));
                            $d_title     = date('H:i', (int)($d['mulai_ts'] ?? 0)) . '–' . date('H:i', (int)($d['selesai_ts'] ?? 0))
                                         . ' · durasi ' . qcFmtDurasiMenit($d_dur_menit)
                                         . ' − istirahat ' . $d_ist . 'm = ' . qcFmtDurasiMenit((int)round($d_efektif / 60)) . ' efektif';
                            $d_lanjut    = ($d['tgl'] !== $date_from);
                        ?>
                        <div class="ratio-day-row"<?php echo $d_lanjut ? ' style="background:#eff5ff;border-radius:6px;"' : ''; ?>>
                            <span style="font-size:10px;color:var(--text2);font-family:'JetBrains Mono',monospace;min-width:80px;"><?php echo $d['tgl']; ?></span>
                            <span style="font-size:10px;color:var(--text3);min-width:44px;"><?php echo $d['shift_nama']; ?><?php if ($d_lanjut): ?><span class="day-lanjut-tag">Lanjutan</span><?php endif; ?></span>
                            <div class="ratio-bar-bg" style="flex:1;height:8px;"><div class="ratio-bar-fill ratio-<?php echo $dcls; ?>" style="width:<?php echo $d['ratio']; ?>%"></div></div>
                            <span class="ratio-val <?php echo $dcls; ?>" style="min-width:42px;"><?php echo $d['ratio']; ?>%</span>
                            <span style="font-size:10px;color:var(--text3);min-width:78px;text-align:right;font-family:'JetBrains Mono',monospace;" title="<?php echo htmlspecialchars($d_title); ?>"><?php echo qcFmtDurasiDetik((int)$d['total_detik']); ?> <span style="color:var(--text3);opacity:.6;">⁄</span> <?php echo qcFmtDurasiDetik($d_efektif); ?></span>
                        </div>
                        <?php endforeach; ?>

                        <div class="ratio-toggle">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h6v6"/><path d="M10 14L21 3"/><path d="M21 14v7H3V3h7"/></svg>
                            <span>Lihat detail order (<?php echo $jml_step; ?>)</span>
                        </div>

                        <div class="ratio-detail">
                            <?php if (empty($detail)): ?>
                                <div class="ratio-detail-empty">Tidak ada detail order.</div>
                            <?php else: foreach ($detail as $it):
                                $ds  = (int)$it['durasi'];
                                $dm  = floor($ds / 60);
                                $dss = $ds % 60;
                            ?>
                            <div class="ratio-detail-item">
                                <div class="ratio-detail-top">
                                    <span class="ratio-detail-time">
                                        <?php echo date('H:i', strtotime($it['start_time'])); ?>–<?php echo date('H:i', strtotime($it['end_time'])); ?>
                                    </span>
                                    <span class="ratio-detail-dur"><?php echo "{$dm}m {$dss}s"; ?></span>
                                </div>
                                <div class="ratio-detail-part">
                                    <span class="ratio-cat"><?php echo htmlspecialchars($it['category']); ?></span>
                                    <?php echo htmlspecialchars($it['part_name'] ?: '(part tidak diketahui)'); ?>
                                    <?php if (!empty($it['part_no'])): ?>
                                        <span style="color:var(--text3);font-weight:400;font-size:10px;">(<?php echo htmlspecialchars($it['part_no']); ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="ratio-detail-meta">
                                    <?php echo htmlspecialchars($it['qc_machine'] ?: '—'); ?>
                                    <?php if (!empty($it['catalog_line'])): ?> · <?php echo htmlspecialchars($it['catalog_line']); ?><?php endif; ?>
                                    <?php if (!empty($it['machine_jig_catalog'])): ?> · <?php echo htmlspecialchars($it['machine_jig_catalog']); ?><?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
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
        <?php if (empty($staff_aktif)): ?>
            <p style="color:var(--text3);font-size:13px;margin-bottom:24px;">Belum ada staff yang mengerjakan order pada periode ini.</p>
        <?php else: ?>
        <div class="staff-grid">
            <?php foreach ($staff_aktif as $staff):
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
        <?php endif; ?>

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

<!-- Pop-up detail order per staff (latar blur) -->
<div id="detailOverlay" class="detail-overlay">
    <div class="detail-box">
        <div class="detail-head">
            <div>
                <div class="detail-head-name" id="detailNama">-</div>
                <div class="detail-head-sub" id="detailSub">-</div>
            </div>
            <button type="button" class="detail-close" onclick="closeDetail()">&times;</button>
        </div>
        <div class="detail-body" id="detailBody"></div>
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
const staffNames  = <?php echo json_encode(array_column($staff_aktif, 'nama')); ?>;
const totalSteps  = <?php echo json_encode(array_map('intval', array_column($staff_aktif, 'total_step'))); ?>;
const totalOrders = <?php echo json_encode(array_map('intval', array_column($staff_aktif, 'total_order'))); ?>;
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

// Buka pop-up detail order untuk satu staff (isi diambil dari .ratio-detail
// tersembunyi di kartu, latar di-blur oleh .detail-overlay).
const detailOverlay = document.getElementById('detailOverlay');

function openDetail(card) {
    document.getElementById('detailNama').textContent = card.querySelector('.ratio-staff-name')?.textContent ?? '';
    document.getElementById('detailSub').textContent  = 'NIK ' + (card.querySelector('.ratio-staff-nik')?.textContent ?? '');
    document.getElementById('detailBody').innerHTML   = card.querySelector('.ratio-detail')?.innerHTML ?? '';
    detailOverlay.classList.add('show');
}

function closeDetail() {
    detailOverlay.classList.remove('show');
}

detailOverlay.addEventListener('click', e => { if (e.target === detailOverlay) closeDetail(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetail(); });

// Auto-reload tiap 30 detik, TAPI jangan reload saat pop-up detail terbuka
// (biar tidak menutup sendiri ketika manager sedang membaca).
setInterval(() => {
    if (!detailOverlay.classList.contains('show')) window.location.reload();
}, 30000);

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
<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/shift.php';
include '../config/csrf.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'qc') {
    header("Location: display.php");
    exit;
}

$section  = isset($_GET['section']) ? $_GET['section'] : 'job';
$today    = date('Y-m-d');
$user_id  = (int)$_SESSION['id'];

// Shift yang sedang berjalan — semua batas waktu berasal dari config/shift.php
$shift         = qcShift();
$shift_date    = $shift['shift_date'];
$shift_nama    = $shift['nama'];
$shift_detik   = $shift['detik'];
$shift_mulai   = date('Y-m-d H:i:s', $shift['mulai_ts']);
$shift_selesai = date('Y-m-d H:i:s', $shift['selesai_ts']);

// ── Personal Operation Ratio Shift Ini ───────────────────────────────────────
// Dibatasi rentang shift yang sedang berjalan. Memfilter pakai DATE() saja
// membuat shift 3 ikut menjumlah kerja shift 1 & 2 hari sebelumnya, karena
// shift_date-nya mundur ke kemarin — ratio langsung mentok 100%.
$today_ratio_query = mysqli_query($conn, "
    SELECT
        MIN(sps.start_time) AS first_start,
        SUM(TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time)) AS total_aktif
    FROM sampling_process_steps sps
    WHERE sps.qc_user_id = $user_id
      AND sps.start_time >= '$shift_mulai'
      AND sps.start_time <  '$shift_selesai'
      AND sps.status IN ('done', 'paused')
      AND sps.end_time IS NOT NULL
");
$today_ratio = mysqli_fetch_assoc($today_ratio_query);

$first_start  = $today_ratio['first_start'] ?? null;
$total_aktif  = (int)($today_ratio['total_aktif'] ?? 0);

$ratio_personal = $shift_detik > 0 ? min(100, round(($total_aktif / $shift_detik) * 100, 1)) : 0;
$aktif_jam      = floor($total_aktif / 3600);
$aktif_mnt      = floor(($total_aktif % 3600) / 60);
$mulai_jam      = $first_start ? date('H:i', strtotime($first_start)) : '-';
// ─────────────────────────────────────────────────────────────────────────────

$subquery = "
    SELECT
        so.id,
        so.order_code,
        so.category,
        so.qty,
        so.status,
        so.created_at,
        mp.part_no,
        mp.part_name,
        ml.catalog_line,
        mm.machine_jig_catalog,

        (
            SELECT s1.qc_machine
            FROM sampling_process_steps s1
            WHERE s1.order_id = so.id
            ORDER BY s1.id DESC
            LIMIT 1
        ) AS qc_machine,

        (
            SELECT s2.start_time
            FROM sampling_process_steps s2
            WHERE s2.order_id = so.id
              AND s2.status = 'in_progress'
            ORDER BY s2.id DESC
            LIMIT 1
        ) AS start_time,

        (
            SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, s3.start_time, s3.end_time)), 0)
            FROM sampling_process_steps s3
            WHERE s3.order_id = so.id
              AND s3.status IN ('done', 'paused')
        ) AS total_done_seconds,

        (
            SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, s6.start_time, s6.end_time)), 0)
            FROM sampling_process_steps s6
            WHERE s6.order_id = so.id
              AND s6.qc_machine = (
                  SELECT s7.qc_machine FROM sampling_process_steps s7
                  WHERE s7.order_id = so.id
                  ORDER BY s7.id DESC LIMIT 1
              )
              AND s6.status IN ('done', 'paused')
        ) AS current_machine_done_seconds,

        (
            SELECT u.nama
            FROM sampling_process_steps s4
            LEFT JOIN users u ON s4.qc_user_id = u.id
            WHERE s4.order_id = so.id
            ORDER BY s4.id DESC
            LIMIT 1
        ) AS qc_nama,

        (
            SELECT s5.status
            FROM sampling_process_steps s5
            WHERE s5.order_id = so.id
            ORDER BY s5.id DESC
            LIMIT 1
        ) AS last_step_status,

        MAX(CASE WHEN sps.qc_machine = 'CMM'              AND sps.status = 'done' THEN 1 ELSE 0 END) AS cmm_done,
        MAX(CASE WHEN sps.qc_machine = 'RONDCOM'          AND sps.status = 'done' THEN 1 ELSE 0 END) AS rondcom_done,
        MAX(CASE WHEN sps.qc_machine = 'ROUGHNESS'        AND sps.status = 'done' THEN 1 ELSE 0 END) AS roughness_done,
        MAX(CASE WHEN sps.qc_machine = 'CONTOUR'          AND sps.status = 'done' THEN 1 ELSE 0 END) AS contour_done,
        MAX(CASE WHEN sps.qc_machine = 'PROFIL PROJECTOR' AND sps.status = 'done' THEN 1 ELSE 0 END) AS profil_done,
        MAX(CASE WHEN sps.qc_machine = 'MANUAL'           AND sps.status = 'done' THEN 1 ELSE 0 END) AS manual_done,
        MAX(CASE WHEN sps.qc_machine = 'HARDNESS CHECK'   AND sps.status = 'done' THEN 1 ELSE 0 END) AS hardness_check_done,

        (
            SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, sps2.start_time, sps2.end_time)), 0)
            FROM sampling_process_steps sps2
            WHERE sps2.order_id = so.id AND sps2.qc_machine = 'CMM'
            AND sps2.status IN ('done', 'paused')
        ) AS cmm_durasi,

        (
            SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, sps2.start_time, sps2.end_time)), 0)
            FROM sampling_process_steps sps2
            WHERE sps2.order_id = so.id AND sps2.qc_machine = 'RONDCOM'
            AND sps2.status IN ('done', 'paused')
        ) AS rondcom_durasi,

        (
            SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, sps2.start_time, sps2.end_time)), 0)
            FROM sampling_process_steps sps2
            WHERE sps2.order_id = so.id AND sps2.qc_machine = 'ROUGHNESS'
            AND sps2.status IN ('done', 'paused')
        ) AS roughness_durasi,

        (
            SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, sps2.start_time, sps2.end_time)), 0)
            FROM sampling_process_steps sps2
            WHERE sps2.order_id = so.id AND sps2.qc_machine = 'CONTOUR'
            AND sps2.status IN ('done', 'paused')
        ) AS contour_durasi,

        (
            SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, sps2.start_time, sps2.end_time)), 0)
            FROM sampling_process_steps sps2
            WHERE sps2.order_id = so.id AND sps2.qc_machine = 'PROFIL PROJECTOR'
            AND sps2.status IN ('done', 'paused')
        ) AS profil_durasi,

        (
            SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, sps2.start_time, sps2.end_time)), 0)
            FROM sampling_process_steps sps2
            WHERE sps2.order_id = so.id AND sps2.qc_machine = 'MANUAL'
            AND sps2.status IN ('done', 'paused')
        ) AS manual_durasi,

        (
            SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, sps2.start_time, sps2.end_time)), 0)
            FROM sampling_process_steps sps2
            WHERE sps2.order_id = so.id AND sps2.qc_machine = 'HARDNESS CHECK'
            AND sps2.status IN ('done', 'paused')
        ) AS hardness_durasi

    FROM sampling_orders so
    JOIN master_parts mp ON so.part_id = mp.id
    JOIN master_lines ml ON so.line_id = ml.id
    JOIN master_machines mm ON so.machine_id = mm.id
    LEFT JOIN sampling_process_steps sps ON sps.order_id = so.id
";

// Query waiting & in_progress — pakai shift_date + shift 3 lintas hari
$query_mine = mysqli_query($conn, $subquery . "
    WHERE so.created_by = $user_id
      AND so.status IN ('waiting', 'in_progress', 'partial_done')
      AND (
          DATE(so.created_at) = '$shift_date'
          OR (
              DATE(so.created_at) = DATE_SUB('$shift_date', INTERVAL 1 DAY)
              AND TIME(so.created_at) >= '23:00:00'
          )
      )
    GROUP BY
        so.id, so.order_code, so.category, so.qty, so.status, so.created_at,
        mp.part_no, mp.part_name, ml.catalog_line, mm.machine_jig_catalog
    ORDER BY so.id DESC
");

// Query done — hanya order milik user (dibuat atau pernah dikerjakan) pada satu
// tanggal kerja (+ shift 3 lintas hari). Halaman Done bisa memilih tanggal lain
// (?tgl=) — ini pengganti halaman History.
function qcQueryDone(mysqli $conn, string $subquery, int $user_id, string $tgl) {
    return mysqli_query($conn, $subquery . "
        WHERE so.status = 'done'
          AND (so.created_by = $user_id
               OR EXISTS (SELECT 1 FROM sampling_process_steps x WHERE x.order_id = so.id AND x.qc_user_id = $user_id))
          AND (
              DATE(so.created_at) = '$tgl'
              OR (
                  DATE(so.created_at) = DATE_SUB('$tgl', INTERVAL 1 DAY)
                  AND TIME(so.created_at) >= '23:00:00'
              )
          )
        GROUP BY
            so.id, so.order_code, so.category, so.qty, so.status, so.created_at,
            mp.part_no, mp.part_name, ml.catalog_line, mm.machine_jig_catalog
        ORDER BY so.id DESC
    ");
}

$done_date = $shift_date;
if ($section === 'done' && isset($_GET['tgl'])) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$_GET['tgl']);
    if ($d && $d->format('Y-m-d') === $_GET['tgl'] && $_GET['tgl'] <= $shift_date) $done_date = $_GET['tgl'];
}
$query_done = qcQueryDone($conn, $subquery, $user_id, $shift_date);

$waiting  = [];
$progress = [];
$done     = [];

while ($row = mysqli_fetch_assoc($query_mine)) {
    if ($row['status'] === 'waiting') {
        $waiting[] = $row;
    } elseif ($row['status'] === 'in_progress' || $row['status'] === 'partial_done') {
        $progress[] = $row;
    }
}

while ($row = mysqli_fetch_assoc($query_done)) {
    $done[] = $row;
}

// Kartu di halaman Done untuk tanggal yang dipilih; angka di header & sidebar tetap shift ini.
$done_view = $done;
if ($done_date !== $shift_date) {
    $done_view = [];
    $q = qcQueryDone($conn, $subquery, $user_id, $done_date);
    while ($row = mysqli_fetch_assoc($q)) $done_view[] = $row;
}

$total_sampling   = count($waiting) + count($progress) + count($done);
$total_processing = count($progress);
$total_done       = count($done);
$date_now         = date('d F Y');
$nama_login       = isset($_SESSION['nama']) ? $_SESSION['nama'] : '';

function durStr(?int $sec): string {
    if ($sec === null) return '';
    $m = floor($sec / 60);
    $s = $sec % 60;
    return "{$m}m {$s}s";
}

// Nama pendek mesin QC untuk chip di kartu.
const QC_MESIN_PENDEK = [
    'CMM' => 'CMM', 'RONDCOM' => 'Rondcom', 'ROUGHNESS' => 'Roughness', 'CONTOUR' => 'Contour',
    'PROFIL PROJECTOR' => 'Profil Proj.', 'MANUAL' => 'Manual', 'HARDNESS CHECK' => 'Hardness',
];

function renderCards(array $rows, string $mode = 'waiting') {
    $tz = new DateTimeZone('Asia/Jakarta');

    if (empty($rows)) {
        $teks = ['waiting' => 'No job orders yet. Create one from the menu.', 'progress' => 'Nothing in progress right now.', 'done' => 'No finished orders on this day.'][$mode] ?? 'No data yet.';
        echo '<div class="qc-empty"><div class="ic">○</div>' . $teks . '</div>';
        return;
    }

    foreach ($rows as $row) {
        $doneSec            = (int)($row['total_done_seconds'] ?? 0);
        $lastStatus         = $row['last_step_status'] ?? '';
        $currentMachineDone = (int)($row['current_machine_done_seconds'] ?? 0);

        $current_step = 0;
        if (!empty($row['start_time'])) {
            $startDt      = new DateTime($row['start_time'], $tz);
            $nowDt        = new DateTime('now', $tz);
            $current_step = $nowDt->getTimestamp() - $startDt->getTimestamp();
            if ($current_step < 0) $current_step = 0;
        }

        $elapsed = $current_step + $doneSec;

        if ($mode === 'done') {
            $progressValue = 100;
        } elseif ($mode === 'progress' && !empty($row['start_time'])) {
            $progressValue = min(100, floor(($elapsed / 3600) * 100));
        } else {
            $progressValue = min(100, floor(($doneSec / 3600) * 100));
        }

        $timer_sec = $currentMachineDone + $current_step;
        $timerText = sprintf('%02d:%02d:%02d', floor($timer_sec / 3600), floor(($timer_sec % 3600) / 60), $timer_sec % 60);
        $totalSec  = $mode === 'done' ? $doneSec : $elapsed;
        $totalText = sprintf('%02d:%02d:%02d', floor($totalSec / 3600), floor(($totalSec % 3600) / 60), $totalSec % 60);

        $mesin_map = [
            'CMM'              => ['done' => $row['cmm_done'],          'durasi' => $row['cmm_durasi']],
            'RONDCOM'          => ['done' => $row['rondcom_done'],       'durasi' => $row['rondcom_durasi']],
            'ROUGHNESS'        => ['done' => $row['roughness_done'],     'durasi' => $row['roughness_durasi']],
            'CONTOUR'          => ['done' => $row['contour_done'],       'durasi' => $row['contour_durasi']],
            'PROFIL PROJECTOR' => ['done' => $row['profil_done'],        'durasi' => $row['profil_durasi']],
            'MANUAL'           => ['done' => $row['manual_done'],        'durasi' => $row['manual_durasi']],
            'HARDNESS CHECK'   => ['done' => $row['hardness_check_done'],'durasi' => $row['hardness_durasi']],
        ];

        // Mesin yang sedang jalan / di-pause (hanya relevan di mode progress).
        $running = ($mode === 'progress' && $lastStatus === 'in_progress') ? ($row['qc_machine'] ?? '') : '';
        $paused  = ($mode === 'progress' && $lastStatus === 'paused') ? ($row['qc_machine'] ?? '') : '';
        $sudah   = array_keys(array_filter($mesin_map, fn($v) => (int)$v['done'] === 1));

        if ($mode === 'waiting')  { $st = 'waiting'; $stLabel = 'Waiting'; }
        elseif ($mode === 'done') { $st = 'done';    $stLabel = 'Done'; }
        elseif ($running)         { $st = 'running'; $stLabel = 'Running'; }
        elseif ($paused)          { $st = 'paused';  $stLabel = 'Paused'; }
        else                      { $st = 'idle';    $stLabel = 'Between machines'; }
        $pendek = QC_MESIN_PENDEK;
        ?>
        <div class="qc-card st-<?php echo $st; ?>">
            <div class="qc-top">
                <span class="qc-code"><?php echo htmlspecialchars($row['order_code']); ?></span>
                <span class="qc-badge"><i></i><?php echo $stLabel; ?></span>
            </div>
            <div class="qc-part"><?php echo htmlspecialchars($row['part_name']); ?></div>
            <div class="qc-partno"><?php echo htmlspecialchars($row['part_no']); ?> · <?php echo htmlspecialchars($row['category']); ?> · Qty <?php echo (int)$row['qty']; ?></div>

            <div class="qc-meta">
                <div><small>Line</small><b><?php echo htmlspecialchars($row['catalog_line']); ?></b></div>
                <div><small>Machine / Jig</small><b><?php echo htmlspecialchars($row['machine_jig_catalog']); ?></b></div>
                <?php if ($mode !== 'waiting'): ?>
                <div class="full"><small>QC Staff</small><b><?php echo $row['qc_nama'] ? htmlspecialchars(ucwords(strtolower($row['qc_nama']))) : '-'; ?></b></div>
                <?php endif; ?>
            </div>

            <?php if ($mode !== 'waiting'): ?>
            <div class="qc-machines">
                <?php foreach ($mesin_map as $nama => $val):
                    $isDone = (int)$val['done'] === 1;
                    $kelas  = $isDone ? 'ok' : ($nama === $running ? 'run' : ($nama === $paused ? 'pause' : ''));
                    $durStr = ($isDone && $val['durasi'] !== null) ? durStr((int)$val['durasi']) : '';
                ?>
                <span class="qm <?php echo $kelas; ?>" title="<?php echo $nama . ($durStr ? ' · ' . $durStr : ''); ?>">
                    <i></i><?php echo $pendek[$nama]; ?><?php if ($durStr): ?><em><?php echo $durStr; ?></em><?php endif; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($running): ?>
            <div class="qc-now">
                <div><small>Now on</small><b><?php echo htmlspecialchars($running); ?></b></div>
                <strong class="live-timer" data-start-time="<?php echo htmlspecialchars($row['start_time']); ?>" data-done-sec="<?php echo $currentMachineDone; ?>"><?php echo $timerText; ?></strong>
            </div>
            <?php elseif ($paused): ?>
            <div class="qc-now paused">
                <div><small>Paused on</small><b><?php echo htmlspecialchars($paused); ?></b></div>
                <strong>⏸</strong>
            </div>
            <?php endif; ?>

            <?php if ($mode !== 'waiting' && $totalSec > 0 || $running): ?>
            <div class="qc-total">
                <small>Total order time</small>
                <b class="<?php echo $mode === 'progress' ? 'live-total-timer' : ''; ?>"
                   data-start-time="<?php echo $running ? htmlspecialchars($row['start_time']) : ''; ?>"
                   data-done-sec="<?php echo $doneSec; ?>"><?php echo $totalText; ?></b>
            </div>
            <?php if ($mode === 'progress'): ?>
            <div class="qc-bar"><div class="live-progress-fill" <?php if ($running): ?>data-start-time="<?php echo htmlspecialchars($row['start_time']); ?>" data-done-sec="<?php echo $doneSec; ?>"<?php endif; ?> style="width:<?php echo $progressValue; ?>%"></div></div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="qc-actions">
            <?php if ($mode === 'waiting'): ?>
                <button type="button" class="qc-btn primary open-process-modal"
                    data-order-id="<?php echo $row['id']; ?>" data-order-code="<?php echo htmlspecialchars($row['order_code']); ?>"
                    data-done="[]" data-running="" data-paused="">▶ Start Process</button>

            <?php elseif ($mode === 'progress'): ?>
                <?php if ($paused): ?>
                    <form method="POST" action="pause_order.php">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="resume" value="1">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="qc-btn green">▶ Resume <?php echo htmlspecialchars($pendek[$paused] ?? $paused); ?></button>
                    </form>
                <?php endif; ?>

                <button type="button" class="qc-btn <?php echo $paused ? 'outline' : 'primary'; ?> open-process-modal"
                    data-order-id="<?php echo $row['id']; ?>" data-order-code="<?php echo htmlspecialchars($row['order_code']); ?>"
                    data-done='<?php echo htmlspecialchars(json_encode($sudah), ENT_QUOTES); ?>'
                    data-running="<?php echo htmlspecialchars($running); ?>" data-paused="<?php echo htmlspecialchars($paused); ?>">
                    Next Machine →
                    <?php if ($running || $paused): ?><small><?php echo htmlspecialchars($pendek[$running ?: $paused] ?? ''); ?> will be marked done ✓</small><?php endif; ?>
                </button>

                <?php if ($running): ?>
                <div class="qc-row2">
                    <form method="POST" action="pause_order.php">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="qc-btn amber">⏸ Pause</button>
                    </form>
                    <form method="POST" action="finish_order.php">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="qc-btn ghost">✓ Finish <?php echo htmlspecialchars($pendek[$running] ?? ''); ?></button>
                    </form>
                </div>
                <?php endif; ?>

                <form method="POST" action="final_done.php"
                    <?php if ($running || $paused): ?>onsubmit="return confirm('Close this order as DONE? <?php echo htmlspecialchars($running ?: $paused, ENT_QUOTES); ?> will be marked done too.');"<?php endif; ?>>
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                    <button type="submit" class="qc-btn done">✓ Final Done</button>
                </form>
            <?php endif; ?>

                <form method="POST" action="delete_order.php" onsubmit="return confirm('Delete this order? Its process data will also be deleted.');">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                    <button type="submit" class="qc-del">Delete order</button>
                </form>
            </div>
        </div>
        <?php
    }
}

$pesan = [
    'error_machine'    => ['err', 'Invalid QC machine.'],
    'error_order'      => ['err', 'Order not found.'],
    'insert_error'     => ['err', 'Failed to start the order process.'],
    'process_success'  => ['ok',  'Machine started.' . (isset($_GET['closed']) ? ' ' . htmlspecialchars($_GET['closed']) . ' was marked done ✓' : '')],
    'finish_success'   => ['ok',  'Machine step finished.'],
    'final_done'       => ['ok',  'Order closed as DONE.'],
    'machine_done'     => ['err', 'That machine is already done for this order.'],
    'already_progress' => ['err', 'That machine is already running for this order.'],
    'pause_success'    => ['ok',  'Order paused.'],
    'resume_success'   => ['ok',  'Order resumed.'],
    'delete_success'   => ['ok',  'Order deleted.'],
    'delete_error'     => ['err', 'Failed to delete order, or it is not yours.'],
];
$judul = [
    'job'      => ['Job Order', 'Orders you created that have not started yet', count($waiting)],
    'progress' => ['On Progress', 'Orders being sampled right now', count($progress)],
    'done'     => ['Done', $done_date === $shift_date ? 'Orders you finished this shift' : 'Orders you finished on ' . date('l, j F Y', strtotime($done_date)), count($done_view)],
][$section] ?? ['Job Order', '', count($waiting)];
$namaRapi = ucwords(strtolower($nama_login));
$ratioWarna = $ratio_personal >= 80 ? 'var(--green)' : ($ratio_personal >= 50 ? '#d97706' : 'var(--red)');
$svg = fn($p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Display QC</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/components.css">
    <script>
    const serverTime = new Date("<?php echo date('Y-m-d H:i:s'); ?>".replace(' ', 'T'));
    const clientTime = new Date();
    const timeDiff   = clientTime - serverTime;
    </script>
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root {
            --red:#CC0000; --red-dark:#A30000; --red-soft:#fff0f0; --red-mid:#ffd5d5;
            --bg:#f4f5f7; --surface:#fff; --surface2:#f9fafb; --border:rgba(0,0,0,0.07);
            --text:#111827; --text2:#6b7280; --text3:#9ca3af;
            --green:#059669; --green-soft:#d1fae5; --amber:#d97706; --amber-soft:#fef3c7; --blue:#2563eb; --blue-soft:#dbeafe; --shadow:0 1px 4px rgba(0,0,0,0.06);
        }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }

        .qc-topbar { position:sticky; top:0; z-index:50; background:var(--surface); border-bottom:1px solid var(--border); height:64px; padding:0 24px; display:flex; align-items:center; gap:16px; }
        .qc-brand { display:flex; align-items:center; gap:10px; font-weight:800; font-size:15px; white-space:nowrap; }
        .qc-brand .lg { width:34px; height:34px; border-radius:9px; background:var(--red); display:flex; align-items:center; justify-content:center; }
        .qc-brand .lg svg { width:18px; height:18px; stroke:#fff; }
        .qc-stats { margin-left:auto; display:flex; flex-wrap:wrap; justify-content:flex-end; gap:6px; }
        .qs { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--text2); background:var(--surface2); border:1px solid var(--border); border-radius:999px; padding:5px 11px; white-space:nowrap; }
        .qs b { color:var(--text); font-weight:700; }
        .qs.me { background:var(--red-soft); border-color:var(--red-mid); color:var(--red); }
        .qs.me b { color:var(--red); }

        .qc-layout { display:flex; min-height:calc(100vh - 64px); }
        .qc-side { width:210px; flex-shrink:0; background:var(--surface); border-right:1px solid var(--border); padding:16px 12px; display:flex; flex-direction:column; gap:2px; position:sticky; top:64px; height:calc(100vh - 64px); }
        .qc-side .lbl { font-size:10px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:.08em; padding:10px 10px 6px; }
        .qc-side a { display:flex; align-items:center; gap:10px; padding:10px; border-radius:9px; font-size:13px; font-weight:600; color:var(--text2); text-decoration:none; transition:background .12s, color .12s; }
        .qc-side a svg { width:17px; height:17px; flex-shrink:0; }
        .qc-side a:hover { background:var(--surface2); color:var(--text); }
        .qc-side a.active { background:var(--red-soft); color:var(--red); }
        .qc-side a .n { margin-left:auto; font-size:11px; font-weight:800; background:var(--surface2); border:1px solid var(--border); color:var(--text2); border-radius:999px; padding:1px 8px; }
        .qc-side a.active .n { background:var(--red); border-color:var(--red); color:#fff; }
        .qc-side a.new { background:var(--red); color:#fff; margin-bottom:8px; justify-content:center; }
        .qc-side a.new:hover { background:var(--red-dark); }
        .qc-side .sp { flex:1; }
        .qc-side a.out { color:var(--red); }

        .qc-main { flex:1; min-width:0; padding:22px 24px 40px; }
        .qc-head { display:flex; align-items:baseline; gap:12px; margin-bottom:18px; }
        .qc-head h1 { font-size:20px; font-weight:800; }
        .qc-head span { font-size:13px; color:var(--text3); }
        .qc-msg { padding:11px 16px; border-radius:10px; font-size:13px; font-weight:600; margin-bottom:14px; }
        .qc-msg.ok { background:var(--green-soft); color:var(--green); }
        .qc-msg.err { background:var(--red-soft); color:var(--red); }

        .qc-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px; align-items:start; }
        .qc-empty { grid-column:1/-1; text-align:center; padding:60px 20px; color:var(--text3); font-size:14px; background:var(--surface); border:1px dashed rgba(0,0,0,.12); border-radius:16px; }
        .qc-empty .ic { font-size:28px; margin-bottom:6px; }

        .qc-card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:16px; box-shadow:0 1px 4px rgba(0,0,0,.05); display:flex; flex-direction:column; gap:12px; border-top:4px solid var(--text3); transition:box-shadow .15s; }
        .qc-card:hover { box-shadow:0 8px 24px rgba(0,0,0,.08); }
        .qc-card.st-waiting { border-top-color:var(--red); }
        .qc-card.st-running { border-top-color:var(--blue); }
        .qc-card.st-paused { border-top-color:var(--amber); }
        .qc-card.st-idle { border-top-color:var(--text3); }
        .qc-card.st-done { border-top-color:var(--green); }
        .qc-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
        .qc-code { font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:700; color:var(--text2); background:var(--surface2); border:1px solid var(--border); padding:3px 8px; border-radius:6px; }
        .qc-badge { display:inline-flex; align-items:center; gap:6px; font-size:11px; font-weight:700; padding:3px 10px; border-radius:999px; background:var(--surface2); color:var(--text2); white-space:nowrap; }
        .qc-badge i { width:7px; height:7px; border-radius:50%; background:currentColor; }
        .st-waiting .qc-badge { background:var(--red-soft); color:var(--red); }
        .st-running .qc-badge { background:var(--blue-soft); color:var(--blue); }
        .st-running .qc-badge i { animation:blink 1.2s infinite; }
        .st-paused .qc-badge { background:var(--amber-soft); color:var(--amber); }
        .st-done .qc-badge { background:var(--green-soft); color:var(--green); }
        @keyframes blink { 50% { opacity:.25; } }
        .qc-part { font-size:19px; font-weight:800; line-height:1.2; margin-top:-2px; }
        .qc-partno { font-size:11px; color:var(--text3); margin-top:-8px; font-family:'JetBrains Mono',monospace; }
        .qc-meta { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .qc-meta div { background:var(--surface2); border-radius:9px; padding:7px 10px; min-width:0; }
        .qc-meta .full { grid-column:1/-1; }
        .qc-meta small { display:block; font-size:9.5px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:.05em; }
        .qc-meta b { display:block; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .qc-machines { display:flex; flex-wrap:wrap; gap:5px; }
        .qm { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; padding:4px 9px; border-radius:999px; border:1px solid var(--border); color:var(--text3); background:var(--surface); }
        .qm i { width:7px; height:7px; border-radius:50%; border:1.5px solid currentColor; }
        .qm em { font-style:normal; font-size:10px; opacity:.75; font-family:'JetBrains Mono',monospace; }
        .qm.ok { background:var(--green-soft); border-color:#a7f3d0; color:var(--green); }
        .qm.ok i { background:currentColor; }
        .qm.run { background:var(--blue-soft); border-color:#bfdbfe; color:var(--blue); }
        .qm.run i { background:currentColor; animation:blink 1.2s infinite; }
        .qm.pause { background:var(--amber-soft); border-color:#fde68a; color:var(--amber); }
        .qc-now { display:flex; align-items:center; justify-content:space-between; background:var(--blue-soft); border-radius:12px; padding:10px 14px; color:var(--blue); }
        .qc-now small { display:block; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; opacity:.8; }
        .qc-now b { font-size:14px; }
        .qc-now strong { font-family:'JetBrains Mono',monospace; font-size:22px; font-weight:700; }
        .qc-now.paused { background:var(--amber-soft); color:var(--amber); }
        .qc-total { display:flex; align-items:baseline; justify-content:space-between; }
        .qc-total small { font-size:11px; color:var(--text3); }
        .qc-total b { font-family:'JetBrains Mono',monospace; font-size:16px; color:var(--text); }
        .qc-bar { height:5px; background:var(--surface2); border-radius:99px; overflow:hidden; margin-top:-6px; }
        .qc-bar div { height:100%; background:var(--red); border-radius:99px; transition:width .5s; }

        .qc-actions { display:flex; flex-direction:column; gap:8px; margin-top:2px; }
        .qc-actions form { margin:0; }
        .qc-row2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .qc-btn { width:100%; font-family:inherit; font-size:13px; font-weight:700; padding:11px 12px; border-radius:10px; border:1px solid transparent; cursor:pointer; transition:background .12s, transform .08s, box-shadow .12s; display:flex; flex-direction:column; align-items:center; gap:2px; }
        .qc-btn:active { transform:scale(.98); }
        .qc-btn small { font-size:10.5px; font-weight:600; opacity:.85; }
        .qc-btn.primary { background:var(--red); color:#fff; box-shadow:0 4px 12px rgba(204,0,0,.2); }
        .qc-btn.primary:hover { background:var(--red-dark); }
        .qc-btn.outline { background:var(--surface); color:var(--red); border-color:var(--red-mid); }
        .qc-btn.outline:hover { background:var(--red-soft); }
        .qc-btn.green { background:var(--green); color:#fff; }
        .qc-btn.amber { background:var(--amber-soft); color:var(--amber); border-color:#fde68a; }
        .qc-btn.amber:hover { background:#fde68a; }
        .qc-btn.ghost { background:var(--surface2); color:var(--text2); border-color:var(--border); }
        .qc-btn.ghost:hover { color:var(--text); }
        .qc-btn.done { background:var(--green-soft); color:var(--green); border-color:#a7f3d0; }
        .qc-btn.done:hover { background:#a7f3d0; }
        .qc-del { width:100%; background:none; border:none; font-family:inherit; font-size:11px; font-weight:600; color:var(--text3); cursor:pointer; padding:4px; }
        .qc-del:hover { color:var(--red); text-decoration:underline; }

        .qc-modal { position:fixed; inset:0; background:rgba(17,24,39,.5); backdrop-filter:blur(3px); display:none; align-items:center; justify-content:center; z-index:100; padding:16px; }
        .qc-modal .box { background:var(--surface); border-radius:18px; width:100%; max-width:440px; padding:22px; box-shadow:0 20px 50px rgba(0,0,0,.25); animation:pop .18s ease; }
        @keyframes pop { from { opacity:0; transform:scale(.97); } to { opacity:1; transform:none; } }
        .qc-modal h3 { font-size:17px; font-weight:800; }
        .qc-modal .sub { font-size:12px; color:var(--text3); font-family:'JetBrains Mono',monospace; margin-top:2px; }
        .qc-modal .info { margin-top:12px; font-size:12px; color:var(--blue); background:var(--blue-soft); border-radius:10px; padding:9px 12px; display:none; }
        .mgrid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:14px; }
        .mopt { position:relative; }
        .mopt input { position:absolute; opacity:0; }
        .mopt label { display:flex; align-items:center; justify-content:space-between; gap:6px; padding:12px; border:1.5px solid var(--border); border-radius:11px; font-size:13px; font-weight:700; cursor:pointer; transition:all .12s; }
        .mopt label:hover { border-color:var(--red-mid); }
        .mopt label em { font-style:normal; font-size:10px; font-weight:700; color:var(--text3); }
        .mopt input:checked + label { border-color:var(--red); background:var(--red-soft); color:var(--red); }
        .mopt input:disabled + label { opacity:.5; cursor:not-allowed; background:var(--surface2); }
        .mopt input:disabled + label em.ok { color:var(--green); }
        .mopt input:disabled + label em.run { color:var(--blue); }
        .mopt.wide { grid-column:1/-1; }
        .qc-modal .acts { display:grid; grid-template-columns:1fr 2fr; gap:8px; margin-top:16px; }

        .shift-toast { position:fixed; right:20px; bottom:20px; z-index:90; display:flex; align-items:center; gap:12px; max-width:340px; background:var(--surface); border:1px solid #fde68a; border-left:5px solid #f59e0b; border-radius:14px; padding:12px 12px 12px 14px; box-shadow:0 12px 32px rgba(0,0,0,.16); animation:toastIn .3s ease; }
        .shift-toast .st-ic { width:38px; height:38px; border-radius:50%; background:var(--amber-soft); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
        .shift-toast .st-tx b { display:block; font-size:14px; color:var(--text); }
        .shift-toast .st-tx span { font-size:12px; color:var(--text2); }
        .shift-toast button { margin-left:auto; align-self:flex-start; width:26px; height:26px; border:none; border-radius:50%; background:var(--surface2); color:var(--text3); font-size:16px; line-height:1; cursor:pointer; flex-shrink:0; }
        .shift-toast button:hover { background:var(--red-soft); color:var(--red); }
        .shift-toast.urgent { border-color:var(--red-mid); border-left-color:var(--red); }
        .shift-toast.urgent .st-ic { background:var(--red-soft); animation:ring 1s ease infinite; }
        @keyframes toastIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:none; } }
        @keyframes ring { 0%,100% { transform:rotate(0); } 25% { transform:rotate(-12deg); } 75% { transform:rotate(12deg); } }

        @media (max-width:900px) {
            .qc-layout { flex-direction:column; }
            .qc-side { width:auto; height:auto; position:static; flex-direction:row; flex-wrap:wrap; }
            .qc-side .lbl, .qc-side .sp { display:none; }
            .qc-topbar { height:auto; padding:10px 16px; flex-wrap:wrap; }
            .qc-main { padding:16px; }
        }
    </style>
</head>
<body>
    <header class="qc-topbar">
        <div class="qc-brand"><span class="lg"><?php echo $svg('<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>'); ?></span>QC Sampling Display</div>
        <div class="qc-stats">
            <span class="qs me">👋 <b><?php echo htmlspecialchars($namaRapi); ?></b></span>
            <span class="qs"><?php echo date('d M Y', strtotime($shift_date)); ?> · <b><?php echo htmlspecialchars($shift_nama); ?></b></span>
            <span class="qs">Processing <b><?php echo $total_processing; ?></b></span>
            <span class="qs">Done <b><?php echo $total_done; ?></b></span>
            <?php if ($first_start): ?>
            <span class="qs">Start <b><?php echo $mulai_jam; ?></b></span>
            <span class="qs">Active <b><?php echo "{$aktif_jam}h {$aktif_mnt}m"; ?></b></span>
            <span class="qs">Ratio <b style="color:<?php echo $ratioWarna; ?>"><?php echo $ratio_personal; ?>%</b></span>
            <?php endif; ?>
        </div>
    </header>

    <div class="qc-layout">
        <nav class="qc-side">
            <a class="new" href="../operator/create_order.php"><?php echo $svg('<path d="M12 5v14M5 12h14"/>'); ?>Create Order</a>
            <div class="lbl">Orders</div>
            <a href="main_display.php?section=job" class="<?php echo $section === 'job' ? 'active' : ''; ?>"><?php echo $svg('<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>'); ?>Job Order<span class="n"><?php echo count($waiting); ?></span></a>
            <a href="main_display.php?section=progress" class="<?php echo $section === 'progress' ? 'active' : ''; ?>"><?php echo $svg('<circle cx="12" cy="12" r="9"/><polyline points="12,7 12,12 15,14"/>'); ?>On Progress<span class="n"><?php echo count($progress); ?></span></a>
            <a href="main_display.php?section=done" class="<?php echo $section === 'done' ? 'active' : ''; ?>"><?php echo $svg('<path d="M20 6L9 17l-5-5"/>'); ?>Done<span class="n"><?php echo count($done); ?></span></a>
            <div class="sp"></div>
            <a href="../menu.php"><?php echo $svg('<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>'); ?>Main Menu</a>
            <a class="out" href="../auth/logout.php"><?php echo $svg('<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/>'); ?>Logout</a>
        </nav>

        <main class="qc-main">
            <?php foreach ($pesan as $k => [$jenis, $teks]): if (isset($_GET[$k])): ?>
                <div class="qc-msg <?php echo $jenis; ?>"><?php echo $teks; ?></div>
            <?php endif; endforeach; ?>

            <div class="qc-head"><h1><?php echo $judul[0]; ?> <span>(<?php echo $judul[2]; ?>)</span></h1><span><?php echo $judul[1]; ?></span></div>

            <?php if ($section === 'done'):
                $dPrev = date('Y-m-d', strtotime("$done_date -1 day"));
                $dNext = date('Y-m-d', strtotime("$done_date +1 day"));
            ?>
            <form class="filterbar" method="GET">
                <input type="hidden" name="section" value="done">
                <div class="datenav">
                    <a class="nav-btn" href="?section=done&tgl=<?php echo $dPrev; ?>" title="Previous day" aria-label="Previous day">‹</a>
                    <label class="fctl" data-picker>
                        <span class="fctl-ico"><?php echo $svg('<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'); ?></span>
                        <span class="fctl-txt"><small>Work Day</small><strong><?php echo date('l, j F Y', strtotime($done_date)); ?></strong></span>
                        <input class="fctl-native" type="date" name="tgl" value="<?php echo $done_date; ?>" max="<?php echo $shift_date; ?>" onchange="this.form.submit()" tabindex="-1">
                    </label>
                    <a class="nav-btn <?php echo $done_date < $shift_date ? '' : 'off'; ?>" href="?section=done&tgl=<?php echo $dNext; ?>" title="Next day" aria-label="Next day">›</a>
                </div>
                <a class="chip-now <?php echo $done_date === $shift_date ? 'on' : ''; ?>" href="?section=done">Today</a>
            </form>
            <?php endif; ?>
            <div class="qc-grid">
                <?php
                if ($section === 'progress')  renderCards($progress, 'progress');
                elseif ($section === 'done')  renderCards($done_view, 'done');
                else                          renderCards($waiting, 'waiting');
                ?>
            </div>
        </main>
    </div>

    <div id="processModal" class="qc-modal">
        <form class="box" action="process_order.php" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="order_id" id="modal_order_id">
            <h3>Select QC machine</h3>
            <div class="sub" id="modalOrderText">-</div>
            <div class="info" id="modalInfo"></div>
            <div class="mgrid">
                <?php foreach (QC_MESIN_PENDEK as $val => $label): ?>
                <div class="mopt <?php echo $val === 'HARDNESS CHECK' ? 'wide' : ''; ?>">
                    <input type="radio" name="qc_machine" id="m-<?php echo md5($val); ?>" value="<?php echo $val; ?>" required>
                    <label for="m-<?php echo md5($val); ?>"><?php echo $val; ?><em></em></label>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="acts">
                <button type="button" class="qc-btn ghost" id="closeModal">Cancel</button>
                <button type="submit" class="qc-btn primary">▶ Start Machine</button>
            </div>
        </form>
    </div>

    <script>
        const modal          = document.getElementById('processModal');
        const modalOrderId   = document.getElementById('modal_order_id');
        const modalOrderText = document.getElementById('modalOrderText');
        const modalInfo      = document.getElementById('modalInfo');
        const closeModalBtn  = document.getElementById('closeModal');

        // Modal pilih mesin: mesin yang sudah selesai / sedang jalan dikunci,
        // dan diberi tahu bahwa mesin aktif akan otomatis ditandai selesai.
        document.querySelectorAll('.open-process-modal').forEach(btn => {
            btn.addEventListener('click', function () {
                const done    = JSON.parse(this.dataset.done || '[]');
                const running = this.dataset.running || '';
                const paused  = this.dataset.paused || '';
                modalOrderId.value         = this.dataset.orderId;
                modalOrderText.textContent = this.dataset.orderCode;
                document.querySelectorAll('.mopt input').forEach(r => {
                    const em = r.nextElementSibling.querySelector('em');
                    r.checked = false;
                    r.disabled = done.includes(r.value) || r.value === running;
                    em.className = done.includes(r.value) ? 'ok' : (r.value === running ? 'run' : '');
                    em.textContent = done.includes(r.value) ? '✓ done' : (r.value === running ? 'running' : (r.value === paused ? 'paused' : ''));
                });
                const aktif = running || paused;
                modalInfo.style.display = aktif ? 'block' : 'none';
                modalInfo.textContent   = aktif ? aktif + ' will be marked done ✓ when the next machine starts.' : '';
                modal.style.display = 'flex';
            });
        });

        closeModalBtn.addEventListener('click', () => modal.style.display = 'none');
        modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') modal.style.display = 'none'; });

        function formatTime(s) {
            return String(Math.floor(s / 3600)).padStart(2, '0') + ':' +
                   String(Math.floor((s % 3600) / 60)).padStart(2, '0') + ':' +
                   String(s % 60).padStart(2, '0');
        }

        function detikSejak(el, now) {
            const start = new Date(el.dataset.startTime.replace(' ', 'T'));
            return Math.max(0, Math.floor((now - start) / 1000)) + (parseInt(el.dataset.doneSec) || 0);
        }

        function updateLiveProgress() {
            const now = new Date(new Date() - timeDiff);
            document.querySelectorAll('.live-timer').forEach(el => {
                if (el.dataset.startTime) el.textContent = formatTime(detikSejak(el, now));
            });
            document.querySelectorAll('.live-progress-fill').forEach(el => {
                if (el.dataset.startTime) el.style.width = Math.min(100, Math.floor(detikSejak(el, now) / 3600 * 100)) + '%';
            });
            document.querySelectorAll('.live-total-timer').forEach(el => {
                el.textContent = el.dataset.startTime ? formatTime(detikSejak(el, now)) : formatTime(parseInt(el.dataset.doneSec) || 0);
            });
        }

        updateLiveProgress();
        setInterval(updateLiveProgress, 1000);
        setInterval(() => { if (modal.style.display !== 'flex') window.location.reload(); }, 60000);


        // Batas shift datang dari config/shift.php lewat PHP, jadi tidak perlu
        // disalin ulang di sini dan tidak bisa lagi melenceng dari perhitungan server.
        const shiftNama    = <?php echo json_encode($shift_nama); ?>;
        const shiftSelesai = new Date("<?php echo $shift_selesai; ?>".replace(' ', 'T'));

        // Peringatan akhir shift: toast di pojok kanan bawah (tidak menutupi topbar).
        // Kalau ditutup, tetap tertutup walau halaman auto-reload tiap menit
        // (disimpan di sessionStorage per shift).
        const kunciToast = 'shiftToast:' + shiftSelesai.getTime();
        function sudahDitutup() { try { return sessionStorage.getItem(kunciToast) === '1'; } catch (e) { return false; } }

        function cekShift() {
            const now     = new Date(new Date() - timeDiff);
            const selisih = Math.ceil((shiftSelesai - now) / 60000);
            let toast = document.getElementById('shift-toast');

            if (selisih > 0 && selisih <= 15 && !sudahDitutup()) {
                if (!toast) {
                    toast = document.createElement('div');
                    toast.id = 'shift-toast';
                    toast.className = 'shift-toast';
                    toast.setAttribute('role', 'alert');
                    toast.innerHTML = '<div class="st-ic">⏰</div><div class="st-tx"><b></b><span>Please finish or pause your sampling.</span></div>'
                                    + '<button type="button" aria-label="Dismiss">×</button>';
                    toast.querySelector('button').addEventListener('click', () => {
                        try { sessionStorage.setItem(kunciToast, '1'); } catch (e) {}
                        toast.remove();
                    });
                    document.body.appendChild(toast);
                }
                toast.classList.toggle('urgent', selisih <= 5);
                toast.querySelector('b').textContent = `${shiftNama} ends in ${selisih} minute${selisih === 1 ? '' : 's'}`;
                return;
            }
            if (toast) toast.remove();
        }

        cekShift();
        setInterval(cekShift, 15000);
    </script>
    <script src="../assets/components.js"></script>
</body>
</html>
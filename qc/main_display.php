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

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'qc') {
    header("Location: display.php");
    exit;
}

$section  = isset($_GET['section']) ? $_GET['section'] : 'job';
$today    = date('Y-m-d');
$user_id  = (int)$_SESSION['id'];

// ── Personal Operation Ratio Hari Ini ────────────────────────────────────────
$today_ratio_query = mysqli_query($conn, "
    SELECT
        MIN(sps.start_time) AS first_start,
        SUM(TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time)) AS total_aktif
    FROM sampling_process_steps sps
    WHERE sps.qc_user_id = $user_id
      AND DATE(sps.start_time) = '$today'
      AND sps.status = 'done'
      AND sps.end_time IS NOT NULL
");
$today_ratio = mysqli_fetch_assoc($today_ratio_query);

$first_start  = $today_ratio['first_start'] ?? null;
$total_aktif  = (int)($today_ratio['total_aktif'] ?? 0);

// Deteksi shift dari jam mulai pertama
$shift_nama   = '-';
$shift_detik  = 28800;
if ($first_start) {
    $h   = (int)date('H', strtotime($first_start));
    $m   = (int)date('i', strtotime($first_start));
    $tot = $h * 60 + $m;
    if ($tot >= 390 && $tot < 915) {
        $shift_nama  = 'Shift 1';
        $shift_detik = 28800;
    } elseif ($tot >= 915 && $tot < 1380) {
        $shift_nama  = 'Shift 2';
        $shift_detik = 27000;
    } else {
        $shift_nama  = 'Shift 3';
        $shift_detik = 24300;
    }
}

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
              AND s3.status = 'done'
        ) AS total_done_seconds,

        (
            SELECT u.nama
            FROM sampling_process_steps s4
            LEFT JOIN users u ON s4.qc_user_id = u.id
            WHERE s4.order_id = so.id
            ORDER BY s4.id DESC
            LIMIT 1
        ) AS qc_nama,

        MAX(CASE WHEN sps.qc_machine = 'CMM'              AND sps.status = 'done' THEN 1 ELSE 0 END) AS cmm_done,
        MAX(CASE WHEN sps.qc_machine = 'RONDCOM'          AND sps.status = 'done' THEN 1 ELSE 0 END) AS rondcom_done,
        MAX(CASE WHEN sps.qc_machine = 'ROUGHNESS'        AND sps.status = 'done' THEN 1 ELSE 0 END) AS roughness_done,
        MAX(CASE WHEN sps.qc_machine = 'CONTOUR'          AND sps.status = 'done' THEN 1 ELSE 0 END) AS contour_done,
        MAX(CASE WHEN sps.qc_machine = 'PROFIL PROJECTOR' AND sps.status = 'done' THEN 1 ELSE 0 END) AS profil_done,
        MAX(CASE WHEN sps.qc_machine = 'MANUAL'           AND sps.status = 'done' THEN 1 ELSE 0 END) AS manual_done,
        MAX(CASE WHEN sps.qc_machine = 'HARDNESS CHECK'   AND sps.status = 'done' THEN 1 ELSE 0 END) AS hardness_check_done,

        MAX(CASE WHEN sps.qc_machine = 'CMM'              AND sps.status = 'done' THEN TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) ELSE NULL END) AS cmm_durasi,
        MAX(CASE WHEN sps.qc_machine = 'RONDCOM'          AND sps.status = 'done' THEN TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) ELSE NULL END) AS rondcom_durasi,
        MAX(CASE WHEN sps.qc_machine = 'ROUGHNESS'        AND sps.status = 'done' THEN TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) ELSE NULL END) AS roughness_durasi,
        MAX(CASE WHEN sps.qc_machine = 'CONTOUR'          AND sps.status = 'done' THEN TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) ELSE NULL END) AS contour_durasi,
        MAX(CASE WHEN sps.qc_machine = 'PROFIL PROJECTOR' AND sps.status = 'done' THEN TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) ELSE NULL END) AS profil_durasi,
        MAX(CASE WHEN sps.qc_machine = 'MANUAL'           AND sps.status = 'done' THEN TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) ELSE NULL END) AS manual_durasi,
        MAX(CASE WHEN sps.qc_machine = 'HARDNESS CHECK'   AND sps.status = 'done' THEN TIMESTAMPDIFF(SECOND, sps.start_time, sps.end_time) ELSE NULL END) AS hardness_durasi

    FROM sampling_orders so
    JOIN master_parts mp ON so.part_id = mp.id
    JOIN master_lines ml ON so.line_id = ml.id
    JOIN master_machines mm ON so.machine_id = mm.id
    LEFT JOIN sampling_process_steps sps ON sps.order_id = so.id
";

$query_mine = mysqli_query($conn, $subquery . "
    WHERE so.created_by = $user_id
      AND so.status IN ('waiting', 'in_progress', 'partial_done')
    GROUP BY
        so.id, so.order_code, so.category, so.qty, so.status, so.created_at,
        mp.part_no, mp.part_name, ml.catalog_line, mm.machine_jig_catalog
    ORDER BY so.id DESC
");

$query_done = mysqli_query($conn, $subquery . "
    WHERE so.status = 'done'
      AND DATE(so.created_at) = '$today'
    GROUP BY
        so.id, so.order_code, so.category, so.qty, so.status, so.created_at,
        mp.part_no, mp.part_name, ml.catalog_line, mm.machine_jig_catalog
    ORDER BY so.id DESC
");

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

function renderCards(array $rows, string $mode = 'waiting') {
    $tz = new DateTimeZone('Asia/Jakarta');

    if (empty($rows)) {
        echo '<p class="empty-section">Belum ada data.</p>';
        return;
    }

    foreach ($rows as $row) {
        $elapsed = 0;
        $doneSec = (int)($row['total_done_seconds'] ?? 0);

        if (!empty($row['start_time'])) {
            $startDt = new DateTime($row['start_time'], $tz);
            $nowDt   = new DateTime('now', $tz);
            $current = $nowDt->getTimestamp() - $startDt->getTimestamp();
            if ($current < 0) $current = 0;
            $elapsed = $current + $doneSec;
        } else {
            $elapsed = $doneSec;
        }

        if ($mode === 'done') {
            $progressValue = 100;
        } elseif ($mode === 'progress' && !empty($row['start_time'])) {
            $progressValue = min(100, floor(($elapsed / 3600) * 100));
        } else {
            $progressValue = 0;
        }

        $hours     = floor($elapsed / 3600);
        $minutes   = floor(($elapsed % 3600) / 60);
        $seconds   = $elapsed % 60;
        $timerText = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

        $mesin_map = [
            'CMM'              => ['done' => $row['cmm_done'],          'durasi' => $row['cmm_durasi']],
            'RONDCOM'          => ['done' => $row['rondcom_done'],       'durasi' => $row['rondcom_durasi']],
            'ROUGHNESS'        => ['done' => $row['roughness_done'],     'durasi' => $row['roughness_durasi']],
            'CONTOUR'          => ['done' => $row['contour_done'],       'durasi' => $row['contour_durasi']],
            'PROFIL PROJECTOR' => ['done' => $row['profil_done'],        'durasi' => $row['profil_durasi']],
            'MANUAL'           => ['done' => $row['manual_done'],        'durasi' => $row['manual_durasi']],
            'HARDNESS CHECK'   => ['done' => $row['hardness_check_done'],'durasi' => $row['hardness_durasi']],
        ];
        ?>
        <div class="job-card">
            <div class="job-card-code"><?php echo htmlspecialchars($row['order_code']); ?></div>
            <div class="job-card-name"><?php echo htmlspecialchars($row['part_name']); ?></div>
            <div class="job-card-partno"><?php echo htmlspecialchars($row['part_no']); ?></div>

            <div class="job-card-row">
                <span>LINE</span>
                <strong><?php echo htmlspecialchars($row['catalog_line']); ?></strong>
            </div>
            <div class="job-card-row">
                <span>MACHINE/JIG</span>
                <strong><?php echo htmlspecialchars($row['machine_jig_catalog']); ?></strong>
            </div>
            <div class="job-card-row">
                <span>STATUS</span>
                <strong><?php echo strtoupper(htmlspecialchars($row['status'])); ?></strong>
            </div>
            <div class="job-card-row">
                <span>QC STAFF</span>
                <strong><?php echo $row['qc_nama'] ? htmlspecialchars($row['qc_nama']) : '-'; ?></strong>
            </div>
            <div class="job-card-row">
                <span>QC MACHINE</span>
                <strong><?php echo $row['qc_machine'] ? htmlspecialchars($row['qc_machine']) : '-'; ?></strong>
            </div>

            <?php foreach ($mesin_map as $nama => $val):
                $isDone  = (int)$val['done'] === 1;
                $durStr  = ($isDone && $val['durasi'] !== null) ? durStr((int)$val['durasi']) : '';
            ?>
            <div class="job-card-row">
                <span><?php echo $nama; ?></span>
                <strong>
                    <?php if ($isDone): ?>
                        <span class="check-done">✅</span>
                        <?php if ($durStr): ?>
                            <span style="font-size:10px;color:var(--text-soft);font-weight:400;margin-left:4px;"><?php echo $durStr; ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="check-pending">—</span>
                    <?php endif; ?>
                </strong>
            </div>
            <?php endforeach; ?>

            <?php if (!empty($row['start_time'])): ?>
            <div class="job-card-row">
                <span>START</span>
                <strong><?php echo htmlspecialchars($row['start_time']); ?></strong>
            </div>
            <?php endif; ?>

            <?php if ($mode === 'progress' && !empty($row['start_time'])): ?>
            <div class="job-card-row">
                <span>TIMER</span>
                <strong class="live-timer"
                    data-start-time="<?php echo htmlspecialchars($row['start_time']); ?>"
                    data-done-sec="<?php echo $doneSec; ?>">
                    <?php echo $timerText; ?>
                </strong>
            </div>
            <?php endif; ?>

            <div class="job-progress-wrap">
                <div class="job-progress-label">
                    PROGRESS
                    <span class="live-progress-text"
                        <?php if ($mode === 'progress' && !empty($row['start_time'])): ?>
                            data-start-time="<?php echo htmlspecialchars($row['start_time']); ?>"
                            data-done-sec="<?php echo $doneSec; ?>"
                        <?php endif; ?>>
                        <?php echo $progressValue; ?>
                    </span>%
                </div>
                <div class="job-progress-bar">
                    <div class="job-progress-fill <?php echo ($progressValue <= 30 ? 'progress-low' : ($progressValue <= 70 ? 'progress-mid' : 'progress-high')); ?> live-progress-fill"
                        <?php if ($mode === 'progress' && !empty($row['start_time'])): ?>
                            data-start-time="<?php echo htmlspecialchars($row['start_time']); ?>"
                            data-done-sec="<?php echo $doneSec; ?>"
                        <?php endif; ?>
                        style="width: <?php echo $progressValue; ?>%;"></div>
                </div>
            </div>

            <?php if ($mode === 'waiting'): ?>
                <button type="button"
                    class="job-btn job-btn-process open-process-modal"
                    data-order-id="<?php echo $row['id']; ?>"
                    data-order-code="<?php echo htmlspecialchars($row['order_code']); ?>">
                    PROCESS
                </button>

            <?php elseif ($mode === 'progress'): ?>
                <?php if (!empty($row['start_time'])): ?>
                <a href="finish_order.php?id=<?php echo $row['id']; ?>" class="job-btn job-btn-progress">
                    SELESAIKAN STEP
                </a>
                <?php endif; ?>

                <button type="button"
                    class="job-btn job-btn-process open-process-modal"
                    data-order-id="<?php echo $row['id']; ?>"
                    data-order-code="<?php echo htmlspecialchars($row['order_code']); ?>"
                    style="margin-top:8px;">
                    LANJUTKAN MESIN
                </button>

                <a href="final_done.php?id=<?php echo $row['id']; ?>" class="job-btn job-btn-done" style="margin-top:8px;">
                    FINAL DONE
                </a>

            <?php else: ?>
                <div class="job-btn job-btn-done">DONE</div>
            <?php endif; ?>
        </div>
        <?php
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Display QC</title>
    <link rel="stylesheet" href="../assets/style.css?v=22">

    <script>
    const serverTime = new Date("<?php echo date('Y-m-d H:i:s'); ?>");
    const clientTime = new Date();
    const timeDiff   = clientTime - serverTime; // selisih ms
    </script>

</head>
<body class="main-display-body">
    <div class="main-display-topbar">
    <div class="main-display-title">QC SAMPLING DISPLAY</div>
    <div class="main-display-info">
        <strong><?php echo htmlspecialchars($nama_login); ?></strong> |
        Tanggal : <strong><?php echo $date_now; ?></strong> |
        Processing : <strong><?php echo $total_processing; ?></strong> |
        Done : <strong><?php echo $total_done; ?></strong>
        <?php if ($first_start): ?>
        &nbsp;|&nbsp;
        <?php echo $shift_nama; ?> |
        Mulai: <strong><?php echo $mulai_jam; ?></strong> |
        Aktif: <strong><?php echo "{$aktif_jam}j {$aktif_mnt}m"; ?></strong> |
        Ratio: <strong style="color:<?php echo $ratio_personal >= 80 ? '#059669' : ($ratio_personal >= 50 ? '#f59e0b' : '#CC0000'); ?>">
            <?php echo $ratio_personal; ?>%
        </strong>
        <?php endif; ?>
    </div>
</div>

    <div class="main-display-layout">
        <aside class="main-display-sidebar">
            <div class="sidebar-title">QC MENU</div>
            <a href="../menu.php" class="sidebar-btn">BACK</a>
            <a href="../operator/create_order.php" class="sidebar-btn">CREATE ORDER</a>
            <div class="sidebar-divider"></div>
            <a href="main_display.php?section=job"      class="sidebar-btn <?php echo $section === 'job'      ? 'active' : ''; ?>">JOB ORDER</a>
            <a href="main_display.php?section=progress" class="sidebar-btn <?php echo $section === 'progress' ? 'active' : ''; ?>">ON PROGRESS</a>
            <a href="main_display.php?section=done"     class="sidebar-btn <?php echo $section === 'done'     ? 'active' : ''; ?>">DONE</a>
            <a href="history.php" class="sidebar-btn">HISTORY</a>
            <a href="../auth/logout.php" class="sidebar-btn sidebar-btn-danger">LOGOUT</a>
        </aside>

        <main class="main-display-content">
            <?php if (isset($_GET['error_machine'])): ?>
                <p class="error">Mesin QC tidak valid.</p>
            <?php endif; ?>
            <?php if (isset($_GET['error_order'])): ?>
                <p class="error">Order tidak ditemukan.</p>
            <?php endif; ?>
            <?php if (isset($_GET['insert_error'])): ?>
                <p class="error">Gagal memulai proses order.</p>
            <?php endif; ?>
            <?php if (isset($_GET['process_success'])): ?>
                <p class="success">Step mesin berhasil dimulai.</p>
            <?php endif; ?>
            <?php if (isset($_GET['finish_success'])): ?>
                <p class="success">Step mesin berhasil diselesaikan.</p>
            <?php endif; ?>
            <?php if (isset($_GET['final_done'])): ?>
                <p class="success">Order berhasil ditutup sebagai DONE.</p>
            <?php endif; ?>
            <?php if (isset($_GET['machine_done'])): ?>
                <p class="error">Mesin itu sudah selesai untuk order ini.</p>
            <?php endif; ?>
            <?php if (isset($_GET['already_progress'])): ?>
                <p class="error">Mesin itu sedang dikerjakan untuk order ini.</p>
            <?php endif; ?>

            <?php if ($section === 'job'): ?>
                <section class="display-section">
                    <div class="section-header section-header-waiting">JOB ORDER — <?php echo htmlspecialchars($nama_login); ?></div>
                    <div class="job-grid">
                        <?php renderCards($waiting, 'waiting'); ?>
                    </div>
                </section>
            <?php elseif ($section === 'progress'): ?>
                <section class="display-section">
                    <div class="section-header section-header-progress">ON PROGRESS — <?php echo htmlspecialchars($nama_login); ?></div>
                    <div class="job-grid">
                        <?php renderCards($progress, 'progress'); ?>
                    </div>
                </section>
            <?php elseif ($section === 'done'): ?>
                <section class="display-section">
                    <div class="section-header section-header-done">DONE — SEMUA STAFF</div>
                    <div class="job-grid">
                        <?php renderCards($done, 'done'); ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <div id="processModal" class="modal-overlay">
        <div class="modal-box">
            <h3>Pilih Mesin QC</h3>
            <p id="modalOrderText">Order: -</p>

            <form action="process_order.php" method="POST">
                <input type="hidden" name="order_id" id="modal_order_id">

                <label>Pilih Mesin QC</label>
                <select name="qc_machine" required>
                    <option value="">-- Pilih Mesin QC --</option>
                    <option value="CMM">CMM</option>
                    <option value="RONDCOM">RONDCOM</option>
                    <option value="ROUGHNESS">ROUGHNESS</option>
                    <option value="CONTOUR">CONTOUR</option>
                    <option value="PROFIL PROJECTOR">PROFIL PROJECTOR</option>
                    <option value="MANUAL">MANUAL</option>
                    <option value="HARDNESS CHECK">HARDNESS CHECK</option>
                </select>

                <div class="modal-actions">
                    <button type="submit" class="btn">Lanjut Process</button>
                    <button type="button" class="btn btn-danger" id="closeModal">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal          = document.getElementById('processModal');
        const modalOrderId   = document.getElementById('modal_order_id');
        const modalOrderText = document.getElementById('modalOrderText');
        const closeModalBtn  = document.getElementById('closeModal');

        document.querySelectorAll('.open-process-modal').forEach(btn => {
            btn.addEventListener('click', function () {
                modalOrderId.value         = this.getAttribute('data-order-id');
                modalOrderText.textContent = 'Order: ' + this.getAttribute('data-order-code');
                modal.style.display        = 'flex';
            });
        });

        closeModalBtn.addEventListener('click', () => modal.style.display = 'none');
        window.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

        function formatTime(s) {
            return String(Math.floor(s / 3600)).padStart(2, '0') + ':' +
                   String(Math.floor((s % 3600) / 60)).padStart(2, '0') + ':' +
                   String(s % 60).padStart(2, '0');
        }

        function updateLiveProgress() {
            const now = new Date(new Date() - timeDiff);

            document.querySelectorAll('.live-timer').forEach(el => {
                const start   = new Date(el.dataset.startTime.replace(' ', 'T'));
                const doneSec = parseInt(el.dataset.doneSec) || 0;
                let current   = Math.floor((now - start) / 1000);
                if (current < 0) current = 0;
                el.textContent = formatTime(current + doneSec);
            });

            document.querySelectorAll('.live-progress-text').forEach(el => {
                if (!el.dataset.startTime) return;
                const start   = new Date(el.dataset.startTime.replace(' ', 'T'));
                const doneSec = parseInt(el.dataset.doneSec) || 0;
                let current   = Math.floor((now - start) / 1000);
                if (current < 0) current = 0;
                const total   = current + doneSec;
                el.textContent = Math.min(100, Math.floor((total / 3600) * 100));
            });

            document.querySelectorAll('.live-progress-fill').forEach(el => {
                if (!el.dataset.startTime) return;
                const start   = new Date(el.dataset.startTime.replace(' ', 'T'));
                const doneSec = parseInt(el.dataset.doneSec) || 0;
                let current   = Math.floor((now - start) / 1000);
                if (current < 0) current = 0;
                const total    = current + doneSec;
                const progress = Math.min(100, Math.floor((total / 3600) * 100));
                el.style.width = progress + '%';
                el.classList.remove('progress-low', 'progress-mid', 'progress-high');
                el.classList.add(progress <= 30 ? 'progress-low' : progress <= 70 ? 'progress-mid' : 'progress-high');
            });
        }

        updateLiveProgress();
        setInterval(updateLiveProgress, 1000);
        setInterval(() => { if (modal.style.display !== 'flex') window.location.reload(); }, 60000);
    </script>
</body>
</html>
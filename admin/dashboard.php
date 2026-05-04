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

$section = isset($_GET['section']) ? $_GET['section'] : 'job';
$today = date('Y-m-d');

$query = mysqli_query($conn, "
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
            SELECT u.nama
            FROM sampling_process_steps s3
            LEFT JOIN users u ON s3.qc_user_id = u.id
            WHERE s3.order_id = so.id
            ORDER BY s3.id DESC
            LIMIT 1
        ) AS qc_nama,

        MAX(CASE WHEN sps.qc_machine = 'CMM'              AND sps.status = 'done' THEN 1 ELSE 0 END) AS cmm_done,
        MAX(CASE WHEN sps.qc_machine = 'RONDCOM'          AND sps.status = 'done' THEN 1 ELSE 0 END) AS rondcom_done,
        MAX(CASE WHEN sps.qc_machine = 'ROUGHNESS'        AND sps.status = 'done' THEN 1 ELSE 0 END) AS roughness_done,
        MAX(CASE WHEN sps.qc_machine = 'CONTOUR'          AND sps.status = 'done' THEN 1 ELSE 0 END) AS contour_done,
        MAX(CASE WHEN sps.qc_machine = 'PROFIL PROJECTOR' AND sps.status = 'done' THEN 1 ELSE 0 END) AS profil_done,
        MAX(CASE WHEN sps.qc_machine = 'MANUAL'           AND sps.status = 'done' THEN 1 ELSE 0 END) AS manual_done,
        MAX(CASE WHEN sps.qc_machine = 'HARDNESS CHECK'   AND sps.status = 'done' THEN 1 ELSE 0 END) AS hardness_check_done

    FROM sampling_orders so
    JOIN master_parts mp ON so.part_id = mp.id
    JOIN master_lines ml ON so.line_id = ml.id
    JOIN master_machines mm ON so.machine_id = mm.id
    LEFT JOIN sampling_process_steps sps ON sps.order_id = so.id
    WHERE (
        DATE(so.created_at) = '$today'
        OR so.status IN ('waiting', 'in_progress', 'partial_done')
    )
    GROUP BY
        so.id, so.order_code, so.category, so.qty, so.status, so.created_at,
        mp.part_no, mp.part_name, ml.catalog_line, mm.machine_jig_catalog
    ORDER BY so.id DESC
");

$waiting  = [];
$progress = [];
$done     = [];

while ($row = mysqli_fetch_assoc($query)) {
    if ($row['status'] === 'waiting') {
        $waiting[] = $row;
    } elseif ($row['status'] === 'in_progress' || $row['status'] === 'partial_done') {
        $progress[] = $row;
    } elseif ($row['status'] === 'done') {
        $done[] = $row;
    }
}

$total_sampling   = count($waiting) + count($progress) + count($done);
$total_processing = count($progress);
$total_done       = count($done);
$date_now         = date('d F Y');

function renderCards(array $rows, string $mode = 'waiting') {
    $tz = new DateTimeZone('Asia/Jakarta');

    if (empty($rows)) {
        echo '<p class="empty-section">Belum ada data.</p>';
        return;
    }

    foreach ($rows as $row) {
        $elapsed = 0;

        if (!empty($row['start_time'])) {
            $startDt = new DateTime($row['start_time'], $tz);
            $nowDt   = new DateTime('now', $tz);
            $elapsed = $nowDt->getTimestamp() - $startDt->getTimestamp();
            if ($elapsed < 0) $elapsed = 0;
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

        if ($mode === 'progress' && !empty($row['start_time']) && $elapsed >= 3600) {
            $progressValue = 100;
            $timerText     = '01:00:00';
        }
        ?>
        <div class="job-card">
            <div class="job-card-code">
                <?php echo htmlspecialchars($row['order_code']); ?>
            </div>

            <div class="job-card-name">
                <?php echo htmlspecialchars($row['part_name']); ?>
            </div>

            <div class="job-card-partno">
                <?php echo htmlspecialchars($row['part_no']); ?>
            </div>

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

            <div class="job-card-row">
                <span>CMM</span>
                <strong><?php echo ((int)$row['cmm_done'] === 1) ? '<span class="check-done">✅</span>' : '<span class="check-pending">—</span>'; ?></strong>
            </div>

            <div class="job-card-row">
                <span>RONDCOM</span>
                <strong><?php echo ((int)$row['rondcom_done'] === 1) ? '<span class="check-done">✅</span>' : '<span class="check-pending">—</span>'; ?></strong>
            </div>

            <div class="job-card-row">
                <span>ROUGHNESS</span>
                <strong><?php echo ((int)$row['roughness_done'] === 1) ? '<span class="check-done">✅</span>' : '<span class="check-pending">—</span>'; ?></strong>
            </div>

            <div class="job-card-row">
                <span>CONTOUR</span>
                <strong><?php echo ((int)$row['contour_done'] === 1) ? '<span class="check-done">✅</span>' : '<span class="check-pending">—</span>'; ?></strong>
            </div>

            <div class="job-card-row">
                <span>PROFIL PROJECTOR</span>
                <strong><?php echo ((int)$row['profil_done'] === 1) ? '<span class="check-done">✅</span>' : '<span class="check-pending">—</span>'; ?></strong>
            </div>

            <div class="job-card-row">
                <span>MANUAL</span>
                <strong><?php echo ((int)$row['manual_done'] === 1) ? '<span class="check-done">✅</span>' : '<span class="check-pending">—</span>'; ?></strong>
            </div>

            <div class="job-card-row">
                <span>HARDNESS CHECK</span>
                <strong><?php echo ((int)$row['hardness_check_done'] === 1) ? '<span class="check-done">✅</span>' : '<span class="check-pending">—</span>'; ?></strong>
            </div>

            <?php if (!empty($row['start_time'])): ?>
            <div class="job-card-row">
                <span>START</span>
                <strong><?php echo htmlspecialchars($row['start_time']); ?></strong>
            </div>
            <?php endif; ?>

            <?php if ($mode === 'progress' && !empty($row['start_time'])): ?>
            <div class="job-card-row">
                <span>TIMER</span>
                <strong class="live-timer" data-start-time="<?php echo htmlspecialchars($row['start_time']); ?>">
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
                        <?php endif; ?>>
                        <?php echo $progressValue; ?>
                    </span>%
                </div>
                <div class="job-progress-bar">
                    <div class="job-progress-fill <?php echo ($progressValue <= 30 ? 'progress-low' : ($progressValue <= 70 ? 'progress-mid' : 'progress-high')); ?> live-progress-fill"
                        <?php if ($mode === 'progress' && !empty($row['start_time'])): ?>
                            data-start-time="<?php echo htmlspecialchars($row['start_time']); ?>"
                        <?php endif; ?>
                        style="width: <?php echo $progressValue; ?>%;"></div>
                </div>
            </div>

            <?php if ($mode === 'waiting'): ?>
                <button
                    type="button"
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

                <button
                    type="button"
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
</head>
<body class="main-display-body">
    <div class="main-display-topbar">
        <div class="main-display-title">QC SAMPLING DISPLAY</div>
        <div class="main-display-info">
            Tanggal : <strong><?php echo $date_now; ?></strong> |
            Total Sampling : <strong><?php echo $total_sampling; ?></strong> |
            Processing : <strong><?php echo $total_processing; ?></strong> |
            Done : <strong><?php echo $total_done; ?></strong>
        </div>
    </div>

    <div class="main-display-layout">
        <aside class="main-display-sidebar">
            <div class="sidebar-title">QC MENU</div>
            <a href="../menu.php" class="sidebar-btn">BACK</a>
            <a href="display.php" class="sidebar-btn">ORDER REQUEST</a>
            <div class="sidebar-divider"></div>
            <a href="main_display.php?section=job"      class="sidebar-btn <?php echo $section === 'job'      ? 'active' : ''; ?>">JOB ORDER</a>
            <a href="main_display.php?section=progress" class="sidebar-btn <?php echo $section === 'progress' ? 'active' : ''; ?>">ON PROGRESS</a>
            <a href="main_display.php?section=done"     class="sidebar-btn <?php echo $section === 'done'     ? 'active' : ''; ?>">DONE</a>
            <a href="history.php" class="sidebar-btn">HISTORY</a>
            <a href="../auth/logout.php" class="sidebar-btn sidebar-btn-danger">LOGOUT</a>
        </aside>

        <main class="main-display-content">
            <?php if (isset($_GET['error_qc'])): ?>
                <p class="error">NIK atau password QC salah.</p>
            <?php endif; ?>
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
                    <div class="section-header section-header-waiting">JOB ORDER</div>
                    <div class="job-grid">
                        <?php renderCards($waiting, 'waiting'); ?>
                    </div>
                </section>
            <?php elseif ($section === 'progress'): ?>
                <section class="display-section">
                    <div class="section-header section-header-progress">ON PROGRESS</div>
                    <div class="job-grid">
                        <?php renderCards($progress, 'progress'); ?>
                    </div>
                </section>
            <?php elseif ($section === 'done'): ?>
                <section class="display-section">
                    <div class="section-header section-header-done">DONE</div>
                    <div class="job-grid">
                        <?php renderCards($done, 'done'); ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal -->
    <div id="processModal" class="modal-overlay">
        <div class="modal-box">
            <h3>Verifikasi QC Staff</h3>
            <p id="modalOrderText">Order: -</p>

            <form action="process_order.php" method="POST">
                <input type="hidden" name="order_id" id="modal_order_id">

                <label>NIK QC</label>
                <input type="text" name="nik" required>

                <label>Password</label>
                <input type="password" name="password" required>

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
        const modal         = document.getElementById('processModal');
        const modalOrderId  = document.getElementById('modal_order_id');
        const modalOrderText= document.getElementById('modalOrderText');
        const closeModalBtn = document.getElementById('closeModal');
        const openButtons   = document.querySelectorAll('.open-process-modal');

        openButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                modalOrderId.value        = this.getAttribute('data-order-id');
                modalOrderText.textContent = 'Order: ' + this.getAttribute('data-order-code');
                modal.style.display       = 'flex';
            });
        });

        closeModalBtn.addEventListener('click', () => modal.style.display = 'none');
        window.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

        function formatTime(s) {
            return String(Math.floor(s/3600)).padStart(2,'0') + ':' +
                   String(Math.floor((s%3600)/60)).padStart(2,'0') + ':' +
                   String(s%60).padStart(2,'0');
        }

        function updateLiveProgress() {
            const now = new Date();

            document.querySelectorAll('.live-timer').forEach(el => {
                const start = new Date(el.dataset.startTime.replace(' ', 'T'));
                let elapsed = Math.floor((now - start) / 1000);
                if (elapsed < 0) elapsed = 0;
                if (elapsed > 3600) elapsed = 3600;
                el.textContent = formatTime(elapsed);
            });

            document.querySelectorAll('.live-progress-text').forEach(el => {
                if (!el.dataset.startTime) return;
                const start = new Date(el.dataset.startTime.replace(' ', 'T'));
                let elapsed = Math.floor((now - start) / 1000);
                if (elapsed < 0) elapsed = 0;
                if (elapsed > 3600) elapsed = 3600;
                el.textContent = Math.floor((elapsed / 3600) * 100);
            });

            document.querySelectorAll('.live-progress-fill').forEach(el => {
                if (!el.dataset.startTime) return;
                const start = new Date(el.dataset.startTime.replace(' ', 'T'));
                let elapsed = Math.floor((now - start) / 1000);
                if (elapsed < 0) elapsed = 0;
                if (elapsed > 3600) elapsed = 3600;
                const progress = Math.floor((elapsed / 3600) * 100);
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
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

$selected_date = isset($_GET['tanggal']) && $_GET['tanggal'] !== ''
    ? $_GET['tanggal']
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
        so.id,
        so.order_code,
        so.category,
        so.status,
        so.created_at,
        mp.part_no,
        mp.part_name,
        ml.catalog_line,
        mm.machine_jig_catalog,

        MAX(CASE WHEN sps.qc_machine = 'CMM' AND sps.status = 'done' THEN 1 ELSE 0 END) AS cmm_done,
        MAX(CASE WHEN sps.qc_machine = 'RUNCOM' AND sps.status = 'done' THEN 1 ELSE 0 END) AS runcom_done,
        MAX(CASE WHEN sps.qc_machine = 'ROUGHNESS' AND sps.status = 'done' THEN 1 ELSE 0 END) AS roughness_done,
        MAX(CASE WHEN sps.qc_machine = 'PROFIL' AND sps.status = 'done' THEN 1 ELSE 0 END) AS profil_done,
        MAX(CASE WHEN sps.qc_machine = 'MANUAL' AND sps.status = 'done' THEN 1 ELSE 0 END) AS manual_done,
        MAX(CASE WHEN sps.qc_machine = 'CONTOURE' AND sps.status = 'done' THEN 1 ELSE 0 END) AS contoure_done,

        MIN(sps.start_time) AS latest_start_time,
        MAX(sps.end_time) AS latest_end_time,
        MAX(u.nama) AS qc_nama
    FROM sampling_orders so
    JOIN master_parts mp ON so.part_id = mp.id
    JOIN master_lines ml ON so.line_id = ml.id
    JOIN master_machines mm ON so.machine_id = mm.id
    LEFT JOIN sampling_process_steps sps ON so.id = sps.order_id
    LEFT JOIN users u ON sps.qc_user_id = u.id
    WHERE DATE(so.created_at) = '$selected_date'
    $whereStatus
    GROUP BY
        so.id, so.order_code, so.category, so.status, so.created_at,
        mp.part_no, mp.part_name, ml.catalog_line, mm.machine_jig_catalog
    ORDER BY so.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History QC</title>
    <link rel="stylesheet" href="../assets/style.css?v=21">
</head>
<body class="history-page">
    <div class="history-container">
        <h2>History QC</h2>

        <form method="GET" class="history-filter-form">
            <label>Pilih Tanggal</label>
            <input type="date" name="tanggal" value="<?php echo htmlspecialchars($selected_date); ?>" required>

            <label>Filter Status</label>
            <select name="status">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Semua</option>
                <option value="waiting" <?php echo $status_filter === 'waiting' ? 'selected' : ''; ?>>Order Request</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress / Partial</option>
                <option value="done" <?php echo $status_filter === 'done' ? 'selected' : ''; ?>>Done</option>
            </select>

            <div class="history-filter-actions">
                <button type="submit">Tampilkan</button>
                <button type="button" class="btn" id="openExportModal">Export Excel</button>
                <a class="btn" href="main_display.php?section=job">Kembali ke Main Display</a>
            </div>
        </form>

        <br>

        <div class="history-table-wrap">
            <table class="history-table">
                <thead>
                    <tr>
                        <th class="col-order">Order Code</th>
                        <th class="col-part">Part</th>
                        <th class="col-line">Line</th>
                        <th class="col-machine">Machine/Jig</th>
                        <th class="col-staff">QC Staff</th>
                        <th class="col-check">CMM</th>
                        <th class="col-check">RUNCOM</th>
                        <th class="col-check">ROUGHNESS</th>
                        <th class="col-check">CONTOURE</th>
                        <th class="col-check">PROFIL</th>
                        <th class="col-check">MANUAL</th>
                        <th class="col-datetime">Start</th>
                        <th class="col-datetime">Finish</th>
                        <th class="col-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($query)): ?>
                            <tr>
                                <td class="col-order"><?php echo htmlspecialchars($row['order_code']); ?></td>

                                <td class="col-part">
                                    <div class="history-part-name"><?php echo htmlspecialchars($row['part_name']); ?></div>
                                    <div class="history-part-no"><?php echo htmlspecialchars($row['part_no']); ?></div>
                                </td>

                                <td class="col-line"><?php echo htmlspecialchars($row['catalog_line']); ?></td>
                                <td class="col-machine"><?php echo htmlspecialchars($row['machine_jig_catalog']); ?></td>
                                <td class="col-staff"><?php echo $row['qc_nama'] ? htmlspecialchars($row['qc_nama']) : '-'; ?></td>

                                <td class="col-check">
                                    <?php echo ((int)$row['cmm_done'] === 1) ? '✅' : '⬜'; ?>
                                </td>

                                <td class="col-check">
                                    <?php echo ((int)$row['runcom_done'] === 1) ? '✅' : '⬜'; ?>
                                </td>

                                <td class="col-check">
                                    <?php echo ((int)$row['roughness_done'] === 1) ? '✅' : '⬜'; ?>
                                </td>

                                <td class="col-check">
                                    <?php echo ((int)$row['contoure_done'] === 1) ? '✅' : '⬜'; ?>
                                </td>

                                <td class="col-check">
                                    <?php echo ((int)$row['profil_done'] === 1) ? '✅' : '⬜'; ?>
                                </td>
                                
                                <td class="col-check">
                                    <?php echo ((int)$row['manual_done'] === 1) ? '✅' : '⬜'; ?>
                                </td>

                                <td class="col-datetime">
                                    <?php echo $row['latest_start_time'] ? htmlspecialchars($row['latest_start_time']) : '-'; ?>
                                </td>

                                <td class="col-datetime">
                                    <?php echo $row['latest_end_time'] ? htmlspecialchars($row['latest_end_time']) : '-'; ?>
                                </td>

                                <td class="col-status">
                                    <?php
                                    if ($row['status'] == 'waiting') {
                                        echo '<span class="status-badge status-waiting">ORDER REQUEST</span>';
                                    } elseif ($row['status'] == 'in_progress' || $row['status'] == 'partial_done') {
                                        echo '<span class="status-badge status-progress">IN PROGRESS</span>';
                                    } elseif ($row['status'] == 'done') {
                                        echo '<span class="status-badge status-done">DONE</span>';
                                    } else {
                                        echo htmlspecialchars($row['status']);
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13" class="history-empty">Tidak ada data pada tanggal ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="exportModal" class="modal-overlay">
        <div class="modal-box">
            <h3>Export History QC</h3>
            <p>Pilih range tanggal untuk export.</p>

            <form action="export_history.php" method="GET">
                <label>Tanggal Dari</label>
                <input type="date" name="tanggal_dari" value="<?php echo htmlspecialchars($selected_date); ?>" required>

                <label>Tanggal Sampai</label>
                <input type="date" name="tanggal_sampai" value="<?php echo htmlspecialchars($selected_date); ?>" required>

                <label>Filter Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Semua</option>
                    <option value="waiting" <?php echo $status_filter === 'waiting' ? 'selected' : ''; ?>>Order Request</option>
                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress / Partial</option>
                    <option value="done" <?php echo $status_filter === 'done' ? 'selected' : ''; ?>>Done</option>
                </select>

                <div class="modal-actions">
                    <button type="submit" class="btn">Download Excel (.xlsx)</button>
                    <button type="button" class="btn btn-danger" id="closeExportModal">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const exportModal = document.getElementById('exportModal');
        const openExportModal = document.getElementById('openExportModal');
        const closeExportModal = document.getElementById('closeExportModal');

        openExportModal.addEventListener('click', function () {
            exportModal.style.display = 'flex';
        });

        closeExportModal.addEventListener('click', function () {
            exportModal.style.display = 'none';
        });

        window.addEventListener('click', function (e) {
            if (e.target === exportModal) {
                exportModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>
<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$is_qc = isset($_SESSION['role']) && $_SESSION['role'] === 'qc';

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
        mm.machine_jig_catalog
    FROM sampling_orders so
    JOIN master_parts mp ON so.part_id = mp.id
    JOIN master_lines ml ON so.line_id = ml.id
    JOIN master_machines mm ON so.machine_id = mm.id
    ORDER BY 
        CASE 
            WHEN so.status = 'waiting' THEN 1
            WHEN so.status = 'in_progress' THEN 2
            WHEN so.status = 'partial_done' THEN 3
            WHEN so.status = 'done' THEN 4
            ELSE 5
        END,
        so.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Request</title>
    <link rel="stylesheet" href="../assets/style.css?v=21">
</head>

<body class="history-page"></body>
    <div class="container">
        <h2>Order Request</h2>

        <?php if (isset($_GET['success']) && isset($_GET['order_code'])): ?>
            <p class="success">
                Order <strong><?php echo htmlspecialchars($_GET['order_code']); ?></strong> berhasil dibuat dan masuk ke request QC.
            </p>
        <?php endif; ?>

        <?php if (isset($_GET['delete_success'])): ?>
            <p class="success">Order berhasil dihapus.</p>
        <?php endif; ?>

        <?php if (isset($_GET['delete_error'])): ?>
            <p class="error">Order gagal dihapus.</p>
        <?php endif; ?>

        <a class="btn" href="../menu.php">Kembali ke Menu</a>

        <?php if ($is_qc): ?>
            <a class="btn" href="main_display.php?section=job">Main Display QC</a>
        <?php endif; ?>

        <br><br>

        <table>
            <tr>
                <th>Order Code</th>
                <th>Category</th>
                <th>Part No</th>
                <th>Part Name</th>
                <th>Catalog Line</th>
                <th>Machine/Jig</th>
                <th>Qty</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Aksi</th>
            </tr>

            <?php while($row = mysqli_fetch_assoc($query)): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['order_code']); ?></td>
                <td><?php echo htmlspecialchars($row['category']); ?></td>
                <td><?php echo htmlspecialchars($row['part_no']); ?></td>
                <td><?php echo htmlspecialchars($row['part_name']); ?></td>
                <td><?php echo htmlspecialchars($row['catalog_line']); ?></td>
                <td><?php echo htmlspecialchars($row['machine_jig_catalog']); ?></td>
                <td><?php echo htmlspecialchars($row['qty']); ?></td>
                <td>
                    <?php
                    if ($row['status'] == 'waiting') {
                        echo '<span class="status-badge status-waiting">ORDER REQUEST</span>';
                    } elseif ($row['status'] == 'in_progress') {
                        echo '<span class="status-badge status-progress">IN PROGRESS</span>';
                    } elseif ($row['status'] == 'partial_done') {
                        echo '<span class="status-badge status-progress">PARTIAL DONE</span>';
                    } elseif ($row['status'] == 'done') {
                        echo '<span class="status-badge status-done">DONE</span>';
                    } else {
                        echo htmlspecialchars($row['status']);
                    }
                    ?>
                </td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td>
                    <?php if ($row['status'] == 'waiting'): ?>
                        <a class="btn btn-danger"
                           href="delete_order.php?id=<?php echo $row['id']; ?>"
                           onclick="return confirm('Yakin mau hapus order ini?');">
                           Hapus
                        </a>
                    <?php else: ?>
                        <span>-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
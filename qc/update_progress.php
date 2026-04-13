<?php
session_start();
include '../config/koneksi.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

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
    ORDER BY so.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Display QC</title>
    <link rel="stylesheet" href="../assets/style.css">
    <meta http-equiv="refresh" content="5">
</head>
<body>
    <div class="container">
        <h2>Display QC</h2>

        <a class="btn" href="../menu.php">Kembali ke Menu</a>
        <br><br>

        <table border="1" cellpadding="10" cellspacing="0" width="100%">
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
            </tr>

            <?php while($row = mysqli_fetch_assoc($query)): ?>
            <tr>
                <td><?php echo $row['order_code']; ?></td>
                <td><?php echo $row['category']; ?></td>
                <td><?php echo $row['part_no']; ?></td>
                <td><?php echo $row['part_name']; ?></td>
                <td><?php echo $row['catalog_line']; ?></td>
                <td><?php echo $row['machine_jig_catalog']; ?></td>
                <td><?php echo $row['qty']; ?></td>
                <td><?php echo $row['status']; ?></td>
                <td><?php echo $row['created_at']; ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
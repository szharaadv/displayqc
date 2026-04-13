<?php
session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'operator' && $_SESSION['role'] != 'admin')) {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Operator</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="container">
        <h2>Dashboard Operator</h2>
        <p>Login sebagai: <strong><?php echo $_SESSION['nik']; ?></strong></p>

        <a class="btn" href="create_order.php">Buat Order Sampling</a>
        <a class="btn btn-danger" href="../auth/logout.php">Logout</a>
    </div>
</body>
</html>
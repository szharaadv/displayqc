<?php
include 'config/koneksi.php';
mysqli_query($conn, "SET time_zone = '+07:00'");

$q = mysqli_fetch_row(mysqli_query($conn, "SELECT NOW()"));
echo "MySQL NOW(): " . $q[0] . "<br>";
echo "PHP date(): " . date('Y-m-d H:i:s') . "<br>";
echo "Waktu lokal kamu: (lihat jam komputer)";
?>
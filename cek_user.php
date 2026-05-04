<?php
include 'config/koneksi.php';

$result = mysqli_query($conn, "SELECT nik, nama, password, role, status FROM users LIMIT 10");
while ($row = mysqli_fetch_assoc($result)) {
    echo "NIK: {$row['nik']} | Nama: {$row['nama']} | Role: {$row['role']} | Status: {$row['status']}<br>";
    echo "Password: {$row['password']}<br>";
    echo "Sudah hash? " . (strlen($row['password']) >= 60 ? '<b style="color:green">YA</b>' : '<b style="color:red">BELUM (masih plain text)</b>') . "<br><hr>";
}
?>
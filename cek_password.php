<?php
include 'config/koneksi.php';

// Cek nama database yang aktif
$dbResult = mysqli_query($conn, "SELECT DATABASE() AS db_name");
$db = mysqli_fetch_assoc($dbResult);
echo "Database aktif: <b>{$db['db_name']}</b><br><br>";

// Cek password user Zainuddin
$result = mysqli_query($conn, "SELECT id, nik, nama, password FROM users WHERE nik = '2019092'");
$user = mysqli_fetch_assoc($result);
echo "NIK: {$user['nik']}<br>";
echo "Nama: {$user['nama']}<br>";
echo "Password: {$user['password']}<br>";
echo "Sudah hash? " . (strlen($user['password']) >= 60 ? '<b style="color:green">YA</b>' : '<b style="color:red">BELUM</b>');
?>
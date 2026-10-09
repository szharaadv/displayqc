<?php
/**
 * Lookup nama dari NIK untuk sapaan "Hi, (nama)" di halaman login.
 * Hanya mengembalikan nama depan + role user AKTIF — tidak ada data lain.
 * Read-only (GET), jadi tanpa CSRF.
 */
include '../config/koneksi.php';
/** @var mysqli $conn */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$nik = trim($_GET['nik'] ?? '');
if (!preg_match('/^[A-Za-z0-9._-]{3,20}$/', $nik)) {
    echo json_encode(['found' => false]);
    exit;
}

$nikE = mysqli_real_escape_string($conn, $nik);
$u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama, role FROM users WHERE nik = '$nikE' AND status = 1 LIMIT 1"));
if (!$u) {
    echo json_encode(['found' => false]);
    exit;
}

$ROLE = ['admin' => 'Manager', 'foreman' => 'Foreman', 'qc' => 'QC Staff', 'operator' => 'Operator'];
echo json_encode([
    'found' => true,
    'nama'  => $u['nama'],
    'role'  => $ROLE[$u['role']] ?? ucfirst($u['role']),
]);

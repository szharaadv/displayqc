<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/csrf.php';
include '../config/foreman.php';
/** @var mysqli $conn */

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: users.php");
    exit;
}
csrfVerify();

function balik(string $jenis, string $pesan): void {
    header("Location: users.php?$jenis=" . urlencode($pesan));
    exit;
}

// ── Hapus permanen: hanya user tanpa riwayat, dan bukan diri sendiri ─────
if (($_POST['aksi'] ?? '') === 'hapus') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$_SESSION['id']) balik('err', 'You cannot delete your own account.');
    $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama, role FROM users WHERE id = $id"));
    if (!$u) balik('err', 'User not found.');
    $ref = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM (" . USER_REF_SQL . ") r WHERE uid = $id"));
    if ((int)$ref[0]) balik('err', "{$u['nama']} has history — cannot be deleted, deactivate instead.");
    if ($u['role'] === 'foreman') mysqli_query($conn, "UPDATE users SET foreman_id = NULL WHERE foreman_id = $id");
    mysqli_query($conn, "DELETE FROM users WHERE id = $id");
    balik('ok', "User {$u['nama']} deleted.");
}

$id        = (int)($_POST['id'] ?? 0);
$nik       = trim($_POST['nik'] ?? '');
$nama      = trim($_POST['nama'] ?? '');
$role      = $_POST['role'] ?? '';
$foremanId = (int)($_POST['foreman_id'] ?? 0);
$status    = ($_POST['status'] ?? '1') === '0' ? 0 : 1;
$password  = (string)($_POST['password'] ?? '');

// Admin yang sedang login: field role/status di-disable di form (tidak terkirim),
// jadi paksa tetap admin & aktif supaya tidak mengunci diri sendiri.
if ($id === (int)$_SESSION['id']) {
    $role   = 'admin';
    $status = 1;
}

if ($nik === '' || $nama === '') balik('err', 'NIK and name are required.');
if (!preg_match('/^[A-Za-z0-9._-]{1,20}$/', $nik)) balik('err', 'NIK may only contain letters/numbers (max 20 characters).');
if (!in_array($role, ['admin', 'foreman', 'qc', 'operator'], true)) balik('err', 'Invalid role.');
if ($id === 0 && $password === '') balik('err', 'Password is required for new users.');
$nama = mb_substr($nama, 0, 100);

// Foreman & admin tidak punya foreman. Foreman yang dipilih harus foreman aktif.
if ($role === 'admin' || $role === 'foreman' || $foremanId === $id) $foremanId = 0;
if ($foremanId > 0) {
    $cek = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE id = $foremanId AND role = 'foreman' AND status = 1"));
    if (!(int)$cek[0]) balik('err', 'The selected foreman is not valid.');
}

$nikE  = mysqli_real_escape_string($conn, $nik);
$dup = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE nik = '$nikE' AND id <> $id"));
if ((int)$dup[0]) balik('err', "NIK $nik is already used by another user.");

$namaE = mysqli_real_escape_string($conn, $nama);
$roleE = mysqli_real_escape_string($conn, $role);
$fmSql = $foremanId > 0 ? $foremanId : 'NULL';

if ($id === 0) {
    $hashE = mysqli_real_escape_string($conn, password_hash($password, PASSWORD_DEFAULT));
    mysqli_query($conn, "INSERT INTO users (nik, nama, password, role, foreman_id, status)
                         VALUES ('$nikE', '$namaE', '$hashE', '$roleE', $fmSql, $status)");
    balik('ok', "User $nama added.");
}

$lama = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id = $id"));
if (!$lama) balik('err', 'User not found.');

$set = "nik = '$nikE', nama = '$namaE', role = '$roleE', foreman_id = $fmSql, status = $status";
if ($password !== '') {
    $set .= ", password = '" . mysqli_real_escape_string($conn, password_hash($password, PASSWORD_DEFAULT)) . "'";
}
mysqli_query($conn, "UPDATE users SET $set WHERE id = $id");

// Foreman yang turun role / dinonaktifkan: lepaskan anggota timnya supaya
// tidak "yatim" menunjuk foreman yang tidak bisa login lagi.
$lepas = 0;
if ($lama['role'] === 'foreman' && ($role !== 'foreman' || $status === 0)) {
    mysqli_query($conn, "UPDATE users SET foreman_id = NULL WHERE foreman_id = $id");
    $lepas = mysqli_affected_rows($conn);
}

balik('ok', "User $nama updated." . ($lepas ? " $lepas team members were released from this foreman." : ''));

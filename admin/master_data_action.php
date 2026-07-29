<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Konfigurasi tiap tabel master: kolom data, kolom FK di sampling_orders, dan
// tab tujuan setelah aksi selesai.
$TABEL = [
    'parts' => [
        'nama_tabel' => 'master_parts',
        'fk'         => 'part_id',
        'fields'     => ['part_no', 'part_name'],
        'maxlen'     => ['part_no' => 30, 'part_name' => 150],
    ],
    'lines' => [
        'nama_tabel' => 'master_lines',
        'fk'         => 'line_id',
        'fields'     => ['catalog_line'],
        'maxlen'     => ['catalog_line' => 100],
    ],
    'machines' => [
        'nama_tabel' => 'master_machines',
        'fk'         => 'machine_id',
        'fields'     => ['machine_jig_catalog'],
        'maxlen'     => ['machine_jig_catalog' => 150],
    ],
];

$KATEGORI = ['CONROD', 'MS1', 'MS2'];

$tab    = $_REQUEST['tab']    ?? '';
$aksi   = $_REQUEST['aksi']   ?? '';

if (!isset($TABEL[$tab])) {
    header("Location: master_data.php?err=" . urlencode('Tabel master tidak valid.'));
    exit;
}
$cfg   = $TABEL[$tab];
$tabel = $cfg['nama_tabel'];

/** Kembali ke halaman master pada tab yang sama, membawa pesan. */
function kembali(string $tab, string $tipe, string $pesan): void {
    header("Location: master_data.php?tab=$tab&$tipe=" . urlencode($pesan));
    exit;
}

// ── HAPUS ─────────────────────────────────────────────────────────────────────
if ($aksi === 'hapus') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) kembali($tab, 'err', 'ID tidak valid.');

    // Blokir hapus kalau sudah dipakai order — kalau tidak, history order lama
    // akan kehilangan JOIN ke master dan datanya jadi rusak.
    $fk     = $cfg['fk'];
    $cekQ   = mysqli_query($conn, "SELECT COUNT(*) AS n FROM sampling_orders WHERE $fk = $id");
    $pakai  = (int)mysqli_fetch_assoc($cekQ)['n'];
    if ($pakai > 0) {
        kembali($tab, 'err', "Tidak bisa dihapus: data ini sudah dipakai di $pakai order. Edit saja bila perlu koreksi.");
    }

    $del = mysqli_query($conn, "DELETE FROM $tabel WHERE id = $id");
    if ($del && mysqli_affected_rows($conn) > 0) {
        kembali($tab, 'ok', 'Data master berhasil dihapus.');
    }
    kembali($tab, 'err', 'Data gagal dihapus atau tidak ditemukan.');
}

// ── TAMBAH / EDIT ─────────────────────────────────────────────────────────────
if ($aksi === 'simpan') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        kembali($tab, 'err', 'Metode tidak valid.');
    }

    $id       = (int)($_POST['id'] ?? 0);   // 0 = tambah baru
    $category = trim($_POST['category'] ?? '');

    if (!in_array($category, $KATEGORI, true)) {
        kembali($tab, 'err', 'Category harus salah satu dari: ' . implode(', ', $KATEGORI));
    }

    // Kumpulkan & validasi field data
    $data = ['category' => $category];
    foreach ($cfg['fields'] as $f) {
        $val = trim($_POST[$f] ?? '');
        if ($val === '') {
            kembali($tab, 'err', 'Semua kolom wajib diisi.');
        }
        if (mb_strlen($val) > $cfg['maxlen'][$f]) {
            kembali($tab, 'err', "Kolom $f maksimal {$cfg['maxlen'][$f]} karakter.");
        }
        $data[$f] = $val;
    }

    // Cegah duplikat persis (kombinasi category + semua field), kecuali baris ini sendiri
    $dupCond = [];
    foreach ($data as $k => $v) {
        $dupCond[] = "$k = '" . mysqli_real_escape_string($conn, $v) . "'";
    }
    $dupWhere = implode(' AND ', $dupCond);
    $dupSql   = "SELECT id FROM $tabel WHERE $dupWhere" . ($id > 0 ? " AND id <> $id" : '');
    if (mysqli_num_rows(mysqli_query($conn, $dupSql)) > 0) {
        kembali($tab, 'err', 'Data yang sama persis sudah ada.');
    }

    if ($id > 0) {
        // EDIT
        $set = [];
        foreach ($data as $k => $v) {
            $set[] = "$k = '" . mysqli_real_escape_string($conn, $v) . "'";
        }
        $sql = "UPDATE $tabel SET " . implode(', ', $set) . " WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            kembali($tab, 'ok', 'Data master berhasil diperbarui.');
        }
        kembali($tab, 'err', 'Data gagal diperbarui: ' . mysqli_error($conn));
    } else {
        // TAMBAH
        $kolom = implode(', ', array_keys($data));
        $nilai = [];
        foreach ($data as $v) {
            $nilai[] = "'" . mysqli_real_escape_string($conn, $v) . "'";
        }
        $sql = "INSERT INTO $tabel ($kolom) VALUES (" . implode(', ', $nilai) . ")";
        if (mysqli_query($conn, $sql)) {
            kembali($tab, 'ok', 'Data master berhasil ditambahkan.');
        }
        kembali($tab, 'err', 'Data gagal ditambahkan: ' . mysqli_error($conn));
    }
}

kembali($tab, 'err', 'Aksi tidak dikenali.');

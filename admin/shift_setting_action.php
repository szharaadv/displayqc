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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: shift_setting.php");
    exit;
}

$TIPE   = ['weekday', 'friday', 'saturday', 'sunday'];
$shifts = $_POST['shift'] ?? [];   // shift[tipe][no][mulai|selesai|istirahat]

/** Menit sejak 00:00 dari 'HH:MM'. */
function jamKeMenit(string $jam): int {
    [$h, $m] = array_map('intval', explode(':', $jam));
    return $h * 60 + $m;
}

// Validasi semua dulu, baru simpan (all-or-nothing) supaya definisi tidak
// setengah jadi kalau ada satu input yang salah.
$update = [];
foreach ($TIPE as $tipe) {
    for ($no = 1; $no <= 3; $no++) {
        $row = $shifts[$tipe][$no] ?? null;
        if (!$row) {
            header("Location: shift_setting.php?err=" . urlencode('Data shift tidak lengkap.'));
            exit;
        }

        $mulai     = trim($row['mulai']     ?? '');
        $selesai   = trim($row['selesai']   ?? '');
        $istirahat = $row['istirahat'] ?? '';

        // Jam harus format HH:MM
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $mulai) ||
            !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $selesai)) {
            header("Location: shift_setting.php?err=" . urlencode("Format jam salah pada $tipe shift $no."));
            exit;
        }

        if (!is_numeric($istirahat) || $istirahat < 0 || $istirahat > 1440) {
            header("Location: shift_setting.php?err=" . urlencode("Istirahat tidak valid pada $tipe shift $no."));
            exit;
        }

        // Durasi shift (menit). Selesai <= mulai berarti lewat tengah malam.
        $span = jamKeMenit($selesai) - jamKeMenit($mulai);
        if ($span <= 0) $span += 1440;

        $istirahat_menit = (int)round((float)$istirahat);
        $efektif_menit   = $span - $istirahat_menit;

        if ($efektif_menit <= 0) {
            header("Location: shift_setting.php?err=" . urlencode("Istirahat melebihi durasi shift pada $tipe shift $no."));
            exit;
        }

        $update[] = [
            'tipe'      => $tipe,
            'no'        => $no,
            'mulai'     => $mulai . ':00',
            'selesai'   => $selesai . ':00',
            'istirahat' => $istirahat_menit,
            'menit'     => $efektif_menit,
        ];
    }
}

// Simpan (UPSERT per baris)
foreach ($update as $u) {
    $tipe      = mysqli_real_escape_string($conn, $u['tipe']);
    $no        = (int)$u['no'];
    $mulai     = mysqli_real_escape_string($conn, $u['mulai']);
    $selesai   = mysqli_real_escape_string($conn, $u['selesai']);
    $istirahat = (int)$u['istirahat'];
    $menit     = (int)$u['menit'];

    $sql = "INSERT INTO master_shifts (hari_tipe, shift_no, jam_mulai, jam_selesai, istirahat_menit, efektif_menit)
            VALUES ('$tipe', $no, '$mulai', '$selesai', $istirahat, $menit)
            ON DUPLICATE KEY UPDATE
                jam_mulai = VALUES(jam_mulai),
                jam_selesai = VALUES(jam_selesai),
                istirahat_menit = VALUES(istirahat_menit),
                efektif_menit = VALUES(efektif_menit)";
    if (!mysqli_query($conn, $sql)) {
        header("Location: shift_setting.php?err=" . urlencode('Gagal menyimpan: ' . mysqli_error($conn)));
        exit;
    }
}

header("Location: shift_setting.php?ok=" . urlencode('Setting shift berhasil disimpan.'));
exit;

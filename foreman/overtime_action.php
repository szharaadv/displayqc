<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/csrf.php';
include '../config/calendar.php';
include '../config/foreman.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

$ctx = fmKonteks($conn);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: overtime.php");
    exit;
}
csrfVerify();

$tglHalaman = fmTanggal($_POST['tgl'] ?? null, date('Y-m-d'));
$balik      = 'overtime.php?tgl=' . $tglHalaman . fmParam($ctx);
$anggota    = fmAnggota($conn, $ctx['foreman_id']);
$aksi       = $_POST['aksi'] ?? '';

/**
 * Cek batas PP 35/2021 (hanya untuk hari kerja). $kecualiId = baris OT yang
 * sedang diedit, supaya durasi lamanya tidak ikut dijumlah.
 * Kembalikan pesan error, atau '' kalau aman.
 */
function cekBatas(mysqli $conn, int $uid, string $tgl, int $durasi, int $kecualiId = 0): string {
    if (!qcHariKerja($tgl)) return '';
    if ($durasi > FM_MAKS_MENIT_HARI) return 'duration ' . fmJam($durasi) . ' exceeds ' . fmJam(FM_MAKS_MENIT_HARI) . '/day';

    $ts    = strtotime($tgl);
    $senin = date('Y-m-d', strtotime('-' . ((int)date('N', $ts) - 1) . ' days', $ts));
    $minggu = date('Y-m-d', strtotime("$senin +6 days"));
    $q = mysqli_query($conn, "SELECT tanggal, durasi_menit FROM overtime
                              WHERE user_id = $uid AND status <> 'batal' AND id <> $kecualiId
                                AND tanggal BETWEEN '$senin' AND '$minggu'");
    $hari = $durasi; $pekan = $durasi;
    while ($r = mysqli_fetch_assoc($q)) {
        if (!qcHariKerja($r['tanggal'])) continue;   // lembur di hari libur tidak dihitung batas
        $pekan += (int)$r['durasi_menit'];
        if ($r['tanggal'] === $tgl) $hari += (int)$r['durasi_menit'];
    }
    if ($hari > FM_MAKS_MENIT_HARI)    return 'that day\'s total ' . fmJam($hari) . ' > ' . fmJam(FM_MAKS_MENIT_HARI);
    if ($pekan > FM_MAKS_MENIT_MINGGU) return 'that week\'s total ' . fmJam($pekan) . ' > ' . fmJam(FM_MAKS_MENIT_MINGGU);
    return '';
}

function jamValid(string $j): bool {
    return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $j);
}

/** Ambil baris OT milik anggota tim ini, atau berhenti. */
function ambilOt(mysqli $conn, array $anggota, string $balik): array {
    $id = (int)($_POST['id'] ?? 0);
    $o  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM overtime WHERE id = $id"));
    if (!$o || !isset($anggota[(int)$o['user_id']])) fmBalik($balik, 'err', 'OT record not found.');
    return $o;
}

// ── Jadwalkan OT baru untuk beberapa orang ───────────────────────────────
if ($aksi === 'buat') {
    $tgl     = $tglHalaman;
    $mulai   = trim($_POST['jam_mulai'] ?? '');
    $selesai = trim($_POST['jam_selesai'] ?? '');
    $ket     = mb_substr(trim($_POST['keterangan'] ?? ''), 0, 150);
    $alasan  = mb_substr(trim($_POST['alasan_lewati'] ?? ''), 0, 200);
    $dipilih = array_values(array_unique(array_map('intval', (array)($_POST['user_id'] ?? []))));

    if (!jamValid($mulai) || !jamValid($selesai)) fmBalik($balik, 'err', 'Invalid OT time.');
    if (!$dipilih) fmBalik($balik, 'err', 'Select at least one member.');
    $durasi = fmDurasi($mulai, $selesai);

    // Kelayakan: anggota tim, tidak absen (izin/sakit/cuti/alpha), belum punya OT aktif di tanggal itu.
    $ids = implode(',', array_keys($anggota)) ?: '0';
    $tidakMasuk = []; $sudahOt = [];
    $q = mysqli_query($conn, "SELECT user_id, status FROM attendance WHERE tanggal = '$tgl' AND user_id IN ($ids)");
    while ($r = mysqli_fetch_assoc($q)) if (!in_array($r['status'], FM_STATUS_MASUK, true)) $tidakMasuk[(int)$r['user_id']] = true;
    $q = mysqli_query($conn, "SELECT user_id FROM overtime WHERE tanggal = '$tgl' AND status <> 'batal' AND user_id IN ($ids)");
    while ($r = mysqli_fetch_assoc($q)) $sudahOt[(int)$r['user_id']] = true;

    $layak = array_filter(array_keys($anggota), fn($u) => !isset($tidakMasuk[$u]) && !isset($sudahOt[$u]));
    foreach ($dipilih as $uid) {
        if (!in_array($uid, $layak, true)) fmBalik($balik, 'err', 'Some members cannot be scheduled (not in team, not present, or already on OT).');
        $e = cekBatas($conn, $uid, $tgl, $durasi);
        if ($e !== '') fmBalik($balik, 'err', $anggota[$uid]['nama'] . ": $e (PP 35/2021).");
    }

    // Pembagian rata: kalau ada anggota layak yang tidak dipilih padahal lebih
    // berhak dari salah satu yang dipilih, wajib ada alasan.
    $rekap = fmRekapOtBulan($conn, substr($tgl, 0, 7));
    $terbesar = max(array_map(fn($u) => fmKunciAdil($rekap, $u), $dipilih));
    $dilewati = array_filter($layak, fn($u) => !in_array($u, $dipilih, true) && fmKunciAdil($rekap, $u) < $terbesar);
    if ($dilewati && $alasan === '') {
        fmBalik($balik, 'err', 'Enter a reason for skipping higher-priority members: '
            . implode(', ', array_map(fn($u) => $anggota[$u]['nama'], $dilewati)) . '.');
    }
    if (!$dilewati) $alasan = '';

    $ketE = mysqli_real_escape_string($conn, $ket);
    $alE  = mysqli_real_escape_string($conn, $alasan);
    $oleh = (int)$_SESSION['id'];
    foreach ($dipilih as $uid) {
        mysqli_query($conn, "INSERT INTO overtime (tanggal, user_id, jam_mulai, jam_selesai, durasi_menit, keterangan, alasan_lewati, dibuat_oleh)
                             VALUES ('$tgl', $uid, '$mulai', '$selesai', $durasi, '$ketE', '$alE', $oleh)");
    }
    fmBalik($balik, 'ok', count($dipilih) . ' people scheduled for OT on ' . fmLabelTanggal($tgl) . " ({$mulai}–{$selesai}).");
}

// ── Tandai selesai (tombol cepat) ────────────────────────────────────────
if ($aksi === 'status') {
    $o = ambilOt($conn, $anggota, $balik);
    if (($_POST['status'] ?? '') !== 'selesai') fmBalik($balik, 'err', 'Invalid status.');
    mysqli_query($conn, "UPDATE overtime SET status = 'selesai' WHERE id = " . (int)$o['id']);
    fmBalik($balik, 'ok', 'OT ' . $anggota[(int)$o['user_id']]['nama'] . ' marked done.');
}

// ── Edit jam aktual / keterangan / status ────────────────────────────────
if ($aksi === 'ubah') {
    $o       = ambilOt($conn, $anggota, $balik);
    $uid     = (int)$o['user_id'];
    $mulai   = trim($_POST['jam_mulai'] ?? '');
    $selesai = trim($_POST['jam_selesai'] ?? '');
    $status  = $_POST['status'] ?? '';
    $ket     = mb_substr(trim($_POST['keterangan'] ?? ''), 0, 150);
    if (!jamValid($mulai) || !jamValid($selesai)) fmBalik($balik, 'err', 'Invalid OT time.');
    if (!isset(FM_STATUS_OT[$status])) fmBalik($balik, 'err', 'Invalid status.');
    $durasi = fmDurasi($mulai, $selesai);

    if ($status !== 'batal') {
        // Mengaktifkan lagi OT yang batal tidak boleh membuat dobel di tanggal yang sama.
        if ($o['status'] === 'batal') {
            $dup = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM overtime WHERE user_id = $uid AND tanggal = '{$o['tanggal']}' AND status <> 'batal' AND id <> {$o['id']}"));
            if ((int)$dup[0]) fmBalik($balik, 'err', $anggota[$uid]['nama'] . ' already has active OT on that date.');
        }
        $e = cekBatas($conn, $uid, $o['tanggal'], $durasi, (int)$o['id']);
        if ($e !== '') fmBalik($balik, 'err', $anggota[$uid]['nama'] . ": $e (PP 35/2021).");
    }

    $ketE = mysqli_real_escape_string($conn, $ket);
    mysqli_query($conn, "UPDATE overtime SET jam_mulai = '$mulai', jam_selesai = '$selesai', durasi_menit = $durasi,
                         keterangan = '$ketE', status = '$status' WHERE id = " . (int)$o['id']);
    fmBalik($balik, 'ok', 'OT ' . $anggota[$uid]['nama'] . ' updated.');
}

fmBalik($balik, 'err', 'Unknown action.');

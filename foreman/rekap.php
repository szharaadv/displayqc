<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/calendar.php';
include '../config/foreman.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

$ctx     = fmKonteks($conn);
$bulan   = fmBulan($_GET['bulan'] ?? null, date('Y-m'));
$anggota = fmAnggota($conn, $ctx['foreman_id']);
$rekapOt = fmRekapOtBulan($conn, $bulan);
$awal    = "$bulan-01";
$akhir   = date('Y-m-t', strtotime($awal));

$absen = [];   // [uid][status] = jumlah hari
$detail = [];  // baris OT untuk sheet detail
if ($anggota) {
    $ids = implode(',', array_keys($anggota));
    $q = mysqli_query($conn, "SELECT user_id, status, COUNT(*) n FROM attendance
                              WHERE tanggal BETWEEN '$awal' AND '$akhir' AND user_id IN ($ids) GROUP BY user_id, status");
    while ($r = mysqli_fetch_assoc($q)) $absen[(int)$r['user_id']][$r['status']] = (int)$r['n'];
    $q = mysqli_query($conn, "SELECT * FROM overtime WHERE tanggal BETWEEN '$awal' AND '$akhir' AND user_id IN ($ids)
                              ORDER BY tanggal, jam_mulai");
    while ($r = mysqli_fetch_assoc($q)) $detail[] = $r;
}

// Urut: OT paling sedikit di atas — langsung kelihatan siapa yang belum kebagian.
$baris = [];
foreach ($anggota as $uid => $a) {
    $baris[] = ['id' => $uid, 'nama' => $a['nama'], 'nik' => $a['nik'],
                'menit' => $rekapOt[$uid]['menit'] ?? 0, 'kali' => $rekapOt[$uid]['kali'] ?? 0,
                'absen' => $absen[$uid] ?? []];
}
usort($baris, fn($x, $y) => [$x['menit'], $x['kali'], $x['nama']] <=> [$y['menit'], $y['kali'], $y['nama']]);

$hariKerja = 0;
for ($t = strtotime($awal); $t <= strtotime($akhir); $t += 86400) {
    if (qcHariKerja(date('Y-m-d', $t))) $hariKerja++;
}

if (isset($_GET['export'])) {
    include '../config/xlsx.php';
    $s1 = [[xb('Attendance & Overtime Recap — ' . fmLabelBulan($bulan))], [],
           array_map('xb', array_merge(['NIK', 'Name'], array_values(FM_STATUS_ABSEN), ['OT (times)', 'Total OT (hours)']))];
    foreach ($baris as $b) {
        $row = [$b['nik'], $b['nama']];
        foreach (FM_STATUS_ABSEN as $k => $_) $row[] = $b['absen'][$k] ?? 0;
        $row[] = $b['kali'];
        $row[] = round($b['menit'] / 60, 2);
        $s1[] = $row;
    }
    $s2 = [array_map('xb', ['Date', 'NIK', 'Name', 'Start Time', 'End Time', 'Duration (hours)', 'Status', 'Notes', 'Skip Reason'])];
    foreach ($detail as $o) {
        $a = $anggota[(int)$o['user_id']];
        $s2[] = [$o['tanggal'], $a['nik'], $a['nama'], substr($o['jam_mulai'], 0, 5), substr($o['jam_selesai'], 0, 5),
                 round($o['durasi_menit'] / 60, 2), FM_STATUS_OT[$o['status']], $o['keterangan'], $o['alasan_lewati']];
    }
    xlsxKirim("Foreman_Recap_$bulan.xlsx", ['Recap' => $s1, 'OT Detail' => $s2]);
    exit;
}

$prev = date('Y-m', strtotime("$awal -1 month"));
$next = date('Y-m', strtotime("$awal +1 month"));
$fmQ  = fmParam($ctx);
$maxMenit = max(1, ...array_map(fn($b) => $b['menit'], $baris ?: [['menit' => 0]]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Recap — QC Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/panel.css">
    <link rel="stylesheet" href="../assets/components.css">
    <style>
        .bar-ot { height:6px; background:var(--surface2); border-radius:3px; min-width:80px; overflow:hidden; }
        .bar-ot span { display:block; height:100%; background:var(--red); border-radius:3px; }
        .z { color:var(--text3); }
    </style>
</head>
<body>

<?php $active_nav = 'rekap'; include __DIR__ . '/../admin/_sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <span class="topbar-title">Monthly Recap</span>
        <?php if ($anggota): ?><a class="btn btn-ghost btn-sm" href="?bulan=<?php echo $bulan . $fmQ; ?>&export=1">⬇ Export Excel</a><?php endif; ?>
    </div>
    <div class="content">
        <?php echo fmFilterBar($ctx, [
            'type' => 'month', 'name' => 'bulan', 'value' => $bulan, 'label' => 'Month',
            'teks' => fmLabelBulan($bulan), 'prev' => $prev, 'next' => $next,
            'now' => date('Y-m'), 'now_label' => 'This month',
        ]); ?>

        <?php if (!$anggota): ?>
            <div class="card"><div class="empty">No members in this team.</div></div>
        <?php else:
            $otAktif = array_filter($detail, fn($o) => $o['status'] !== 'batal');
            $totMenit = array_sum(array_column($baris, 'menit'));
            $belum = count(array_filter($baris, fn($b) => $b['kali'] === 0)); ?>

        <div class="stats">
            <div class="stat"><div class="k">Working Days</div><div class="v"><?php echo $hariKerja; ?></div></div>
            <div class="stat"><div class="k">Team OT Total</div><div class="v"><?php echo fmJam($totMenit); ?></div></div>
            <div class="stat"><div class="k">OT Schedules</div><div class="v"><?php echo count($otAktif); ?>×</div></div>
            <div class="stat"><div class="k">Average / Person</div><div class="v"><?php echo fmJam((int)round($totMenit / count($baris))); ?></div></div>
            <div class="stat"><div class="k">No OT Yet</div><div class="v" style="color:<?php echo $belum ? 'var(--amber)' : 'var(--green)'; ?>"><?php echo $belum; ?></div></div>
        </div>

        <div class="card">
            <div class="card-head"><span class="bar"></span><span class="t"><?php echo fmLabelBulan($bulan); ?></span><span class="sub">Sorted by least OT</span></div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr>
                        <th>Name</th>
                        <?php foreach (FM_STATUS_ABSEN as $l): ?><th class="num"><?php echo $l; ?></th><?php endforeach; ?>
                        <th class="num">OT (times)</th><th class="num">OT (hours)</th><th style="width:140px"></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($baris as $b): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($b['nama']); ?></strong> <span class="nik"><?php echo htmlspecialchars($b['nik']); ?></span></td>
                            <?php foreach (FM_STATUS_ABSEN as $k => $_):
                                $n = $b['absen'][$k] ?? 0; ?>
                                <td class="num <?php echo $n ? '' : 'z'; ?>"><?php echo $n; ?></td>
                            <?php endforeach; ?>
                            <td class="num"><?php echo $b['kali'] ? $b['kali'] . '×' : '<span class="badge b-amber">None</span>'; ?></td>
                            <td class="num"><strong><?php echo fmJam($b['menit']); ?></strong></td>
                            <td><div class="bar-ot"><span style="width:<?php echo round($b['menit'] / $maxMenit * 100); ?>%"></span></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="intro">Attendance counts only days saved by the foreman. OT with status <strong>Cancelled</strong> is not counted.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

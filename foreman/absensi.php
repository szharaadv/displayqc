<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/csrf.php';
include '../config/calendar.php';
include '../config/foreman.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

$ctx     = fmKonteks($conn);
$tanggal = fmTanggal($_GET['tgl'] ?? null, date('Y-m-d'));
$anggota = fmAnggota($conn, $ctx['foreman_id']);
$hari    = qcHari($tanggal);

// Absensi yang sudah tersimpan + OT terjadwal di tanggal ini.
$absen = [];
$ot    = [];
if ($anggota) {
    $ids = implode(',', array_keys($anggota));
    $q = mysqli_query($conn, "SELECT user_id, status, keterangan FROM attendance WHERE tanggal = '$tanggal' AND user_id IN ($ids)");
    while ($r = mysqli_fetch_assoc($q)) $absen[(int)$r['user_id']] = $r;
    $q = mysqli_query($conn, "SELECT user_id, jam_mulai, jam_selesai, status FROM overtime
                              WHERE tanggal = '$tanggal' AND status <> 'batal' AND user_id IN ($ids)");
    while ($r = mysqli_fetch_assoc($q)) $ot[(int)$r['user_id']] = $r;
}

$sudahIsi = count($absen);
$hitung = array_fill_keys(array_keys(FM_STATUS_ABSEN), 0);
foreach ($anggota as $uid => $_) $hitung[$absen[$uid]['status'] ?? 'hadir']++;

$prev = date('Y-m-d', strtotime("$tanggal -1 day"));
$next = date('Y-m-d', strtotime("$tanggal +1 day"));
$fmQ  = fmParam($ctx);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance — QC Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/panel.css">
    <link rel="stylesheet" href="../assets/components.css">
</head>
<body>

<?php $active_nav = 'absensi'; include __DIR__ . '/../admin/_sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <span class="topbar-title">Daily Attendance</span>
        <span class="nik"><?php echo fmLabelTanggal($tanggal); ?></span>
    </div>
    <div class="content">
        <?php if (isset($_GET['ok'])): ?><div class="banner ok"><?php echo htmlspecialchars($_GET['ok']); ?></div><?php endif; ?>
        <?php if (isset($_GET['err'])): ?><div class="banner err"><?php echo htmlspecialchars($_GET['err']); ?></div><?php endif; ?>

        <?php echo fmFilterBar($ctx, [
            'type' => 'date', 'name' => 'tgl', 'value' => $tanggal, 'label' => 'Attendance Date',
            'teks' => fmLabelTanggal($tanggal), 'prev' => $prev, 'next' => $next,
            'now' => date('Y-m-d'), 'now_label' => 'Today',
        ]); ?>

        <?php if (!$hari['kerja']): ?>
            <div class="banner warn">
                <?php echo $hari['tipe'] === 'holiday' ? 'Holiday' : 'Weekend'; ?><?php echo $hari['label'] ? ' — ' . htmlspecialchars($hari['label']) : ''; ?>.
                Attendance can still be recorded for anyone working (e.g. overtime).
            </div>
        <?php endif; ?>

        <?php if (!$anggota): ?>
            <div class="card"><div class="empty">
                <?php echo $ctx['is_admin'] ? 'No members in this team.' : 'Your team has no members yet. Ask an admin to set up the team in the Users menu.'; ?>
            </div></div>
        <?php else: ?>

        <div class="stats">
            <div class="stat"><div class="k">Members</div><div class="v"><?php echo count($anggota); ?></div></div>
            <div class="stat"><div class="k">Present</div><div class="v" style="color:var(--green)"><?php echo $hitung['hadir'] + $hitung['terlambat']; ?></div></div>
            <div class="stat"><div class="k">Not Present</div><div class="v" style="color:var(--red)"><?php echo count($anggota) - $hitung['hadir'] - $hitung['terlambat']; ?></div></div>
            <div class="stat"><div class="k">Entry Status</div><div class="v" style="font-size:14px;margin-top:10px">
                <?php echo $sudahIsi >= count($anggota) ? '<span class="badge b-green">Saved</span>'
                         : ($sudahIsi ? '<span class="badge b-amber">Partial</span>' : '<span class="badge b-amber">Not saved</span>'); ?>
            </div></div>
        </div>

        <form method="POST" action="absensi_action.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="tgl" value="<?php echo $tanggal; ?>">
            <input type="hidden" name="fm" value="<?php echo $ctx['foreman_id']; ?>">
            <div class="card">
                <div class="card-head">
                    <span class="bar"></span><span class="t">Team Members</span>
                    <span class="sub">Default is <strong>Present</strong> — change only those absent / late, then save.</span>
                </div>
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead><tr><th>Name</th><th>Status</th><th>Notes</th><th>Overtime</th></tr></thead>
                        <tbody>
                        <?php foreach ($anggota as $uid => $a):
                            $st = $absen[$uid]['status'] ?? 'hadir'; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($a['nama']); ?></strong><br><span class="nik"><?php echo htmlspecialchars($a['nik']); ?></span>
                                    <?php if (!isset($absen[$uid])): ?><span class="badge" title="Not saved yet">new</span><?php endif; ?></td>
                                <td><div class="seg">
                                    <?php foreach (FM_STATUS_ABSEN as $k => $l): ?>
                                        <input type="radio" id="s<?php echo "{$uid}_$k"; ?>" name="status[<?php echo $uid; ?>]" value="<?php echo $k; ?>" <?php echo $st === $k ? 'checked' : ''; ?>>
                                        <label for="s<?php echo "{$uid}_$k"; ?>"><?php echo $l; ?></label>
                                    <?php endforeach; ?>
                                </div></td>
                                <td><input type="text" name="ket[<?php echo $uid; ?>]" maxlength="150" style="width:100%;min-width:160px" value="<?php echo htmlspecialchars($absen[$uid]['keterangan'] ?? ''); ?>" placeholder="optional"></td>
                                <td>
                                    <?php if (isset($ot[$uid])):
                                        $o = $ot[$uid];
                                        $tdkMasuk = !in_array($st, FM_STATUS_MASUK, true); ?>
                                        <span class="badge <?php echo $tdkMasuk ? 'b-red' : 'b-blue'; ?>" title="<?php echo $tdkMasuk ? 'Scheduled for OT but not present — check the Overtime page' : ''; ?>">
                                            <?php echo substr($o['jam_mulai'], 0, 5) . '–' . substr($o['jam_selesai'], 0, 5); ?>
                                        </span>
                                    <?php else: ?><span class="nik">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="toolbar">
                <button type="submit" class="btn btn-red">Save Attendance</button>
                <button type="button" class="btn btn-ghost" onclick="document.querySelectorAll('input[type=radio][value=hadir]').forEach(function(r){r.checked=true;})">Mark all Present</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

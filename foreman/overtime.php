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
$bulan   = substr($tanggal, 0, 7);
$anggota = fmAnggota($conn, $ctx['foreman_id']);
$hari    = qcHari($tanggal);
$rekap   = fmRekapOtBulan($conn, $bulan);
$fmQ     = fmParam($ctx);

$absen = [];   // status absensi di tanggal OT (kalau sudah diisi)
$otHari = [];  // yang sudah punya OT aktif di tanggal ini
$daftar = [];  // semua OT tim di bulan ini
if ($anggota) {
    $ids = implode(',', array_keys($anggota));
    $q = mysqli_query($conn, "SELECT user_id, status FROM attendance WHERE tanggal = '$tanggal' AND user_id IN ($ids)");
    while ($r = mysqli_fetch_assoc($q)) $absen[(int)$r['user_id']] = $r['status'];

    $awal = "$bulan-01"; $akhir = date('Y-m-t', strtotime($awal));
    $q = mysqli_query($conn, "SELECT o.*, u.nama AS pembuat FROM overtime o LEFT JOIN users u ON u.id = o.dibuat_oleh
                              WHERE o.user_id IN ($ids) AND o.tanggal BETWEEN '$awal' AND '$akhir'
                              ORDER BY o.tanggal DESC, o.jam_mulai, o.id");
    while ($r = mysqli_fetch_assoc($q)) {
        $daftar[] = $r;
        if ($r['tanggal'] === $tanggal && $r['status'] !== 'batal') $otHari[(int)$r['user_id']] = $r;
    }
}

// Kandidat diurutkan untuk pembagian rata: jam OT bulan ini paling sedikit,
// lalu jumlah kali paling sedikit, lalu nama.
$kandidat = [];
foreach ($anggota as $uid => $a) {
    $st = $absen[$uid] ?? null;
    $alasanTidak = '';
    if (isset($otHari[$uid]))                                       $alasanTidak = 'Already scheduled for OT';
    elseif ($st !== null && !in_array($st, FM_STATUS_MASUK, true)) $alasanTidak = FM_STATUS_ABSEN[$st];
    $kandidat[] = [
        'id' => $uid, 'nama' => $a['nama'], 'nik' => $a['nik'],
        'menit' => $rekap[$uid]['menit'] ?? 0, 'kali' => $rekap[$uid]['kali'] ?? 0,
        'absen' => $st, 'tidak' => $alasanTidak,
    ];
}
usort($kandidat, fn($x, $y) => [$x['menit'], $x['kali'], $x['nama']] <=> [$y['menit'], $y['kali'], $y['nama']]);

$totalMenit = array_sum(array_map(fn($u) => $rekap[$u]['menit'] ?? 0, array_keys($anggota)));
$belumDapat = count(array_filter($kandidat, fn($k) => $k['kali'] === 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime — QC Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/panel.css">
    <link rel="stylesheet" href="../assets/components.css">
    <style>
        .rank { display:inline-flex; width:24px; height:24px; border-radius:50%; background:var(--surface2); border:1px solid var(--border); align-items:center; justify-content:center; font-size:11px; font-weight:700; color:var(--text2); }
        tr.pick td { background:#fffafa; }
        tr.lewat td { background:var(--amber-soft); }
        #alasan-wrap { display:none; }
        #alasan-wrap.show { display:flex; }
        .durasi-out { font-size:14px; font-weight:700; color:var(--red); padding:8px 0; }
    </style>
</head>
<body>

<?php $active_nav = 'overtime'; include __DIR__ . '/../admin/_sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <span class="topbar-title">Overtime</span>
        <span class="nik">Allocation for <?php echo fmLabelBulan($bulan); ?></span>
    </div>
    <div class="content">
        <?php if (isset($_GET['ok'])): ?><div class="banner ok"><?php echo htmlspecialchars($_GET['ok']); ?></div><?php endif; ?>
        <?php if (isset($_GET['err'])): ?><div class="banner err"><?php echo htmlspecialchars($_GET['err']); ?></div><?php endif; ?>

        <?php echo fmFilterBar($ctx, [
            'type' => 'date', 'name' => 'tgl', 'value' => $tanggal, 'label' => 'OT Date',
            'teks' => fmLabelTanggal($tanggal),
            'prev' => date('Y-m-d', strtotime("$tanggal -1 day")), 'next' => date('Y-m-d', strtotime("$tanggal +1 day")),
            'now' => date('Y-m-d'), 'now_label' => 'Today',
        ]); ?>

        <?php if (!$anggota): ?>
            <div class="card"><div class="empty">
                <?php echo $ctx['is_admin'] ? 'No members in this team.' : 'Your team has no members yet. Ask an admin to set up the team in the Users menu.'; ?>
            </div></div>
        <?php else: ?>

        <div class="stats">
            <div class="stat"><div class="k">Team OT (this month)</div><div class="v"><?php echo fmJam($totalMenit); ?></div></div>
            <div class="stat"><div class="k">No OT Yet</div><div class="v" style="color:<?php echo $belumDapat ? 'var(--amber)' : 'var(--green)'; ?>"><?php echo $belumDapat; ?> <span style="font-size:13px;color:var(--text3)">/ <?php echo count($anggota); ?></span></div></div>
            <div class="stat"><div class="k">Day <?php echo (int)date('j', strtotime($tanggal)) . ' ' . substr(FM_NAMA_BULAN[(int)substr($tanggal, 5, 2)], 0, 3); ?></div><div class="v" style="font-size:14px;margin-top:10px">
                <?php echo $hari['kerja'] ? '<span class="badge b-green">Working day</span>'
                         : '<span class="badge b-red">' . ($hari['tipe'] === 'holiday' ? 'Holiday' : 'Weekend') . '</span>'; ?>
            </div></div>
            <div class="stat"><div class="k">Scheduled This Day</div><div class="v"><?php echo count($otHari); ?></div></div>
        </div>

        <form class="card" method="POST" action="overtime_action.php" id="form-ot">
            <?php echo csrfField(); ?>
            <input type="hidden" name="aksi" value="buat">
            <input type="hidden" name="tgl" value="<?php echo $tanggal; ?>">
            <input type="hidden" name="fm" value="<?php echo $ctx['foreman_id']; ?>">
            <div class="card-head">
                <span class="bar"></span><span class="t">Schedule OT — <?php echo fmLabelTanggal($tanggal); ?></span>
                <?php if ($hari['label']): ?><span class="sub"><?php echo htmlspecialchars($hari['label']); ?></span><?php endif; ?>
            </div>
            <div class="card-body">
                <div class="toolbar" style="margin-bottom:6px">
                    <div class="field"><label>Start Time</label><input type="time" name="jam_mulai" id="jm" value="<?php echo $hari['kerja'] ? '16:30' : '07:30'; ?>" required></div>
                    <div class="field"><label>End Time</label><input type="time" name="jam_selesai" id="js" value="<?php echo $hari['kerja'] ? '19:30' : '16:30'; ?>" required></div>
                    <div class="field"><label>Duration</label><div class="durasi-out" id="durasi">—</div></div>
                    <div class="field" style="flex:1;min-width:220px"><label>Notes / Work</label><input type="text" name="keterangan" maxlength="150" placeholder="e.g. urgent sampling line A"></div>
                </div>
                <p class="intro" style="margin-bottom:0">
                    <?php if ($hari['kerja']): ?>
                        Working-day limit (PP 35/2021): max <strong><?php echo fmJam(FM_MAKS_MENIT_HARI); ?>/day</strong> and
                        <strong><?php echo fmJam(FM_MAKS_MENIT_MINGGU); ?>/week</strong> per person.
                    <?php else: ?>
                        Holiday / weekend — the 4 h/day &amp; 18 h/week limits do not apply.
                    <?php endif; ?>
                    The order below = <strong>fair-allocation priority</strong> (fewest OT hours this month, then fewest times).
                </p>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th style="width:36px"></th><th>#</th><th>Name</th><th class="num">OT hours this month</th><th class="num">Times</th><th>Attendance</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php $rank = 0; foreach ($kandidat as $k):
                        $bisa = $k['tidak'] === ''; ?>
                        <tr class="<?php echo $bisa ? '' : 'dim'; ?>" data-id="<?php echo $k['id']; ?>" data-menit="<?php echo $k['menit']; ?>" data-kali="<?php echo $k['kali']; ?>" data-bisa="<?php echo $bisa ? 1 : 0; ?>">
                            <td><input type="checkbox" name="user_id[]" value="<?php echo $k['id']; ?>" <?php echo $bisa ? '' : 'disabled'; ?>></td>
                            <td><span class="rank"><?php echo $bisa ? ++$rank : '–'; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($k['nama']); ?></strong> <span class="nik"><?php echo htmlspecialchars($k['nik']); ?></span></td>
                            <td class="num"><?php echo fmJam($k['menit']); ?></td>
                            <td class="num"><?php echo $k['kali']; ?>×</td>
                            <td><?php
                                if ($k['absen'] === null) echo '<span class="nik">not recorded</span>';
                                else echo '<span class="badge ' . (in_array($k['absen'], FM_STATUS_MASUK, true) ? 'b-green' : 'b-red') . '">' . FM_STATUS_ABSEN[$k['absen']] . '</span>';
                            ?></td>
                            <td><?php
                                if (!$bisa) echo '<span class="nik">' . htmlspecialchars($k['tidak']) . '</span>';
                                elseif ($k['kali'] === 0) echo '<span class="badge b-amber">No OT yet</span>';
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body">
                <div class="field" id="alasan-wrap" style="margin-bottom:12px">
                    <label style="color:var(--amber)">Reason for skipping a higher-priority member (required)</label>
                    <input type="text" name="alasan_lewati" id="alasan" maxlength="200" placeholder="e.g. needs CMM skill, others are in training">
                    <span class="hint" id="alasan-info"></span>
                </div>
                <div class="toolbar" style="margin-bottom:0">
                    <button type="submit" class="btn btn-red" id="btn-simpan" disabled>Schedule OT</button>
                    <div class="field"><label>Auto-select (highest priority)</label>
                        <div style="display:flex;gap:6px">
                            <input type="number" id="n-auto" min="1" max="<?php echo $rank ?: 1; ?>" value="<?php echo min(3, max(1, $rank)); ?>" style="width:70px">
                            <button type="button" class="btn btn-ghost btn-sm" onclick="pilihOtomatis()">Select</button>
                        </div>
                    </div>
                    <span class="nik" id="n-pilih">0 selected</span>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-head"><span class="bar"></span><span class="t">OT Schedule — <?php echo fmLabelBulan($bulan); ?></span><span class="sub"><?php echo count($daftar); ?> entries</span></div>
            <?php if (!$daftar): ?>
                <div class="empty">No OT scheduled this month yet.</div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Date</th><th>Name</th><th>Time</th><th class="num">Duration</th><th>Notes</th><th>Status</th><th>Scheduled by</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($daftar as $o):
                        $uid = (int)$o['user_id']; ?>
                        <tr class="<?php echo $o['status'] === 'batal' ? 'dim' : ''; ?>">
                            <td><a href="?tgl=<?php echo $o['tanggal'] . $fmQ; ?>" style="color:inherit"><?php echo date('d/m', strtotime($o['tanggal'])); ?></a> <span class="nik"><?php echo substr(FM_NAMA_HARI[(int)date('N', strtotime($o['tanggal']))], 0, 3); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($anggota[$uid]['nama'] ?? '?'); ?></strong></td>
                            <td><?php echo substr($o['jam_mulai'], 0, 5) . '–' . substr($o['jam_selesai'], 0, 5); ?></td>
                            <td class="num"><?php echo fmJam((int)$o['durasi_menit']); ?></td>
                            <td><?php echo htmlspecialchars($o['keterangan']); ?>
                                <?php if ($o['alasan_lewati'] !== ''): ?><br><span class="nik" title="Reason for skipping a higher-priority member">⤷ <?php echo htmlspecialchars($o['alasan_lewati']); ?></span><?php endif; ?></td>
                            <td><span class="badge <?php echo ['rencana' => 'b-blue', 'selesai' => 'b-green', 'batal' => ''][$o['status']]; ?>"><?php echo FM_STATUS_OT[$o['status']]; ?></span></td>
                            <td><span class="nik"><?php echo htmlspecialchars($o['pembuat'] ?? '-'); ?><br><?php echo date('d/m H:i', strtotime($o['created_at'])); ?></span></td>
                            <td class="num" style="white-space:nowrap">
                                <?php if ($o['status'] === 'rencana'): ?>
                                    <form method="POST" action="overtime_action.php" style="display:inline">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="aksi" value="status"><input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>">
                                        <input type="hidden" name="status" value="selesai"><input type="hidden" name="tgl" value="<?php echo $tanggal; ?>"><input type="hidden" name="fm" value="<?php echo $ctx['foreman_id']; ?>">
                                        <button class="btn btn-green btn-sm" type="submit">Done</button>
                                    </form>
                                <?php endif; ?>
                                <button class="btn btn-ghost btn-sm" type="button" onclick='bukaEdit(<?php echo json_encode([
                                    "id" => (int)$o['id'], "nama" => $anggota[$uid]['nama'] ?? '', "tanggal" => fmLabelTanggal($o['tanggal']),
                                    "mulai" => substr($o['jam_mulai'], 0, 5), "selesai" => substr($o['jam_selesai'], 0, 5),
                                    "status" => $o['status'], "ket" => $o['keterangan'],
                                ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-bg" id="modal">
    <form class="modal" method="POST" action="overtime_action.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="aksi" value="ubah">
        <input type="hidden" name="id" id="e-id">
        <input type="hidden" name="tgl" value="<?php echo $tanggal; ?>">
        <input type="hidden" name="fm" value="<?php echo $ctx['foreman_id']; ?>">
        <div class="modal-head" id="e-title">Edit OT</div>
        <div class="modal-body">
            <div class="row2">
                <div class="field"><label>Start Time (actual)</label><input type="time" name="jam_mulai" id="e-mulai" required></div>
                <div class="field"><label>End Time (actual)</label><input type="time" name="jam_selesai" id="e-selesai" required></div>
            </div>
            <div class="field"><label>Notes</label><input type="text" name="keterangan" id="e-ket" maxlength="150"></div>
            <div class="field"><label>Status</label>
                <select name="status" id="e-status">
                    <?php foreach (FM_STATUS_OT as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo $l; ?></option><?php endforeach; ?>
                </select>
                <span class="hint">Cancelled = not counted in fair allocation.</span>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" onclick="tutupEdit()">Close</button>
            <button type="submit" class="btn btn-red">Save</button>
        </div>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('form-ot');
    if (!form) return;
    var rows = Array.prototype.slice.call(form.querySelectorAll('tbody tr'));
    var alasanWrap = document.getElementById('alasan-wrap'), alasan = document.getElementById('alasan');

    function menit(t) { if (!t) return null; var p = t.split(':'); return +p[0] * 60 + +p[1]; }
    function fmt(m) { return Math.floor(m / 60) + 'h' + (m % 60 ? ' ' + (m % 60) + 'm' : ''); }
    function durasi() {
        var a = menit(document.getElementById('jm').value), b = menit(document.getElementById('js').value);
        if (a === null || b === null) { document.getElementById('durasi').textContent = '—'; return; }
        var d = b - a; if (d <= 0) d += 1440;
        document.getElementById('durasi').textContent = fmt(d);
    }
    // Lebih berhak = jam OT lebih sedikit, atau jam sama tapi kali lebih sedikit.
    function lebihKecil(x, y) { return x.m < y.m || (x.m === y.m && x.k < y.k); }
    function cek() {
        var dipilih = [], lain = [];
        rows.forEach(function (tr) {
            var cb = tr.querySelector('input[type=checkbox]');
            var d = {tr: tr, m: +tr.dataset.menit, k: +tr.dataset.kali, nama: tr.querySelector('strong').textContent};
            tr.classList.toggle('pick', cb.checked);
            tr.classList.remove('lewat');
            if (cb.checked) dipilih.push(d); else if (tr.dataset.bisa === '1') lain.push(d);
        });
        var terbesar = null;
        dipilih.forEach(function (d) { if (!terbesar || lebihKecil(terbesar, d)) terbesar = d; });
        var dilewati = terbesar ? lain.filter(function (d) { return lebihKecil(d, terbesar); }) : [];
        dilewati.forEach(function (d) { d.tr.classList.add('lewat'); });
        alasanWrap.classList.toggle('show', dilewati.length > 0);
        alasan.required = dilewati.length > 0;
        document.getElementById('alasan-info').textContent = dilewati.length
            ? 'Skipped: ' + dilewati.map(function (d) { return d.nama; }).join(', ') : '';
        document.getElementById('n-pilih').textContent = dipilih.length + ' selected';
        document.getElementById('btn-simpan').disabled = dipilih.length === 0;
    }
    window.pilihOtomatis = function () {
        var n = +document.getElementById('n-auto').value || 0;
        rows.forEach(function (tr) {
            var cb = tr.querySelector('input[type=checkbox]');
            if (!cb.disabled) { cb.checked = n > 0; if (n > 0) n--; }
        });
        cek();
    };
    rows.forEach(function (tr) { tr.querySelector('input[type=checkbox]').addEventListener('change', cek); });
    ['jm', 'js'].forEach(function (id) { document.getElementById(id).addEventListener('input', durasi); });
    durasi(); cek();
})();

function bukaEdit(o) {
    document.getElementById('e-title').textContent = 'Edit OT — ' + o.nama + ' · ' + o.tanggal;
    document.getElementById('e-id').value = o.id;
    document.getElementById('e-mulai').value = o.mulai;
    document.getElementById('e-selesai').value = o.selesai;
    document.getElementById('e-ket').value = o.ket;
    document.getElementById('e-status').value = o.status;
    document.getElementById('modal').classList.add('open');
}
function tutupEdit() { document.getElementById('modal').classList.remove('open'); }
document.getElementById('modal').addEventListener('click', function (e) { if (e.target === this) tutupEdit(); });
</script>
</body>
</html>

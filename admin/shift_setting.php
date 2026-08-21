<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/shift.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Ambil nilai shift saat ini dari DB (mentah), supaya form menampilkan jam apa
// adanya. Kalau tabel kosong, isi dari default lewat qcMuatShift().
$tersimpan = [];
try {
    $q = mysqli_query($conn, "SELECT hari_tipe, shift_no, jam_mulai, jam_selesai, istirahat_menit, efektif_menit FROM master_shifts");
    while ($q && $r = mysqli_fetch_assoc($q)) {
        $tersimpan[$r['hari_tipe']][(int)$r['shift_no']] = $r;
    }
} catch (\Throwable $e) {
    // Tabel belum dibuat — form akan menampilkan nilai default dari qcMuatShift().
    $tersimpan = [];
}

$LABEL_TIPE = [
    'weekday'  => 'Senin – Kamis',
    'friday'   => 'Jumat',
    'saturday' => 'Sabtu',
    'sunday'   => 'Minggu',
];

// Ambil jam mulai/selesai + istirahat (menit) untuk sebuah sel form, jatuh ke
// default qcMuatShift() bila belum ada di DB. Jam efektif TIDAK diinput lagi —
// dihitung otomatis (durasi shift dikurangi istirahat) di sisi klien & server.
$def = qcMuatShift();
function nilaiShift(array $tersimpan, array $def, string $tipe, int $no): array {
    if (isset($tersimpan[$tipe][$no])) {
        $r = $tersimpan[$tipe][$no];
        return [
            'mulai'     => substr($r['jam_mulai'], 0, 5),
            'selesai'   => substr($r['jam_selesai'], 0, 5),
            'istirahat' => (int)($r['istirahat_menit'] ?? 0),
        ];
    }
    // fallback dari default (menit → HH:MM); istirahat = durasi − efektif
    $d = $def[$tipe][$no - 1];
    $span = $d['selesai'] - $d['mulai'];
    if ($span < 0) $span += 1440;
    $istirahat = max(0, $span - (int)round($d['detik'] / 60));
    $mm = $d['mulai'] % 1440; $ss = $d['selesai'] % 1440;
    return [
        'mulai'     => sprintf('%02d:%02d', intdiv($mm, 60), $mm % 60),
        'selesai'   => sprintf('%02d:%02d', intdiv($ss, 60), $ss % 60),
        'istirahat' => $istirahat,
    ];
}

$nama_login = $_SESSION['nama'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setting Shift — QC Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root {
            --red:#CC0000; --red-dark:#A30000; --red-soft:#fff0f0; --red-mid:#ffd5d5;
            --bg:#f4f5f7; --surface:#fff; --surface2:#f9fafb; --border:rgba(0,0,0,0.07);
            --text:#111827; --text2:#6b7280; --text3:#9ca3af; --green:#059669; --green-soft:#d1fae5;
            --sidebar-w:220px; --radius:12px; --radius-sm:8px; --shadow:0 1px 4px rgba(0,0,0,0.07);
        }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; bottom:0; z-index:200; }
        .sidebar-logo { padding:24px 20px 20px; border-bottom:1px solid var(--border); }
        .sidebar-logo-badge { display:inline-flex; align-items:center; gap:8px; }
        .logo-icon { width:32px; height:32px; background:var(--red); border-radius:8px; display:flex; align-items:center; justify-content:center; }
        .logo-icon svg { width:18px; height:18px; fill:#fff; }
        .logo-text { display:flex; flex-direction:column; }
        .logo-name { font-size:13px; font-weight:700; letter-spacing:0.02em; }
        .logo-sub { font-size:10px; color:var(--text3); text-transform:uppercase; letter-spacing:0.08em; }
        .sidebar-nav { padding:16px 12px; flex:1; }
        .nav-label { font-size:10px; font-weight:600; color:var(--text3); text-transform:uppercase; letter-spacing:0.08em; padding:0 8px; margin-bottom:8px; }
        .nav-item { display:flex; align-items:center; gap:10px; padding:9px 10px; border-radius:var(--radius-sm); font-size:13px; font-weight:500; color:var(--text2); text-decoration:none; margin-bottom:2px; }
        .nav-item:hover { background:var(--surface2); color:var(--text); }
        .nav-item.active { background:var(--red-soft); color:var(--red); }
        .nav-item svg { width:16px; height:16px; flex-shrink:0; }
        .nav-item.active svg { stroke:var(--red); }
        .sidebar-footer { padding:16px 12px; border-top:1px solid var(--border); }
        .user-card { display:flex; align-items:center; gap:10px; padding:8px; border-radius:var(--radius-sm); background:var(--surface2); }
        .user-avatar { width:32px; height:32px; background:var(--red); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; }
        .user-info { flex:1; min-width:0; }
        .user-name { font-size:12px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .user-role { font-size:10px; color:var(--text3); text-transform:uppercase; letter-spacing:0.06em; }
        .btn-logout-sm { font-size:10px; padding:4px 8px; border-radius:6px; border:1px solid var(--red-mid); background:var(--red-soft); color:var(--red); text-decoration:none; font-weight:600; }
        .main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; }
        .topbar { background:var(--surface); border-bottom:1px solid var(--border); padding:0 28px; height:56px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
        .topbar-title { font-size:15px; font-weight:700; }
        .content { padding:24px 28px; max-width:1400px; }
        .banner { padding:12px 16px; border-radius:var(--radius-sm); font-size:13px; font-weight:500; margin-bottom:16px; }
        .banner.ok { background:var(--green-soft); color:var(--green); }
        .banner.err { background:var(--red-soft); color:var(--red); }
        .intro { font-size:13px; color:var(--text2); margin-bottom:20px; line-height:1.6; }
        .intro strong { color:var(--text); }
        .shift-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-bottom:20px; }
        @media (max-width:1200px) { .shift-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:640px)  { .shift-grid { grid-template-columns:1fr; } }
        .shift-card { background:var(--surface); border-radius:var(--radius); border:1px solid var(--border); box-shadow:var(--shadow); overflow:hidden; }
        .shift-card-head { padding:14px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; }
        .shift-card-head .bar { width:3px; height:16px; background:var(--red); border-radius:2px; }
        .shift-card-head .t { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; }
        .shift-row { padding:12px 18px; border-bottom:1px solid var(--border); }
        .shift-row:last-child { border-bottom:none; }
        .shift-row-label { font-size:11px; font-weight:700; color:var(--red); margin-bottom:8px; }
        .field-line { display:flex; gap:10px; flex-wrap:wrap; }
        .field { display:flex; flex-direction:column; gap:4px; }
        .field label { font-size:9px; font-weight:600; color:var(--text3); text-transform:uppercase; letter-spacing:0.05em; }
        .field input { font-family:inherit; font-size:13px; padding:7px 9px; border-radius:6px; border:1px solid var(--border); background:var(--surface2); outline:none; width:100px; }
        .field input:focus { border-color:var(--red); }
        .field .hint { font-size:9px; color:var(--text3); }
        .field input[type=time] { width:124px; }
        .field .out-efektif { font-family:inherit; font-size:14px; font-weight:700; color:var(--red); padding:7px 9px; border-radius:6px; border:1px solid var(--red-mid); background:var(--red-soft); width:100px; display:inline-block; }
        .field .out-efektif.bad { color:var(--text3); background:var(--surface2); border-color:var(--border); }
        .shift-breakdown { margin-top:10px; font-size:11px; color:var(--text2); font-family:'JetBrains Mono',monospace; background:var(--surface2); border:1px dashed var(--border); border-radius:6px; padding:7px 10px; line-height:1.5; }
        .shift-breakdown .eq { color:var(--red); font-weight:700; }
        .shift-breakdown.bad { color:var(--text3); }
        .actions { display:flex; gap:10px; align-items:center; }
        .btn { font-family:inherit; font-size:13px; font-weight:600; padding:10px 22px; border-radius:var(--radius-sm); border:none; cursor:pointer; text-decoration:none; }
        .btn-red { background:var(--red); color:#fff; } .btn-red:hover { background:var(--red-dark); }
        .btn-ghost { background:var(--surface2); color:var(--text2); border:1px solid var(--border); }
        .note { font-size:11px; color:var(--text3); }
    </style>
</head>
<body>

<?php $active_nav = 'shift_setting'; include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <div class="topbar"><span class="topbar-title">Setting Shift</span></div>
    <div class="content">

        <?php if (isset($_GET['ok'])): ?>
            <div class="banner ok"><?php echo htmlspecialchars($_GET['ok']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['err'])): ?>
            <div class="banner err"><?php echo htmlspecialchars($_GET['err']); ?></div>
        <?php endif; ?>

        <p class="intro">
            Atur jam mulai, jam selesai, dan <strong>lama istirahat (menit)</strong> tiap shift.
            <strong>Jam efektif</strong> dihitung otomatis = (jam selesai − jam mulai) − istirahat, dan dipakai
            sebagai pembagi Operation Ratio. Untuk shift malam, jam selesai boleh lebih kecil dari jam mulai
            (otomatis dianggap lewat tengah malam).
        </p>

        <form action="shift_setting_action.php" method="POST">
            <div class="shift-grid">
                <?php foreach ($LABEL_TIPE as $tipe => $labelTipe): ?>
                    <div class="shift-card">
                        <div class="shift-card-head"><span class="bar"></span><span class="t"><?php echo $labelTipe; ?></span></div>
                        <?php for ($no = 1; $no <= 3; $no++):
                            $v = nilaiShift($tersimpan, $def, $tipe, $no);
                        ?>
                        <div class="shift-row">
                            <div class="shift-row-label">Shift <?php echo $no; ?></div>
                            <div class="field-line">
                                <div class="field">
                                    <label>Jam Mulai</label>
                                    <input type="time" class="in-mulai" name="shift[<?php echo $tipe; ?>][<?php echo $no; ?>][mulai]" value="<?php echo $v['mulai']; ?>" required>
                                </div>
                                <div class="field">
                                    <label>Jam Selesai</label>
                                    <input type="time" class="in-selesai" name="shift[<?php echo $tipe; ?>][<?php echo $no; ?>][selesai]" value="<?php echo $v['selesai']; ?>" required>
                                </div>
                                <div class="field">
                                    <label>Istirahat</label>
                                    <input type="number" class="in-istirahat" step="1" min="0" max="1440" name="shift[<?php echo $tipe; ?>][<?php echo $no; ?>][istirahat]" value="<?php echo $v['istirahat']; ?>" required>
                                    <span class="hint">menit</span>
                                </div>
                                <div class="field">
                                    <label>Jam Efektif</label>
                                    <output class="out-efektif">—</output>
                                    <span class="hint">otomatis (jam kerja bersih)</span>
                                </div>
                            </div>
                            <div class="shift-breakdown">—</div>
                        </div>
                        <?php endfor; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-red">Simpan Semua</button>
                <a href="dashboard.php" class="btn btn-ghost">Batal</a>
                <span class="note">Perubahan langsung dipakai untuk semua perhitungan ratio &amp; shift.</span>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // Jam efektif = (jam_selesai - jam_mulai) - istirahat, dihitung otomatis.
    // Kalau jam_selesai <= jam_mulai berarti shift lewat tengah malam (+1440).
    function menit(t) {
        if (!t) return null;
        var p = t.split(':');
        return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
    }
    function fmtJam(m) {
        var j = m / 60;
        return (Math.round(j * 100) / 100).toString().replace(/\.00$/, '') + ' jam';
    }
    // menit → "8j 45m" (format jelas untuk rincian)
    function fmtJM(m) {
        return Math.floor(m / 60) + 'j ' + (m % 60) + 'm';
    }
    function hitung(row) {
        var vMulai   = row.querySelector('.in-mulai').value;
        var vSelesai = row.querySelector('.in-selesai').value;
        var mulai    = menit(vMulai);
        var selesai  = menit(vSelesai);
        var ist      = parseInt(row.querySelector('.in-istirahat').value, 10);
        var out      = row.querySelector('.out-efektif');
        var bd       = row.querySelector('.shift-breakdown');
        if (mulai === null || selesai === null || isNaN(ist)) {
            out.textContent = '—'; out.classList.add('bad');
            if (bd) { bd.textContent = '—'; bd.classList.add('bad'); }
            return;
        }
        var span = selesai - mulai;
        if (span <= 0) span += 1440;
        var efektif = span - ist;
        if (efektif <= 0) {
            out.textContent = 'invalid'; out.classList.add('bad');
            if (bd) { bd.textContent = 'Istirahat melebihi durasi shift'; bd.classList.add('bad'); }
            return;
        }
        out.textContent = fmtJam(efektif); out.classList.remove('bad');
        if (bd) {
            bd.classList.remove('bad');
            // Rincian dengan jam format 24-jam yang tidak ambigu.
            bd.innerHTML = vMulai + ' → ' + vSelesai + ' · durasi ' + fmtJM(span)
                + ' − istirahat ' + ist + 'm '
                + '<span class="eq">= ' + fmtJM(efektif) + ' efektif</span>';
        }
    }
    document.querySelectorAll('.shift-row').forEach(function (row) {
        row.querySelectorAll('.in-mulai, .in-selesai, .in-istirahat').forEach(function (el) {
            el.addEventListener('input', function () { hitung(row); });
        });
        hitung(row);
    });
})();
</script>
</body>
</html>

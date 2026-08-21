<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/calendar.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$tahun = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
if ($tahun < 2000 || $tahun > 2100) $tahun = (int)date('Y');

// Pengecualian tahun ini untuk tabel di bawah.
$map = qcMuatKalender();
$pengecualian = [];
foreach ($map as $tgl => $info) {
    if (substr($tgl, 0, 4) === (string)$tahun) {
        $pengecualian[$tgl] = $info;
    }
}
ksort($pengecualian);

$BULAN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
          'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$HARI  = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

$nama_login = $_SESSION['nama'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalender Kerja — QC Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root {
            --red:#CC0000; --red-dark:#A30000; --red-soft:#fff0f0; --red-mid:#ffd5d5;
            --bg:#f4f5f7; --surface:#fff; --surface2:#f9fafb; --border:rgba(0,0,0,0.07);
            --text:#111827; --text2:#6b7280; --text3:#9ca3af;
            --green:#059669; --green-soft:#d1fae5; --amber:#b45309; --amber-soft:#fef3c7;
            --sidebar-w:220px; --radius:12px; --radius-sm:8px; --shadow:0 1px 4px rgba(0,0,0,0.07);
        }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }
        .main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; }
        .topbar { background:var(--surface); border-bottom:1px solid var(--border); padding:0 28px; height:56px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
        .topbar-title { font-size:15px; font-weight:700; }
        .year-nav { display:flex; align-items:center; gap:8px; }
        .year-nav a { width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid var(--border); background:var(--surface2); color:var(--text2); text-decoration:none; font-weight:700; }
        .year-nav a:hover { background:var(--surface); color:var(--red); border-color:var(--red-mid); }
        .year-nav .yr { font-size:14px; font-weight:700; min-width:56px; text-align:center; }
        .content { padding:24px 28px; max-width:1240px; }
        .banner { padding:12px 16px; border-radius:var(--radius-sm); font-size:13px; font-weight:500; margin-bottom:16px; }
        .banner.ok { background:var(--green-soft); color:var(--green); }
        .banner.err { background:var(--red-soft); color:var(--red); }
        .intro { font-size:13px; color:var(--text2); margin-bottom:16px; line-height:1.6; }
        .intro strong { color:var(--text); }
        .legend { display:flex; flex-wrap:wrap; gap:14px; margin-bottom:20px; font-size:12px; color:var(--text2); }
        .legend span { display:inline-flex; align-items:center; gap:6px; }
        .swatch { width:14px; height:14px; border-radius:4px; border:1px solid var(--border); }
        .sw-work { background:#fff; } .sw-off { background:#eef0f3; }
        .sw-holiday { background:var(--red-soft); border-color:var(--red-mid); }
        .sw-overtime { background:var(--green-soft); border-color:#a7f3d0; }
        .cal-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
        .month { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
        .month-head { padding:12px 16px; border-bottom:1px solid var(--border); font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; }
        .month-body { padding:10px 12px 14px; }
        .dow { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; margin-bottom:4px; }
        .dow span { text-align:center; font-size:9px; font-weight:700; color:var(--text3); text-transform:uppercase; }
        .days { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }
        .day { aspect-ratio:1; display:flex; align-items:center; justify-content:center; font-size:12px; border-radius:6px; cursor:pointer; border:1px solid transparent; position:relative; }
        .day.empty { cursor:default; }
        .day.work { background:#fff; } .day.off { background:#eef0f3; color:var(--text3); }
        .day.holiday { background:var(--red-soft); color:var(--red); font-weight:700; }
        .day.overtime { background:var(--green-soft); color:var(--green); font-weight:700; }
        .day:not(.empty):hover { border-color:var(--red); }
        .day.today::after { content:''; position:absolute; bottom:3px; left:50%; transform:translateX(-50%); width:4px; height:4px; border-radius:50%; background:var(--red); }
        .excep { margin-top:26px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
        .excep-head { padding:14px 18px; border-bottom:1px solid var(--border); font-size:13px; font-weight:700; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:10px 18px; font-size:12px; border-bottom:1px solid var(--border); }
        th { font-size:10px; text-transform:uppercase; letter-spacing:0.05em; color:var(--text3); font-weight:700; }
        tr:last-child td { border-bottom:none; }
        .pill { font-size:10px; font-weight:700; padding:3px 8px; border-radius:20px; }
        .pill.holiday { background:var(--red-soft); color:var(--red); }
        .pill.overtime { background:var(--green-soft); color:var(--green); }
        .tbl-empty { padding:20px 18px; font-size:12px; color:var(--text3); }
        .link-edit { color:var(--red); text-decoration:none; font-weight:600; font-size:11px; }
        /* Modal */
        .overlay { position:fixed; inset:0; background:rgba(17,24,39,0.5); display:none; align-items:center; justify-content:center; z-index:300; }
        .overlay.show { display:flex; }
        .modal { background:var(--surface); border-radius:var(--radius); width:360px; max-width:92vw; box-shadow:0 20px 50px rgba(0,0,0,0.25); overflow:hidden; }
        .modal-head { padding:16px 20px; border-bottom:1px solid var(--border); font-size:14px; font-weight:700; }
        .modal-body { padding:18px 20px; }
        .modal-body .lbl { font-size:10px; font-weight:600; color:var(--text3); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px; display:block; }
        .modal-body .val-date { font-size:15px; font-weight:700; margin-bottom:16px; }
        .radio-set { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
        .radio-set label { display:flex; align-items:center; gap:8px; font-size:13px; padding:9px 12px; border:1px solid var(--border); border-radius:8px; cursor:pointer; }
        .radio-set label:hover { background:var(--surface2); }
        .radio-set input { accent-color:var(--red); }
        .modal-body input[type=text] { width:100%; font-family:inherit; font-size:13px; padding:9px 11px; border:1px solid var(--border); border-radius:8px; background:var(--surface2); outline:none; }
        .modal-body input[type=text]:focus { border-color:var(--red); }
        .modal-foot { padding:14px 20px; border-top:1px solid var(--border); display:flex; gap:10px; justify-content:flex-end; }
        .btn { font-family:inherit; font-size:13px; font-weight:600; padding:9px 18px; border-radius:var(--radius-sm); border:none; cursor:pointer; text-decoration:none; }
        .btn-red { background:var(--red); color:#fff; } .btn-red:hover { background:var(--red-dark); }
        .btn-ghost { background:var(--surface2); color:var(--text2); border:1px solid var(--border); }
    </style>
</head>
<body>

<?php $active_nav = 'kalender'; include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <span class="topbar-title">Kalender Kerja YADIN</span>
        <div class="year-nav">
            <a href="?y=<?php echo $tahun - 1; ?>">&lsaquo;</a>
            <span class="yr"><?php echo $tahun; ?></span>
            <a href="?y=<?php echo $tahun + 1; ?>">&rsaquo;</a>
        </div>
    </div>
    <div class="content">

        <?php if (isset($_GET['ok'])): ?>
            <div class="banner ok"><?php echo htmlspecialchars($_GET['ok']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['err'])): ?>
            <div class="banner err"><?php echo htmlspecialchars($_GET['err']); ?></div>
        <?php endif; ?>

        <p class="intro">
            Hari normal diturunkan otomatis: <strong>Senin–Jumat kerja</strong>, <strong>Sabtu–Minggu libur</strong>.
            Klik tanggal untuk menandainya sebagai <strong>libur</strong> (hari kerja yang diliburkan) atau
            <strong>lembur</strong> (akhir pekan yang tetap dikerjakan). Hanya tanggal pengecualian yang disimpan.
        </p>

        <div class="legend">
            <span><i class="swatch sw-work"></i> Hari kerja</span>
            <span><i class="swatch sw-off"></i> Libur akhir pekan</span>
            <span><i class="swatch sw-holiday"></i> Libur (holiday)</span>
            <span><i class="swatch sw-overtime"></i> Lembur (overtime)</span>
        </div>

        <div class="cal-grid">
            <?php
            $hariIni = date('Y-m-d');
            for ($m = 1; $m <= 12; $m++):
                $pertama    = mktime(0, 0, 0, $m, 1, $tahun);
                $jmlHari    = (int)date('t', $pertama);
                $offset     = (int)date('N', $pertama) - 1; // 0=Senin
            ?>
            <div class="month">
                <div class="month-head"><?php echo $BULAN[$m]; ?></div>
                <div class="month-body">
                    <div class="dow"><?php foreach ($HARI as $h) echo "<span>$h</span>"; ?></div>
                    <div class="days">
                        <?php for ($i = 0; $i < $offset; $i++) echo '<div class="day empty"></div>'; ?>
                        <?php for ($d = 1; $d <= $jmlHari; $d++):
                            $tgl  = sprintf('%04d-%02d-%02d', $tahun, $m, $d);
                            $info = qcHari($tgl);
                            $cls  = $info['tipe']; // work|off|holiday|overtime
                            $today = ($tgl === $hariIni) ? ' today' : '';
                            $ttl  = $info['label'] !== '' ? htmlspecialchars($info['label']) : '';
                        ?>
                        <div class="day <?php echo $cls . $today; ?>"
                             data-date="<?php echo $tgl; ?>"
                             data-tipe="<?php echo $cls === 'off' || $cls === 'work' ? 'normal' : $cls; ?>"
                             data-label="<?php echo htmlspecialchars($info['label']); ?>"
                             title="<?php echo $ttl; ?>"><?php echo $d; ?></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <div class="excep">
            <div class="excep-head">Daftar Pengecualian <?php echo $tahun; ?> (<?php echo count($pengecualian); ?>)</div>
            <?php if (!$pengecualian): ?>
                <div class="tbl-empty">Belum ada pengecualian untuk tahun ini.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Tanggal</th><th>Hari</th><th>Tipe</th><th>Keterangan</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pengecualian as $tgl => $info):
                    $ts = strtotime($tgl);
                    $namaHari = $HARI[(int)date('N', $ts) - 1];
                ?>
                    <tr>
                        <td><?php echo date('d M Y', $ts); ?></td>
                        <td><?php echo $namaHari; ?></td>
                        <td><span class="pill <?php echo $info['tipe']; ?>"><?php echo $info['tipe'] === 'holiday' ? 'Libur' : 'Lembur'; ?></span></td>
                        <td><?php echo htmlspecialchars($info['label']); ?></td>
                        <td><a href="#" class="link-edit"
                               data-date="<?php echo $tgl; ?>"
                               data-tipe="<?php echo $info['tipe']; ?>"
                               data-label="<?php echo htmlspecialchars($info['label']); ?>">Ubah</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal edit -->
<div class="overlay" id="overlay">
    <form class="modal" action="kalender_action.php" method="POST">
        <div class="modal-head">Atur Tanggal</div>
        <div class="modal-body">
            <span class="lbl">Tanggal</span>
            <div class="val-date" id="m-date-text">—</div>
            <input type="hidden" name="tanggal" id="m-tanggal">

            <span class="lbl">Status</span>
            <div class="radio-set">
                <label><input type="radio" name="tipe" value="normal" id="r-normal"> Normal (ikut aturan hari)</label>
                <label><input type="radio" name="tipe" value="holiday" id="r-holiday"> Libur (holiday)</label>
                <label><input type="radio" name="tipe" value="overtime" id="r-overtime"> Lembur (overtime)</label>
            </div>

            <span class="lbl">Keterangan</span>
            <input type="text" name="label" id="m-label" maxlength="100" placeholder="mis. Tahun Baru / Lembur Sabtu">
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="m-cancel">Batal</button>
            <button type="submit" class="btn btn-red">Simpan</button>
        </div>
    </form>
</div>

<script>
(function () {
    var overlay = document.getElementById('overlay');
    var mDateText = document.getElementById('m-date-text');
    var mTanggal = document.getElementById('m-tanggal');
    var mLabel = document.getElementById('m-label');

    function fmt(iso) {
        var p = iso.split('-');
        var bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        return parseInt(p[2], 10) + ' ' + bulan[parseInt(p[1], 10) - 1] + ' ' + p[0];
    }

    function openModal(date, tipe, label) {
        mTanggal.value = date;
        mDateText.textContent = fmt(date);
        mLabel.value = label || '';
        document.getElementById('r-' + (tipe || 'normal')).checked = true;
        overlay.classList.add('show');
    }
    function closeModal() { overlay.classList.remove('show'); }

    document.querySelectorAll('.day:not(.empty)').forEach(function (el) {
        el.addEventListener('click', function () {
            openModal(el.dataset.date, el.dataset.tipe, el.dataset.label);
        });
    });
    document.querySelectorAll('.link-edit').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            openModal(el.dataset.date, el.dataset.tipe, el.dataset.label);
        });
    });

    document.getElementById('m-cancel').addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
})();
</script>
</body>
</html>

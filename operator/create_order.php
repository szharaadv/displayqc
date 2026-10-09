<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/csrf.php';
/** @var mysqli $conn */

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Master data dikirim ke JS sekali; filter per category & pencarian part terjadi di browser.
$ambil = function (string $sql) use ($conn): array {
    $out = [];
    $q = mysqli_query($conn, $sql);
    while ($r = mysqli_fetch_assoc($q)) $out[] = $r;
    return $out;
};
$parts    = $ambil("SELECT id, category, part_no, part_name FROM master_parts ORDER BY category, part_name");
$lines    = $ambil("SELECT id, category, catalog_line AS nama FROM master_lines ORDER BY category, catalog_line");
$machines = $ambil("SELECT id, category, machine_jig_catalog AS nama FROM master_machines ORDER BY category, machine_jig_catalog");

$KATEGORI = ['CONROD', 'MS1', 'MS2'];
$jumlahPart = array_count_values(array_column($parts, 'category'));

$nama    = ucwords(strtolower(trim($_SESSION['nama'] ?? '')));
$depan   = explode(' ', $nama)[0];
$kembali = ($_SESSION['role'] ?? '') === 'qc' && str_contains($_SERVER['HTTP_REFERER'] ?? '', 'main_display') ? '../qc/main_display.php' : '../menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Order — QC Display</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root {
            --red:#CC0000; --red-dark:#A30000; --red-soft:#fff0f0; --red-mid:#ffd5d5;
            --bg:#f4f5f7; --surface:#fff; --surface2:#f9fafb; --border:rgba(0,0,0,0.08);
            --text:#111827; --text2:#6b7280; --text3:#9ca3af; --green:#059669; --green-soft:#d1fae5;
        }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
        .top { background:var(--surface); border-bottom:1px solid var(--border); height:60px; padding:0 24px; display:flex; align-items:center; gap:14px; position:sticky; top:0; z-index:10; }
        .back { width:36px; height:36px; border-radius:10px; border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--text2); text-decoration:none; font-size:18px; }
        .back:hover { color:var(--red); border-color:var(--red-mid); background:var(--red-soft); }
        .top h1 { font-size:16px; font-weight:800; }
        .top .who { margin-left:auto; font-size:13px; color:var(--text2); }
        .top .who b { color:var(--text); }

        .wrap { max-width:1100px; margin:0 auto; padding:24px; display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:20px; align-items:start; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:16px; box-shadow:0 1px 4px rgba(0,0,0,.05); }
        .step { padding:20px 22px; border-bottom:1px solid var(--border); }
        .step:last-child { border-bottom:none; }
        .step-head { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
        .num { width:26px; height:26px; border-radius:50%; background:var(--surface2); border:1px solid var(--border); color:var(--text3); font-size:12px; font-weight:800; display:flex; align-items:center; justify-content:center; transition:all .2s; }
        .step.done .num { background:var(--green); border-color:var(--green); color:#fff; }
        .step-head b { font-size:14px; }
        .step-head span { font-size:12px; color:var(--text3); margin-left:auto; }
        .step.locked { opacity:.45; pointer-events:none; }

        .cats { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
        .cat { position:relative; border:1.5px solid var(--border); border-radius:12px; padding:14px; cursor:pointer; transition:all .15s; background:var(--surface); }
        .cat:hover { border-color:var(--red-mid); }
        .cat input { position:absolute; opacity:0; }
        .cat b { display:block; font-size:16px; font-weight:800; }
        .cat small { font-size:11px; color:var(--text3); }
        .cat.on { border-color:var(--red); background:var(--red-soft); box-shadow:0 0 0 3px rgba(204,0,0,.08); }
        .cat.on b { color:var(--red); }

        .search { width:100%; font-family:inherit; font-size:14px; padding:11px 14px 11px 38px; border-radius:10px; border:1px solid var(--border); background:var(--surface2) no-repeat 12px center / 16px url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E"); outline:none; }
        .search:focus { border-color:var(--red); background-color:#fff; }
        .plist { margin-top:10px; max-height:260px; overflow-y:auto; border:1px solid var(--border); border-radius:10px; }
        .pitem { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 14px; border-bottom:1px solid var(--border); cursor:pointer; font-size:13px; }
        .pitem:last-child { border-bottom:none; }
        .pitem:hover { background:var(--surface2); }
        .pitem.on { background:var(--red-soft); }
        .pitem.on b { color:var(--red); }
        .pitem code { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--text3); white-space:nowrap; }
        .pempty { padding:18px; text-align:center; color:var(--text3); font-size:13px; }
        .picked { display:flex; align-items:center; gap:12px; padding:12px 14px; border:1.5px solid var(--red); background:var(--red-soft); border-radius:12px; animation:pop .2s ease; }
        .picked[hidden] { display:none; }
        .picked .ok { width:30px; height:30px; border-radius:50%; background:var(--red); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; flex-shrink:0; }
        .picked .t { min-width:0; flex:1; }
        .picked .t b { display:block; font-size:15px; color:var(--red); }
        .picked .t code { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--text2); }
        .picked button { font-family:inherit; font-size:12px; font-weight:700; padding:7px 14px; border-radius:999px; border:1px solid var(--red-mid); background:#fff; color:var(--red); cursor:pointer; }
        .picked button:hover { background:var(--red); color:#fff; }
        @keyframes pop { from { opacity:0; transform:scale(.98); } to { opacity:1; transform:none; } }

        .row2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .field label { display:block; font-size:11px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
        .field select { width:100%; appearance:none; font-family:inherit; font-size:14px; padding:11px 36px 11px 14px; border-radius:10px; border:1px solid var(--border); background:var(--surface2) no-repeat right 12px center / 14px url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2.5'%3E%3Cpolyline points='6,9 12,15 18,9'/%3E%3C/svg%3E"); color:var(--text); outline:none; cursor:pointer; }
        .field select:focus { border-color:var(--red); background-color:#fff; }

        .qty { display:inline-flex; align-items:center; border:1px solid var(--border); border-radius:12px; overflow:hidden; }
        .qty button { width:44px; height:44px; border:none; background:var(--surface2); font-size:20px; color:var(--text2); cursor:pointer; }
        .qty button:hover { background:var(--red-soft); color:var(--red); }
        .qty input { width:70px; height:44px; border:none; border-left:1px solid var(--border); border-right:1px solid var(--border); text-align:center; font-family:inherit; font-size:18px; font-weight:800; outline:none; -moz-appearance:textfield; }
        .qty input::-webkit-inner-spin-button { -webkit-appearance:none; }

        .sum { position:sticky; top:84px; padding:20px; }
        .sum h2 { font-size:13px; text-transform:uppercase; letter-spacing:.06em; color:var(--text3); margin-bottom:14px; }
        .sum dl { display:grid; gap:12px; }
        .sum dt { font-size:11px; color:var(--text3); font-weight:600; }
        .sum dd { font-size:14px; font-weight:700; margin-top:2px; word-break:break-word; }
        .sum dd.empty { color:var(--text3); font-weight:500; font-style:italic; }
        .sum dd code { display:block; font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--text2); font-weight:500; margin-top:2px; }
        .submit { width:100%; margin-top:18px; font-family:inherit; font-size:15px; font-weight:800; padding:14px; border:none; border-radius:12px; background:var(--red); color:#fff; cursor:pointer; transition:background .15s, transform .1s; }
        .submit:hover:not(:disabled) { background:var(--red-dark); }
        .submit:active:not(:disabled) { transform:scale(.98); }
        .submit:disabled { background:#e5e7eb; color:var(--text3); cursor:not-allowed; }
        .note { font-size:11px; color:var(--text3); text-align:center; margin-top:10px; }
        @media (max-width:860px) { .wrap { grid-template-columns:1fr; padding:16px; } .sum { position:static; } .row2 { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="top">
        <a class="back" href="<?php echo $kembali; ?>" title="Back">‹</a>
        <h1>Create Sampling Order</h1>
        <div class="who">Ordering as <b><?php echo htmlspecialchars($nama); ?></b></div>
    </div>

    <form class="wrap" action="simpan_order.php" method="POST" id="f">
        <?php echo csrfField(); ?>
        <input type="hidden" name="part_id" id="part_id">

        <div class="card">
            <div class="step" id="s1">
                <div class="step-head"><div class="num">1</div><b>Category</b><span>Hi <?php echo htmlspecialchars($depan); ?>, what are we sampling today?</span></div>
                <div class="cats">
                    <?php foreach ($KATEGORI as $k): ?>
                    <label class="cat"><input type="radio" name="category" value="<?php echo $k; ?>" required>
                        <b><?php echo $k; ?></b><small><?php echo (int)($jumlahPart[$k] ?? 0); ?> parts</small></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="step locked" id="s2">
                <div class="step-head"><div class="num">2</div><b>Part</b><span id="pcount"></span></div>
                <div id="ppilih">
                    <input type="text" class="search" id="pcari" placeholder="Search part name or part number…" autocomplete="off">
                    <div class="plist" id="plist"></div>
                </div>
                <div class="picked" id="picked" hidden>
                    <div class="ok">✓</div>
                    <div class="t"><b id="pk-nama"></b><code id="pk-no"></code></div>
                    <button type="button" id="pk-ganti">Change</button>
                </div>
            </div>

            <div class="step locked" id="s3">
                <div class="step-head"><div class="num">3</div><b>Line &amp; Machine</b></div>
                <div class="row2">
                    <div class="field"><label>Catalog Line</label><select name="line_id" id="line_id" required></select></div>
                    <div class="field"><label>Machine No / Jig Catalog</label><select name="machine_id" id="machine_id" required></select></div>
                </div>
            </div>

            <div class="step locked" id="s4">
                <div class="step-head"><div class="num">4</div><b>Quantity</b></div>
                <div class="qty">
                    <button type="button" data-d="-1" aria-label="Decrease">−</button>
                    <input type="number" name="qty" id="qty" value="1" min="1" max="99" required>
                    <button type="button" data-d="1" aria-label="Increase">+</button>
                </div>
            </div>
        </div>

        <div class="card sum">
            <h2>Order summary</h2>
            <dl>
                <div><dt>Category</dt><dd id="v-cat" class="empty">Not selected</dd></div>
                <div><dt>Part</dt><dd id="v-part" class="empty">Not selected</dd></div>
                <div><dt>Line</dt><dd id="v-line" class="empty">Not selected</dd></div>
                <div><dt>Machine / Jig</dt><dd id="v-mesin" class="empty">Not selected</dd></div>
                <div><dt>Quantity</dt><dd id="v-qty">1</dd></div>
            </dl>
            <button type="submit" class="submit" id="btn" disabled>Submit Order</button>
            <div class="note">The order goes straight to the QC request queue.</div>
        </div>
    </form>

    <script>
    (function () {
        var PARTS = <?php echo json_encode($parts, JSON_HEX_TAG); ?>,
            LINES = <?php echo json_encode($lines, JSON_HEX_TAG); ?>,
            MACH  = <?php echo json_encode($machines, JSON_HEX_TAG); ?>;
        var $ = function (id) { return document.getElementById(id); };
        var kat = '', part = null;

        function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
        function isi(sel, rows, label) {
            sel.innerHTML = '<option value="">— Select ' + label + ' —</option>' + rows.filter(function (r) { return r.category === kat; })
                .map(function (r) { return '<option value="' + r.id + '">' + esc(r.nama) + '</option>'; }).join('');
        }
        function tampilPart() {
            var q = $('pcari').value.trim().toLowerCase();
            var list = PARTS.filter(function (p) { return p.category === kat && (!q || (p.part_name + ' ' + p.part_no).toLowerCase().indexOf(q) >= 0); });
            $('pcount').textContent = list.length + ' found';
            $('plist').innerHTML = list.length ? list.map(function (p) {
                return '<div class="pitem' + (part && part.id === p.id ? ' on' : '') + '" data-id="' + p.id + '"><b>' + esc(p.part_name) + '</b><code>' + esc(p.part_no) + '</code></div>';
            }).join('') : '<div class="pempty">No part matches “' + esc(q) + '”.</div>';
        }
        function set(id, teks, sub) {
            var el = $(id);
            el.classList.toggle('empty', !teks);
            el.innerHTML = teks ? esc(teks) + (sub ? '<code>' + esc(sub) + '</code>' : '') : 'Not selected';
        }
        function opsiTeks(sel) { return sel.value ? sel.options[sel.selectedIndex].text : ''; }
        function segarkan() {
            var line = $('line_id').value, mesin = $('machine_id').value;
            $('s1').classList.toggle('done', !!kat);
            $('s2').classList.toggle('locked', !kat); $('s2').classList.toggle('done', !!part);
            $('s3').classList.toggle('locked', !part); $('s3').classList.toggle('done', !!(line && mesin));
            $('s4').classList.toggle('locked', !(line && mesin)); $('s4').classList.toggle('done', !!(line && mesin));
            set('v-cat', kat);
            set('v-part', part ? part.part_name : '', part ? part.part_no : '');
            set('v-line', opsiTeks($('line_id')));
            set('v-mesin', opsiTeks($('machine_id')));
            $('v-qty').textContent = $('qty').value;
            $('btn').disabled = !(kat && part && line && mesin && +$('qty').value >= 1);
        }

        document.querySelectorAll('.cat input').forEach(function (r) {
            r.addEventListener('change', function () {
                kat = r.value; part = null; $('part_id').value = ''; $('pcari').value = ''; tutupDaftar(false);
                document.querySelectorAll('.cat').forEach(function (c) { c.classList.toggle('on', c.contains(r)); });
                isi($('line_id'), LINES, 'line'); isi($('machine_id'), MACH, 'machine');
                tampilPart(); segarkan(); $('pcari').focus();
            });
        });
        // Setelah part dipilih, daftar ditutup & diganti kartu part terpilih (+ tombol Change).
        function tutupDaftar(tutup) {
            $('ppilih').hidden = tutup;
            $('picked').hidden = !tutup;
            if (tutup) { $('pk-nama').textContent = part.part_name; $('pk-no').textContent = part.part_no; }
        }
        $('pcari').addEventListener('input', tampilPart);
        $('plist').addEventListener('click', function (e) {
            var it = e.target.closest('.pitem'); if (!it) return;
            part = PARTS.find(function (p) { return p.id === it.dataset.id; });
            $('part_id').value = part.id;
            tutupDaftar(true); segarkan();
            $('line_id').focus();
        });
        $('pk-ganti').addEventListener('click', function () {
            tutupDaftar(false); tampilPart(); $('pcari').focus();
        });
        ['line_id', 'machine_id', 'qty'].forEach(function (id) { $(id).addEventListener('change', segarkan); $(id).addEventListener('input', segarkan); });
        document.querySelectorAll('.qty button').forEach(function (b) {
            b.addEventListener('click', function () { $('qty').value = Math.min(99, Math.max(1, (+$('qty').value || 1) + +b.dataset.d)); segarkan(); });
        });
        $('f').addEventListener('submit', function () { $('btn').disabled = true; $('btn').textContent = 'Submitting…'; });
        segarkan();
    })();
    </script>
</body>
</html>

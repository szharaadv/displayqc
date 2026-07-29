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

// Definisi tiap tab master — dipakai untuk header tabel, form, dan query.
$TAB = [
    'parts' => [
        'label'      => 'Part',
        'nama_tabel' => 'master_parts',
        'fk'         => 'part_id',
        'kolom'      => ['part_no' => 'Part Number', 'part_name' => 'Part Name'],
    ],
    'lines' => [
        'label'      => 'Line',
        'nama_tabel' => 'master_lines',
        'fk'         => 'line_id',
        'kolom'      => ['catalog_line' => 'Catalog Line'],
    ],
    'machines' => [
        'label'      => 'Machine / Jig',
        'nama_tabel' => 'master_machines',
        'fk'         => 'machine_id',
        'kolom'      => ['machine_jig_catalog' => 'Machine No / Jig Catalog'],
    ],
];
$KATEGORI = ['CONROD', 'MS1', 'MS2'];

$tab = $_GET['tab'] ?? 'parts';
if (!isset($TAB[$tab])) $tab = 'parts';
$cfg   = $TAB[$tab];
$tabel = $cfg['nama_tabel'];
$fk    = $cfg['fk'];

$filter_kat = $_GET['kat'] ?? 'all';
$whereKat   = '';
if (in_array($filter_kat, $KATEGORI, true)) {
    $whereKat = " WHERE category = '" . mysqli_real_escape_string($conn, $filter_kat) . "' ";
}

// Ambil data + berapa kali dipakai order (untuk menandai baris yang tak bisa dihapus)
$kolom_sel = implode(', ', array_keys($cfg['kolom']));
$rows = [];
$q = mysqli_query($conn, "
    SELECT m.id, m.category, $kolom_sel,
           (SELECT COUNT(*) FROM sampling_orders so WHERE so.$fk = m.id) AS dipakai
    FROM $tabel m
    $whereKat
    ORDER BY m.category ASC, " . array_key_first($cfg['kolom']) . " ASC
");
while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;

$nama_login = $_SESSION['nama'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Master — QC Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --red:#CC0000; --red-dark:#A30000; --red-soft:#fff0f0; --red-mid:#ffd5d5;
            --bg:#f4f5f7; --surface:#fff; --surface2:#f9fafb; --border:rgba(0,0,0,0.07);
            --text:#111827; --text2:#6b7280; --text3:#9ca3af; --green:#059669; --green-soft:#d1fae5;
            --sidebar-w:220px; --radius:12px; --radius-sm:8px;
            --shadow:0 1px 4px rgba(0,0,0,0.07); --shadow-md:0 4px 20px rgba(0,0,0,0.09);
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
        .nav-item { display:flex; align-items:center; gap:10px; padding:9px 10px; border-radius:var(--radius-sm); font-size:13px; font-weight:500; color:var(--text2); text-decoration:none; transition:background .12s,color .12s; margin-bottom:2px; }
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
        .btn-logout-sm:hover { background:var(--red-mid); }
        .main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; }
        .topbar { background:var(--surface); border-bottom:1px solid var(--border); padding:0 28px; height:56px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
        .topbar-title { font-size:15px; font-weight:700; }
        .content { padding:24px 28px; }
        .banner { padding:12px 16px; border-radius:var(--radius-sm); font-size:13px; font-weight:500; margin-bottom:16px; }
        .banner.ok  { background:var(--green-soft); color:var(--green); }
        .banner.err { background:var(--red-soft); color:var(--red); }
        .tabs { display:flex; gap:6px; margin-bottom:18px; border-bottom:1px solid var(--border); }
        .tab { padding:10px 18px; font-size:13px; font-weight:600; color:var(--text2); text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-1px; }
        .tab:hover { color:var(--text); }
        .tab.active { color:var(--red); border-bottom-color:var(--red); }
        .toolbar { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin-bottom:16px; }
        .filter-group { display:flex; flex-direction:column; gap:5px; }
        .filter-label { font-size:10px; font-weight:600; color:var(--text3); text-transform:uppercase; letter-spacing:0.07em; }
        .filter-input { font-family:inherit; font-size:13px; padding:7px 11px; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--surface2); color:var(--text); outline:none; min-width:150px; }
        .filter-input:focus { border-color:var(--red); }
        .btn { font-family:inherit; font-size:13px; font-weight:600; padding:9px 18px; border-radius:var(--radius-sm); border:none; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
        .btn-red { background:var(--red); color:#fff; } .btn-red:hover { background:var(--red-dark); }
        .btn-ghost { background:var(--surface2); color:var(--text2); border:1px solid var(--border); } .btn-ghost:hover { color:var(--text); }
        .btn-mini { font-size:11px; padding:5px 10px; border-radius:6px; }
        .spacer { flex:1; }
        .table-card { background:var(--surface); border-radius:var(--radius); border:1px solid var(--border); overflow:hidden; box-shadow:var(--shadow); }
        .m-table { width:100%; border-collapse:collapse; font-size:13px; }
        .m-table th { padding:11px 16px; text-align:left; font-size:10px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:0.06em; background:var(--surface2); border-bottom:1px solid var(--border); }
        .m-table td { padding:11px 16px; border-bottom:1px solid var(--border); }
        .m-table tr:last-child td { border-bottom:none; }
        .m-table tr:hover td { background:#fafafa; }
        .cat-badge { display:inline-block; padding:2px 9px; border-radius:20px; font-size:10px; font-weight:700; background:var(--surface2); color:var(--text2); border:1px solid var(--border); }
        .used-badge { font-size:10px; color:var(--text3); font-family:'JetBrains Mono',monospace; }
        .row-actions { display:flex; gap:6px; justify-content:flex-end; }
        .empty { padding:40px; text-align:center; color:var(--text3); font-size:13px; }
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(17,24,39,0.5); z-index:500; align-items:center; justify-content:center; padding:20px; }
        .modal-box { background:var(--surface); border-radius:var(--radius); padding:24px; width:100%; max-width:440px; box-shadow:var(--shadow-md); }
        .modal-box h3 { font-size:16px; margin-bottom:16px; }
        .modal-box label { display:block; font-size:11px; font-weight:600; color:var(--text2); text-transform:uppercase; letter-spacing:0.05em; margin:12px 0 5px; }
        .modal-box input, .modal-box select { width:100%; font-family:inherit; font-size:14px; padding:9px 12px; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--surface2); outline:none; }
        .modal-box input:focus, .modal-box select:focus { border-color:var(--red); }
        .modal-actions { display:flex; gap:10px; margin-top:20px; }
        .modal-actions .btn { flex:1; justify-content:center; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-badge">
            <div class="logo-icon"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
            <div class="logo-text"><span class="logo-name">QC Display</span><span class="logo-sub">Yanmar · Manager</span></div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Menu</div>
        <a class="nav-item" href="dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard</a>
        <a class="nav-item" href="evaluation.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>Evaluation</a>
        <a class="nav-item" href="cycle_time.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>Cycle Time</a>
        <a class="nav-item active" href="master_data.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0018 0V5"/><path d="M3 12a9 3 0 0018 0"/></svg>Data Master</a>
        <a class="nav-item" href="../qc/history.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>History QC</a>
        <a class="nav-item" href="../menu.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>Main Menu</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?php echo strtoupper(substr($nama_login, 0, 2)); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($nama_login); ?></div>
                <div class="user-role">Manager</div>
            </div>
            <a href="../auth/logout.php" class="btn-logout-sm">Logout</a>
        </div>
    </div>
</aside>

<div class="main">
    <div class="topbar"><span class="topbar-title">Data Master</span></div>
    <div class="content">

        <?php if (isset($_GET['ok'])): ?>
            <div class="banner ok"><?php echo htmlspecialchars($_GET['ok']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['err'])): ?>
            <div class="banner err"><?php echo htmlspecialchars($_GET['err']); ?></div>
        <?php endif; ?>

        <div class="tabs">
            <?php foreach ($TAB as $key => $t): ?>
                <a class="tab <?php echo $key === $tab ? 'active' : ''; ?>" href="master_data.php?tab=<?php echo $key; ?>"><?php echo $t['label']; ?></a>
            <?php endforeach; ?>
        </div>

        <div class="toolbar">
            <form method="GET" style="display:flex;gap:12px;align-items:flex-end;">
                <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select name="kat" class="filter-input" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_kat === 'all' ? 'selected' : ''; ?>>Semua Category</option>
                        <?php foreach ($KATEGORI as $k): ?>
                            <option value="<?php echo $k; ?>" <?php echo $filter_kat === $k ? 'selected' : ''; ?>><?php echo $k; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            <div class="filter-group">
                <label class="filter-label">Cari</label>
                <input type="text" id="cari" class="filter-input" style="min-width:260px;"
                       placeholder="Ketik untuk menyaring…" autocomplete="off">
            </div>
            <div class="spacer"></div>
            <button type="button" class="btn btn-red" onclick="bukaTambah()">+ Tambah <?php echo $cfg['label']; ?></button>
        </div>

        <p id="cariInfo" style="font-size:12px;color:var(--text2);margin-bottom:12px;display:none;"></p>

        <div class="table-card">
            <table class="m-table">
                <thead>
                    <tr>
                        <th style="width:110px;">Category</th>
                        <?php foreach ($cfg['kolom'] as $label): ?>
                            <th><?php echo htmlspecialchars($label); ?></th>
                        <?php endforeach; ?>
                        <th style="width:90px;">Dipakai</th>
                        <th style="width:150px;text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="<?php echo count($cfg['kolom']) + 3; ?>" class="empty"><?php echo ($cari !== '' || $filter_kat !== 'all') ? 'Tidak ada data yang cocok dengan pencarian/filter.' : 'Belum ada data.'; ?></td></tr>
                    <?php else: foreach ($rows as $r):
                        // Data untuk dibawa ke modal edit
                        $edit = ['id' => $r['id'], 'category' => $r['category']];
                        foreach ($cfg['kolom'] as $col => $_) $edit[$col] = $r[$col];
                        // Teks yang bisa dicari oleh live filter: kategori + semua kolom data
                        $teks_cari = $r['category'];
                        foreach ($cfg['kolom'] as $col => $_) $teks_cari .= ' ' . $r[$col];
                    ?>
                        <tr class="baris-data" data-cari="<?php echo htmlspecialchars(strtolower($teks_cari)); ?>">
                            <td><span class="cat-badge"><?php echo htmlspecialchars($r['category']); ?></span></td>
                            <?php foreach ($cfg['kolom'] as $col => $_): ?>
                                <td><?php echo htmlspecialchars($r[$col]); ?></td>
                            <?php endforeach; ?>
                            <td><span class="used-badge"><?php echo (int)$r['dipakai'] > 0 ? $r['dipakai'] . ' order' : '—'; ?></span></td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="btn btn-ghost btn-mini"
                                        onclick='bukaEdit(<?php echo json_encode($edit, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                                    <?php if ((int)$r['dipakai'] > 0): ?>
                                        <button type="button" class="btn btn-ghost btn-mini" style="opacity:.5;cursor:not-allowed;"
                                            title="Sudah dipakai <?php echo (int)$r['dipakai']; ?> order, tidak bisa dihapus" disabled>Hapus</button>
                                    <?php else: ?>
                                        <a class="btn btn-mini" style="background:var(--red);color:#fff;"
                                           href="master_data_action.php?aksi=hapus&tab=<?php echo $tab; ?>&id=<?php echo $r['id']; ?>"
                                           onclick="return confirm('Yakin hapus data master ini?');">Hapus</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr id="barisKosongCari" style="display:none;">
                        <td colspan="<?php echo count($cfg['kolom']) + 3; ?>" class="empty">Tidak ada data yang cocok dengan pencarian.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah / Edit -->
<div id="modal" class="modal-overlay">
    <div class="modal-box">
        <h3 id="modalJudul">Tambah <?php echo $cfg['label']; ?></h3>
        <form action="master_data_action.php" method="POST">
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="tab" value="<?php echo $tab; ?>">
            <input type="hidden" name="id" id="f_id" value="0">

            <label>Category</label>
            <select name="category" id="f_category" required>
                <option value="">-- Pilih Category --</option>
                <?php foreach ($KATEGORI as $k): ?>
                    <option value="<?php echo $k; ?>"><?php echo $k; ?></option>
                <?php endforeach; ?>
            </select>

            <?php foreach ($cfg['kolom'] as $col => $label): ?>
                <label><?php echo htmlspecialchars($label); ?></label>
                <input type="text" name="<?php echo $col; ?>" id="f_<?php echo $col; ?>" required>
            <?php endforeach; ?>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="tutupModal()">Batal</button>
                <button type="submit" class="btn btn-red">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal   = document.getElementById('modal');
    const kolom   = <?php echo json_encode(array_keys($cfg['kolom'])); ?>;
    const labelTab = <?php echo json_encode($cfg['label']); ?>;

    function bukaTambah() {
        document.getElementById('modalJudul').textContent = 'Tambah ' + labelTab;
        document.getElementById('f_id').value = '0';
        document.getElementById('f_category').value = '';
        kolom.forEach(c => document.getElementById('f_' + c).value = '');
        modal.style.display = 'flex';
    }

    function bukaEdit(data) {
        document.getElementById('modalJudul').textContent = 'Edit ' + labelTab;
        document.getElementById('f_id').value = data.id;
        document.getElementById('f_category').value = data.category;
        kolom.forEach(c => document.getElementById('f_' + c).value = data[c] ?? '');
        modal.style.display = 'flex';
    }

    function tutupModal() { modal.style.display = 'none'; }
    modal.addEventListener('click', e => { if (e.target === modal) tutupModal(); });

    // ── Live search: saring baris langsung saat mengetik ──────────────────────
    const inputCari  = document.getElementById('cari');
    const infoCari   = document.getElementById('cariInfo');
    const barisData  = [...document.querySelectorAll('.baris-data')];
    const barisKosong = document.getElementById('barisKosongCari');

    function jalankanCari() {
        const kata = inputCari.value.trim().toLowerCase();
        let cocok = 0;

        barisData.forEach(tr => {
            const tampil = kata === '' || tr.dataset.cari.includes(kata);
            tr.style.display = tampil ? '' : 'none';
            if (tampil) cocok++;
        });

        // Baris "tidak ada hasil" hanya muncul kalau sedang mencari & nol cocok
        barisKosong.style.display = (kata !== '' && cocok === 0) ? '' : 'none';

        if (kata === '') {
            infoCari.style.display = 'none';
        } else {
            infoCari.style.display = '';
            infoCari.innerHTML = 'Hasil untuk "<strong>' + inputCari.value.replace(/</g,'&lt;') +
                                 '</strong>" — ' + cocok + ' data ditemukan.';
        }
    }

    inputCari.addEventListener('input', jalankanCari);
</script>
</body>
</html>

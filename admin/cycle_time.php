<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
/** @var mysqli $conn */
mysqli_query($conn, "SET time_zone = '+07:00'");

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$mesin_list = ['CMM','RONDCOM','ROUGHNESS','CONTOUR','PROFIL PROJECTOR','MANUAL','HARDNESS CHECK'];

// Ambil semua kategori part yang ada
$katQuery = mysqli_query($conn, "SELECT DISTINCT category FROM master_parts ORDER BY category ASC");
$kategori_list = [];
while ($k = mysqli_fetch_assoc($katQuery)) $kategori_list[] = $k['category'];

// Handle POST — simpan/update
$msg_success = '';
$msg_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $category      = mysqli_real_escape_string($conn, trim($_POST['category'] ?? ''));
        $qc_machine    = mysqli_real_escape_string($conn, trim($_POST['qc_machine'] ?? ''));
        $menit         = (int)($_POST['menit'] ?? 0);
        $detik         = (int)($_POST['detik'] ?? 0);
        $keterangan    = mysqli_real_escape_string($conn, trim($_POST['keterangan'] ?? ''));
        $standard_detik = ($menit * 60) + $detik;

        if ($category && $qc_machine && $standard_detik > 0) {
            $res = mysqli_query($conn, "
                INSERT INTO master_cycle_time (category, qc_machine, standard_detik, keterangan)
                VALUES ('$category', '$qc_machine', $standard_detik, '$keterangan')
                ON DUPLICATE KEY UPDATE
                    standard_detik = $standard_detik,
                    keterangan     = '$keterangan',
                    updated_at     = NOW()
            ");
            if ($res) $msg_success = "Standard cycle time berhasil disimpan!";
            else $msg_error = "Gagal menyimpan data.";
        } else {
            $msg_error = "Semua field wajib diisi dan waktu harus lebih dari 0.";
        }
    }

    if ($action === 'delete') {
        $del_id = (int)($_POST['del_id'] ?? 0);
        if ($del_id > 0) {
            mysqli_query($conn, "DELETE FROM master_cycle_time WHERE id = $del_id");
            $msg_success = "Data berhasil dihapus.";
        }
    }
}

// Ambil semua data cycle time
$ctQuery = mysqli_query($conn, "
    SELECT * FROM master_cycle_time
    ORDER BY category ASC, qc_machine ASC
");
$ct_data = [];
while ($row = mysqli_fetch_assoc($ctQuery)) $ct_data[] = $row;

// Kelompokkan per kategori
$ct_grouped = [];
foreach ($ct_data as $ct) {
    $ct_grouped[$ct['category']][] = $ct;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standard Cycle Time — QC Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --red:       #CC0000;
            --red-dark:  #A30000;
            --red-soft:  #fff0f0;
            --red-mid:   #ffd5d5;
            --bg:        #f4f5f7;
            --surface:   #ffffff;
            --surface2:  #f9fafb;
            --border:    rgba(0,0,0,0.07);
            --text:      #111827;
            --text2:     #6b7280;
            --text3:     #9ca3af;
            --green:     #059669;
            --green-soft:#d1fae5;
            --blue:      #1d4ed8;
            --blue-soft: #dbeafe;
            --sidebar-w: 220px;
            --radius:    12px;
            --radius-sm: 8px;
            --shadow:    0 1px 4px rgba(0,0,0,0.07);
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

        .sidebar { width: var(--sidebar-w); background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 200; }
        .sidebar-logo { padding: 24px 20px 20px; border-bottom: 1px solid var(--border); }
        .sidebar-logo-badge { display: inline-flex; align-items: center; gap: 8px; }
        .logo-icon { width: 32px; height: 32px; background: var(--red); border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .logo-icon svg { width: 18px; height: 18px; fill: #fff; }
        .logo-text { display: flex; flex-direction: column; }
        .logo-name { font-size: 13px; font-weight: 700; color: var(--text); }
        .logo-sub  { font-size: 10px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.08em; }
        .sidebar-nav { padding: 16px 12px; flex: 1; }
        .nav-label { font-size: 10px; font-weight: 600; color: var(--text3); text-transform: uppercase; letter-spacing: 0.08em; padding: 0 8px; margin-bottom: 8px; margin-top: 16px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; color: var(--text2); text-decoration: none; transition: background 0.12s, color 0.12s; margin-bottom: 2px; }
        .nav-item:hover { background: var(--surface2); color: var(--text); }
        .nav-item.active { background: var(--red-soft); color: var(--red); }
        .nav-item svg { width: 16px; height: 16px; flex-shrink: 0; }
        .nav-item.active svg { stroke: var(--red); }
        .sidebar-footer { padding: 16px 12px; border-top: 1px solid var(--border); }
        .user-card { display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: var(--radius-sm); background: var(--surface2); }
        .user-avatar { width: 32px; height: 32px; background: var(--red); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0; }
        .user-info { flex: 1; min-width: 0; }
        .user-name { font-size: 12px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 10px; color: var(--text3); text-transform: uppercase; }
        .btn-logout-sm { font-size: 10px; padding: 4px 8px; border-radius: 6px; border: 1px solid var(--red-mid); background: var(--red-soft); color: var(--red); text-decoration: none; font-weight: 600; white-space: nowrap; }

        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }
        .topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 28px; height: 56px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .topbar-title { font-size: 15px; font-weight: 700; color: var(--text); }
        .content { padding: 24px 28px; height: calc(100vh - 56px); overflow-y: auto; }

        /* FORM */
        .form-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); padding: 22px; margin-bottom: 24px; box-shadow: var(--shadow); }
        .form-title { font-size: 13px; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .form-title-bar { width: 3px; height: 16px; background: var(--red); border-radius: 2px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr 120px 120px 1fr auto; gap: 12px; align-items: end; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-label { font-size: 10px; font-weight: 600; color: var(--text3); text-transform: uppercase; letter-spacing: 0.07em; }
        .form-input { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface2); color: var(--text); outline: none; transition: border-color 0.15s; }
        .form-input:focus { border-color: var(--red); }
        .btn-save { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; font-weight: 600; padding: 8px 20px; border-radius: var(--radius-sm); border: none; background: var(--red); color: #fff; cursor: pointer; white-space: nowrap; height: 38px; }
        .btn-save:hover { background: var(--red-dark); }

        /* ALERT */
        .alert { padding: 12px 16px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; margin-bottom: 18px; }
        .alert-success { background: var(--green-soft); color: var(--green); border: 1px solid #a7f3d0; }
        .alert-error   { background: var(--red-soft);   color: var(--red);   border: 1px solid var(--red-mid); }

        /* TABLE */
        .section-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .section-head-line { width: 3px; height: 16px; background: var(--red); border-radius: 2px; }
        .section-head-title { font-size: 13px; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: 0.05em; }

        .category-block { margin-bottom: 20px; }
        .category-label { font-size: 12px; font-weight: 700; color: var(--text); background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 8px 14px; margin-bottom: 8px; display: inline-block; text-transform: uppercase; letter-spacing: 0.05em; }

        .table-card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow); }
        .dash-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .dash-table th { padding: 10px 14px; background: var(--surface2); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text3); text-align: left; border-bottom: 1px solid var(--border); }
        .dash-table td { padding: 11px 14px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
        .dash-table tr:last-child td { border-bottom: none; }
        .dash-table tr:hover td { background: #fafafa; }
        .mono { font-family: 'JetBrains Mono', monospace; }

        .mesin-badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .mesin-badge.cmm      { background: #ede9fe; color: #7c3aed; }
        .mesin-badge.rondcom  { background: #fef3c7; color: #b45309; }
        .mesin-badge.roughness{ background: var(--green-soft); color: var(--green); }
        .mesin-badge.contour  { background: #fce7f3; color: #be185d; }
        .mesin-badge.profil   { background: var(--blue-soft); color: var(--blue); }
        .mesin-badge.manual   { background: var(--surface2); color: var(--text2); }
        .mesin-badge.hardness { background: #ffedd5; color: #c2410c; }

        .btn-delete { font-size: 11px; padding: 4px 10px; border-radius: 6px; border: 1px solid var(--red-mid); background: var(--red-soft); color: var(--red); cursor: pointer; font-weight: 600; font-family: 'Plus Jakarta Sans', sans-serif; }
        .btn-delete:hover { background: var(--red-mid); }

        .empty-state { padding: 40px; text-align: center; color: var(--text3); font-size: 13px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-badge">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            </div>
            <div class="logo-text">
                <span class="logo-name">QC Display</span>
                <span class="logo-sub">Yanmar · Manager</span>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Menu</div>
        <a class="nav-item" href="dashboard.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a class="nav-item" href="evaluation.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Evaluation
        </a>
        <a class="nav-item active" href="cycle_time.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
            Cycle Time
        </a>
        <a class="nav-item" href="../qc/history.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
            History QC
        </a>
        <a class="nav-item" href="../menu.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            Main Menu
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?php echo isset($_SESSION['nama']) ? strtoupper(substr($_SESSION['nama'], 0, 2)) : 'AD'; ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Admin'; ?></div>
                <div class="user-role">Manager</div>
            </div>
            <a href="../auth/logout.php" class="btn-logout-sm">Logout</a>
        </div>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <span class="topbar-title">Standard Cycle Time</span>
        <span style="font-size:12px;color:var(--text3);font-family:'JetBrains Mono',monospace;"><?php echo date('D, d M Y'); ?></span>
    </div>

    <div class="content">

        <?php if ($msg_success): ?>
        <div class="alert alert-success">✅ <?php echo $msg_success; ?></div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
        <div class="alert alert-error">⚠️ <?php echo $msg_error; ?></div>
        <?php endif; ?>

        <!-- Form Input -->
        <div class="form-card">
            <div class="form-title">
                <div class="form-title-bar"></div>
                Tambah / Update Standard Cycle Time
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Kategori Part</label>
                        <select name="category" class="form-input" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori_list as $kat): ?>
                            <option value="<?php echo htmlspecialchars($kat); ?>"><?php echo htmlspecialchars($kat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mesin QC</label>
                        <select name="qc_machine" class="form-input" required>
                            <option value="">-- Pilih Mesin --</option>
                            <?php foreach ($mesin_list as $m): ?>
                            <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Menit</label>
                        <input type="number" name="menit" class="form-input" min="0" max="999" placeholder="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Detik</label>
                        <input type="number" name="detik" class="form-input" min="0" max="59" placeholder="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Keterangan (opsional)</label>
                        <input type="text" name="keterangan" class="form-input" placeholder="misal: termasuk setup">
                    </div>
                    <button type="submit" class="btn-save">Simpan</button>
                </div>
            </form>
        </div>

        <!-- List -->
        <div class="section-head">
            <div class="section-head-line"></div>
            <div class="section-head-title">Daftar Standard Cycle Time</div>
            <span style="font-size:11px;color:var(--text3);margin-left:8px;"><?php echo count($ct_data); ?> record</span>
        </div>

        <?php if (empty($ct_data)): ?>
        <div class="table-card">
            <div class="empty-state">Belum ada standard cycle time yang diinput.</div>
        </div>
        <?php else: ?>
            <?php foreach ($ct_grouped as $category => $items): ?>
            <div class="category-block">
                <div class="category-label">📦 <?php echo htmlspecialchars($category); ?></div>
                <div class="table-card">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Mesin QC</th>
                                <th>Standard Waktu</th>
                                <th>Keterangan</th>
                                <th>Diupdate</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $ct):
                                $mnt = floor($ct['standard_detik'] / 60);
                                $det = $ct['standard_detik'] % 60;
                                $mesin_key = strtolower(str_replace([' ', '/'], '', $ct['qc_machine']));
                                if (str_contains($mesin_key, 'cmm'))       $mk = 'cmm';
                                elseif (str_contains($mesin_key, 'rond'))  $mk = 'rondcom';
                                elseif (str_contains($mesin_key, 'rough')) $mk = 'roughness';
                                elseif (str_contains($mesin_key, 'cont'))  $mk = 'contour';
                                elseif (str_contains($mesin_key, 'prof'))  $mk = 'profil';
                                elseif (str_contains($mesin_key, 'man'))   $mk = 'manual';
                                elseif (str_contains($mesin_key, 'hard'))  $mk = 'hardness';
                                else $mk = 'manual';
                            ?>
                            <tr>
                                <td><span class="mesin-badge <?php echo $mk; ?>"><?php echo htmlspecialchars($ct['qc_machine']); ?></span></td>
                                <td>
                                    <strong class="mono" style="font-size:13px;"><?php echo "{$mnt}m {$det}s"; ?></strong>
                                    <span style="font-size:10px;color:var(--text3);margin-left:6px;">(<?php echo $ct['standard_detik']; ?> detik)</span>
                                </td>
                                <td style="color:var(--text2);font-size:12px;"><?php echo $ct['keterangan'] ? htmlspecialchars($ct['keterangan']) : '—'; ?></td>
                                <td class="mono" style="font-size:11px;color:var(--text3);"><?php echo date('d/m/Y H:i', strtotime($ct['updated_at'])); ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Hapus standard ini?');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="del_id" value="<?php echo $ct['id']; ?>">
                                        <button type="submit" class="btn-delete">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
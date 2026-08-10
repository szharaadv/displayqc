<?php
/**
 * Sidebar admin — SATU sumber, dipakai semua halaman manager.
 *
 * Cara pakai (sebelum include, set item aktif):
 *   <?php $active_nav = 'dashboard'; include '_sidebar.php'; ?>
 *
 * Ini sengaja dijadikan satu file supaya menu tidak lagi lepas-sinkron antar
 * halaman (dulu tiap halaman menyalin sidebar sendiri, jadi menu bisa hilang
 * atau urutannya beda).
 */
$active_nav = $active_nav ?? '';

$menu = [
    ['key' => 'dashboard',     'href' => 'dashboard.php',      'label' => 'Dashboard',
     'svg' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
    ['key' => 'evaluation',    'href' => 'evaluation.php',     'label' => 'Evaluation',
     'svg' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>'],
    ['key' => 'cycle_time',    'href' => 'cycle_time.php',     'label' => 'Cycle Time',
     'svg' => '<circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/>'],
    ['key' => 'master_data',   'href' => 'master_data.php',    'label' => 'Data Master',
     'svg' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0018 0V5"/><path d="M3 12a9 3 0 0018 0"/>'],
    ['key' => 'shift_setting', 'href' => 'shift_setting.php',  'label' => 'Setting Shift',
     'svg' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
    ['key' => 'history',       'href' => '../qc/history.php',  'label' => 'History QC',
     'svg' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14,2 14,8 20,8"/>'],
    ['key' => 'main_menu',     'href' => '../menu.php',        'label' => 'Main Menu',
     'svg' => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>'],
];

$nama = $_SESSION['nama'] ?? 'Admin';
?>
<style>
/* CSS sidebar kanonik — SATU sumber. Diletakkan di sini (dalam body, setelah
   <style> di head tiap halaman) supaya menang atas definisi lama yang sudah
   melenceng antar-halaman, jadi sidebar tampil identik di mana pun. */
.sidebar { width:220px; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; bottom:0; z-index:200; }
.sidebar-logo { padding:24px 20px 20px; border-bottom:1px solid var(--border); }
.sidebar-logo-badge { display:inline-flex; align-items:center; gap:8px; }
.logo-icon { width:32px; height:32px; background:var(--red); border-radius:8px; display:flex; align-items:center; justify-content:center; }
.logo-icon svg { width:18px; height:18px; fill:#fff; }
.logo-text { display:flex; flex-direction:column; }
.logo-name { font-size:13px; font-weight:700; color:var(--text); letter-spacing:0.02em; }
.logo-sub { font-size:10px; color:var(--text3); text-transform:uppercase; letter-spacing:0.08em; }
.sidebar-nav { padding:16px 12px; flex:1; }
.nav-label { font-size:10px; font-weight:600; color:var(--text3); text-transform:uppercase; letter-spacing:0.08em; padding:0 8px; margin-top:0; margin-bottom:8px; }
.nav-item { display:flex; align-items:center; gap:10px; padding:9px 10px; border-radius:var(--radius-sm); font-size:13px; font-weight:500; color:var(--text2); text-decoration:none; transition:background .12s,color .12s; cursor:pointer; margin-bottom:2px; }
.nav-item:hover { background:var(--surface2); color:var(--text); }
.nav-item.active { background:var(--red-soft); color:var(--red); }
.nav-item svg { width:16px; height:16px; flex-shrink:0; }
.nav-item.active svg { stroke:var(--red); }
.sidebar-footer { padding:16px 12px; border-top:1px solid var(--border); }
.user-card { display:flex; align-items:center; gap:10px; padding:8px; border-radius:var(--radius-sm); background:var(--surface2); }
.user-avatar { width:32px; height:32px; background:var(--red); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; }
.user-info { flex:1; min-width:0; }
.user-name { font-size:12px; font-weight:600; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.user-role { font-size:10px; color:var(--text3); text-transform:uppercase; letter-spacing:0.06em; }
.btn-logout-sm { font-size:10px; padding:4px 8px; border-radius:6px; border:1px solid var(--red-mid); background:var(--red-soft); color:var(--red); text-decoration:none; font-weight:600; white-space:nowrap; }
.btn-logout-sm:hover { background:var(--red-mid); }
</style>
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-badge">
            <div class="logo-icon"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
            <div class="logo-text"><span class="logo-name">QC Display</span><span class="logo-sub">Yanmar · Manager</span></div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Menu</div>
        <?php foreach ($menu as $m): ?>
        <a class="nav-item <?php echo $active_nav === $m['key'] ? 'active' : ''; ?>" href="<?php echo $m['href']; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?php echo $m['svg']; ?></svg>
            <?php echo $m['label']; ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?php echo strtoupper(substr($nama, 0, 2)); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($nama); ?></div>
                <div class="user-role">Manager</div>
            </div>
            <a href="../auth/logout.php" class="btn-logout-sm">Logout</a>
        </div>
    </div>
</aside>

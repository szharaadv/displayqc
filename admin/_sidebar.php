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
 *
 * Dipakai juga oleh halaman foreman/ — karena itu href ditulis relatif dari
 * root aplikasi ('../admin/..', '../foreman/..'; semua halaman 1 level di
 * bawah root) dan tiap grup punya 'roles' yang boleh melihatnya.
 */
$active_nav = $active_nav ?? '';
$sb_role    = $_SESSION['role'] ?? '';

// Menu dikelompokkan per-section. Tiap grup punya judul + daftar item.
$menu_groups = [
    ['title' => 'Management', 'roles' => ['admin'], 'items' => [
        ['key' => 'dashboard',     'href' => '../admin/dashboard.php',  'label' => 'Dashboard',
         'svg' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
        ['key' => 'evaluation',    'href' => '../admin/evaluation.php', 'label' => 'Evaluation',
         'svg' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>'],
        ['key' => 'cycle_time',    'href' => '../admin/cycle_time.php', 'label' => 'Cycle Time',
         'svg' => '<circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/>'],
    ]],
    ['title' => 'Foreman', 'roles' => ['admin', 'foreman'], 'items' => [
        ['key' => 'absensi',       'href' => '../foreman/absensi.php',  'label' => 'Attendance',
         'svg' => '<path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17,11 19,13 23,9"/>'],
        ['key' => 'overtime',      'href' => '../foreman/overtime.php', 'label' => 'Overtime',
         'svg' => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2"/><path d="M5 3L2 6M22 6l-3-3"/>'],
        ['key' => 'rekap',         'href' => '../foreman/rekap.php',    'label' => 'Monthly Recap',
         'svg' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
    ]],
    ['title' => 'Configuration', 'roles' => ['admin'], 'items' => [
        ['key' => 'users',         'href' => '../admin/users.php',      'label' => 'Users',
         'svg' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>'],
        ['key' => 'master_data',   'href' => '../admin/master_data.php', 'label' => 'Master Data',
         'svg' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0018 0V5"/><path d="M3 12a9 3 0 0018 0"/>'],
        ['key' => 'shift_setting', 'href' => '../admin/shift_setting.php', 'label' => 'Shift Settings',
         'svg' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
        ['key' => 'kalender',      'href' => '../admin/kalender.php', 'label' => 'Work Calendar',
         'svg' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    ]],
    ['title' => 'General', 'roles' => ['admin', 'foreman'], 'items' => [
        ['key' => 'main_menu',     'href' => '../menu.php',        'label' => 'Main Menu',
         'svg' => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>'],
    ]],
];

$menu_groups = array_filter($menu_groups, fn($g) => in_array($sb_role, $g['roles'], true));
$sb_label    = $sb_role === 'foreman' ? 'Foreman' : 'Manager';

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
.nav-group { margin-bottom:18px; }
.nav-group:last-child { margin-bottom:0; }
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
            <div class="logo-text"><span class="logo-name">QC Display</span><span class="logo-sub">Yanmar · <?php echo $sb_label; ?></span></div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menu_groups as $g): ?>
        <div class="nav-group">
            <div class="nav-label"><?php echo $g['title']; ?></div>
            <?php foreach ($g['items'] as $m): ?>
            <a class="nav-item <?php echo $active_nav === $m['key'] ? 'active' : ''; ?>" href="<?php echo $m['href']; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?php echo $m['svg']; ?></svg>
                <?php echo $m['label']; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?php echo strtoupper(substr($nama, 0, 2)); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($nama); ?></div>
                <div class="user-role"><?php echo $sb_label; ?></div>
            </div>
            <a href="../auth/logout.php" class="btn-logout-sm">Logout</a>
        </div>
    </div>
</aside>

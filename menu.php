<?php
date_default_timezone_set('Asia/Jakarta');
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: auth/login.php");
    exit;
}

include 'config/koneksi.php';
include 'config/shift.php';
/** @var mysqli $conn */

$role       = $_SESSION['role'] ?? '';
$is_qc      = $role === 'qc';
$is_admin   = $role === 'admin';
$is_foreman = $role === 'foreman';

// "MUHAMMAD RIZKI" -> "Muhammad Rizki"; inisial "MR"
$nama    = ucwords(strtolower(trim($_SESSION['nama'] ?? '')));
$bagian  = preg_split('/\s+/', $nama);
$inisial = strtoupper(substr($bagian[0] ?? '', 0, 1) . (isset($bagian[1]) ? substr($bagian[1], 0, 1) : substr($bagian[0] ?? '', 1, 1)));
$jam     = (int)date('G');
$sapa    = $jam < 11 ? 'Good morning' : ($jam < 15 ? 'Good afternoon' : ($jam < 19 ? 'Good evening' : 'Hi'));
$ROLE    = ['admin' => 'Manager', 'foreman' => 'Foreman', 'qc' => 'QC Staff', 'operator' => 'Operator'];
$shift   = qcShift();

// Ikon (path SVG) per menu.
$ico = [
    'order'   => '<path d="M12 5v14M5 12h14"/>',
    'request' => '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/>',
    'display' => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
    'manager' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
    'foreman' => '<path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17,11 19,13 23,9"/>',
];
$menu = [
    ['href' => 'operator/create_order.php', 'ico' => 'order',   'judul' => 'Create Order',  'sub' => 'Submit a new sampling order', 'utama' => true],
    ['href' => 'qc/display.php',            'ico' => 'request', 'judul' => 'Order Request', 'sub' => 'See all orders and their status'],
];
if ($is_qc)      $menu[] = ['href' => 'qc/main_display.php', 'ico' => 'display', 'judul' => 'Main Display QC',   'sub' => 'Process your sampling jobs'];
if ($is_foreman) $menu[] = ['href' => 'foreman/absensi.php', 'ico' => 'foreman', 'judul' => 'Foreman',           'sub' => 'Attendance & overtime'];
if ($is_admin)   $menu[] = ['href' => 'admin/dashboard.php', 'ico' => 'manager', 'judul' => 'Manager Dashboard', 'sub' => 'Operation ratio & evaluation'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Menu — QC Display</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root {
            --red:#CC0000; --red-dark:#A30000; --red-soft:#fff0f0; --red-mid:#ffd5d5;
            --bg:#f4f5f7; --surface:#fff; --border:rgba(0,0,0,0.07);
            --text:#111827; --text2:#6b7280; --text3:#9ca3af;
        }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px 16px; }
        .home { width:100%; max-width:560px; }
        .brand { display:flex; align-items:center; gap:10px; margin-bottom:18px; }
        .brand-icon { width:34px; height:34px; background:var(--red); border-radius:9px; display:flex; align-items:center; justify-content:center; }
        .brand-icon svg { width:18px; height:18px; fill:none; stroke:#fff; stroke-width:2; }
        .brand b { font-size:14px; } .brand small { display:block; font-size:10px; color:var(--text3); text-transform:uppercase; letter-spacing:.08em; }

        .hello { position:relative; overflow:hidden; background:linear-gradient(135deg,#CC0000 0%,#a30000 100%); color:#fff; border-radius:18px; padding:22px 24px; display:flex; align-items:center; gap:16px; box-shadow:0 10px 30px rgba(204,0,0,.22); animation:rise .45s ease both; }
        .hello::after { content:''; position:absolute; right:-40px; top:-40px; width:160px; height:160px; border-radius:50%; background:rgba(255,255,255,.08); }
        .avatar { width:54px; height:54px; border-radius:50%; background:rgba(255,255,255,.18); border:2px solid rgba(255,255,255,.35); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px; flex-shrink:0; }
        .hello h1 { font-size:21px; font-weight:800; line-height:1.25; }
        .wave { display:inline-block; transform-origin:70% 70%; animation:wave 1.2s ease .4s 2; }
        @keyframes wave { 0%,100%{transform:rotate(0)} 20%{transform:rotate(16deg)} 40%{transform:rotate(-10deg)} 60%{transform:rotate(12deg)} 80%{transform:rotate(-6deg)} }
        .hello .meta { font-size:12px; opacity:.9; margin-top:5px; display:flex; flex-wrap:wrap; align-items:center; gap:6px 12px; }
        .chip { display:inline-block; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; background:rgba(255,255,255,.2); padding:2px 9px; border-radius:999px; }

        .tiles { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:16px; }
        .tile { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px; text-decoration:none; color:inherit; display:flex; flex-direction:column; gap:10px; box-shadow:0 1px 4px rgba(0,0,0,.05); transition:transform .15s, box-shadow .15s, border-color .15s; animation:rise .45s ease both; }
        .tile:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(0,0,0,.08); border-color:var(--red-mid); }
        .tile .ic { width:40px; height:40px; border-radius:11px; background:var(--red-soft); color:var(--red); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .tile .ic svg { width:20px; height:20px; }
        .tile b { font-size:14px; } .tile span { font-size:12px; color:var(--text2); }
        .tile.utama { grid-column:1 / -1; flex-direction:row; align-items:center; gap:14px; border:1.5px solid var(--red-mid); }
        .tile.utama .ic { background:var(--red); color:#fff; width:48px; height:48px; }
        .tile.utama .go { margin-left:auto; font-size:22px; color:var(--red); transition:transform .15s; }
        .tile.utama:hover .go { transform:translateX(4px); }
        .foot { display:flex; justify-content:space-between; align-items:center; margin-top:16px; font-size:12px; }
        .foot a { color:var(--text2); text-decoration:none; font-weight:600; }
        .foot a:hover { color:var(--red); }
        .foot .out { color:var(--red); background:var(--red-soft); border:1px solid var(--red-mid); padding:7px 14px; border-radius:999px; }
        @keyframes rise { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
        @media (max-width:480px) { .tiles { grid-template-columns:1fr; } .hello h1 { font-size:18px; } }
    </style>
</head>
<body>
    <div class="home">
        <div class="brand">
            <div class="brand-icon"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
            <div><b>QC Display</b><small>Yanmar · Manufacturing</small></div>
        </div>

        <div class="hello">
            <div class="avatar"><?php echo htmlspecialchars($inisial); ?></div>
            <div>
                <h1><?php echo $sapa; ?>, <?php echo htmlspecialchars($nama); ?> <span class="wave">👋</span></h1>
                <div class="meta">
                    <span class="chip"><?php echo $ROLE[$role] ?? ucfirst($role); ?></span>
                    <span>NIK <?php echo htmlspecialchars($_SESSION['nik'] ?? ''); ?></span>
                    <span><?php echo date('l, j F Y'); ?> · <?php echo htmlspecialchars($shift['nama']); ?></span>
                </div>
            </div>
        </div>

        <div class="tiles">
            <?php foreach ($menu as $i => $m): ?>
            <a class="tile <?php echo !empty($m['utama']) ? 'utama' : ''; ?>" href="<?php echo $m['href']; ?>" style="animation-delay:<?php echo 0.08 + $i * 0.05; ?>s">
                <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $ico[$m['ico']]; ?></svg></div>
                <div><b><?php echo $m['judul']; ?></b><br><span><?php echo $m['sub']; ?></span></div>
                <?php if (!empty($m['utama'])): ?><div class="go">→</div><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="foot">
            <a href="auth/ganti_password.php">Change password</a>
            <a class="out" href="auth/logout.php">Logout</a>
        </div>
    </div>
</body>
</html>

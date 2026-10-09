<?php
session_start();
include '../config/csrf.php';

// Kalau sudah login, redirect ke menu
if (isset($_SESSION['id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } elseif ($_SESSION['role'] === 'foreman') {
        header("Location: ../foreman/absensi.php");
    } else {
        header("Location: ../menu.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — QC Display Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        /* Kartu sapaan "Hi, (nama)" — muncul setelah NIK dikenali (auth/cek_nik.php). */
        .hi-card { display:flex; align-items:center; gap:14px; padding:0 16px; max-height:0; opacity:0; overflow:hidden; border-radius:14px; background:linear-gradient(135deg,#fff5f5,#fff); border:1px solid transparent; transform:translateY(-6px); transition:max-height .35s ease, opacity .25s ease, transform .3s ease, padding .3s ease, margin .3s ease, border-color .3s; }
        .hi-card.show { max-height:120px; opacity:1; padding:14px 16px; margin-bottom:18px; border-color:var(--red-mid); transform:none; }
        .hi-avatar { width:46px; height:46px; border-radius:50%; background:var(--red); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; box-shadow:0 4px 12px rgba(204,0,0,.25); }
        .hi-text { min-width:0; }
        .hi-title { font-size:17px; font-weight:700; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .hi-wave { display:inline-block; transform-origin:70% 70%; }
        .hi-card.show .hi-wave { animation:wave 1.2s ease .2s 2; }
        @keyframes wave { 0%,100%{transform:rotate(0)} 20%{transform:rotate(16deg)} 40%{transform:rotate(-10deg)} 60%{transform:rotate(12deg)} 80%{transform:rotate(-6deg)} }
        .hi-sub { font-size:12px; color:var(--text2); margin-top:3px; }
        .hi-role { display:inline-block; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--red); background:var(--red-soft); border:1px solid var(--red-mid); padding:2px 8px; border-radius:999px; margin-right:6px; }
        .nik-hint { font-size:11px; color:var(--red); min-height:14px; }
        .nik-wrap { position:relative; }
        .nik-wrap .spin { position:absolute; right:12px; top:50%; width:14px; height:14px; margin-top:-13px; border:2px solid var(--red-mid); border-top-color:var(--red); border-radius:50%; animation:spin .7s linear infinite; display:none; }
        .nik-wrap.loading .spin { display:block; }
        @keyframes spin { to { transform:rotate(360deg); } }
    </style>
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-brand">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                </div>
                <div class="brand-text">
                    <span class="brand-name">QC Display</span>
                    <span class="brand-sub">Yanmar · Manufacturing</span>
                </div>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="error">Incorrect NIK or password.</div>
            <?php endif; ?>

            <div class="hi-card" id="hiCard" aria-live="polite">
                <div class="hi-avatar" id="hiAvatar"></div>
                <div class="hi-text">
                    <div class="hi-title"><span id="hiGreet">Hi</span>, <span id="hiName"></span> <span class="hi-wave">👋</span></div>
                    <div class="hi-sub"><span class="hi-role" id="hiRole"></span>Enter your password to continue</div>
                </div>
            </div>

            <form action="proses_login.php" method="POST">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label class="form-label">NIK</label>
                    <div class="nik-wrap" id="nikWrap">
                        <input type="text" name="nik" id="nik" class="form-input" placeholder="Enter NIK" required autofocus autocomplete="username">
                        <span class="spin"></span>
                    </div>
                    <div class="nik-hint" id="nikHint"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Enter password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-login">SIGN IN</button>
            </form>

            <div class="login-footer">
                <a href="ganti_password.php">Change Password</a>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var nik = document.getElementById('nik'), card = document.getElementById('hiCard'),
            hint = document.getElementById('nikHint'), wrap = document.getElementById('nikWrap'),
            pass = document.getElementById('password'), timer = null, last = '';

        function greet() {
            var h = new Date().getHours();
            return h < 11 ? 'Good morning' : h < 15 ? 'Good afternoon' : h < 19 ? 'Good evening' : 'Hi';
        }
        // "MUHAMMAD RIZKI" -> "Muhammad Rizki"; inisial "MR"
        function rapi(n) { return n.toLowerCase().replace(/\b\w/g, function (c) { return c.toUpperCase(); }); }
        function inisial(n) { var p = n.trim().split(/\s+/); return (p[0][0] + (p[1] ? p[1][0] : (p[0][1] || ''))).toUpperCase(); }

        function cari() {
            var v = nik.value.trim();
            if (v === last) return;
            last = v;
            if (v.length < 3) { card.classList.remove('show'); hint.textContent = ''; return; }
            wrap.classList.add('loading');
            fetch('cek_nik.php?nik=' + encodeURIComponent(v))
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (nik.value.trim() !== v) return;   // sudah diketik ulang
                    if (d.found) {
                        document.getElementById('hiGreet').textContent = greet();
                        document.getElementById('hiName').textContent = rapi(d.nama);
                        document.getElementById('hiAvatar').textContent = inisial(d.nama);
                        document.getElementById('hiRole').textContent = d.role;
                        card.classList.remove('show'); void card.offsetWidth; card.classList.add('show');
                        hint.textContent = '';
                    } else {
                        card.classList.remove('show');
                        hint.textContent = 'NIK not registered or inactive.';
                    }
                })
                .catch(function () { card.classList.remove('show'); })
                .then(function () { wrap.classList.remove('loading'); });
        }

        nik.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(cari, 350); });
        // Enter di kolom NIK pindah ke password (bukan submit) supaya sapaan sempat tampil.
        nik.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && pass.value === '') { e.preventDefault(); clearTimeout(timer); cari(); pass.focus(); }
        });
        if (nik.value) cari();
    })();
    </script>
</body>
</html>

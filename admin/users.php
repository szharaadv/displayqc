<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../config/koneksi.php';
include '../config/csrf.php';
include '../config/foreman.php';
/** @var mysqli $conn */

if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
fmCekMigrasi($conn);

$ROLE = ['admin' => 'Admin', 'foreman' => 'Foreman', 'qc' => 'QC', 'operator' => 'Operator'];
$ROLE_BADGE = ['admin' => 'b-red', 'foreman' => 'b-blue', 'qc' => 'b-green', 'operator' => ''];

$users = [];
$q = mysqli_query($conn, "SELECT id, nik, nama, role, foreman_id, status FROM users ORDER BY FIELD(role,'admin','foreman','qc','operator'), nama");
while ($r = mysqli_fetch_assoc($q)) $users[(int)$r['id']] = $r;

// Jumlah riwayat yang menunjuk tiap user — hanya user tanpa riwayat yang boleh dihapus.
$riwayat = [];
$q = mysqli_query($conn, "SELECT uid, COUNT(*) n FROM (" . USER_REF_SQL . ") r GROUP BY uid");
while ($r = mysqli_fetch_assoc($q)) $riwayat[(int)$r['uid']] = (int)$r['n'];

$foremen = array_filter($users, fn($u) => $u['role'] === 'foreman' && (int)$u['status'] === 1);

$filter = $_GET['role'] ?? '';
$tampil = isset($ROLE[$filter]) ? array_filter($users, fn($u) => $u['role'] === $filter) : $users;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — QC Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/panel.css">
    <link rel="stylesheet" href="../assets/components.css">
</head>
<body>

<?php $active_nav = 'users'; include __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <span class="topbar-title">Users</span>
        <button class="btn btn-red btn-sm" type="button" onclick="bukaModal(0)">+ Add User</button>
    </div>
    <div class="content">
        <?php if (isset($_GET['ok'])): ?><div class="banner ok"><?php echo htmlspecialchars($_GET['ok']); ?></div><?php endif; ?>
        <?php if (isset($_GET['err'])): ?><div class="banner err"><?php echo htmlspecialchars($_GET['err']); ?></div><?php endif; ?>

        <p class="intro">
            Set each user's <strong>role</strong> and <strong>foreman team</strong>. Team members (usually QC)
            assigned to a foreman appear on that foreman's Attendance &amp; Overtime pages.
            A user can only be <strong>deleted</strong> if they have no history (orders, sampling, attendance, OT);
            users with history should be deactivated instead so their data stays intact.
        </p>

        <div class="toolbar">
            <div class="seg">
                <a class="btn btn-sm <?php echo $filter === '' ? 'btn-red' : 'btn-ghost'; ?>" href="users.php">All (<?php echo count($users); ?>)</a>
                <?php foreach ($ROLE as $k => $l):
                    $n = count(array_filter($users, fn($u) => $u['role'] === $k)); ?>
                    <a class="btn btn-sm <?php echo $filter === $k ? 'btn-red' : 'btn-ghost'; ?>" href="users.php?role=<?php echo $k; ?>"><?php echo $l; ?> (<?php echo $n; ?>)</a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>NIK</th><th>Name</th><th>Role</th><th>Foreman (Team)</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($tampil as $u):
                        $fid = (int)$u['foreman_id']; ?>
                        <tr class="<?php echo (int)$u['status'] === 1 ? '' : 'dim'; ?>">
                            <td><?php echo htmlspecialchars($u['nik'] ?? ''); ?></td>
                            <td><strong><?php echo htmlspecialchars($u['nama']); ?></strong></td>
                            <td><span class="badge <?php echo $ROLE_BADGE[$u['role']] ?? ''; ?>"><?php echo $ROLE[$u['role']] ?? $u['role']; ?></span></td>
                            <td><?php echo $fid && isset($users[$fid]) ? htmlspecialchars($users[$fid]['nama']) : '<span class="nik">—</span>'; ?></td>
                            <td><?php echo (int)$u['status'] === 1 ? '<span class="badge b-green">Active</span>' : '<span class="badge">Inactive</span>'; ?></td>
                            <td class="num" style="white-space:nowrap">
                                <button class="btn btn-ghost btn-sm" type="button" onclick="bukaModal(<?php echo (int)$u['id']; ?>)">Edit</button>
                                <?php if ((int)$u['id'] !== (int)$_SESSION['id']):
                                    $n = $riwayat[(int)$u['id']] ?? 0; ?>
                                    <?php if ($n): ?>
                                        <button class="btn btn-soft btn-sm" type="button" disabled title="Has <?php echo $n; ?> history records (order/sampling/attendance/OT) — deactivate instead">Delete</button>
                                    <?php else: ?>
                                        <form method="POST" action="users_action.php" style="display:inline" onsubmit="return confirm('Permanently delete user <?php echo htmlspecialchars(addslashes($u['nama']), ENT_QUOTES); ?>? This cannot be undone.')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                            <button class="btn btn-soft btn-sm" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-bg" id="modal">
    <form class="modal" method="POST" action="users_action.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="id" id="f-id">
        <div class="modal-head" id="m-title">Add User</div>
        <div class="modal-body">
            <div class="row2">
                <div class="field"><label>NIK</label><input type="text" name="nik" id="f-nik" maxlength="20" required></div>
                <div class="field"><label>Role</label>
                    <select name="role" id="f-role">
                        <?php foreach ($ROLE as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo $l; ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field"><label>Name</label><input type="text" name="nama" id="f-nama" maxlength="100" required></div>
            <div class="field" id="wrap-foreman"><label>Foreman (Team)</label>
                <select name="foreman_id" id="f-foreman">
                    <option value="0">— None —</option>
                    <?php foreach ($foremen as $f): ?><option value="<?php echo (int)$f['id']; ?>"><?php echo htmlspecialchars($f['nama']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="row2">
                <div class="field"><label>Status</label>
                    <select name="status" id="f-status"><option value="1">Active</option><option value="0">Inactive</option></select>
                </div>
                <div class="field"><label id="l-pass">Password</label>
                    <input type="password" name="password" id="f-pass" autocomplete="new-password">
                    <span class="hint" id="h-pass">Required for new users</span>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" onclick="tutupModal()">Cancel</button>
            <button type="submit" class="btn btn-red">Save</button>
        </div>
    </form>
</div>

<script>
var USERS = <?php echo json_encode(array_values(array_map(fn($u) => [
    'id' => (int)$u['id'], 'nik' => $u['nik'], 'nama' => $u['nama'], 'role' => $u['role'],
    'foreman_id' => (int)$u['foreman_id'], 'status' => (int)$u['status'],
], $users))); ?>;
var SELF_ID = <?php echo (int)$_SESSION['id']; ?>;

function el(id) { return document.getElementById(id); }
function syncForeman() {
    // Foreman & admin tidak masuk tim siapa pun.
    var r = el('f-role').value;
    el('wrap-foreman').style.display = (r === 'admin' || r === 'foreman') ? 'none' : '';
}
function bukaModal(id) {
    var u = USERS.find(function (x) { return x.id === id; }) || {id: 0, nik: '', nama: '', role: 'qc', foreman_id: 0, status: 1};
    el('m-title').textContent = id ? 'Edit User' : 'Add User';
    el('f-id').value = u.id; el('f-nik').value = u.nik || ''; el('f-nama').value = u.nama;
    el('f-role').value = u.role; el('f-foreman').value = u.foreman_id; el('f-status').value = u.status;
    el('f-pass').value = ''; el('f-pass').required = !id;
    el('h-pass').textContent = id ? 'Leave blank to keep current' : 'Required for new users';
    // Admin yang sedang login tidak bisa menurunkan role / menonaktifkan dirinya sendiri.
    el('f-role').disabled = el('f-status').disabled = (id === SELF_ID);
    syncForeman();
    el('modal').classList.add('open');
    el('f-nama').focus();
}
function tutupModal() { el('modal').classList.remove('open'); }
el('f-role').addEventListener('change', syncForeman);
el('modal').addEventListener('click', function (e) { if (e.target === this) tutupModal(); });
</script>
</body>
</html>

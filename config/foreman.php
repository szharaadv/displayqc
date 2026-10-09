<?php
/**
 * Modul Foreman — absensi harian & overtime per tim. Satu sumber untuk:
 *   - siapa yang boleh akses (foreman = timnya sendiri, admin = pilih tim),
 *   - daftar anggota tim,
 *   - rekap OT bulanan (dasar pembagian rata),
 *   - batas OT (PP 35/2021).
 *
 * Halaman yang include file ini harus sudah session_start() dan include
 * koneksi.php + calendar.php.
 */

/** Status absensi → label tampilan. 'hadir' & 'terlambat' dihitung masuk. */
const FM_STATUS_ABSEN = [
    'hadir'     => 'Present',
    'terlambat' => 'Late',
    'izin'      => 'Excused',
    'sakit'     => 'Sick',
    'cuti'      => 'Leave',
    'alpha'     => 'Absent',
];
const FM_STATUS_MASUK = ['hadir', 'terlambat'];

const FM_STATUS_OT = ['rencana' => 'Planned', 'selesai' => 'Done', 'batal' => 'Cancelled'];

// Batas lembur PP 35/2021 pasal 26: maks 4 jam/hari & 18 jam/minggu. Tidak
// berlaku untuk lembur di hari istirahat mingguan / libur resmi, jadi hanya
// dicek untuk tanggal yang qcHariKerja() = true.
const FM_MAKS_MENIT_HARI   = 240;
const FM_MAKS_MENIT_MINGGU = 1080;

/**
 * Semua kolom yang menunjuk users.id (sebagai subquery `uid`). User yang masih
 * muncul di sini punya riwayat dan tidak boleh dihapus (admin/users_action.php).
 */
const USER_REF_SQL = "SELECT created_by uid FROM sampling_orders
    UNION ALL SELECT qc_user_id FROM sampling_process_steps
    UNION ALL SELECT qc_user_id FROM sampling_progress
    UNION ALL SELECT user_id FROM attendance
    UNION ALL SELECT dicatat_oleh FROM attendance
    UNION ALL SELECT user_id FROM overtime
    UNION ALL SELECT dibuat_oleh FROM overtime";

/**
 * Konteks akses halaman foreman. Berhenti (redirect) kalau bukan foreman/admin.
 *
 * foreman_id: tim yang sedang dilihat. Foreman selalu timnya sendiri; admin
 * memilih lewat parameter ?fm= (0 = semua QC aktif).
 * @return array{is_admin:bool,foreman_id:int,foremen:array}
 */
function fmKonteks(mysqli $conn): array {
    $role = $_SESSION['role'] ?? '';
    if (!isset($_SESSION['id']) || ($role !== 'foreman' && $role !== 'admin')) {
        header("Location: ../auth/login.php");
        exit;
    }
    fmCekMigrasi($conn);
    if ($role === 'foreman') {
        return ['is_admin' => false, 'foreman_id' => (int)$_SESSION['id'], 'foremen' => []];
    }
    $foremen = [];
    $q = mysqli_query($conn, "SELECT id, nama FROM users WHERE role = 'foreman' AND status = 1 ORDER BY nama");
    while ($r = mysqli_fetch_assoc($q)) $foremen[(int)$r['id']] = $r['nama'];
    $fm = (int)($_REQUEST['fm'] ?? 0);
    if (!isset($foremen[$fm])) $fm = 0;
    return ['is_admin' => true, 'foreman_id' => $fm, 'foremen' => $foremen];
}

/**
 * Pesan jelas (bukan fatal error) kalau config/foreman.sql belum dijalankan
 * di server ini — mode exception mysqli akan mematikan halaman di query pertama.
 */
function fmCekMigrasi(mysqli $conn): void {
    try {
        mysqli_query($conn, "SELECT foreman_id FROM users LIMIT 0");
        mysqli_query($conn, "SELECT 1 FROM attendance LIMIT 0");
        mysqli_query($conn, "SELECT 1 FROM overtime LIMIT 0");
    } catch (\Throwable $e) {
        http_response_code(500);
        die('Foreman module is not active yet: run the config/foreman.sql migration on this database.');
    }
}

/** Anggota aktif tim, urut nama. foreman_id 0 = semua QC aktif (khusus admin). */
function fmAnggota(mysqli $conn, int $foremanId): array {
    $where = $foremanId > 0 ? "foreman_id = $foremanId" : "role = 'qc'";
    $q = mysqli_query($conn, "SELECT id, nik, nama FROM users WHERE status = 1 AND $where ORDER BY nama");
    $out = [];
    while ($r = mysqli_fetch_assoc($q)) $out[(int)$r['id']] = $r;
    return $out;
}

/** Query string penanda tim untuk link/form admin (kosong untuk foreman). */
function fmParam(array $ctx): string {
    return $ctx['is_admin'] && $ctx['foreman_id'] > 0 ? '&fm=' . $ctx['foreman_id'] : '';
}

/**
 * Rekap OT per user dalam satu bulan (status selain batal).
 * @return array<int,array{kali:int,menit:int}>
 */
function fmRekapOtBulan(mysqli $conn, string $bulan): array {
    $awal  = $bulan . '-01';
    $akhir = date('Y-m-t', strtotime($awal));
    $q = mysqli_query($conn, "SELECT user_id, COUNT(*) kali, COALESCE(SUM(durasi_menit),0) menit
                              FROM overtime WHERE status <> 'batal' AND tanggal BETWEEN '$awal' AND '$akhir'
                              GROUP BY user_id");
    $out = [];
    while ($r = mysqli_fetch_assoc($q)) {
        $out[(int)$r['user_id']] = ['kali' => (int)$r['kali'], 'menit' => (int)$r['menit']];
    }
    return $out;
}

/** Urutan prioritas pembagian rata: jam OT paling sedikit, lalu jumlah kali paling sedikit. */
function fmKunciAdil(array $rekap, int $uid): array {
    return [$rekap[$uid]['menit'] ?? 0, $rekap[$uid]['kali'] ?? 0];
}

/** Durasi menit dari jam HH:MM; selesai <= mulai dianggap lewat tengah malam. */
function fmDurasi(string $mulai, string $selesai): int {
    [$h1, $m1] = array_map('intval', explode(':', $mulai));
    [$h2, $m2] = array_map('intval', explode(':', $selesai));
    $d = ($h2 * 60 + $m2) - ($h1 * 60 + $m1);
    return $d <= 0 ? $d + 1440 : $d;
}

/** 150 → "2h 30m" */
function fmJam(int $menit): string {
    if ($menit <= 0) return '0h';
    $s = intdiv($menit, 60) . 'h';
    return $menit % 60 ? $s . ' ' . ($menit % 60) . 'm' : $s;
}

/** Validasi tanggal Y-m-d; kembalikan $default kalau tidak valid. */
function fmTanggal(?string $s, string $default): string {
    $d = DateTime::createFromFormat('Y-m-d', (string)$s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : $default;
}

/** Validasi bulan Y-m; kembalikan $default kalau tidak valid. */
function fmBulan(?string $s, string $default): string {
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)$s) ? $s : $default;
}

const FM_NAMA_BULAN = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
const FM_NAMA_HARI  = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

function fmLabelBulan(string $bulan): string {
    return FM_NAMA_BULAN[(int)substr($bulan, 5, 2)] . ' ' . substr($bulan, 0, 4);
}

function fmLabelTanggal(string $tgl): string {
    $ts = strtotime($tgl);
    return FM_NAMA_HARI[(int)date('N', $ts)] . ', ' . (int)date('j', $ts) . ' '
         . FM_NAMA_BULAN[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Filter bar seragam untuk halaman foreman: pilihan tim (admin saja) + navigasi
 * tanggal/bulan (‹ label ›, tombol sekarang). Input date/month aslinya
 * disembunyikan di balik label dan dibuka lewat showPicker().
 *
 * $o: type ('date'|'month'), name, value, label, teks, prev, next, now, now_label
 */
function fmFilterBar(array $ctx, array $o): string {
    $fmQ  = fmParam($ctx);
    $link = fn($v) => '?' . $o['name'] . '=' . $v . $fmQ;
    $ico  = [
        'tim' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
        'cal' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    ];
    $svg = fn($k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $ico[$k] . '</svg>';
    ob_start(); ?>
    <form class="filterbar" method="GET">
        <?php if ($ctx['is_admin']): ?>
        <label class="fctl">
            <span class="fctl-ico"><?php echo $svg('tim'); ?></span>
            <span class="fctl-txt"><small>Foreman Team</small>
                <select name="fm" onchange="this.form.submit()">
                    <option value="0">All QC</option>
                    <?php foreach ($ctx['foremen'] as $fid => $fn): ?>
                        <option value="<?php echo $fid; ?>" <?php echo $fid === $ctx['foreman_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($fn); ?></option>
                    <?php endforeach; ?>
                </select>
            </span>
        </label>
        <?php endif; ?>
        <div class="datenav">
            <a class="nav-btn" href="<?php echo $link($o['prev']); ?>" title="Previous" aria-label="Previous">‹</a>
            <label class="fctl" data-picker>
                <span class="fctl-ico"><?php echo $svg('cal'); ?></span>
                <span class="fctl-txt"><small><?php echo $o['label']; ?></small><strong><?php echo $o['teks']; ?></strong></span>
                <input class="fctl-native" type="<?php echo $o['type']; ?>" name="<?php echo $o['name']; ?>" value="<?php echo $o['value']; ?>" onchange="this.form.submit()" tabindex="-1">
            </label>
            <a class="nav-btn" href="<?php echo $link($o['next']); ?>" title="Next" aria-label="Next">›</a>
        </div>
        <a class="chip-now <?php echo $o['value'] === $o['now'] ? 'on' : ''; ?>" href="<?php echo $link($o['now']); ?>"><?php echo $o['now_label']; ?></a>
    </form>
    <script src="../assets/components.js" defer></script>
    <?php return ob_get_clean();
}

/** Redirect balik dengan pesan ok/err (pola yang sama dengan halaman admin lain). */
function fmBalik(string $url, string $jenis, string $pesan): void {
    header('Location: ' . $url . (strpos($url, '?') === false ? '?' : '&') . $jenis . '=' . urlencode($pesan));
    exit;
}

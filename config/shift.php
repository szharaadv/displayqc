<?php
/**
 * Definisi shift QC — satu-satunya sumber kebenaran.
 *
 * Batas shift ditentukan oleh hari MULAI shift, bukan hari kalender waktu yang
 * dicek. Ini penting karena shift 3 selalu melewati tengah malam: step yang
 * mulai jam 01:00 hari Jumat tetap milik shift 3 yang dimulai Kamis malam, dan
 * memakai durasi efektif milik Kamis.
 */

date_default_timezone_set('Asia/Jakarta');

/**
 * Nilai default (fallback) — dipakai kalau tabel master_shifts belum ada atau
 * belum lengkap, supaya sistem tetap jalan. Kunci: weekday (Senin-Kamis),
 * friday, saturday, sunday (Minggu). Format tiap shift seperti qcBatasShift().
 */
function qcShiftDefault(): array {
    return [
        'weekday' => [
            ['nama' => 'Shift 1', 'mulai' => 390,  'selesai' => 915,  'detik' => 28800],
            ['nama' => 'Shift 2', 'mulai' => 915,  'selesai' => 1380, 'detik' => 27000],
            ['nama' => 'Shift 3', 'mulai' => 1380, 'selesai' => 1830, 'detik' => 24300],
        ],
        'friday' => [
            ['nama' => 'Shift 1', 'mulai' => 390,  'selesai' => 885,  'detik' => 29700],
            ['nama' => 'Shift 2', 'mulai' => 885,  'selesai' => 1365, 'detik' => 28800],
            ['nama' => 'Shift 3', 'mulai' => 1365, 'selesai' => 1830, 'detik' => 27900],
        ],
        'saturday' => [
            ['nama' => 'Shift 1', 'mulai' => 390,  'selesai' => 855,  'detik' => 27900],
            ['nama' => 'Shift 2', 'mulai' => 855,  'selesai' => 1305, 'detik' => 27000],
            ['nama' => 'Shift 3', 'mulai' => 1305, 'selesai' => 1755, 'detik' => 27000],
        ],
        'sunday' => [
            ['nama' => 'Shift 1', 'mulai' => 390,  'selesai' => 915,  'detik' => 28800],
            ['nama' => 'Shift 2', 'mulai' => 915,  'selesai' => 1380, 'detik' => 27000],
            ['nama' => 'Shift 3', 'mulai' => 1380, 'selesai' => 1830, 'detik' => 24300],
        ],
    ];
}

/** Ubah 'HH:MM:SS' menjadi menit sejak 00:00. */
function qcJamKeMenit(string $jam): int {
    [$h, $m] = array_map('intval', explode(':', $jam));
    return $h * 60 + $m;
}

/**
 * Muat definisi shift dari tabel master_shifts (via $conn global), sekali per
 * request. Kalau tabel tidak ada, gagal, atau tidak lengkap (butuh 12 baris:
 * 4 tipe hari × 3 shift), pakai qcShiftDefault() supaya tidak pernah error.
 */
function qcMuatShift(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = qcShiftDefault();

    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        // Bungkus try/catch: kalau tabel master_shifts belum dibuat (sebelum
        // migrasi dijalankan), mysqli mode-exception akan melempar — jangan
        // sampai membuat seluruh halaman crash; cukup pakai default.
        try {
            $res = mysqli_query($conn, "SELECT hari_tipe, shift_no, jam_mulai, jam_selesai, efektif_menit FROM master_shifts");
        } catch (\Throwable $e) {
            $res = false;
        }
        if ($res && mysqli_num_rows($res) === 12) {
            $tmp = [];
            while ($r = mysqli_fetch_assoc($res)) {
                $mulai   = qcJamKeMenit($r['jam_mulai']);
                $selesai = qcJamKeMenit($r['jam_selesai']);
                if ($selesai <= $mulai) $selesai += 1440; // lewat tengah malam
                $tmp[$r['hari_tipe']][(int)$r['shift_no']] = [
                    'nama'    => 'Shift ' . (int)$r['shift_no'],
                    'mulai'   => $mulai,
                    'selesai' => $selesai,
                    'detik'   => (int)$r['efektif_menit'] * 60,
                ];
            }
            // Pastikan ketiga tipe hari & ketiga shift lengkap sebelum dipakai
            $lengkap = true;
            foreach (['weekday', 'friday', 'saturday', 'sunday'] as $t) {
                for ($n = 1; $n <= 3; $n++) {
                    if (!isset($tmp[$t][$n])) { $lengkap = false; break 2; }
                }
            }
            if ($lengkap) {
                foreach ($tmp as $t => $shifts) {
                    ksort($shifts);
                    $cache[$t] = array_values($shifts);
                }
            }
        }
    }
    return $cache;
}

/**
 * Batas tiap shift dalam menit sejak 00:00 pada hari mulai shift.
 * Nilai 'selesai' boleh lebih dari 1440 (= lewat tengah malam).
 * 'detik' = durasi kerja efektif, dipakai sebagai pembagi operation ratio.
 *
 * @param int $hari 1=Senin .. 7=Minggu — hari MULAI shift
 */
function qcBatasShift(int $hari): array {
    $cfg = qcMuatShift();
    if ($hari === 5) return $cfg['friday'];
    if ($hari === 6) return $cfg['saturday'];
    if ($hari === 7) return $cfg['sunday'];
    return $cfg['weekday']; // Senin–Kamis
}

/** Ketiga shift milik satu hari, sudah jadi timestamp absolut. */
function qcShiftPadaHari(int $ts_hari): array {
    $midnight = strtotime(date('Y-m-d 00:00:00', $ts_hari));
    $hasil    = [];

    foreach (qcBatasShift((int)date('N', $ts_hari)) as $b) {
        $hasil[] = [
            'nama'       => $b['nama'],
            'detik'      => $b['detik'],
            'mulai_ts'   => $midnight + $b['mulai']   * 60,
            'selesai_ts' => $midnight + $b['selesai'] * 60,
            'shift_date' => date('Y-m-d', $ts_hari),
        ];
    }
    return $hasil;
}

/**
 * Shift yang memuat $waktu.
 *
 * 'shift_date' = tanggal hari mulai shift — inilah "tanggal kerja" yang dipakai
 * untuk mengelompokkan order dan step, supaya shift 3 tidak terbelah dua tanggal.
 *
 * Kalau $waktu jatuh di celah antar shift (Minggu 05:15–06:29, karena shift 3
 * Sabtu sudah selesai tapi shift 1 Minggu belum mulai), dikembalikan shift
 * terakhir yang sudah berjalan.
 *
 * @param string|int $waktu timestamp, atau apa pun yang diterima strtotime()
 * @return array{nama:string,detik:int,mulai_ts:int,selesai_ts:int,shift_date:string}
 */
function qcShift($waktu = 'now'): array {
    $ts = is_int($waktu) ? $waktu : strtotime($waktu);

    // Kandidat harus mencakup hari sebelumnya, karena shift 3 kemarin bisa
    // meluber sampai pagi ini.
    $kandidat = array_merge(
        qcShiftPadaHari(strtotime('-1 day', $ts)),
        qcShiftPadaHari($ts)
    );

    foreach ($kandidat as $s) {
        if ($ts >= $s['mulai_ts'] && $ts < $s['selesai_ts']) {
            return $s;
        }
    }

    $sudah_lewat = array_filter($kandidat, fn($s) => $s['selesai_ts'] <= $ts);
    return $sudah_lewat ? end($sudah_lewat) : $kandidat[0];
}

/** Label shift aktif untuk ditampilkan, mis. "Shift 3 (23:00-06:30) | Efektif 6,75 jam". */
function qcLabelShift(array $shift): string {
    $jam = rtrim(rtrim(number_format($shift['detik'] / 3600, 2, ',', ''), '0'), ',');
    return sprintf(
        '%s (%s-%s) | Efektif %s jam',
        $shift['nama'],
        date('H:i', $shift['mulai_ts']),
        date('H:i', $shift['selesai_ts']),
        $jam
    );
}

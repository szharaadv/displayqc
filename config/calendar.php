<?php
/**
 * Kalender kerja YADIN — LAPISAN BACA (satu-satunya sumber kebenaran).
 *
 * Tabel master_calendar hanya menyimpan tanggal PENGECUALIAN. Hari normal
 * diturunkan di sini: Senin–Jumat = kerja, Sabtu–Minggu = libur.
 *   tipe 'holiday'  -> libur walau jatuh di hari kerja (tidak dikerjakan).
 *   tipe 'overtime' -> akhir pekan yang tetap dikerjakan (lembur).
 *
 * CATATAN: fungsi-fungsi ini SENGAJA belum dipanggil oleh perhitungan
 * Operation Ratio / dashboard. Integrasi ke ratio adalah keputusan terpisah
 * (termasuk jam shift mana yang dipakai untuk akhir pekan yang dikerjakan).
 * Untuk sekarang: master + tampilan dulu.
 */

date_default_timezone_set('Asia/Jakarta');

/**
 * Muat seluruh pengecualian kalender dari master_calendar (cache per-request).
 * Dibungkus try/catch karena mode exception mysqli akan mematikan halaman
 * kalau tabel belum dibuat (sebelum migrasi dijalankan). Kalau gagal → [].
 */
function qcMuatKalender(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $map = [];
    try {
        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            $q = mysqli_query($conn, "SELECT `tanggal`, `tipe`, `label` FROM `master_calendar`");
            while ($q && $r = mysqli_fetch_assoc($q)) {
                $map[$r['tanggal']] = ['tipe' => $r['tipe'], 'label' => $r['label']];
            }
        }
    } catch (\Throwable $e) {
        $map = [];
    }

    $cache = $map;
    return $cache;
}

/**
 * Status sebuah tanggal.
 * @return array{tipe:string,label:string,kerja:bool}
 *   tipe: 'work' | 'off' | 'holiday' | 'overtime'
 */
function qcHari(string $tgl): array {
    $ts  = strtotime($tgl);
    $key = date('Y-m-d', $ts);
    $dow = (int)date('N', $ts); // 1=Senin .. 7=Minggu

    $map = qcMuatKalender();
    if (isset($map[$key])) {
        if ($map[$key]['tipe'] === 'holiday') {
            return ['tipe' => 'holiday', 'label' => $map[$key]['label'], 'kerja' => false];
        }
        // overtime — akhir pekan yang tetap dikerjakan
        return ['tipe' => 'overtime', 'label' => $map[$key]['label'], 'kerja' => true];
    }

    // Hari normal (tanpa pengecualian)
    if ($dow >= 6) {
        return ['tipe' => 'off', 'label' => '', 'kerja' => false];
    }
    return ['tipe' => 'work', 'label' => '', 'kerja' => true];
}

/** Apakah tanggal ini hari kerja? (shortcut) */
function qcHariKerja(string $tgl): bool {
    return qcHari($tgl)['kerja'];
}

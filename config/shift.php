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
 * Batas tiap shift dalam menit sejak 00:00 pada hari mulai shift.
 * Nilai 'selesai' boleh lebih dari 1440 (= lewat tengah malam).
 * 'detik' = durasi kerja efektif, dipakai sebagai pembagi operation ratio.
 *
 * @param int $hari 1=Senin .. 7=Minggu — hari MULAI shift
 */
function qcBatasShift(int $hari): array {
    if ($hari === 5) { // Jumat — 06:30 / 14:45 / 22:45, shift 3 selesai 06:30
        return [
            ['nama' => 'Shift 1', 'mulai' => 390,  'selesai' => 885,  'detik' => 29700],
            ['nama' => 'Shift 2', 'mulai' => 885,  'selesai' => 1365, 'detik' => 28800],
            ['nama' => 'Shift 3', 'mulai' => 1365, 'selesai' => 1830, 'detik' => 27900],
        ];
    }
    if ($hari === 6) { // Sabtu — 06:30 / 14:15 / 21:45, shift 3 selesai 05:15
        return [
            ['nama' => 'Shift 1', 'mulai' => 390,  'selesai' => 855,  'detik' => 27900],
            ['nama' => 'Shift 2', 'mulai' => 855,  'selesai' => 1305, 'detik' => 27000],
            ['nama' => 'Shift 3', 'mulai' => 1305, 'selesai' => 1755, 'detik' => 27000],
        ];
    }
    // Senin–Kamis & Minggu — 06:30 / 15:15 / 23:00, shift 3 selesai 06:30
    return [
        ['nama' => 'Shift 1', 'mulai' => 390,  'selesai' => 915,  'detik' => 28800],
        ['nama' => 'Shift 2', 'mulai' => 915,  'selesai' => 1380, 'detik' => 27000],
        ['nama' => 'Shift 3', 'mulai' => 1380, 'selesai' => 1830, 'detik' => 24300],
    ];
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

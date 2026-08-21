<?php
/**
 * Helper tampilan Operation Ratio — dipakai bersama oleh dashboard.php &
 * evaluation.php. Semua di sini murni presentasi (tidak mengubah perhitungan):
 * ratio tetap = total_detik / work_sec. Tujuannya membuat angka bisa ditelusuri
 * (aktif ⁄ efektif + rincian istirahat) dan mudah dipahami orang awam.
 *
 * Warna memakai CSS variable yang sudah ada di tiap halaman (dengan nilai
 * cadangan terang), jadi tidak ada warna baru yang diperkenalkan.
 */

/** Detik → "8j 5m". */
function qcFmtDurasiDetik(int $detik): string {
    $detik = max(0, $detik);
    return intdiv($detik, 3600) . 'j ' . intdiv($detik % 3600, 60) . 'm';
}

/** Menit → "8j 5m". */
function qcFmtDurasiMenit(int $menit): string {
    $menit = max(0, $menit);
    return intdiv($menit, 60) . 'j ' . ($menit % 60) . 'm';
}

/** Baris kecil "X aktif ⁄ Y efektif" untuk ditaruh di bawah angka ratio. */
function qcRatioFraction(int $total_detik, int $work_sec): string {
    return '<div class="qc-frac"><b>' . qcFmtDurasiDetik($total_detik)
         . '</b> aktif &frasl; <b>' . qcFmtDurasiDetik(max(0, $work_sec))
         . '</b> efektif</div>';
}

/**
 * Ikon "?" + tooltip rincian jam efektif: durasi shift − istirahat = efektif.
 * Durasi & istirahat diturunkan dari mulai_ts/selesai_ts & work_sec (efektif),
 * jadi tidak perlu data tambahan.
 */
function qcRatioHelp(int $mulai_ts, int $selesai_ts, int $work_sec): string {
    $durasi_menit  = (int)round(($selesai_ts - $mulai_ts) / 60);
    $efektif_menit = (int)round($work_sec / 60);
    $istirahat     = max(0, $durasi_menit - $efektif_menit);
    $mulai   = date('H:i', $mulai_ts);
    $selesai = date('H:i', $selesai_ts);

    $titleAttr = htmlspecialchars(
        $mulai . '–' . $selesai . ' · durasi ' . qcFmtDurasiMenit($durasi_menit)
        . ' − istirahat ' . $istirahat . 'm = ' . qcFmtDurasiMenit($efektif_menit) . ' efektif'
    );

    ob_start(); ?>
<span class="qc-help" tabindex="0" title="<?php echo $titleAttr; ?>">?<span class="qc-tip"><span class="r"><span class="lab"><?php echo $mulai; ?>–<?php echo $selesai; ?></span><span class="num">durasi <?php echo qcFmtDurasiMenit($durasi_menit); ?></span></span><span class="r"><span class="lab">− Istirahat</span><span class="num"><?php echo $istirahat; ?>m</span></span><span class="r tot"><span class="lab">= Jam efektif</span><span class="num"><?php echo qcFmtDurasiMenit($efektif_menit); ?></span></span></span></span>
<?php
    return ob_get_clean();
}

/** <style> untuk fraction + tooltip. Echo SEKALI per halaman. */
function qcRatioUiStyle(): string {
    return <<<'CSS'
<style>
.qc-frac { font-size:10.5px; color:var(--text3,#9ca3af); margin-top:3px; font-family:'JetBrains Mono',monospace; }
.qc-frac b { color:var(--text2,#6b7280); font-weight:600; }
.qc-help { position:relative; display:inline-flex; align-items:center; justify-content:center; width:15px; height:15px; border-radius:50%; border:1.4px solid var(--text3,#9ca3af); color:var(--text3,#9ca3af); font-size:10px; font-weight:700; cursor:help; margin-left:6px; vertical-align:middle; }
.qc-help:hover, .qc-help:focus { border-color:var(--red,#CC0000); color:var(--red,#CC0000); outline:none; }
.qc-tip { position:absolute; bottom:calc(100% + 9px); left:50%; transform:translateX(-50%); width:212px; background:var(--surface,#fff); color:var(--text,#111827); border:1px solid var(--border,rgba(0,0,0,0.1)); border-radius:9px; box-shadow:0 8px 24px rgba(0,0,0,0.14); padding:10px 12px; font-size:11px; font-weight:500; font-family:'Plus Jakarta Sans',sans-serif; line-height:1.5; text-align:left; opacity:0; visibility:hidden; transition:opacity .12s; z-index:80; }
.qc-tip::after { content:''; position:absolute; top:100%; left:50%; width:10px; height:10px; background:var(--surface,#fff); border-right:1px solid var(--border,rgba(0,0,0,0.1)); border-bottom:1px solid var(--border,rgba(0,0,0,0.1)); transform:translate(-50%,-6px) rotate(45deg); }
.qc-help:hover .qc-tip, .qc-help:focus .qc-tip { opacity:1; visibility:visible; }
.qc-tip .r { display:flex; justify-content:space-between; gap:14px; padding:2px 0; }
.qc-tip .r .lab { color:var(--text2,#6b7280); }
.qc-tip .r .num { font-family:'JetBrains Mono',monospace; color:var(--text,#111827); }
.qc-tip .r.tot { border-top:1px solid var(--border,rgba(0,0,0,0.1)); margin-top:4px; padding-top:6px; font-weight:700; }
.qc-tip .r.tot .num { color:var(--red,#CC0000); }
</style>
CSS;
}

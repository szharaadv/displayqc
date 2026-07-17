<?php
/**
 * Penulis file .xlsx minimal — tanpa library eksternal.
 *
 * Format xlsx sebenarnya cuma arsip ZIP berisi beberapa file XML, jadi cukup
 * ekstensi zip bawaan PHP. Dipakai supaya export benar-benar Excel: angka tetap
 * angka, teks UTF-8 tidak mojibake, dan tidak ada masalah pemisah koma/titik-koma
 * seperti kalau memakai CSV di Excel berlokal Indonesia.
 *
 * Pemakaian:
 *   $sheets = [
 *       'Ratio' => [
 *           [xb('Nama'), xb('Ratio')],   // xb() = cetak tebal
 *           ['BAYU', 63.5],              // angka ditulis sebagai angka
 *       ],
 *   ];
 *   xlsxKirim('Laporan.xlsx', $sheets);
 */

/** Tandai sebuah sel supaya dicetak tebal. */
function xb($nilai): array {
    return ['__bold' => true, 'v' => $nilai];
}

/** Index kolom 0-based → huruf kolom Excel (0=A, 26=AA). */
function xlsxKolom(int $i): string {
    $s = '';
    $i++;
    while ($i > 0) {
        $m = ($i - 1) % 26;
        $s = chr(65 + $m) . $s;
        $i = intdiv($i - 1, 26);
    }
    return $s;
}

function xlsxEsc(string $t): string {
    // Buang karakter kontrol yang ilegal di XML, sisanya di-escape normal.
    $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $t);
    return htmlspecialchars($t, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function xlsxSheetXml(array $rows): string {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

    foreach ($rows as $ri => $row) {
        $r    = $ri + 1;
        $xml .= '<row r="' . $r . '">';

        foreach (array_values($row) as $ci => $sel) {
            $bold = false;
            if (is_array($sel) && isset($sel['__bold'])) {
                $bold = true;
                $sel  = $sel['v'];
            }
            if ($sel === null || $sel === '') continue;

            $ref = xlsxKolom($ci) . $r;
            $s   = $bold ? ' s="1"' : '';

            if (is_int($sel) || is_float($sel)) {
                $xml .= '<c r="' . $ref . '"' . $s . '><v>' . $sel . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">'
                      . xlsxEsc((string)$sel) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
    }
    return $xml . '</sheetData></worksheet>';
}

/**
 * Bangun file xlsx, kirim sebagai download, lalu exit.
 *
 * @param string $namafile nama file yang diunduh, mis. 'Laporan.xlsx'
 * @param array  $sheets   ['Nama Sheet' => array baris], tiap baris array sel
 */
function xlsxKirim(string $namafile, array $sheets): void {
    if (empty($sheets)) $sheets = ['Sheet1' => []];

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);

    $n = count($sheets);

    // [Content_Types].xml
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    for ($i = 1; $i <= $n; $i++) {
        $ct .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $zip->addFromString('[Content_Types].xml', $ct . '</Types>');

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
      . '</Relationships>');

    // workbook.xml + relasinya
    $wb   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
          . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

    $i = 1;
    foreach ($sheets as $nama => $rows) {
        // Nama sheet Excel: maks 31 char, tanpa : \ / ? * [ ]
        $nama_aman = mb_substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], '-', (string)$nama), 0, 31);
        $wb   .= '<sheet name="' . xlsxEsc($nama_aman) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
        $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', xlsxSheetXml($rows));
        $i++;
    }
    $rels .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $zip->addFromString('xl/workbook.xml', $wb . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', $rels . '</Relationships>');

    // styles.xml — hanya dua gaya: normal (s=0) dan tebal (s=1)
    $zip->addFromString('xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
      . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
      . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
      . '<fills count="2"><fill><patternFill patternType="none"/></fill>'
      . '<fill><patternFill patternType="gray125"/></fill></fills>'
      . '<borders count="1"><border/></borders>'
      . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
      . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
      . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
      . '</styleSheet>');

    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $namafile . '"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: max-age=0');
    readfile($tmp);
    unlink($tmp);
    exit;
}

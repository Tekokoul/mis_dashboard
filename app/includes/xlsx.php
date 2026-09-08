<?php
/**
 * A small reader for .xlsx workbooks, on PHP's own zip and XML support.
 *
 * The dashboard has no spreadsheet library in vendor/ and does not want one
 * for this: an .xlsx is a zip of XML files, and reading one sheet of text,
 * numbers and dates takes a page of code. Anything fancier (formulas are
 * read by their cached value, merged cells by their top-left cell, rich text
 * by its plain words) is not needed to import a work plan.
 *
 * Every function throws RuntimeException with a message fit to show the
 * person who uploaded the file. Sizes are capped before anything is
 * inflated: an upload is a stranger's file.
 */

const XLSX_MAX_FILE_BYTES  = 25 * 1024 * 1024;   // the upload itself
// Inflated XML, not compressed bytes. A zip of 200 KB can hold 60 MB of
// repeated <si><t>a</t></si>, and SimpleXML builds a node tree several times
// the size of the text, so a cap the memory limit cannot honour is no cap at
// all: these two are set against php.ini's 256 M, not against what a
// spreadsheet could theoretically contain.
const XLSX_MAX_PART_BYTES  = 12 * 1024 * 1024;   // any one XML part, inflated
const XLSX_MAX_TOTAL_BYTES = 32 * 1024 * 1024;   // all parts of one workbook together
const XLSX_MAX_ROWS        = 20000;
const XLSX_NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
const XLSX_NS_REL  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

/** [sheet name => zip path of its XML], in workbook order. */
function xlsx_sheets($path) {
    $z = xlsx_open($path);
    try { return xlsx_sheet_map($z); } finally { $z->close(); }
}

/**
 * One sheet as rows: [row number => [0-based column => value]]. Empty cells
 * are absent. Text comes back as strings, numbers as numeric strings, dates
 * (cells with a date format) as "Y-m-d", booleans as "TRUE"/"FALSE".
 * $sheet is a name, or null for the first sheet.
 */
function xlsx_read($path, $sheet = null) {
    $z = xlsx_open($path);
    try {
        $sheets = xlsx_sheet_map($z);
        if (!$sheets) { throw new RuntimeException('The workbook has no sheets.'); }
        if ($sheet === null) { $sheet = array_key_first($sheets); }
        if (!isset($sheets[$sheet])) { throw new RuntimeException('The workbook has no sheet called "' . $sheet . '".'); }
        $strings = xlsx_shared_strings($z);
        $dateStyles = xlsx_date_styles($z);
        $xml = xlsx_part($z, $sheets[$sheet]);
        $doc = xlsx_xml($xml, 'the sheet "' . $sheet . '"');
        $rows = [];
        $count = 0;
        foreach ($doc->sheetData->row ?? [] as $row) {
            if (++$count > XLSX_MAX_ROWS) { throw new RuntimeException('The sheet has more than ' . XLSX_MAX_ROWS . ' rows.'); }
            $r = (int)$row['r'];
            if ($r <= 0) { $r = $count; }
            $cells = [];
            // The r attribute is optional; without it a cell's column is its
            // position among ALL the row's cells. Counting the ones kept so
            // far would shift every value left of an empty cell.
            $pos = 0;
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                $col = xlsx_col_index(preg_replace('/\d+$/', '', $ref));
                if ($col < 0) { $col = $pos; }
                $pos = $col + 1;
                $v = xlsx_cell_value($c, $strings, $dateStyles);
                if ($v === null || $v === '') { continue; }
                $cells[$col] = $v;
            }
            if ($cells) { $rows[$r] = $cells; }
        }
        return ['sheet' => $sheet, 'rows' => $rows];
    } finally {
        $z->close();
    }
}

/**
 * How much inflated XML this workbook has cost so far. Reset when a workbook
 * is opened, added to as each part is read: one part under the limit says
 * nothing about six of them.
 */
function xlsx_budget($add = 0, $reset = false) {
    static $used = 0;
    if ($reset) { $used = 0; return 0; }
    $used += (int)$add;
    return $used;
}

function xlsx_open($path) {
    if (!is_file($path) || !is_readable($path)) { throw new RuntimeException('The file could not be read.'); }
    $size = filesize($path);
    if ($size === false || $size > XLSX_MAX_FILE_BYTES) { throw new RuntimeException('The file is larger than 25 MB.'); }
    $head = (string)file_get_contents($path, false, null, 0, 4);
    if (strncmp($head, "PK\x03\x04", 4) !== 0) { throw new RuntimeException('That is not an .xlsx workbook (an Excel 2007 or later file).'); }
    $z = new ZipArchive();
    $ok = $z->open($path, ZipArchive::RDONLY);
    if ($ok !== true) { throw new RuntimeException('The workbook could not be opened (zip error ' . (int)$ok . ').'); }
    if ($z->locateName('xl/workbook.xml') === false) { $z->close(); throw new RuntimeException('That is not an .xlsx workbook: it has no xl/workbook.xml.'); }
    xlsx_budget(0, true);
    return $z;
}

/** One part of the zip, size-checked before it is inflated. */
function xlsx_part(ZipArchive $z, $name) {
    $idx = $z->locateName($name);
    if ($idx === false) { return ''; }
    $st = $z->statIndex($idx);
    // The declared uncompressed size, checked BEFORE anything is inflated.
    if (!$st || (int)$st['size'] > XLSX_MAX_PART_BYTES) { throw new RuntimeException('A part of the workbook (' . $name . ') is too large to read.'); }
    if (xlsx_budget((int)$st['size']) > XLSX_MAX_TOTAL_BYTES) { throw new RuntimeException('The workbook holds more data than can be read at once.'); }
    $data = $z->getFromIndex($idx);
    return $data === false ? '' : $data;
}

function xlsx_xml($xml, $what) {
    if (trim((string)$xml) === '') { throw new RuntimeException('The workbook is missing ' . $what . '.'); }
    // No external entities, no network: an uploaded file is not trusted.
    $prev = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if ($doc === false) { throw new RuntimeException('The workbook could not be parsed (' . $what . ').'); }
    return $doc;
}

function xlsx_sheet_map(ZipArchive $z) {
    $wb = xlsx_xml(xlsx_part($z, 'xl/workbook.xml'), 'xl/workbook.xml');
    $rels = [];
    $relXml = xlsx_part($z, 'xl/_rels/workbook.xml.rels');
    if ($relXml !== '') {
        foreach (xlsx_xml($relXml, 'the workbook relationships')->Relationship as $rel) {
            $target = (string)$rel['Target'];
            if ($target === '') { continue; }
            // Targets are relative to xl/ unless they start with a slash.
            $target = ($target[0] === '/') ? ltrim($target, '/') : 'xl/' . ltrim($target, './');
            $rels[(string)$rel['Id']] = $target;
        }
    }
    $out = [];
    $n = 0;
    foreach ($wb->sheets->sheet ?? [] as $sh) {
        $n++;
        $name = (string)$sh['name'];
        $rid  = (string)$sh->attributes(XLSX_NS_REL)['id'];
        $path = $rels[$rid] ?? ('xl/worksheets/sheet' . $n . '.xml');
        if ($name === '') { $name = 'Sheet' . $n; }
        $out[$name] = $path;
    }
    return $out;
}

function xlsx_shared_strings(ZipArchive $z) {
    $xml = xlsx_part($z, 'xl/sharedStrings.xml');
    if ($xml === '') { return []; }
    $doc = xlsx_xml($xml, 'the shared strings');
    $out = [];
    foreach ($doc->si as $si) {
        // Plain <t>, or rich text as several <r><t>: the words are all that matter.
        $parts = [];
        foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $t) { $parts[] = (string)$t; }
        $out[] = implode('', $parts);
    }
    return $out;
}

/** [style index => true] for the cell styles that format a number as a date. */
function xlsx_date_styles(ZipArchive $z) {
    $xml = xlsx_part($z, 'xl/styles.xml');
    if ($xml === '') { return []; }
    $doc = xlsx_xml($xml, 'the styles');
    $custom = [];
    foreach ($doc->numFmts->numFmt ?? [] as $nf) { $custom[(int)$nf['numFmtId']] = (string)$nf['formatCode']; }
    $out = [];
    $i = 0;
    foreach ($doc->cellXfs->xf ?? [] as $xf) {
        $id = (int)$xf['numFmtId'];
        if (xlsx_is_date_format($id, $custom[$id] ?? null)) { $out[$i] = true; }
        $i++;
    }
    return $out;
}

function xlsx_is_date_format($id, $code) {
    // Excel's built-in date and time formats.
    if (($id >= 14 && $id <= 22) || ($id >= 27 && $id <= 36) || ($id >= 45 && $id <= 47) || ($id >= 50 && $id <= 58)) { return true; }
    if ($code === null || $code === '') { return false; }
    // A custom format is a date when it has a day, month, year or hour token
    // outside quoted text, [colour] blocks and backslash escapes.
    $bare = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', $code);
    return (bool)preg_match('/[dmyh]/i', $bare);
}

function xlsx_cell_value(SimpleXMLElement $c, array $strings, array $dateStyles) {
    $type = (string)$c['t'];
    if ($type === 'inlineStr') {
        $parts = [];
        foreach ($c->is->xpath('.//*[local-name()="t"]') ?: [] as $t) { $parts[] = (string)$t; }
        return xlsx_clean(implode('', $parts));
    }
    if (!isset($c->v)) { return null; }
    $v = (string)$c->v;
    if ($type === 's') { return xlsx_clean($strings[(int)$v] ?? ''); }
    if ($type === 'b') { return $v === '1' ? 'TRUE' : 'FALSE'; }
    if ($type === 'str' || $type === 'e') { return xlsx_clean($v); }
    // A number. With a date style it is days since 1899-12-30.
    if (is_numeric($v)) {
        $style = (int)$c['s'];
        if (isset($dateStyles[$style]) && (float)$v >= 1 && (float)$v < 2958466) {
            return gmdate('Y-m-d', (int)round(((float)$v - 25569) * 86400));
        }
        // 46237 stays "46237", 504000.0 becomes "504000", 0.5 stays "0.5".
        $f = (float)$v;
        return (floor($f) == $f && abs($f) < 1e15) ? (string)(int)$f : rtrim(rtrim(sprintf('%.10F', $f), '0'), '.');
    }
    return xlsx_clean($v);
}

function xlsx_clean($s) {
    $s = str_replace(["\r\n", "\r"], "\n", (string)$s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
    return trim($s);
}

function xlsx_col_index($letters) {
    $letters = strtoupper(trim((string)$letters));
    if ($letters === '' || !ctype_alpha($letters)) { return -1; }
    $n = 0;
    foreach (str_split($letters) as $ch) { $n = $n * 26 + (ord($ch) - 64); }
    return $n - 1;
}

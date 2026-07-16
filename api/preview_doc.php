<?php
/**
 * preview_doc.php — faithful inline preview of an uploaded Office document.
 *
 * Converts the file to PDF with LibreOffice (headless) — the same server-side
 * approach LMSes like itslearning/Blackboard use — and streams the PDF so the
 * browser can render it inline. The result is cached per file (keyed by path +
 * mtime), so conversion only happens on the first view.
 *
 * GET ?file=<path under uploads/>
 *   200 application/pdf (inline)         — converted (or already a PDF)
 *   400/404                              — bad or unknown file
 *   415 (JSON)                           — type LibreOffice won't convert
 *   500 (JSON)                           — LibreOffice missing / conversion failed
 * On any non-200 the grading UI falls back to the mammoth.js preview.
 */

// LibreOffice binary — Windows/XAMPP defaults first, then PATH.
$SOFFICE_CANDIDATES = [
    'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
    'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
    'soffice',
];

function fail(int $code, string $msg): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $msg]);
    exit;
}

function serve_pdf(string $path): void {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="preview.pdf"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

$root = realpath(__DIR__ . '/../uploads');
if ($root === false) fail(500, 'uploads directory missing');

// Resolve the requested file safely inside uploads/ (no traversal).
$rel = ltrim(str_replace('\\', '/', (string)($_GET['file'] ?? '')), '/');
if (strncmp($rel, 'uploads/', 8) === 0) $rel = substr($rel, 8);
if ($rel === '' || strpos($rel, '..') !== false) fail(400, 'invalid file path');

$src = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
if ($src === false || strncmp($src, $root, strlen($root)) !== 0 || !is_file($src)) {
    fail(404, 'file not found');
}

$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
if ($ext === 'pdf') serve_pdf($src);   // nothing to convert

$CONVERTIBLE = ['docx', 'doc', 'odt', 'rtf', 'ppt', 'pptx', 'odp', 'xls', 'xlsx', 'ods'];
if (!in_array($ext, $CONVERTIBLE, true)) fail(415, 'type not previewable');

// Locate LibreOffice.
$soffice = null;
foreach ($SOFFICE_CANDIDATES as $c) {
    if ($c === 'soffice' || is_file($c)) { $soffice = $c; break; }
}
if ($soffice === null) fail(500, 'LibreOffice not installed');

$cacheDir = $root . DIRECTORY_SEPARATOR . '.previews';
if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0777, true)) fail(500, 'cannot create cache dir');

$key = sha1($rel . '|' . filemtime($src));
$pdf = $cacheDir . DIRECTORY_SEPARATOR . $key . '.pdf';

if (!is_file($pdf)) {
    // Convert into a unique temp dir so two same-basename jobs can't collide.
    $tmp = $cacheDir . DIRECTORY_SEPARATOR . 'tmp_' . $key;
    @mkdir($tmp, 0777, true);
    // A dedicated writable profile avoids "LibreOffice is already running" errors
    // when Apache's user has no/locked LO profile.
    $profile = 'file:///' . str_replace('\\', '/', $cacheDir) . '/.loprofile';
    $cmd = escapeshellarg($soffice)
         . ' --headless --norestore'
         . ' ' . escapeshellarg('-env:UserInstallation=' . $profile)
         . ' --convert-to pdf --outdir ' . escapeshellarg($tmp)
         . ' ' . escapeshellarg($src) . ' 2>&1';
    @exec($cmd, $out, $rc);

    $produced = $tmp . DIRECTORY_SEPARATOR . pathinfo($src, PATHINFO_FILENAME) . '.pdf';
    if (is_file($produced)) @rename($produced, $pdf);

    // Clean the temp dir.
    if (is_dir($tmp)) {
        foreach (glob($tmp . DIRECTORY_SEPARATOR . '*') ?: [] as $f) @unlink($f);
        @rmdir($tmp);
    }
    if (!is_file($pdf)) fail(500, 'conversion failed: ' . implode(' ', array_slice($out, -3)));
}

serve_pdf($pdf);

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * No PDF/Excel library (PhpSpreadsheet, TCPDF, mPDF, ...) is installed
 * in this project (no composer.json / vendor/ present) — per the
 * instructions not to invent dependencies that aren't actually
 * available, these two helpers use dependency-free approaches instead:
 *
 *  - Excel: outputs an HTML <table> with an .xls filename and the
 *    'application/vnd.ms-excel' content type. Excel opens this
 *    correctly (it detects the HTML content, not the extension) — a
 *    long-standing, reliable trick for CI3 projects without composer.
 *  - PDF: outputs a print-friendly HTML page in a new tab; the person
 *    uses the browser's own "Print -> Save as PDF". Not a true
 *    server-generated PDF file.
 *
 * If you later composer-require PhpSpreadsheet and/or Dompdf/TCPDF/
 * mPDF, these two functions are the only place that needs to change —
 * every report controller calls them, not the export mechanics directly.
 */
if (!function_exists('export_excel')) {
    function export_excel(string $filename, array $headers, array $rows, array $rowKeys): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "<table border='1'><thead><tr>";
        foreach ($headers as $h) echo '<th>' . htmlspecialchars($h) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($rowKeys as $key) {
                $value = is_callable($key) ? $key($row) : ($row[$key] ?? '');
                echo '<td>' . htmlspecialchars((string) $value) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
        exit;
    }
}

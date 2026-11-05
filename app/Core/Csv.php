<?php
declare(strict_types=1);

namespace App\Core;

/** Export CSV compatible Excel (UTF-8 BOM, séparateur « ; »). */
final class Csv
{
    public static function stream(string $filename, array $headers, iterable $rows): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($out, array_values($row), ';', '"', '\\');
        }
        fclose($out);
        exit;
    }
}

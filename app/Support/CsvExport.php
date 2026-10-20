<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams rows as a UTF-8 CSV file that opens correctly in Excel. Cells that
 * start with a formula character are prefixed to prevent CSV injection.
 */
class CsvExport
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $name, array $headings, iterable $rows): StreamedResponse
    {
        $filename = $name.'-'.now()->format('Ymd-His').'.csv';
        Audit::log('exported', null, $filename);

        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(fn ($h) => __($h), $headings), escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row), escape: '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function cell(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)
            ? "'".$value
            : $value;
    }
}

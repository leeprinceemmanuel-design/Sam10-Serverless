<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Reads a CSV file into an array of rows.
 * Each row is an associative array keyed by the header (first line) of the file.
 */
final class CsvReader
{
    /** @return list<array<string, string|null>> */
    public static function read(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("CSV file not found: " . basename($path));
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open CSV file: " . basename($path));
        }

        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header === false) {
            fclose($handle);
            return [];
        }

        // Remove a UTF-8 BOM from the first column name, if the file has one
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map('trim', $header);

        $rows = [];
        while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($line === [null] || $line === ['']) {
                continue; // skip blank lines
            }

            $row = [];
            foreach ($header as $i => $column) {
                $value = isset($line[$i]) ? trim($line[$i]) : null;
                // The dataset writes empty foreign keys as the word "null"
                if ($value === '' || strtolower((string) $value) === 'null') {
                    $value = null;
                }
                $row[$column] = $value;
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }
}

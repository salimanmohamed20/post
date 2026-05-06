<?php

namespace App\Imports;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LegacySourceReader
{
    /**
     * @param  array<int, string>  $preferredTables
     * @return array<int, array<string, mixed>>
     */
    public function rows(?string $path, array $preferredTables = []): array
    {
        if (blank($path)) {
            return [];
        }

        $contents = is_file($path)
            ? file_get_contents($path)
            : Storage::disk('local')->get($path);

        if ($this->looksLikeSql($path, (string) $contents)) {
            return $this->sqlRows((string) $contents, $preferredTables);
        }

        $decoded = json_decode((string) $contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        foreach (['data', 'items', 'rows', 'categories', 'articles'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return array_values($decoded[$key]);
            }
        }

        return array_is_list($decoded) ? array_values($decoded) : [];
    }

    private function looksLikeSql(string $path, string $contents): bool
    {
        return Str::endsWith(Str::lower($path), '.sql')
            || preg_match('/^\s*(INSERT\s+INTO|--|\/\*|CREATE\s+TABLE)/i', $contents) === 1;
    }

    /**
     * @param  array<int, string>  $preferredTables
     * @return array<int, array<string, mixed>>
     */
    private function sqlRows(string $contents, array $preferredTables = []): array
    {
        preg_match_all(
            '/INSERT\s+INTO\s+[`"]?(?<table>[\w\.]+)[`"]?\s*\((?<columns>[^)]+)\)\s*VALUES\s*(?<values>.*?);/is',
            $contents,
            $matches,
            PREG_SET_ORDER,
        );

        $rows = [];
        $normalizedPreferredTables = array_map(
            fn (string $table): string => Str::lower(trim($table, "`\" ")),
            $preferredTables,
        );

        foreach ($matches as $match) {
            $table = Str::lower(trim((string) $match['table'], "`\" "));
            $table = Str::afterLast($table, '.');

            if ($normalizedPreferredTables !== [] && ! in_array($table, $normalizedPreferredTables, true)) {
                continue;
            }

            $columns = array_map(
                fn (string $column): string => trim($column, " \t\n\r\0\x0B`\""),
                explode(',', (string) $match['columns']),
            );

            foreach ($this->parseSqlTuples((string) $match['values']) as $tuple) {
                $values = $this->splitSqlTupleValues($tuple);

                if (count($columns) !== count($values)) {
                    continue;
                }

                $rows[] = array_combine($columns, array_map(
                    fn (string $value): mixed => $this->normalizeSqlValue($value),
                    $values,
                ));
            }
        }

        return $rows;
    }

    /** @return array<int, string> */
    private function parseSqlTuples(string $valuesChunk): array
    {
        $tuples = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $length = strlen($valuesChunk);

        for ($i = 0; $i < $length; $i++) {
            $char = $valuesChunk[$i];
            $previous = $i > 0 ? $valuesChunk[$i - 1] : null;
            $next = $i + 1 < $length ? $valuesChunk[$i + 1] : null;

            if ($char === "'" && $previous !== '\\') {
                if ($inString && $next === "'") {
                    $current .= "''";
                    $i++;
                    continue;
                }

                $inString = ! $inString;
                $current .= $char;
                continue;
            }

            if (! $inString) {
                if ($char === '(') {
                    if ($depth === 0) {
                        $current = '';
                    } else {
                        $current .= $char;
                    }

                    $depth++;
                    continue;
                }

                if ($char === ')') {
                    $depth--;

                    if ($depth === 0) {
                        $tuples[] = $current;
                        $current = '';
                        continue;
                    }
                }
            }

            if ($depth > 0) {
                $current .= $char;
            }
        }

        return $tuples;
    }

    /** @return array<int, string> */
    private function splitSqlTupleValues(string $tuple): array
    {
        $parts = [];
        $current = '';
        $inString = false;
        $length = strlen($tuple);

        for ($i = 0; $i < $length; $i++) {
            $char = $tuple[$i];
            $previous = $i > 0 ? $tuple[$i - 1] : null;
            $next = $i + 1 < $length ? $tuple[$i + 1] : null;

            if ($char === "'" && $previous !== '\\') {
                if ($inString && $next === "'") {
                    $current .= "''";
                    $i++;
                    continue;
                }

                $inString = ! $inString;
                $current .= $char;
                continue;
            }

            if ($char === ',' && ! $inString) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $parts[] = trim($current);

        return $parts;
    }

    private function normalizeSqlValue(string $value): mixed
    {
        $value = trim($value);

        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        if (preg_match('/^[-+]?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (preg_match('/^[-+]?\d+\.\d+$/', $value) === 1) {
            return (float) $value;
        }

        if (Str::startsWith($value, "'") && Str::endsWith($value, "'")) {
            $value = substr($value, 1, -1);
            $value = str_replace("''", "'", $value);

            return stripcslashes($value);
        }

        return $value;
    }
}

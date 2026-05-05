<?php

namespace App\Imports;

use Illuminate\Support\Facades\Storage;

class LegacySourceReader
{
    /** @return array<int, array<string, mixed>> */
    public function rows(?string $path): array
    {
        if (blank($path)) {
            return [];
        }

        $contents = is_file($path)
            ? file_get_contents($path)
            : Storage::disk('local')->get($path);

        $decoded = json_decode((string) $contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values($decoded);
    }
}

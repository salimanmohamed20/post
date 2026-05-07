<?php

namespace App\Services;

use App\Imports\LegacySourceReader;
use App\Jobs\ImportArticlesJob;
use App\Jobs\ImportCategoriesJob;
use App\Models\ImportLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportRetryService
{
    public function __construct(private readonly LegacySourceReader $reader) {}

    /** @return array{retried:int,source:string,path:string}|array{retried:int,source:string,path:null} */
    public function retry(ImportLog $log): array
    {
        $failed = collect($log->failed_rows ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->values();

        if ($failed->isEmpty()) {
            return ['retried' => 0, 'source' => $log->source, 'path' => null];
        }

        $isCategories = $log->source === 'categories';
        $sourcePath = $isCategories ? config('legacy_import.categories') : config('legacy_import.articles');
        $tables = $isCategories ? config('legacy_import.category_tables', []) : config('legacy_import.article_tables', []);

        $allRows = $this->reader->rows($sourcePath, $tables);
        if ($allRows === []) {
            return ['retried' => 0, 'source' => $log->source, 'path' => null];
        }

        $targets = [];
        foreach ($failed as $item) {
            $slug = trim((string) ($item['old_slug'] ?? ''));
            $legacy = $item['old_source_id'] ?? null;

            $targets[] = [
                'slug' => $slug,
                'legacy' => $legacy !== null ? (string) $legacy : null,
            ];
        }

        $subset = collect($allRows)->filter(function (array $row) use ($targets): bool {
            $legacy = $this->legacyId($row);
            $slug = $this->slug($row);

            foreach ($targets as $target) {
                if ($target['legacy'] !== null && $legacy !== null && $target['legacy'] === $legacy) {
                    return true;
                }

                if ($target['slug'] !== '' && $slug !== '' && $target['slug'] === $slug) {
                    return true;
                }
            }

            return false;
        })->values()->all();

        if ($subset === []) {
            return ['retried' => 0, 'source' => $log->source, 'path' => null];
        }

        $tempPath = 'imports/retry/' . $log->source . '-' . now()->format('Ymd-His') . '-' . Str::random(8) . '.json';
        Storage::disk('local')->put($tempPath, json_encode($subset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($isCategories) {
            app()->call([new ImportCategoriesJob($tempPath), 'handle']);
        } else {
            app()->call([new ImportArticlesJob($tempPath), 'handle']);
        }

        return ['retried' => count($subset), 'source' => $log->source, 'path' => $tempPath];
    }

    /** @param array<string, mixed> $row */
    private function legacyId(array $row): ?string
    {
        $legacy = $row['legacy_id']
            ?? $row['old_source_id']
            ?? $row['old_id']
            ?? $row['term_id']
            ?? $row['ID']
            ?? $row['id']
            ?? null;

        return $legacy !== null ? (string) $legacy : null;
    }

    /** @param array<string, mixed> $row */
    private function slug(array $row): string
    {
        return trim((string) ($row['slug'] ?? $row['post_name'] ?? $row['name'] ?? ''));
    }
}


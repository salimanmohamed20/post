<?php

namespace App\Jobs;

use App\Imports\LegacySourceReader;
use App\Models\Category;
use App\Models\ImportLog;
use App\Services\CacheInvalidationService;
use App\Services\SlugService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportCategoriesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $sourcePath = null) {}

    public function handle(LegacySourceReader $reader, SlugService $slugs, CacheInvalidationService $cache): void
    {
        $imported = 0;
        $failed = [];
        $seen = [];

        foreach ($reader->rows($this->sourcePath ?? config('legacy_import.categories'), config('legacy_import.category_tables', [])) as $row) {
            $legacyId = $this->legacyId($row);
            $slug = (string) ($row['slug'] ?? '');

            if (isset($seen[$slug])) {
                $failed[] = $this->failure($legacyId, $slug, 'duplicate_slug_in_import_file');
                continue;
            }

            $seen[$slug] = true;
            $existing = Category::query()
                ->when(
                    filled($legacyId),
                    fn ($query) => $query->where('legacy_source_id', (string) $legacyId)->orWhere('slug', $slug),
                    fn ($query) => $query->where('slug', $slug),
                )
                ->first();
            $reason = $slugs->validateImportedSlug($slug, Category::class, $existing?->id);

            if ($reason !== null) {
                $failed[] = $this->failure($legacyId, $slug, $reason);
                continue;
            }

            Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => (string) ($row['name'] ?? $row['title'] ?? $slug),
                    'legacy_source_id' => filled($legacyId) ? (string) $legacyId : null,
                ],
            );

            $imported++;
        }

        ImportLog::query()->create([
            'source' => 'categories',
            'imported_count' => $imported,
            'skipped_count' => count($failed),
            'failed_rows' => $failed,
        ]);

        $cache->flushPublicContent();
    }

    /** @return array{old_source_id:mixed,old_slug:string,reason:string} */
    private function failure(mixed $legacyId, string $slug, string $reason): array
    {
        return ['old_source_id' => $legacyId, 'old_slug' => $slug, 'reason' => $reason];
    }

    /** @param array<string, mixed> $row */
    private function legacyId(array $row): mixed
    {
        return $row['legacy_id'] ?? $row['old_source_id'] ?? $row['old_id'] ?? $row['id'] ?? null;
    }
}

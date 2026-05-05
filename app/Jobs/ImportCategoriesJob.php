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

        foreach ($reader->rows($this->sourcePath ?? config('legacy_import.categories')) as $row) {
            $legacyId = $row['id'] ?? $row['old_id'] ?? null;
            $slug = (string) ($row['slug'] ?? '');

            if (isset($seen[$slug])) {
                $failed[] = $this->failure($legacyId, $slug, 'duplicate_slug_in_import_file');
                continue;
            }

            $seen[$slug] = true;
            $existing = Category::query()->where('slug', $slug)->first();
            $reason = $slugs->validateImportedSlug($slug, Category::class, $existing?->id);

            if ($reason !== null) {
                $failed[] = $this->failure($legacyId, $slug, $reason);
                continue;
            }

            Category::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => (string) ($row['name'] ?? $row['title'] ?? $slug)],
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
}

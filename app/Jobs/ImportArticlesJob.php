<?php

namespace App\Jobs;

use App\Imports\LegacySourceReader;
use App\Models\Article;
use App\Models\Category;
use App\Models\ImportLog;
use App\Services\CacheInvalidationService;
use App\Services\ImageService;
use App\Services\SlugService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class ImportArticlesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $sourcePath = null) {}

    public function handle(
        LegacySourceReader $reader,
        SlugService $slugs,
        ImageService $images,
        CacheInvalidationService $cache,
    ): void {
        $imported = 0;
        $failed = [];
        $seen = [];

        foreach ($reader->rows($this->sourcePath ?? config('legacy_import.articles')) as $row) {
            $legacyId = $row['id'] ?? $row['old_id'] ?? null;
            $slug = (string) ($row['slug'] ?? '');
            $categorySlug = (string) ($row['category_slug'] ?? '');

            if (isset($seen[$slug])) {
                $failed[] = $this->failure($legacyId, $slug, 'duplicate_slug_in_import_file');
                continue;
            }

            $seen[$slug] = true;
            $category = Category::query()->where('slug', $categorySlug)->first();

            if (! $category) {
                $failed[] = $this->failure($legacyId, $slug, 'missing_category_mapping');
                continue;
            }

            $existing = Article::query()->where('slug', $slug)->first();
            $reason = $slugs->validateImportedSlug($slug, Article::class, $existing?->id);

            if ($reason !== null) {
                $failed[] = $this->failure($legacyId, $slug, $reason);
                continue;
            }

            $article = Article::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => (string) ($row['title'] ?? $slug),
                    'body' => (string) ($row['body'] ?? ''),
                    'excerpt' => $row['excerpt'] ?? null,
                    'category_id' => $category->id,
                    'published_at' => isset($row['published_at']) ? Carbon::parse($row['published_at']) : null,
                    'is_published' => (bool) ($row['is_published'] ?? $row['published'] ?? false),
                ],
            );

            try {
                $images->attachArticleImages($article, (array) ($row['images'] ?? []), true);
            } catch (\Throwable $exception) {
                $failed[] = $this->failure($legacyId, $slug, 'failed_image_import: ' . $exception->getMessage());
            }

            $imported++;
        }

        ImportLog::query()->create([
            'source' => 'articles',
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

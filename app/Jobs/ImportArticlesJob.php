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

        foreach ($reader->rows($this->sourcePath ?? config('legacy_import.articles'), config('legacy_import.article_tables', [])) as $row) {
            $legacyId = $this->legacyId($row);
            $slug = (string) ($row['slug'] ?? '');

            if (isset($seen[$slug])) {
                $failed[] = $this->failure($legacyId, $slug, 'duplicate_slug_in_import_file');
                continue;
            }

            $seen[$slug] = true;
            $category = $this->resolveCategory($row);

            if (! $category) {
                $failed[] = $this->failure($legacyId, $slug, 'missing_category_mapping');
                continue;
            }

            $existing = Article::query()
                ->when(
                    filled($legacyId),
                    fn ($query) => $query->where('legacy_source_id', (string) $legacyId)->orWhere('slug', $slug),
                    fn ($query) => $query->where('slug', $slug),
                )
                ->first();
            $reason = $slugs->validateImportedSlug($slug, Article::class, $existing?->id);

            if ($reason !== null) {
                $failed[] = $this->failure($legacyId, $slug, $reason);
                continue;
            }

            $article = Article::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => (string) ($row['title'] ?? $slug),
                    'legacy_source_id' => filled($legacyId) ? (string) $legacyId : null,
                    'body' => (string) ($row['body'] ?? ''),
                    'excerpt' => $row['excerpt'] ?? null,
                    'category_id' => $category->id,
                    'published_at' => isset($row['published_at']) ? Carbon::parse($row['published_at']) : null,
                    'is_published' => (bool) ($row['is_published'] ?? $row['published'] ?? false),
                ],
            );

            try {
                $images->attachArticleImages($article, $this->imagePaths($row), true);
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

    /** @param array<string, mixed> $row */
    private function resolveCategory(array $row): ?Category
    {
        $nestedCategory = is_array($row['category'] ?? null) ? $row['category'] : [];

        $categorySlug = $row['category_slug']
            ?? $nestedCategory['slug']
            ?? null;

        if (filled($categorySlug)) {
            $category = Category::query()->where('slug', (string) $categorySlug)->first();

            if ($category) {
                return $category;
            }
        }

        $categoryLegacyId = $row['category_old_id']
            ?? $row['category_legacy_id']
            ?? $row['category_id']
            ?? $nestedCategory['legacy_id']
            ?? $nestedCategory['old_source_id']
            ?? $nestedCategory['old_id']
            ?? $nestedCategory['id']
            ?? null;

        if (filled($categoryLegacyId)) {
            return Category::query()
                ->where('legacy_source_id', (string) $categoryLegacyId)
                ->first();
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function imagePaths(array $row): array
    {
        $images = $row['images'] ?? $row['image_urls'] ?? $row['image_paths'] ?? [];

        if (is_string($images)) {
            $images = array_filter(array_map('trim', explode(',', $images)));
        }

        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $image): ?string {
            if (is_string($image) && filled($image)) {
                return $image;
            }

            if (is_array($image)) {
                $path = $image['url'] ?? $image['path'] ?? $image['src'] ?? null;

                return filled($path) ? (string) $path : null;
            }

            return null;
        }, $images)));
    }

    /** @param array<string, mixed> $row */
    private function legacyId(array $row): mixed
    {
        return $row['legacy_id'] ?? $row['old_source_id'] ?? $row['old_id'] ?? $row['id'] ?? null;
    }
}

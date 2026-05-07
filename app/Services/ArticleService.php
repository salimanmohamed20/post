<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ArticleService
{
    public function __construct(private readonly CacheInvalidationService $cache) {}

    public function latest(int $limit = 6): Collection
    {
        $ids = Cache::remember(
            $this->cache->key('latest-articles', [$limit]),
            now()->addMinutes(30),
            fn () => Article::published()
                ->latest('published_at')
                ->limit($limit)
                ->pluck('id')
                ->all(),
        );

        if (! is_array($ids) || $ids === []) {
            return collect();
        }

        $articles = Article::query()
            ->with(['category', 'media'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn (mixed $id) => $articles->get((int) $id))
            ->filter()
            ->values();
    }

    public function paginate(int $perPage = 9): LengthAwarePaginator
    {
        return Article::published()
            ->with(['category', 'media'])
            ->latest('published_at')
            ->paginate($perPage);
    }

    public function findPublishedBySlug(string $slug): Article
    {
        $slugCandidates = $this->slugCandidates($slug);
        $cacheKey = $this->cache->key('article', [implode('|', $slugCandidates)]);

        $articleId = Cache::remember(
            $cacheKey,
            now()->addMinutes(30),
            fn () => Article::published()
                ->whereIn('slug', $slugCandidates)
                ->value('id'),
        );

        if (! is_numeric($articleId)) {
            abort(404);
        }

        $article = Article::published()
            ->with(['category', 'media'])
            ->whereKey((int) $articleId)
            ->firstOrFail();

        $normalizedBody = $this->normalizeBrokenImageUrls((string) $article->body);
        if ($normalizedBody !== (string) $article->body) {
            $article->body = $normalizedBody;
            $article->saveQuietly();
        }

        return $article;
    }

    /** @return array<int, string> */
    private function slugCandidates(string $slug): array
    {
        $trimmed = trim($slug);
        if ($trimmed === '') {
            return [''];
        }

        $decoded = rawurldecode($trimmed);
        $encodedFromDecoded = rawurlencode($decoded);
        $encodedDirect = rawurlencode($trimmed);
        $decodedDirect = urldecode($trimmed);

        return array_values(array_unique(array_filter([
            $trimmed,
            $decoded,
            $decodedDirect,
            $encodedFromDecoded,
            $encodedDirect,
        ], fn (string $value): bool => $value !== '')));
    }

    public function paginateByCategory(Category $category, int $perPage = 9): LengthAwarePaginator
    {
        return Article::published()
            ->with(['category', 'media'])
            ->whereBelongsTo($category)
            ->latest('published_at')
            ->paginate($perPage);
    }

    private function normalizeBrokenImageUrls(string $html): string
    {
        if ($html === '' || ! str_contains($html, '<img')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'][^>]*>)/i',
            function (array $matches): string {
                $prefix = $matches[1] ?? '';
                $src = trim((string) ($matches[2] ?? ''));
                $suffix = $matches[3] ?? '';

                if ($src === '') {
                    return $prefix . $src . $suffix;
                }

                $fixed = $this->fixDuplicatedAbsoluteUrl($src);

                return $prefix . $fixed . $suffix;
            },
            $html,
        );
    }

    private function fixDuplicatedAbsoluteUrl(string $value): string
    {
        if (! str_contains($value, 'http://') && ! str_contains($value, 'https://')) {
            return $value;
        }

        $firstSchemePos = stripos($value, '://');
        if ($firstSchemePos !== false) {
            $secondHttp = stripos($value, 'http://', $firstSchemePos + 3);
            $secondHttps = stripos($value, 'https://', $firstSchemePos + 3);
            $candidates = array_filter([$secondHttp, $secondHttps], fn ($v) => $v !== false);

            if ($candidates !== []) {
                $start = min($candidates);
                if (is_int($start) && $start > 0) {
                    return substr($value, $start);
                }
            }
        }

        if (preg_match('/https?:\/\/.+?(https?:\/\/.+)$/i', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }
}

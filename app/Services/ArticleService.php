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
        return Cache::remember(
            $this->cache->key('latest-articles', [$limit]),
            now()->addMinutes(30),
            fn () => Article::published()
                ->with(['category', 'media'])
                ->latest('published_at')
                ->limit($limit)
                ->get(),
        );
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
        return Cache::remember(
            $this->cache->key('article', [$slug]),
            now()->addMinutes(30),
            fn () => Article::published()
                ->with(['category', 'media'])
                ->where('slug', $slug)
                ->firstOrFail(),
        );
    }

    public function paginateByCategory(Category $category, int $perPage = 9): LengthAwarePaginator
    {
        return Article::published()
            ->with(['category', 'media'])
            ->whereBelongsTo($category)
            ->latest('published_at')
            ->paginate($perPage);
    }
}

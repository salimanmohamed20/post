<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SeoService
{
    public function __construct(private readonly CacheInvalidationService $cache) {}

    /** @return array{title:string,description:string,canonical:string} */
    public function forArticle(Article $article): array
    {
        return [
            'title' => $article->title,
            'description' => $article->excerpt ?: Str::limit(strip_tags($article->body), 155),
            'canonical' => route('articles.show', $article->slug),
        ];
    }

    /** @return array{title:string,description:string,canonical:string} */
    public function forCategory(Category $category): array
    {
        return [
            'title' => $category->name,
            'description' => "Articles in {$category->name}",
            'canonical' => route('categories.show', $category->slug),
        ];
    }

    /** @return array{title:string,description:string,canonical:string} */
    public function forPage(Page $page): array
    {
        return [
            'title' => $page->meta_title ?: $page->title,
            'description' => $page->meta_description ?: Str::limit(strip_tags($page->body), 155),
            'canonical' => route('pages.show', $page->slug),
        ];
    }

    /** @return array<int, array{loc:string,lastmod:string}> */
    public function sitemap(): array
    {
        return Cache::remember($this->cache->key('sitemap'), now()->addHour(), function (): array {
            $urls = [
                ['loc' => route('home'), 'lastmod' => now()->toAtomString()],
                ['loc' => route('articles.index'), 'lastmod' => now()->toAtomString()],
            ];

            Article::published()->each(fn (Article $article) => $urls[] = [
                'loc' => route('articles.show', $article->slug),
                'lastmod' => $article->updated_at->toAtomString(),
            ]);

            Category::query()->each(fn (Category $category) => $urls[] = [
                'loc' => route('categories.show', $category->slug),
                'lastmod' => $category->updated_at->toAtomString(),
            ]);

            Page::query()->each(fn (Page $page) => $urls[] = [
                'loc' => route('pages.show', $page->slug),
                'lastmod' => $page->updated_at->toAtomString(),
            ]);

            return $urls;
        });
    }
}

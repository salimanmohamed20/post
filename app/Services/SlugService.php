<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SlugService
{
    /** @param class-string<Model> $modelClass */
    public function generateUnique(string $source, string $modelClass, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: Str::random(8);
        $slug = $base;
        $counter = 2;

        while ($this->modelHasSlug($modelClass, $slug, $ignoreId)) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function validateImportedSlug(string $slug, ?string $targetModel = null, ?int $ignoreId = null): ?string
    {
        if ($slug === '' || str_contains($slug, '/')) {
            return 'invalid_slug';
        }

        foreach ([Article::class, Category::class, Page::class] as $modelClass) {
            $ignore = $modelClass === $targetModel ? $ignoreId : null;

            if ($this->modelHasSlug($modelClass, $slug, $ignore)) {
                return 'duplicate_slug';
            }
        }

        return null;
    }

    /** @param class-string<Model> $modelClass */
    private function modelHasSlug(string $modelClass, string $slug, ?int $ignoreId = null): bool
    {
        $query = $modelClass::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}

<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ImageService
{
    /** @param array<int, string> $pathsOrUrls */
    public function attachArticleImages(Article $article, array $pathsOrUrls, bool $clearExisting = false): void
    {
        if ($clearExisting) {
            $article->clearMediaCollection('images');
        }

        foreach (array_values($pathsOrUrls) as $index => $pathOrUrl) {
            $media = $this->attachOne($article, $pathOrUrl);
            $media->order_column = $index + 1;
            $media->save();
        }
    }

    private function attachOne(Article $article, string $pathOrUrl): Media
    {
        if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            return $article
                ->addMediaFromUrl($pathOrUrl)
                ->toMediaCollection('images');
        }

        if (Storage::disk('public')->exists($pathOrUrl)) {
            return $article
                ->addMedia(Storage::disk('public')->path($pathOrUrl))
                ->preservingOriginal()
                ->toMediaCollection('images');
        }

        return $article
            ->addMedia($pathOrUrl)
            ->preservingOriginal()
            ->toMediaCollection('images');
    }
}

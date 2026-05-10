<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use App\Services\InlineArticleImageService;
use App\Services\SlugService;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    public function getTitle(): string
    {
        return 'تعديل المقال';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $body = (string) ($data['body'] ?? '');

        if ($body !== '') {
            $data['body'] = app(InlineArticleImageService::class)
                ->replaceWithStoredMediaUrls($this->record, $body);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['slug'] = $data['slug'] ?: app(SlugService::class)->generateUnique($data['title'], Article::class, $this->record->id);

        return $data;
    }
}
<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    public function getTitle(): string
    {
        return 'المقالات';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->badge(fn (): int => ArticleResource::getModel()::query()->count()),
            'published' => Tab::make('منشورة')
                ->badge(fn (): int => ArticleResource::getModel()::published()->count())
                ->query(fn (Builder $query): Builder => $query->published()),
            'scheduled' => Tab::make('مجدولة')
                ->badge(fn (): int => ArticleResource::getModel()::query()
                    ->where('is_published', true)
                    ->where('published_at', '>', now())
                    ->count())
                ->query(fn (Builder $query): Builder => $query
                    ->where('is_published', true)
                    ->where('published_at', '>', now())),
            'drafts' => Tab::make('مسودات')
                ->badge(fn (): int => ArticleResource::getModel()::query()
                    ->where(function (Builder $query): void {
                        $query->where('is_published', false)
                            ->orWhereNull('published_at');
                    })
                    ->count())
                ->query(fn (Builder $query): Builder => $query
                    ->where(function (Builder $query): void {
                        $query->where('is_published', false)
                            ->orWhereNull('published_at');
                    })),
        ];
    }
}

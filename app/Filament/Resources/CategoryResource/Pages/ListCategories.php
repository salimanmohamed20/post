<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    public function getTitle(): string
    {
        return 'التصنيفات';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->badge(fn (): int => CategoryResource::getModel()::query()->count()),
            'with_articles' => Tab::make('بها مقالات')
                ->badge(fn (): int => CategoryResource::getModel()::query()->has('articles')->count())
                ->query(fn (Builder $query): Builder => $query->has('articles')),
            'empty' => Tab::make('فارغة')
                ->badge(fn (): int => CategoryResource::getModel()::query()->doesntHave('articles')->count())
                ->query(fn (Builder $query): Builder => $query->doesntHave('articles')),
        ];
    }
}

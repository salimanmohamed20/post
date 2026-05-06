<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        return 'الصفحات';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->badge(fn (): int => PageResource::getModel()::query()->count()),
            'seo_ready' => Tab::make('مكتملة SEO')
                ->badge(fn (): int => PageResource::getModel()::query()
                    ->whereNotNull('meta_title')
                    ->whereNotNull('meta_description')
                    ->count())
                ->query(fn (Builder $query): Builder => $query
                    ->whereNotNull('meta_title')
                    ->whereNotNull('meta_description')),
            'needs_seo' => Tab::make('تحتاج SEO')
                ->badge(fn (): int => PageResource::getModel()::query()
                    ->where(function (Builder $query): void {
                        $query->whereNull('meta_title')
                            ->orWhereNull('meta_description');
                    })
                    ->count())
                ->query(fn (Builder $query): Builder => $query
                    ->where(function (Builder $query): void {
                        $query->whereNull('meta_title')
                            ->orWhereNull('meta_description');
                    })),
        ];
    }
}

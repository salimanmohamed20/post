<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\Category;
use App\Models\Page;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class ContentStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('المقالات', Article::query()->count())
                ->description('إجمالي المقالات')
                ->descriptionIcon('heroicon-m-newspaper')
                ->color('primary')
                ->chart($this->dailyCounts(Article::class)),
            Stat::make('المقالات المنشورة', Article::published()->count())
                ->description('ظاهرة في الموقع')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('التصنيفات', Category::query()->count())
                ->description('تصنيفات المحتوى')
                ->descriptionIcon('heroicon-m-folder')
                ->color('info'),
            Stat::make('الصفحات والمستخدمون', Page::query()->count() . ' / ' . User::query()->count())
                ->description('صفحات ثابتة / مستخدمون')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }

    /** @param class-string<Model> $model */
    private function dailyCounts(string $model): array
    {
        return collect(range(6, 0))
            ->map(fn (int $daysAgo): int => $model::query()
                ->whereDate('created_at', now()->subDays($daysAgo)->toDateString())
                ->count())
            ->all();
    }
}

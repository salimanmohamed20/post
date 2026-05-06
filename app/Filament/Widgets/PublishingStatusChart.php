<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class PublishingStatusChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'حالة النشر';

    protected string $color = 'primary';

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $published = Article::published()->count();
        $scheduled = Article::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '>', now())
            ->count();
        $drafts = Article::query()
            ->where(function (Builder $query): void {
                $query->where('is_published', false)
                    ->orWhereNull('published_at');
            })
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'المقالات',
                    'data' => [$published, $scheduled, $drafts],
                    'backgroundColor' => ['#10b981', '#f59e0b', '#64748b'],
                ],
            ],
            'labels' => ['منشورة', 'مجدولة', 'مسودات'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

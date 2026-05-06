<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Widgets\ChartWidget;

class ArticlesByMonthChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'المقالات المنشورة شهرياً';

    protected string $color = 'success';

    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $months = collect(range(5, 0))->map(fn (int $monthsAgo) => now()->subMonths($monthsAgo));

        return [
            'datasets' => [
                [
                    'label' => 'مقالات منشورة',
                    'data' => $months
                        ->map(fn ($month): int => Article::published()
                            ->whereBetween('published_at', [
                                $month->copy()->startOfMonth(),
                                $month->copy()->endOfMonth(),
                            ])
                            ->count())
                        ->all(),
                ],
            ],
            'labels' => $months->map(fn ($month): string => $month->translatedFormat('M Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

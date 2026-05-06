<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use Filament\Widgets\ChartWidget;

class CategoryArticlesChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'أكثر التصنيفات استخداماً';

    protected string $color = 'info';

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $categories = Category::query()
            ->withCount('articles')
            ->orderByDesc('articles_count')
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'عدد المقالات',
                    'data' => $categories->pluck('articles_count')->all(),
                    'backgroundColor' => '#0f766e',
                ],
            ],
            'labels' => $categories->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

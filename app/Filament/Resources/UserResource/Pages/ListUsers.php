<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'المستخدمون';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->badge(fn (): int => UserResource::getModel()::query()->count()),
            'admins' => Tab::make('المديرون')
                ->badge(fn (): int => UserResource::getModel()::query()->where('role', 'admin')->count())
                ->query(fn (Builder $query): Builder => $query->where('role', 'admin')),
            'editors' => Tab::make('المحررون')
                ->badge(fn (): int => UserResource::getModel()::query()->where('role', 'editor')->count())
                ->query(fn (Builder $query): Builder => $query->where('role', 'editor')),
        ];
    }
}

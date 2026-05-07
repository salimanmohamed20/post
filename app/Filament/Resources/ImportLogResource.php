<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImportLogResource\Pages\ListImportLogs;
use App\Models\ImportLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ImportLogResource extends Resource
{
    protected static ?string $model = ImportLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'سجل الاستيراد';

    protected static ?string $pluralModelLabel = 'سجل الاستيراد';

    protected static ?string $modelLabel = 'سجل استيراد';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->since()
                    ->sortable(),
                TextColumn::make('source')
                    ->label('المصدر')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'articles' ? 'primary' : 'info'),
                TextColumn::make('imported_count')
                    ->label('تم استيراده')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('skipped_count')
                    ->label('تم تخطيه')
                    ->numeric()
                    ->sortable()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('failed_rows_count')
                    ->label('عدد الأخطاء')
                    ->state(fn (ImportLog $record): int => is_array($record->failed_rows) ? count($record->failed_rows) : 0)
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('فلترة بالتاريخ')
                    ->form([
                        DatePicker::make('from')->label('من تاريخ'),
                        DatePicker::make('until')->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('created_at', '>=', $data['from']),
                            )
                            ->when(
                                filled($data['until'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('created_at', '<=', $data['until']),
                            );
                    }),
                Filter::make('errors_only')
                    ->label('عرض الأخطاء فقط')
                    ->query(fn (Builder $query): Builder => $query->where('skipped_count', '>', 0)),
            ])
            ->actions([
                Action::make('view_errors')
                    ->label('عرض الأخطاء')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->visible(fn (ImportLog $record): bool => (int) $record->skipped_count > 0)
                    ->modalHeading('تفاصيل الأخطاء')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalWidth('4xl')
                    ->modalContent(fn (ImportLog $record) => view('filament.import-logs.errors', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportLogs::route('/'),
        ];
    }
}

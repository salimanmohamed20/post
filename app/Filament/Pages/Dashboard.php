<?php

namespace App\Filament\Pages;

use App\Jobs\ImportArticlesJob;
use App\Jobs\ImportCategoriesJob;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Bus;

class Dashboard extends BaseDashboard
{
    /** @return array<int, string> */
    private static function importAcceptedFileTypes(): array
    {
        return [
            'application/json',
            'text/json',
            'application/sql',
            'application/x-sql',
            'text/sql',
            'text/x-sql',
            'text/plain',
            'application/octet-stream',
        ];
    }

    protected static ?string $title = 'لوحة التحكم';

    protected static ?string $navigationLabel = 'لوحة التحكم';

    protected static ?int $navigationSort = -2;

    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importAll')
                ->label('استيراد شامل')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->schema([
                    FileUpload::make('uploaded_file')
                        ->label('رفع ملف الاستيراد')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(self::importAcceptedFileTypes())
                        ->helperText('ارفع ملف SQL أو JSON يحتوي جداول التصنيفات والمقالات')
                        ->required(),
                ])
                ->modalHeading('استيراد التصنيفات والمقالات من ملف واحد')
                ->modalSubmitActionLabel('ابدأ الاستيراد')
                ->action(function (array $data): void {
                    $sourcePath = $this->resolveUploadedFilePath($data, 'uploaded_file');

                    if (! $sourcePath) {
                        Notification::make()
                            ->title('لم يتم رفع ملف صالح')
                            ->danger()
                            ->send();

                        return;
                    }

                    Bus::chain([
                        new ImportCategoriesJob($sourcePath),
                        new ImportArticlesJob($sourcePath),
                    ])->dispatch();

                    Notification::make()
                        ->title('بدأ الاستيراد')
                        ->body('سيتم استيراد التصنيفات أولا ثم المقالات في الخلفية.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /** @param array<string, mixed> $data */
    private function resolveUploadedFilePath(array $data, string $uploadField): ?string
    {
        $uploadedPath = $data[$uploadField] ?? null;

        if (is_string($uploadedPath) && filled($uploadedPath)) {
            return $uploadedPath;
        }

        return null;
    }
}

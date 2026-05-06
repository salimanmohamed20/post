<?php

namespace App\Filament\Pages;

use App\Jobs\ImportArticlesJob;
use App\Jobs\ImportCategoriesJob;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

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
            Action::make('importCategories')
                ->label('استيراد التصنيفات')
                ->icon('heroicon-o-folder-arrow-down')
                ->color('info')
                ->schema([
                    FileUpload::make('uploaded_file')
                        ->label('رفع ملف التصنيفات')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(self::importAcceptedFileTypes())
                        ->helperText('JSON أو SQL')
                        ->required(),
                    ViewField::make('upload_progress_categories')
                        ->view('filament.components.upload-progress'),
                    Toggle::make('run_now')
                        ->label('تنفيذ فوري')
                        ->default(true),
                ])
                ->modalHeading('استيراد التصنيفات')
                ->modalSubmitActionLabel('ابدأ الاستيراد')
                ->action(function (array $data): void {
                    $job = new ImportCategoriesJob($this->resolveUploadedFilePath($data, 'uploaded_file'));

                    if ($data['run_now'] ?? false) {
                        app()->call([$job, 'handle']);

                        Notification::make()
                            ->title('تم استيراد التصنيفات')
                            ->success()
                            ->send();

                        return;
                    }

                    dispatch($job);

                    Notification::make()
                        ->title('تم إرسال استيراد التصنيفات للطابور')
                        ->success()
                        ->send();
                }),
            Action::make('importArticles')
                ->label('استيراد المقالات')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->schema([
                    FileUpload::make('uploaded_file')
                        ->label('رفع ملف المقالات')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(self::importAcceptedFileTypes())
                        ->helperText('JSON أو SQL')
                        ->required(),
                    ViewField::make('upload_progress_articles')
                        ->view('filament.components.upload-progress'),
                    Toggle::make('run_now')
                        ->label('تنفيذ فوري')
                        ->default(true),
                ])
                ->modalHeading('استيراد المقالات')
                ->modalSubmitActionLabel('ابدأ الاستيراد')
                ->action(function (array $data): void {
                    $job = new ImportArticlesJob($this->resolveUploadedFilePath($data, 'uploaded_file'));

                    if ($data['run_now'] ?? false) {
                        app()->call([$job, 'handle']);

                        Notification::make()
                            ->title('تم استيراد المقالات')
                            ->success()
                            ->send();

                        return;
                    }

                    dispatch($job);

                    Notification::make()
                        ->title('تم إرسال استيراد المقالات للطابور')
                        ->success()
                        ->send();
                }),
            Action::make('importAll')
                ->label('استيراد الكل')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->schema([
                    FileUpload::make('categories_uploaded_file')
                        ->label('رفع ملف التصنيفات')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(self::importAcceptedFileTypes())
                        ->helperText('JSON أو SQL')
                        ->required(),
                    ViewField::make('upload_progress_all_categories')
                        ->view('filament.components.upload-progress'),
                    FileUpload::make('articles_uploaded_file')
                        ->label('رفع ملف المقالات')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(self::importAcceptedFileTypes())
                        ->helperText('JSON أو SQL')
                        ->required(),
                    ViewField::make('upload_progress_all_articles')
                        ->view('filament.components.upload-progress'),
                    Toggle::make('run_now')
                        ->label('تنفيذ فوري')
                        ->default(true),
                ])
                ->modalHeading('استيراد التصنيفات والمقالات')
                ->modalSubmitActionLabel('ابدأ الاستيراد')
                ->action(function (array $data): void {
                    $categoriesPath = $this->resolveUploadedFilePath($data, 'categories_uploaded_file');
                    $articlesPath = $this->resolveUploadedFilePath($data, 'articles_uploaded_file');

                    if ($data['run_now'] ?? false) {
                        app()->call([new ImportCategoriesJob($categoriesPath), 'handle']);
                        app()->call([new ImportArticlesJob($articlesPath), 'handle']);

                        Notification::make()
                            ->title('تم اكتمال الاستيراد')
                            ->success()
                            ->send();

                        return;
                    }

                    dispatch(new ImportCategoriesJob($categoriesPath));
                    dispatch(new ImportArticlesJob($articlesPath));

                    Notification::make()
                        ->title('تم إرسال مهام الاستيراد للطابور')
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

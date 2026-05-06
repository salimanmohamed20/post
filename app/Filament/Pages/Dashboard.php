<?php

namespace App\Filament\Pages;

use App\Jobs\ImportArticlesJob;
use App\Jobs\ImportCategoriesJob;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
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
                    TextInput::make('path')
                        ->label('ملف التصنيفات')
                        ->placeholder('مثال: imports/categories.json')
                        ->helperText('يمكنك إدخال مسار كامل، أو مسار داخل storage/app/private')
                        ->required(),
                    Toggle::make('run_now')
                        ->label('تنفيذ فوري')
                        ->default(true)
                        ->helperText('أوقفه إذا كنت تريد إرسال المهمة إلى queue'),
                ])
                ->modalHeading('استيراد التصنيفات')
                ->modalDescription('سيتم الحفاظ على الـ slug القديم كما هو، وأي تعارض سيتم تسجيله في سجل الاستيراد.')
                ->modalSubmitActionLabel('ابدأ الاستيراد')
                ->action(function (array $data): void {
                    $job = new ImportCategoriesJob($data['path']);

                    if ($data['run_now'] ?? false) {
                        app()->call([$job, 'handle']);

                        Notification::make()
                            ->title('تم استيراد التصنيفات')
                            ->body('اكتمل التنفيذ الفوري، ويمكنك مراجعة النتائج الآن.')
                            ->success()
                            ->send();

                        return;
                    }

                    dispatch($job);

                    Notification::make()
                        ->title('تم إرسال استيراد التصنيفات')
                        ->body('المهمة أُرسلت إلى الطابور. شغّل queue:work إذا لم يكن يعمل بالفعل.')
                        ->success()
                        ->send();
                }),
            Action::make('importArticles')
                ->label('استيراد المقالات')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->schema([
                    TextInput::make('path')
                        ->label('ملف المقالات')
                        ->placeholder('مثال: imports/articles.json')
                        ->helperText('الصور تُقرأ من images أو image_urls أو image_paths')
                        ->required(),
                    Toggle::make('run_now')
                        ->label('تنفيذ فوري')
                        ->default(true)
                        ->helperText('أوقفه إذا كنت تريد إرسال المهمة إلى queue'),
                ])
                ->modalHeading('استيراد المقالات')
                ->modalDescription('تأكد من استيراد التصنيفات أولًا حتى ينجح الربط بين المقالات والتصنيفات.')
                ->modalSubmitActionLabel('ابدأ الاستيراد')
                ->action(function (array $data): void {
                    $job = new ImportArticlesJob($data['path']);

                    if ($data['run_now'] ?? false) {
                        app()->call([$job, 'handle']);

                        Notification::make()
                            ->title('تم استيراد المقالات')
                            ->body('اكتمل التنفيذ الفوري، ويمكنك مراجعة النتائج الآن.')
                            ->success()
                            ->send();

                        return;
                    }

                    dispatch($job);

                    Notification::make()
                        ->title('تم إرسال استيراد المقالات')
                        ->body('المهمة أُرسلت إلى الطابور. شغّل queue:work إذا لم يكن يعمل بالفعل.')
                        ->success()
                        ->send();
                }),
            Action::make('importAll')
                ->label('استيراد الكل')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->schema([
                    TextInput::make('categories_path')
                        ->label('ملف التصنيفات')
                        ->placeholder('مثال: imports/categories.json')
                        ->helperText('يمكنك إدخال مسار JSON أو SQL')
                        ->required(),
                    TextInput::make('articles_path')
                        ->label('ملف المقالات')
                        ->placeholder('مثال: imports/articles.json')
                        ->helperText('يمكنك إدخال مسار JSON أو SQL')
                        ->required(),
                    Toggle::make('run_now')
                        ->label('تنفيذ فوري')
                        ->default(true)
                        ->helperText('أوقفه إذا كنت تريد إرسال المهمتين إلى queue'),
                ])
                ->modalHeading('استيراد التصنيفات والمقالات')
                ->modalDescription('سيتم تشغيل استيراد التصنيفات أولًا ثم المقالات باستخدام نفس قواعد الحفاظ على الـ slug القديم.')
                ->modalSubmitActionLabel('ابدأ الاستيراد')
                ->action(function (array $data): void {
                    if ($data['run_now'] ?? false) {
                        app()->call([new ImportCategoriesJob($data['categories_path']), 'handle']);
                        app()->call([new ImportArticlesJob($data['articles_path']), 'handle']);

                        Notification::make()
                            ->title('تم استيراد التصنيفات والمقالات')
                            ->body('اكتمل التنفيذ الفوري للملفين.')
                            ->success()
                            ->send();

                        return;
                    }

                    dispatch(new ImportCategoriesJob($data['categories_path']));
                    dispatch(new ImportArticlesJob($data['articles_path']));

                    Notification::make()
                        ->title('تم إرسال مهمات الاستيراد')
                        ->body('تم إرسال التصنيفات والمقالات إلى الطابور.')
                        ->success()
                        ->send();
                }),
        ];
    }
}

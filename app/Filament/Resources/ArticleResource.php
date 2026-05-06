<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Services\SlugService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'المقالات';

    protected static ?string $modelLabel = 'مقال';

    protected static ?string $pluralModelLabel = 'المقالات';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('محتوى المقال')
                    ->description('ابدأ بالعنوان والمقتطف ثم حرر النص الكامل للمقال.')
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('العنوان')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (blank($get('slug')) && filled($state)) {
                                    $set('slug', app(SlugService::class)->generateUnique($state, Article::class));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('الرابط المختصر')
                            ->helperText('هذا هو الجزء الذي يظهر داخل رابط المقال.')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('excerpt')
                            ->label('المقتطف')
                            ->rows(4)
                            ->helperText('ملخص قصير يظهر في البطاقات وصفحات القوائم.')
                            ->columnSpanFull(),
                        RichEditor::make('body')
                            ->label('نص المقال')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('إعدادات النشر')
                    ->description('حدد أين ومتى يظهر المقال.')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('category_id')
                            ->label('التصنيف')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Toggle::make('is_published')
                            ->label('منشور')
                            ->default(false)
                            ->inline(false),
                        DateTimePicker::make('published_at')
                            ->label('تاريخ النشر')
                            ->seconds(false)
                            ->native(false)
                            ->helperText('اتركه فارغًا إذا كان المقال مسودة.'),
                    ])
                    ->compact(),
                Section::make('صور المقال')
                    ->description('رتب الصور بحيث تكون الصورة الأولى هي صورة الغلاف.')
                    ->columnSpanFull()
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('images')
                            ->label('معرض الصور')
                            ->collection('images')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->panelLayout('grid')
                            ->imageEditor()
                            ->helperText('يمكنك السحب والإفلات، وإعادة الترتيب، وحذف أي صورة قبل الحفظ.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('images')->label('الصورة')->collection('images')->conversion('thumb'),
                TextColumn::make('title')->label('العنوان')->searchable()->sortable(),
                TextColumn::make('slug')->label('الرابط')->searchable()->sortable(),
                TextColumn::make('category.name')->label('التصنيف')->sortable(),
                IconColumn::make('is_published')->label('منشور')->boolean()->sortable(),
                TextColumn::make('published_at')->label('تاريخ النشر')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')->label('التصنيف')->relationship('category', 'name'),
            ])
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
            ])
            ->toolbarActions([
                CreateAction::make()->label('مقال جديد'),
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('حذف المحدد'),
                ]),
            ])
            ->defaultSort('published_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}

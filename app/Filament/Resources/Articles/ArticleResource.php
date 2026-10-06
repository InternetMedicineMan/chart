<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Article Content')
                    ->description('Create and manage your article content')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->columnSpanFull(),

                        RichEditor::make('content')
                            ->fileAttachmentsDisk(config('filesystems.default'))
                            ->fileAttachmentsDirectory('article')
                            ->fileAttachmentsVisibility('public')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                ['table', 'attachFiles'],
                                ['undo', 'redo'],
                            ]),

                        FileUpload::make('thumbnail')
                            ->label('Featured Image')
                            ->disk(config('filesystems.default'))
                            ->directory('articles')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Upload a featured image for your article'),

                        Toggle::make('active')
                            ->label('Published')
                            ->default(true)
                            ->helperText('Toggle to publish or unpublish this article'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('SEO Settings')
                    ->description('Optimize your article for search engines')
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('SEO Title')
                            ->maxLength(60)
                            ->helperText('Recommended: 50-60 characters')
                            ->columnSpanFull(),

                        TextInput::make('seo_description')
                            ->label('Meta Description')
                            ->maxLength(160)
                            ->helperText('Recommended: 120-160 characters')
                            ->columnSpanFull(),

                        TextInput::make('seo_keywords')
                            ->label('Keywords')
                            ->helperText('Comma-separated keywords for this article')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('slug'),
                IconColumn::make('active')
                    ->sortable()
                    ->boolean(),
                TextColumn::make('created_at'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}

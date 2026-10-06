<?php

namespace App\Filament\Resources\Changelogs;

use App\Filament\Resources\Changelogs\Pages\CreateChangelog;
use App\Filament\Resources\Changelogs\Pages\EditChangelog;
use App\Filament\Resources\Changelogs\Pages\ListChangelogs;
use App\Models\Changelog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChangelogResource extends Resource
{
    protected static ?string $model = Changelog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Changelog Content')
                    ->description('Create and manage changelog entries')
                    ->schema([
                        TextInput::make('title')
                            ->maxLength(255)
                            ->required()
                            ->live(onBlur: true)
                            ->helperText('A brief title for this changelog entry')
                            ->columnSpanFull(),

                        MarkdownEditor::make('description')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                ['bold', 'italic', 'strike', 'link'],
                                ['heading'],
                                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ])
                            ->helperText('Detailed description using Markdown syntax'),

                        DateTimePicker::make('published_at')
                            ->label('Publish Date & Time')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->helperText('When this changelog should be published')
                            ->columnSpan(1),

                        TextInput::make('tags')
                            ->maxLength(255)
                            ->placeholder('feature, bugfix, improvement')
                            ->helperText('Comma-separated tags for categorization')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('tags')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ])
            ->defaultSort('published_at', 'desc');
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
            'index' => ListChangelogs::route('/'),
            'create' => CreateChangelog::route('/create'),
            'edit' => EditChangelog::route('/{record}/edit'),
        ];
    }
}

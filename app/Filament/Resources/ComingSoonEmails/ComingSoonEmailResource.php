<?php

namespace App\Filament\Resources\ComingSoonEmails;

use App\Filament\Resources\ComingSoonEmails\Pages\CreateComingSoonEmail;
use App\Filament\Resources\ComingSoonEmails\Pages\EditComingSoonEmail;
use App\Filament\Resources\ComingSoonEmails\Pages\ListComingSoonEmails;
use App\Filament\Resources\ComingSoonEmails\Pages\ViewComingSoonEmail;
use App\Models\ComingSoonEmail;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ComingSoonEmailResource extends Resource
{
    protected static ?string $model = ComingSoonEmail::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Email Subscription')
                    ->description('Manage coming soon email subscribers')
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Email address of the subscriber')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
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
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => ListComingSoonEmails::route('/'),
            'create' => CreateComingSoonEmail::route('/create'),
            'view' => ViewComingSoonEmail::route('/{record}'),
            'edit' => EditComingSoonEmail::route('/{record}/edit'),
        ];
    }
}

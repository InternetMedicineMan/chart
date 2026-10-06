<?php

namespace App\Filament\Resources\LemonSqueezySubscriptions;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\LemonSqueezySubscriptions\Pages\CreateLemonSqueezySubscription;
use App\Filament\Resources\LemonSqueezySubscriptions\Pages\EditLemonSqueezySubscription;
use App\Filament\Resources\LemonSqueezySubscriptions\Pages\ListLemonSqueezySubscriptions;
use App\Filament\Resources\LemonSqueezySubscriptions\Pages\ViewLemonSqueezySubscription;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use LemonSqueezy\Laravel\Subscription;

class LemonSqueezySubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $modelLabel = 'LemonSqueezy Subscriptions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lemon_squeezy_id')
                    ->label('LemonSqueezy ID')
                    ->searchable(),
                TextColumn::make('billable.name')
                    ->label('User Name')
                    ->searchable(),
                TextColumn::make('billable.email')
                    ->label('User Email')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('product_id')
                    ->searchable(),
                TextColumn::make('variant_id')
                    ->searchable(),
                TextColumn::make('card_brand')
                    ->searchable(),
                TextColumn::make('card_last_four')
                    ->searchable(),
                TextColumn::make('pause_mode')
                    ->searchable(),
                TextColumn::make('pause_resumes_at')
                    ->dateTime(),
                TextColumn::make('trial_ends_at')
                    ->dateTime(),
                TextColumn::make('renews_at')
                    ->dateTime(),
                TextColumn::make('ends_at')
                    ->dateTime(),
                TextColumn::make('created_at')
                    ->dateTime(),
                TextColumn::make('updated_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('generateInvoice')
                    ->label('Generate Invoice')
                    ->icon('heroicon-o-document-plus')
                    ->url(fn (Subscription $record) => InvoiceResource::lemonSqueezySubscriptionCreateUrl($record)),
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
            'index' => ListLemonSqueezySubscriptions::route('/'),
            'create' => CreateLemonSqueezySubscription::route('/create'),
            'view' => ViewLemonSqueezySubscription::route('/{record}'),
            'edit' => EditLemonSqueezySubscription::route('/{record}/edit'),
        ];
    }
}

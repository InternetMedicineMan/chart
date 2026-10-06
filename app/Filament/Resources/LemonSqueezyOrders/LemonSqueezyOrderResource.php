<?php

namespace App\Filament\Resources\LemonSqueezyOrders;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\LemonSqueezyOrders\Pages\ListOrders;
use App\Filament\Resources\LemonSqueezyOrders\Widgets\OrdersStats;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use LemonSqueezy\Laravel\Order;

//
class LemonSqueezyOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'LemonSqueezy Orders';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

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
                TextColumn::make('order_number')
                    ->searchable(),
                TextColumn::make('status')
                    ->sortable()
                    ->badge(),
                // Change the currency to match your store currency
                TextColumn::make('total')
                    ->money(config('services.cashier.currency'), divideBy: 100)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('ordered_at')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('generateInvoice')
                    ->label('Generate Invoice')
                    ->icon('heroicon-o-document-plus')
                    ->url(fn (Order $record) => InvoiceResource::lemonSqueezyOrderCreateUrl($record)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->defaultSort('ordered_at', 'desc');
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
            'index' => ListOrders::route('/'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            OrdersStats::class,
        ];
    }
}

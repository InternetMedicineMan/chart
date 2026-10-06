<?php

namespace App\Filament\Resources\StripeOrders;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\StripeOrders\Pages\CreateStripeOrder;
use App\Filament\Resources\StripeOrders\Pages\EditStripeOrder;
use App\Filament\Resources\StripeOrders\Pages\ListStripeOrders;
use App\Models\StripeOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StripeOrderResource extends Resource
{
    protected static ?string $model = StripeOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Information')
                    ->description('Stripe order details and customer information')
                    ->schema([
                        Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('The customer who placed this order')
                            ->columnSpan(1),

                        TextInput::make('amount')
                            ->label('Order Amount')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->helperText('Amount in cents (e.g., 1000 = $10.00)')
                            ->columnSpan(1),

                        Select::make('currency')
                            ->required()
                            ->options([
                                'usd' => 'USD ($)',
                                'eur' => 'EUR (€)',
                                'gbp' => 'GBP (£)',
                            ])
                            ->default('usd')
                            ->columnSpan(1),

                        TextInput::make('price_id')
                            ->label('Stripe Price ID')
                            ->required()
                            ->maxLength(255)
                            ->prefix('price_')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Order Status')
                    ->description('Current status of the order and payment')
                    ->schema([
                        Select::make('status')
                            ->required()
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'canceled' => 'Canceled',
                            ])
                            ->default('pending')
                            ->columnSpan(1),

                        Select::make('payment_status')
                            ->required()
                            ->options([
                                'paid' => 'Paid',
                                'unpaid' => 'Unpaid',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                            ])
                            ->default('unpaid')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Stripe Configuration')
                    ->description('Stripe-specific order settings')
                    ->schema([
                        TextInput::make('stripe_id')
                            ->label('Stripe Order ID')
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Automatically assigned by Stripe')
                            ->columnSpanFull(),

                        Textarea::make('metadata')
                            ->label('Metadata (JSON)')
                            ->rows(3)
                            ->helperText('Additional metadata in JSON format')
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
                TextColumn::make('stripe_id')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price_id')
                    ->searchable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->money(config('services.cashier.currency'), divideBy: 100)
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->sortable()
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
                Action::make('generateInvoice')
                    ->label('Generate Invoice')
                    ->icon('heroicon-o-document-plus')
                    ->url(fn (StripeOrder $record) => InvoiceResource::stripeOrderCreateUrl($record)),
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
            'index' => ListStripeOrders::route('/'),
            'create' => CreateStripeOrder::route('/create'),
            'edit' => EditStripeOrder::route('/{record}/edit'),
        ];
    }
}

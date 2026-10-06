<?php

namespace App\Filament\Resources\Prices;

use App\Filament\Resources\Prices\Pages\CreatePrice;
use App\Filament\Resources\Prices\Pages\EditPrice;
use App\Filament\Resources\Prices\Pages\ListPrices;
use App\Models\Price;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceResource extends Resource
{
    protected static ?string $model = Price::class;

    protected static ?string $navigationLabel = 'Stripe Prices';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Price Details')
                    ->description('Configure the pricing information')
                    ->schema([
                        TextInput::make('unit_amount')
                            ->label('Price Amount')
                            ->numeric()
                            ->required()
                            ->prefix('$')
                            ->helperText('Amount in cents (e.g., 1000 = $10.00)')
                            ->columnSpan(1),

                        Select::make('currency')
                            ->required()
                            ->options([
                                'usd' => 'USD ($)',
                                'eur' => 'EUR (€)',
                                'gbp' => 'GBP (£)',
                                'cad' => 'CAD (C$)',
                                'aud' => 'AUD (A$)',
                            ])
                            ->default('usd')
                            ->searchable()
                            ->columnSpan(1),

                        Select::make('type')
                            ->label('Price Type')
                            ->required()
                            ->options([
                                'one_time' => 'One-time',
                                'recurring' => 'Recurring',
                            ])
                            ->default('recurring')
                            ->live()
                            ->columnSpan(1),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Toggle to activate or deactivate this price')
                            ->inline(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Billing Configuration')
                    ->description('Set up billing and subscription details')
                    ->schema([
                        Select::make('interval')
                            ->label('Billing Interval')
                            ->options([
                                'day' => 'Daily',
                                'week' => 'Weekly',
                                'month' => 'Monthly',
                                'year' => 'Yearly',
                            ])
                            ->default('month')
                            ->visible(fn ($get) => $get('type') === 'recurring')
                            ->columnSpan(1),

                        TextInput::make('interval_count')
                            ->label('Interval Count')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->helperText('Number of intervals between billings')
                            ->visible(fn ($get) => $get('type') === 'recurring')
                            ->columnSpan(1),

                        TextInput::make('trial_period_days')
                            ->label('Trial Period (Days)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Number of days for trial period')
                            ->visible(fn ($get) => $get('type') === 'recurring')
                            ->columnSpan(1),

                        Select::make('billing_scheme')
                            ->label('Billing Scheme')
                            ->required()
                            ->options([
                                'per_unit' => 'Per Unit',
                                'tiered' => 'Tiered',
                            ])
                            ->default('per_unit')
                            ->columnSpan(1),

                        Select::make('usage_type')
                            ->label('Usage Type')
                            ->options([
                                'licensed' => 'Licensed',
                                'metered' => 'Metered',
                            ])
                            ->default('licensed')
                            ->helperText('How the price is charged')
                            ->columnSpan(1),

                        TextInput::make('tiers_mode')
                            ->label('Tiers Mode')
                            ->maxLength(255)
                            ->placeholder('graduated')
                            ->visible(fn ($get) => $get('billing_scheme') === 'tiered')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Stripe Configuration')
                    ->description('Stripe-specific price settings')
                    ->schema([
                        TextInput::make('stripe_id')
                            ->label('Stripe Price ID')
                            ->required()
                            ->maxLength(255)
                            ->prefix('price_')
                            ->helperText('The unique identifier from Stripe')
                            ->columnSpanFull(),

                        TextInput::make('product_id')
                            ->label('Stripe Product ID')
                            ->required()
                            ->maxLength(255)
                            ->prefix('prod_')
                            ->helperText('The associated product ID from Stripe')
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
                    ->label('Stripe Price ID')
                    ->searchable(),
                TextColumn::make('product_id')
                    ->label('Stripe Product ID')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('interval')
                    ->searchable(),
                TextColumn::make('interval_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('trial_period_days')
                    ->numeric()
                    ->sortable(),
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
            'index' => ListPrices::route('/'),
            'create' => CreatePrice::route('/create'),
            'edit' => EditPrice::route('/{record}/edit'),
        ];
    }
}

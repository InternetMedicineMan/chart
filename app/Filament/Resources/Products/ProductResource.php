<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationLabel = 'Stripe Products';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->description('Manage your Stripe product details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->helperText('The name of the product as it appears to customers')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->rows(3)
                            ->helperText('A brief description of the product')
                            ->columnSpanFull(),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Toggle to activate or deactivate this product')
                            ->inline(false),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Stripe Configuration')
                    ->description('Stripe-specific product settings')
                    ->schema([
                        TextInput::make('stripe_id')
                            ->label('Stripe Product ID')
                            ->required()
                            ->maxLength(255)
                            ->helperText('The unique identifier from Stripe (e.g., prod_xxxxx)')
                            ->prefix('prod_')
                            ->columnSpanFull(),

                        TextInput::make('type')
                            ->label('Product Type')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('service')
                            ->helperText('The type of product (e.g., service, good)')
                            ->columnSpan(1),

                        TextInput::make('default_price')
                            ->label('Default Price ID')
                            ->maxLength(255)
                            ->helperText('The default Stripe price ID (e.g., price_xxxxx)')
                            ->prefix('price_')
                            ->columnSpan(1),

                        Textarea::make('metadata')
                            ->label('Metadata (JSON)')
                            ->rows(3)
                            ->helperText('Additional metadata in JSON format')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
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
                    ->label('Stripe ID')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('default_price')
                    ->label('Default Price ID')
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
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}

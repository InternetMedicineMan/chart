<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Invoice;
use App\Models\Price;
use App\Models\User;
use App\Notifications\InvoiceCreatedNotification;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->schema([
                        Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => request('user_id'))
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $user = User::find($state);

                                if (! $user) {
                                    return;
                                }

                                $set('customer_name', $user->name);
                                $set('customer_email', $user->email);
                            })
                            ->columnSpan(1),

                        TextInput::make('customer_name')
                            ->required()
                            ->maxLength(255)
                            ->default(fn () => request('customer_name'))
                            ->columnSpan(1),

                        TextInput::make('customer_email')
                            ->email()
                            ->maxLength(255)
                            ->default(fn () => request('customer_email'))
                            ->columnSpan(1),

                        Select::make('status')
                            ->required()
                            ->options(Invoice::statuses())
                            ->default(fn () => request('status', Invoice::STATUS_DRAFT))
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Invoice Details')
                    ->schema([
                        TextInput::make('number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Assigned when created')
                            ->columnSpan(1),

                        DatePicker::make('issued_at')
                            ->required()
                            ->native(false)
                            ->default(fn () => request('issued_at', now()->toDateString()))
                            ->columnSpan(1),

                        DatePicker::make('due_at')
                            ->native(false)
                            ->default(fn () => request('due_at'))
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
                            ->default(fn () => request('currency', config('services.cashier.currency', 'usd')))
                            ->searchable()
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Line Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                TextInput::make('description')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(4),

                                TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->columnSpan(1),

                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('$')
                                    ->helperText('Amount in cents')
                                    ->columnSpan(1),

                                TextInput::make('tax_rate')
                                    ->label('Tax %')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->columnSpan(1),
                            ])
                            ->columns(7)
                            ->default(fn () => [[
                                'description' => request('item_description', 'Invoice item'),
                                'quantity' => request('quantity', 1),
                                'unit_price' => request('unit_price', 0),
                                'tax_rate' => request('tax_rate', 0),
                            ]])
                            ->addActionLabel('Add line item')
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('Source & Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->default(fn () => request('notes'))
                            ->columnSpanFull(),

                        TextInput::make('provider')
                            ->default(fn () => request('provider'))
                            ->maxLength(255)
                            ->columnSpan(1),

                        TextInput::make('provider_type')
                            ->default(fn () => request('provider_type'))
                            ->maxLength(255)
                            ->columnSpan(1),

                        TextInput::make('provider_id')
                            ->default(fn () => request('provider_id'))
                            ->maxLength(255)
                            ->columnSpan(1),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('total')
                    ->formatStateUsing(fn (int $state, Invoice $record) => strtoupper($record->currency).' '.number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
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
                self::downloadAction(),
                self::emailAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function downloadAction(): Action
    {
        return Action::make('downloadInvoice')
            ->label('Download PDF')
            ->icon('heroicon-o-arrow-down-tray')
            ->url(fn (Invoice $record) => route('invoices.download', $record))
            ->openUrlInNewTab();
    }

    public static function emailAction(): Action
    {
        return Action::make('emailInvoice')
            ->label('Email Customer')
            ->icon('heroicon-o-envelope')
            ->requiresConfirmation()
            ->visible(fn (Invoice $record) => filled($record->customer_email))
            ->action(function (Invoice $record) {
                NotificationFacade::route('mail', $record->customer_email)
                    ->notify(new InvoiceCreatedNotification($record));

                $record->update(['status' => Invoice::STATUS_SENT]);
            });
    }

    public static function prefilledCreateUrl(array $data): string
    {
        return self::getUrl('create', array_filter($data, fn ($value) => filled($value)));
    }

    public static function stripeOrderCreateUrl(Model $record): string
    {
        $record->loadMissing(['user', 'price.product']);

        return self::prefilledCreateUrl([
            'user_id' => $record->user_id,
            'customer_name' => $record->user?->name,
            'customer_email' => $record->user?->email,
            'currency' => $record->currency,
            'item_description' => $record->price?->product?->name ?? $record->price_id,
            'quantity' => 1,
            'unit_price' => $record->amount,
            'provider' => 'stripe',
            'provider_type' => 'order',
            'provider_id' => $record->stripe_id,
        ]);
    }

    public static function stripeSubscriptionCreateUrl(Model $record): string
    {
        $record->loadMissing('user');

        $price = Price::query()
            ->with('product')
            ->where('stripe_id', $record->stripe_price)
            ->first();

        return self::prefilledCreateUrl([
            'user_id' => $record->user_id,
            'customer_name' => $record->user?->name,
            'customer_email' => $record->user?->email,
            'currency' => $price?->currency ?? config('services.cashier.currency', 'usd'),
            'item_description' => $price?->product?->name ?? $record->stripe_price ?? $record->stripe_id,
            'quantity' => $record->quantity ?? 1,
            'unit_price' => $price?->unit_amount ?? 0,
            'provider' => 'stripe',
            'provider_type' => 'subscription',
            'provider_id' => $record->stripe_id,
        ]);
    }

    public static function lemonSqueezyOrderCreateUrl(Model $record): string
    {
        $record->loadMissing('billable');

        return self::prefilledCreateUrl([
            'user_id' => $record->billable?->getKey(),
            'customer_name' => $record->billable?->name,
            'customer_email' => $record->billable?->email,
            'currency' => config('services.cashier.currency', 'usd'),
            'item_description' => 'LemonSqueezy order '.($record->order_number ?? $record->lemon_squeezy_id),
            'quantity' => 1,
            'unit_price' => $record->total ?? 0,
            'provider' => 'lemonsqueezy',
            'provider_type' => 'order',
            'provider_id' => $record->lemon_squeezy_id,
        ]);
    }

    public static function lemonSqueezySubscriptionCreateUrl(Model $record): string
    {
        $record->loadMissing('billable');

        return self::prefilledCreateUrl([
            'user_id' => $record->billable?->getKey(),
            'customer_name' => $record->billable?->name,
            'customer_email' => $record->billable?->email,
            'currency' => config('services.cashier.currency', 'usd'),
            'item_description' => 'LemonSqueezy subscription '.($record->lemon_squeezy_id ?? $record->id),
            'quantity' => 1,
            'unit_price' => 0,
            'provider' => 'lemonsqueezy',
            'provider_type' => 'subscription',
            'provider_id' => $record->lemon_squeezy_id,
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
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}

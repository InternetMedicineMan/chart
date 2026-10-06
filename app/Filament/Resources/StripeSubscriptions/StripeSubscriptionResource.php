<?php

namespace App\Filament\Resources\StripeSubscriptions;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\StripeSubscriptions\Pages\CreateStripeSubscription;
use App\Filament\Resources\StripeSubscriptions\Pages\EditStripeSubscription;
use App\Filament\Resources\StripeSubscriptions\Pages\ListStripeSubscriptions;
use App\Filament\Resources\StripeSubscriptions\Pages\ViewStripeSubscription;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Laravel\Cashier\Subscription;

class StripeSubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $modelLabel = 'Stripe Subscriptions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subscription Information')
                    ->description('Manage customer subscription details')
                    ->schema([
                        Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('The customer for this subscription')
                            ->columnSpan(1),

                        TextInput::make('stripe_price')
                            ->label('Stripe Price ID')
                            ->required()
                            ->maxLength(255)
                            ->prefix('price_')
                            ->helperText('The Stripe price ID for this subscription')
                            ->columnSpan(1),

                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->helperText('Number of subscription units')
                            ->columnSpan(1),

                        Select::make('stripe_status')
                            ->label('Status')
                            ->required()
                            ->options([
                                'active' => 'Active',
                                'canceled' => 'Canceled',
                                'incomplete' => 'Incomplete',
                                'incomplete_expired' => 'Incomplete Expired',
                                'past_due' => 'Past Due',
                                'trialing' => 'Trialing',
                                'unpaid' => 'Unpaid',
                            ])
                            ->default('active')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Trial & Expiration')
                    ->description('Trial period and subscription end dates')
                    ->schema([
                        DateTimePicker::make('trial_ends_at')
                            ->label('Trial Ends At')
                            ->native(false)
                            ->helperText('When the trial period ends')
                            ->columnSpan(1),

                        DateTimePicker::make('ends_at')
                            ->label('Subscription Ends At')
                            ->native(false)
                            ->helperText('When the subscription ends (for canceled subscriptions)')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Stripe Configuration')
                    ->description('Stripe-specific subscription settings')
                    ->schema([
                        TextInput::make('stripe_id')
                            ->label('Stripe Subscription ID')
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Automatically assigned by Stripe')
                            ->columnSpanFull(),

                        DateTimePicker::make('created_at')
                            ->label('Created At')
                            ->disabled()
                            ->dehydrated(false)
                            ->native(false)
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
                    ->label('Stripe ID')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('stripe_status')
                    ->label('Status')
                    ->searchable()
                    ->badge(),
                TextColumn::make('stripe_price')
                    ->label('Price')
                    ->searchable(),
                TextColumn::make('quantity'),
                TextColumn::make('trial_ends_at')->dateTime(),
                TextColumn::make('ends_at')->dateTime(),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('generateInvoice')
                    ->label('Generate Invoice')
                    ->icon('heroicon-o-document-plus')
                    ->url(fn (Subscription $record) => InvoiceResource::stripeSubscriptionCreateUrl($record)),
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
            'index' => ListStripeSubscriptions::route('/'),
            'create' => CreateStripeSubscription::route('/create'),
            'view' => ViewStripeSubscription::route('/{record}'),
            'edit' => EditStripeSubscription::route('/{record}/edit'),
        ];
    }
}

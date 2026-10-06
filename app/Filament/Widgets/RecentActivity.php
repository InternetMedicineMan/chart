<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivity extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent User Registrations')
            ->query(
                User::query()
                    ->with('subscriptions')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-envelope')
                    ->copyable()
                    ->copyMessage('Email copied!')
                    ->copyMessageDuration(1500),

                BadgeColumn::make('email_verified_at')
                    ->label('Verified')
                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                    ->colors([
                        'success' => fn ($state) => $state !== null,
                        'danger' => fn ($state) => $state === null,
                    ]),

                BadgeColumn::make('subscription_status')
                    ->label('Subscription')
                    ->getStateUsing(function ($record) {
                        return $record->subscribed() ? 'Active' : 'None';
                    })
                    ->colors([
                        'success' => 'Active',
                        'gray' => 'None',
                    ]),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->since()
                    ->description(fn ($record) => $record->created_at->diffForHumans()),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

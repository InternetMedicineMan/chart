<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Laravel\Cashier\Subscription;

class SubscriptionStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // Total users
        $totalUsers = User::count();
        $usersToday = User::whereDate('created_at', today())->count();
        $usersYesterday = User::whereDate('created_at', today()->subDay())->count();

        // Active subscriptions
        $activeSubscriptions = Subscription::where('stripe_status', 'active')->count();
        $subscriptionsToday = Subscription::where('stripe_status', 'active')
            ->whereDate('created_at', today())
            ->count();
        $subscriptionsYesterday = Subscription::where('stripe_status', 'active')
            ->whereDate('created_at', today()->subDay())
            ->count();

        // Calculate conversion rate
        $conversionRate = $totalUsers > 0 ? ($activeSubscriptions / $totalUsers) * 100 : 0;

        // Cancelled subscriptions
        $cancelledSubscriptions = Subscription::where('stripe_status', 'canceled')->count();
        $churnRate = $activeSubscriptions > 0
            ? ($cancelledSubscriptions / ($activeSubscriptions + $cancelledSubscriptions)) * 100
            : 0;

        // Get chart data for last 7 days
        $chartData = Subscription::where('stripe_status', 'active')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();

        return [
            Stat::make('Total Users', number_format($totalUsers))
                ->description(($usersToday >= $usersYesterday ? '+' : '').($usersToday - $usersYesterday).' today')
                ->descriptionIcon($usersToday >= $usersYesterday ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($usersToday >= $usersYesterday ? 'success' : 'danger')
                ->chart(User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->pluck('count')
                    ->toArray()),

            Stat::make('Active Subscriptions', number_format($activeSubscriptions))
                ->description(number_format($conversionRate, 1).'% conversion rate')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart($chartData),

            Stat::make('Churn Rate', number_format($churnRate, 1).'%')
                ->description($cancelledSubscriptions.' cancelled subscriptions')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($churnRate > 5 ? 'danger' : ($churnRate > 2 ? 'warning' : 'success')),
        ];
    }
}

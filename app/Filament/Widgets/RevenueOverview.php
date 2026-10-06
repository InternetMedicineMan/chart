<?php

namespace App\Filament\Widgets;

use App\Models\StripeOrder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class RevenueOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Get revenue data
        $totalRevenue = StripeOrder::sum('amount') / 100; // Convert cents to dollars
        $todayRevenue = StripeOrder::whereDate('created_at', today())->sum('amount') / 100;
        $yesterdayRevenue = StripeOrder::whereDate('created_at', today()->subDay())->sum('amount') / 100;

        $last30DaysRevenue = StripeOrder::where('created_at', '>=', now()->subDays(30))
            ->sum('amount') / 100;
        $previous30DaysRevenue = StripeOrder::whereBetween('created_at', [
            now()->subDays(60),
            now()->subDays(30),
        ])->sum('amount') / 100;

        // Calculate percentage change
        $revenueChange = $previous30DaysRevenue > 0
            ? (($last30DaysRevenue - $previous30DaysRevenue) / $previous30DaysRevenue) * 100
            : 0;

        // Get chart data for last 7 days
        $chartData = StripeOrder::where('created_at', '>=', now()->subDays(7))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) / 100 as revenue'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('revenue')
            ->toArray();

        return [
            Stat::make('Total Revenue', '$'.number_format($totalRevenue, 2))
                ->description('All time revenue')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart($chartData)
                ->color('success'),

            Stat::make('Today\'s Revenue', '$'.number_format($todayRevenue, 2))
                ->description(($todayRevenue >= $yesterdayRevenue ? '+' : '').'$'.number_format($todayRevenue - $yesterdayRevenue, 2).' from yesterday')
                ->descriptionIcon($todayRevenue >= $yesterdayRevenue ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayRevenue >= $yesterdayRevenue ? 'success' : 'danger'),

            Stat::make('Last 30 Days', '$'.number_format($last30DaysRevenue, 2))
                ->description(($revenueChange >= 0 ? '+' : '').number_format($revenueChange, 1).'% from previous period')
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange >= 0 ? 'success' : 'danger'),
        ];
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\StripeOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Revenue Overview (Last 30 Days)';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        // Generate data for last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('M d');

            $revenue = StripeOrder::whereDate('created_at', $date)
                ->sum('amount') / 100;
            $data[] = round($revenue, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Daily Revenue ($)',
                    'data' => $data,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "$" + value; }',
                    ],
                ],
            ],
        ];
    }
}

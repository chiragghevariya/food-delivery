<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\TrendQuery;
use Filament\Widgets\ChartWidget;

class OrdersRevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Orders & Revenue (last 14 days)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected function getData(): array
    {
        $orders = TrendQuery::daily(fn () => Order::query(), days: 14);
        $revenue = TrendQuery::daily(
            fn () => Order::query()->where('status', OrderStatus::DELIVERED),
            days: 14,
            sumColumn: 'total',
        );

        return [
            'labels' => $orders['labels'],
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $orders['counts'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'yAxisID' => 'y',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Revenue',
                    'data' => $revenue['sums'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
                    'yAxisID' => 'y1',
                    'tension' => 0.3,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /** Two y-axes so the order count and the (larger) revenue numbers stay readable. */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'position' => 'left',
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Orders'],
                ],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Revenue'],
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
        ];
    }
}

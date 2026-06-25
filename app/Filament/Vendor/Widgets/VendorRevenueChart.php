<?php

namespace App\Filament\Vendor\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\TrendQuery;
use Filament\Widgets\ChartWidget;

class VendorRevenueChart extends ChartWidget
{
    protected static ?string $heading = 'My Orders & Revenue (last 14 days)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    private function restaurantId(): int
    {
        return auth()->user()?->restaurant?->id ?? 0;
    }

    protected function getData(): array
    {
        $rid = $this->restaurantId();

        $orders = TrendQuery::daily(
            fn () => Order::query()->where('restaurant_id', $rid),
            days: 14,
        );
        $revenue = TrendQuery::daily(
            fn () => Order::query()->where('restaurant_id', $rid)->where('status', OrderStatus::DELIVERED),
            days: 14,
            sumColumn: 'subtotal',
        );

        return [
            'labels' => $orders['labels'],
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $orders['counts'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
                    'yAxisID' => 'y',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Revenue',
                    'data' => $revenue['sums'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
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

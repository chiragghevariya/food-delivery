<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrderStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Order Status Distribution';

    protected static ?int $sort = 3;

    protected static ?string $pollingInterval = null;

    /** Map our enum colour names to concrete hex values for Chart.js. */
    private const HEX = [
        'gray'    => '#9ca3af',
        'info'    => '#3b82f6',
        'warning' => '#f59e0b',
        'success' => '#10b981',
        'danger'  => '#ef4444',
    ];

    protected function getData(): array
    {
        // One grouped query: status => count.
        $counts = Order::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $labels = [];
        $data = [];
        $colors = [];

        foreach (OrderStatus::cases() as $status) {
            $labels[] = $status->label();
            $data[] = (int) ($counts[$status->value] ?? 0);
            $colors[] = self::HEX[$status->color()] ?? '#9ca3af';
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => $colors,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

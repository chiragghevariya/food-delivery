<?php

namespace App\Filament\Vendor\Widgets;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopSellingItems extends ChartWidget
{
    protected static ?string $heading = 'Top Selling Items';

    protected static ?int $sort = 3;

    protected static ?string $pollingInterval = null;

    private function restaurantId(): int
    {
        return auth()->user()?->restaurant?->id ?? 0;
    }

    protected function getData(): array
    {
        $rows = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('restaurant_id', $this->restaurantId())
                ->where('status', '!=', OrderStatus::CANCELLED->value))
            ->select('menu_item_id', DB::raw('SUM(quantity) as qty'))
            ->groupBy('menu_item_id')
            ->orderByDesc('qty')
            ->limit(8)
            ->with('menuItem:id,name')
            ->get();

        return [
            'labels' => $rows->map(fn ($r) => $r->menuItem?->name ?? '(deleted item)')->all(),
            'datasets' => [[
                'label' => 'Units sold',
                'data' => $rows->map(fn ($r) => (int) $r->qty)->all(),
                'backgroundColor' => '#f59e0b',
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** Horizontal bars read better for a ranked list. */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}

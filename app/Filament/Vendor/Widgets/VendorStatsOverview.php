<?php

namespace App\Filament\Vendor\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class VendorStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /** Base query already filtered to the signed-in owner's restaurant (0 => none). */
    private function ownOrders(): Builder
    {
        return Order::query()->where('restaurant_id', auth()->user()?->restaurant?->id ?? 0);
    }

    protected function getStats(): array
    {
        $symbol = Setting::get('currency_symbol', '$');
        $today = now()->toDateString();

        $ordersToday = (clone $this->ownOrders())->whereDate('created_at', $today)->count();

        // Earnings = subtotal of the restaurant's delivered orders today
        // (delivery fee goes to the rider/platform, not the restaurant).
        $earningsToday = (clone $this->ownOrders())
            ->where('status', OrderStatus::DELIVERED)
            ->whereDate('created_at', $today)
            ->sum('subtotal');

        $pending = (clone $this->ownOrders())
            ->where('status', OrderStatus::PENDING)
            ->count();

        return [
            Stat::make("Today's Orders", number_format($ordersToday))
                ->description('Placed today')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make("Today's Earnings", $symbol . number_format($earningsToday, 2))
                ->description('Delivered today (excl. delivery fee)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Pending Orders', number_format($pending))
                ->description($pending > 0 ? 'Need your attention!' : 'All caught up')
                ->descriptionIcon($pending > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($pending > 0 ? 'danger' : 'gray'),
        ];
    }
}

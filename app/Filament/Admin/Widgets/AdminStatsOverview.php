<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\OrderStatus;
use App\Enums\RestaurantStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Setting;
use App\Models\User;
use App\Support\TrendQuery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $symbol = Setting::get('currency_symbol', '$');

        // Realized revenue (GMV) = total of delivered orders.
        $gmv = Order::where('status', OrderStatus::DELIVERED)->sum('total');

        // 7-day sparklines.
        $orderTrend = TrendQuery::daily(fn () => Order::query(), days: 7);
        $revenueTrend = TrendQuery::daily(
            fn () => Order::query()->where('status', OrderStatus::DELIVERED),
            days: 7,
            sumColumn: 'total',
        );

        return [
            Stat::make('Total Orders', number_format(Order::count()))
                ->description('All-time orders')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart($orderTrend['counts'])
                ->color('primary'),

            Stat::make('Revenue (GMV)', $symbol . number_format($gmv, 2))
                ->description('Delivered orders')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($revenueTrend['sums'])
                ->color('success'),

            Stat::make('Active Restaurants', number_format(
                Restaurant::where('status', RestaurantStatus::APPROVED)->count()
            ))
                ->description(Restaurant::where('status', RestaurantStatus::PENDING)->count() . ' awaiting approval')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info'),

            Stat::make('Active Riders', number_format(
                User::where('role', 'rider')->where('is_active', true)->count()
            ))
                ->description('Currently enabled')
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning'),
        ];
    }
}

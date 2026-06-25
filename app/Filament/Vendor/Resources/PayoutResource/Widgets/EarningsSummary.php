<?php

namespace App\Filament\Vendor\Resources\PayoutResource\Widgets;

use App\Models\Setting;
use App\Services\PayoutService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lifetime earnings summary for the signed-in owner's restaurant.
 * Lives under the resource (not the discovered Widgets dir) so it only appears
 * on the Earnings page, not the dashboard. All figures come from the shared
 * PayoutService so they always match what the admin payout generator produces.
 */
class EarningsSummary extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $symbol = Setting::get('currency_symbol', '$');
        $restaurant = auth()->user()?->restaurant;

        if ($restaurant === null) {
            return [
                Stat::make('Total Sales', $symbol . '0.00')->description('No restaurant yet'),
            ];
        }

        $service = app(PayoutService::class);
        $lifetime = $service->restaurantLifetimeSummary($restaurant);
        $unsettled = $service->restaurantUnsettledSummary($restaurant);

        return [
            Stat::make('Total Sales', $symbol . number_format($lifetime['gross'], 2))
                ->description($lifetime['orders_count'] . ' delivered orders')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('Commission Deducted', $symbol . number_format($lifetime['commission'], 2))
                ->description('Platform fee (' . $service->commissionRate() . '%)')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('danger'),

            Stat::make('Net Earnings', $symbol . number_format($lifetime['net'], 2))
                ->description('After commission')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Unsettled', $symbol . number_format($unsettled['net'], 2))
                ->description($unsettled['orders_count'] . ' orders awaiting payout')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}

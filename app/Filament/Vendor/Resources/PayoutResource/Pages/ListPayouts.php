<?php

namespace App\Filament\Vendor\Resources\PayoutResource\Pages;

use App\Filament\Vendor\Resources\PayoutResource;
use App\Filament\Vendor\Resources\PayoutResource\Widgets\EarningsSummary;
use Filament\Resources\Pages\ListRecords;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** Earnings summary cards sit above the payout-history table. */
    protected function getHeaderWidgets(): array
    {
        return [
            EarningsSummary::class,
        ];
    }
}

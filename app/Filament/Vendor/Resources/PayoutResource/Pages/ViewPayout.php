<?php

namespace App\Filament\Vendor\Resources\PayoutResource\Pages;

use App\Enums\PayoutStatus;
use App\Filament\Vendor\Resources\PayoutResource;
use App\Models\Setting;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPayout extends ViewRecord
{
    protected static string $resource = PayoutResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        $currency = Setting::get('currency', 'USD');

        return $infolist
            ->schema([
                Section::make('Statement')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('period_start')->date(),
                        TextEntry::make('period_end')->date(),
                        TextEntry::make('orders_count')->label('Orders'),
                        TextEntry::make('gross_amount')->label('Sales')->money($currency),
                        TextEntry::make('commission_amount')->label('Commission')->money($currency)->color('danger'),
                        TextEntry::make('net_amount')->label('Net earnings')->money($currency)->weight('bold'),
                    ]),

                Section::make('Payment')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (PayoutStatus $state) => $state === PayoutStatus::PAID ? 'success' : 'warning')
                            ->formatStateUsing(fn (PayoutStatus $state) => $state->label()),
                        TextEntry::make('paid_at')->dateTime()->placeholder('Awaiting payment'),
                        TextEntry::make('reference')->placeholder('—'),
                    ]),
            ]);
    }
}

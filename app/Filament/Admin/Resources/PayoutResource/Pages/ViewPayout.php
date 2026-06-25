<?php

namespace App\Filament\Admin\Resources\PayoutResource\Pages;

use App\Enums\PayoutStatus;
use App\Filament\Admin\Resources\PayoutResource;
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
                Section::make('Payout')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')->label('Payout #')->prefix('#'),
                        TextEntry::make('payee')
                            ->label('Payee')
                            ->state(fn ($record) => PayoutResource::payeeLabel($record)),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (PayoutStatus $state) => $state->label()),
                        TextEntry::make('period_start')->date(),
                        TextEntry::make('period_end')->date(),
                        TextEntry::make('orders_count')->label('Orders settled'),
                    ]),

                Section::make('Amounts')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('gross_amount')->money($currency),
                        TextEntry::make('commission_amount')->money($currency)->color('danger'),
                        TextEntry::make('net_amount')->money($currency)->weight('bold'),
                    ]),

                Section::make('Settlement')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('paid_at')->dateTime()->placeholder('Not paid yet'),
                        TextEntry::make('reference')->placeholder('—'),
                    ]),
            ]);
    }
}

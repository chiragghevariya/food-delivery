<?php

namespace App\Filament\Vendor\Resources\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Vendor\Resources\OrderResource;
use App\Models\Setting;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        $currency = Setting::get('currency', 'USD');

        return $infolist
            ->schema([
                Section::make('Order')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')->label('Order #')->prefix('#'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (OrderStatus $state) => $state->color())
                            ->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                        TextEntry::make('created_at')->label('Placed')->dateTime(),
                        TextEntry::make('customer.name')->label('Customer'),
                        TextEntry::make('rider.name')->label('Rider')->placeholder('Unassigned'),
                    ]),

                Section::make('Delivery')
                    ->schema([
                        TextEntry::make('address')->columnSpanFull(),
                        TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Totals')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal')->money($currency),
                        TextEntry::make('delivery_fee')->money($currency),
                        TextEntry::make('total')->money($currency)->weight('bold'),
                    ]),
            ]);
    }
}

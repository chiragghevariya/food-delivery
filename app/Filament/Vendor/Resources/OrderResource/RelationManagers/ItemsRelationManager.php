<?php

namespace App\Filament\Vendor\Resources\OrderResource\RelationManagers;

use App\Models\Setting;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The line items of an order (read-only).
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items';

    public function table(Table $table): Table
    {
        $currency = Setting::get('currency', 'USD');

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('menuItem.name')
                    ->label('Item')
                    ->placeholder('(deleted item)'),
                Tables\Columns\TextColumn::make('quantity')->label('Qty'),
                Tables\Columns\TextColumn::make('unit_price')->money($currency),
                Tables\Columns\TextColumn::make('subtotal')
                    ->money($currency)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money($currency)),
            ])
            ->paginated(false);
    }
}

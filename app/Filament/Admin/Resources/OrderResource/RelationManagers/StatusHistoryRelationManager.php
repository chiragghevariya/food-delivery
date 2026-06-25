<?php

namespace App\Filament\Admin\Resources\OrderResource\RelationManagers;

use App\Enums\OrderStatus;
use App\Filament\Admin\Resources\OrderResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Append-only timeline of every status change on the order.
 */
class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';

    protected static ?string $title = 'Status timeline';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (OrderStatus $state) => OrderResource::statusColor($state))
                    ->formatStateUsing(fn (OrderStatus $state) => str($state->value)->headline()),
                Tables\Columns\TextColumn::make('note')
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->since(),
            ])
            ->defaultSort('created_at', 'asc')
            ->paginated(false);
    }
}

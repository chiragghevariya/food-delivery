<?php

namespace App\Filament\Admin\Resources;

use App\Enums\OrderStatus;
use App\Filament\Admin\Resources\OrderResource\Pages;
use App\Filament\Admin\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    /** Colour mapping reused by columns and infolists (delegates to the enum). */
    public static function statusColor(OrderStatus $status): string
    {
        return $status->color();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Order #')
                    ->prefix('#')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rider.name')
                    ->label('Rider')
                    ->placeholder('Unassigned')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (OrderStatus $state) => self::statusColor($state))
                    ->formatStateUsing(fn (OrderStatus $state) => str($state->value)->headline()),
                Tables\Columns\TextColumn::make('total')
                    ->money(fn () => \App\Models\Setting::get('currency', 'USD'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Placed')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(OrderStatus::cases())
                        ->mapWithKeys(fn ($s) => [$s->value => str($s->value)->headline()])),
                Tables\Filters\SelectFilter::make('restaurant')
                    ->relationship('restaurant', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Manually advance/override status, but only to transitions the
                // OrderStatus state machine allows from the current state.
                Tables\Actions\Action::make('changeStatus')
                    ->label('Change status')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Order $o) => ! empty($o->status->allowedTransitions()))
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('New status')
                            ->options(fn (Order $o) => collect($o->status->allowedTransitions())
                                ->mapWithKeys(fn (OrderStatus $s) => [$s->value => str($s->value)->headline()]))
                            ->required(),
                        Forms\Components\TextInput::make('note')
                            ->label('Note (optional)')
                            ->maxLength(255),
                    ])
                    ->action(function (Order $o, array $data) {
                        $o->transitionTo(OrderStatus::from($data['status']), $data['note'] ?? null);
                        Notification::make()
                            ->success()
                            ->title("Order #{$o->id} → " . str($data['status'])->headline())
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // live monitor — refresh the list every 30s
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\StatusHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // orders are created by the customer app, not here
    }
}

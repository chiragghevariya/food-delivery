<?php

namespace App\Filament\Vendor\Resources;

use App\Enums\OrderStatus;
use App\Filament\Vendor\Concerns\ScopesToOwnerRestaurant;
use App\Filament\Vendor\Resources\OrderResource\Pages;
use App\Filament\Vendor\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    use ScopesToOwnerRestaurant;

    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Orders';

    protected static ?string $navigationLabel = 'Live Orders';

    protected static ?int $navigationSort = 1;

    /** Badge: how many orders need the owner's attention right now. */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->whereIn('status', [OrderStatus::PENDING, OrderStatus::ACCEPTED, OrderStatus::PREPARING])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::get('currency', 'USD');

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Order #')
                    ->prefix('#')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge(),
                Tables\Columns\TextColumn::make('total')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (OrderStatus $state) => $state->color())
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Placed')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // Accept a brand-new order.
                Tables\Actions\Action::make('accept')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Order $o) => $o->status === OrderStatus::PENDING)
                    ->requiresConfirmation()
                    ->action(fn (Order $o) => static::transition($o, OrderStatus::ACCEPTED)),

                // Begin cooking.
                Tables\Actions\Action::make('startPreparing')
                    ->label('Start preparing')
                    ->icon('heroicon-o-fire')
                    ->color('warning')
                    ->visible(fn (Order $o) => $o->status === OrderStatus::ACCEPTED)
                    ->action(fn (Order $o) => static::transition($o, OrderStatus::PREPARING)),

                // Food is ready for a rider to collect.
                Tables\Actions\Action::make('markReady')
                    ->label('Mark ready')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('info')
                    ->visible(fn (Order $o) => $o->status === OrderStatus::PREPARING)
                    ->action(fn (Order $o) => static::transition($o, OrderStatus::READY_FOR_PICKUP)),

                // Reject / cancel while still in the restaurant's hands.
                Tables\Actions\Action::make('reject')
                    ->label(fn (Order $o) => $o->status === OrderStatus::PENDING ? 'Reject' : 'Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $o) => in_array($o->status, [
                        OrderStatus::PENDING, OrderStatus::ACCEPTED, OrderStatus::PREPARING,
                    ], true))
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\TextInput::make('note')
                            ->label('Reason (optional)')
                            ->maxLength(255),
                    ])
                    ->action(fn (Order $o, array $data) => static::transition($o, OrderStatus::CANCELLED, $data['note'] ?? null)),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('15s'); // live queue — refresh every 15s
    }

    /**
     * Safely move an order through the shared OrderStatus state machine and
     * surface the result to the owner. transitionTo() rejects illegal jumps.
     */
    protected static function transition(Order $order, OrderStatus $to, ?string $note = null): void
    {
        $order->transitionTo($to, $note);

        Notification::make()
            ->success()
            ->title("Order #{$order->id} → {$to->label()}")
            ->send();
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
        return false; // orders come from the customer app
    }
}

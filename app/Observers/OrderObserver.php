<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

class OrderObserver
{
    /**
     * When a new pending order is placed (by the customer app), push a database
     * notification to the restaurant owner. The vendor panel polls the bell icon
     * every 15s, so it appears live without a page reload.
     */
    public function created(Order $order): void
    {
        if ($order->status !== OrderStatus::PENDING) {
            return;
        }

        $owner = $order->restaurant?->owner;

        if ($owner === null) {
            return;
        }

        $symbol = Setting::get('currency_symbol', '$');

        Notification::make()
            ->title('New order received')
            ->body("Order #{$order->id} · {$symbol}" . number_format((float) $order->total, 2))
            ->icon('heroicon-o-bell-alert')
            ->iconColor('warning')
            ->actions([
                Action::make('view')
                    ->label('View order')
                    ->url(url("/dashboard/orders/{$order->id}"))
                    ->markAsRead(),
            ])
            ->sendToDatabase($owner);
    }
}

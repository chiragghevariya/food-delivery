<?php

namespace App\Filament\Vendor\Resources;

use App\Enums\PayoutStatus;
use App\Filament\Vendor\Resources\PayoutResource\Pages;
use App\Filament\Vendor\Resources\PayoutResource\Widgets\EarningsSummary;
use App\Models\Payout;
use App\Models\Restaurant;
use App\Models\Setting;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only earnings / payout history for the signed-in owner.
 * Scoped to payouts whose payee is THIS owner's restaurant only.
 */
class PayoutResource extends Resource
{
    protected static ?string $model = Payout::class;

    protected static ?string $slug = 'earnings';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Business';

    protected static ?string $navigationLabel = 'Earnings';

    protected static ?string $modelLabel = 'payout';

    /** Only this owner's restaurant payouts — never another restaurant or any rider. */
    public static function getEloquentQuery(): Builder
    {
        $restaurantId = auth()->user()?->restaurant?->id ?? 0;

        return parent::getEloquentQuery()
            ->where('payee_type', Restaurant::class)
            ->where('payee_id', $restaurantId);
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::get('currency', 'USD');

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period')
                    ->formatStateUsing(fn (Payout $p) => $p->period_start?->format('M j, Y') . ' – ' . $p->period_end?->format('M j, Y'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->badge(),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Sales')
                    ->money($currency),
                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('Commission')
                    ->money($currency)
                    ->color('danger'),
                Tables\Columns\TextColumn::make('net_amount')
                    ->label('Net earnings')
                    ->money($currency)
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (PayoutStatus $state) => $state === PayoutStatus::PAID ? 'success' : 'warning')
                    ->formatStateUsing(fn (PayoutStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid on')
                    ->date()
                    ->placeholder('Awaiting payment'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(PayoutStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No payouts yet')
            ->emptyStateDescription('Earnings from delivered orders appear here once the platform settles them.');
    }

    public static function getWidgets(): array
    {
        return [
            EarningsSummary::class,
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayouts::route('/'),
            'view' => Pages\ViewPayout::route('/{record}'),
        ];
    }
}

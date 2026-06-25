<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PayoutStatus;
use App\Filament\Admin\Resources\PayoutResource\Pages;
use App\Models\Payout;
use App\Models\Restaurant;
use App\Models\Setting;
use App\Models\User;
use App\Services\PayoutService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutResource extends Resource
{
    protected static ?string $model = Payout::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    /** Badge: how many payouts are still owed (not yet paid). */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', '!=', PayoutStatus::PAID)->count();

        return $pending > 0 ? (string) $pending : null;
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
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payee_label')
                    ->label('Payee')
                    ->state(fn (Payout $p) => static::payeeLabel($p))
                    ->description(fn (Payout $p) => class_basename($p->payee_type)),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->badge(),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Gross')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('Commission')
                    ->money($currency)
                    ->color('danger'),
                Tables\Columns\TextColumn::make('net_amount')
                    ->label('Net payable')
                    ->money($currency)
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period')
                    ->formatStateUsing(fn (Payout $p) => $p->period_start?->format('M j') . ' – ' . $p->period_end?->format('M j')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (PayoutStatus $state) => match ($state) {
                        PayoutStatus::PAID       => 'success',
                        PayoutStatus::PROCESSING => 'info',
                        PayoutStatus::FAILED     => 'danger',
                        PayoutStatus::PENDING    => 'warning',
                    })
                    ->formatStateUsing(fn (PayoutStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(PayoutStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('payee_type')
                    ->label('Payee type')
                    ->options([
                        Restaurant::class => 'Restaurants',
                        User::class       => 'Riders',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generate')
                    ->label('Generate payouts')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->form([
                        Forms\Components\CheckboxList::make('targets')
                            ->label('Generate for')
                            ->options([
                                'restaurants' => 'Restaurants (subtotal − commission)',
                                'riders'      => 'Riders (delivery fees)',
                            ])
                            ->default(['restaurants', 'riders'])
                            ->required(),
                    ])
                    ->action(function (array $data, PayoutService $service) {
                        $created = 0;
                        if (in_array('restaurants', $data['targets'] ?? [], true)) {
                            $created += $service->generateRestaurantPayouts()->count();
                        }
                        if (in_array('riders', $data['targets'] ?? [], true)) {
                            $created += $service->generateRiderPayouts()->count();
                        }

                        Notification::make()
                            ->success()
                            ->title($created > 0 ? "{$created} payout(s) generated" : 'Nothing to settle')
                            ->body($created > 0 ? null : 'There are no unsettled delivered orders.')
                            ->send();
                    }),

                Tables\Actions\Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => static::exportCsv()),
            ])
            ->actions([
                Tables\Actions\Action::make('markPaid')
                    ->label('Mark as paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Payout $p) => $p->status !== PayoutStatus::PAID)
                    ->form([
                        Forms\Components\TextInput::make('reference')
                            ->label('Payment reference (optional)')
                            ->maxLength(255),
                    ])
                    ->action(function (Payout $p, array $data) {
                        $p->markPaid($data['reference'] ?? null);
                        Notification::make()->success()->title("Payout #{$p->id} marked as paid")->send();
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markPaidBulk')
                        ->label('Mark as paid')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->markPaid())
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** A readable payee name for a polymorphic payout. */
    public static function payeeLabel(Payout $payout): string
    {
        return $payout->payee?->name
            ?? (class_basename($payout->payee_type) . ' #' . $payout->payee_id);
    }

    /** Stream the full payout ledger as a CSV download. */
    public static function exportCsv(): StreamedResponse
    {
        $filename = 'payouts-' . Carbon::now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Payee', 'Type', 'Orders', 'Gross', 'Commission', 'Net', 'Status', 'Period start', 'Period end', 'Paid at', 'Reference']);

            Payout::with('payee')->chunk(200, function ($payouts) use ($handle) {
                foreach ($payouts as $p) {
                    fputcsv($handle, [
                        $p->id,
                        static::payeeLabel($p),
                        class_basename($p->payee_type),
                        $p->orders_count,
                        $p->gross_amount,
                        $p->commission_amount,
                        $p->net_amount,
                        $p->status->value,
                        $p->period_start?->toDateString(),
                        $p->period_end?->toDateString(),
                        $p->paid_at?->toDateTimeString(),
                        $p->reference,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public static function canCreate(): bool
    {
        return false; // payouts are generated, never hand-created
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayouts::route('/'),
            'view' => Pages\ViewPayout::route('/{record}'),
        ];
    }
}

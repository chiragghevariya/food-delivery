<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RiderResource\Pages;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A read-focused view of delivery riders (users with the `rider` role),
 * including their live location and delivery counts. Uses the same User
 * model as UserResource but scoped to riders only.
 */
class RiderResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'riders';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'rider';

    /** Only ever query rider accounts here. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'rider');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->circular()
                    ->defaultImageUrl(fn (User $u) => 'https://ui-avatars.com/api/?name=' . urlencode($u->name)),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->icon('heroicon-m-phone'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('deliveries_count')
                    ->label('Deliveries')
                    ->counts('deliveries')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_updated_at')
                    ->label('Last seen')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\Action::make('locate')
                    ->label('Map')
                    ->icon('heroicon-o-map-pin')
                    ->color('info')
                    ->visible(fn (User $u) => $u->lat && $u->lng)
                    ->url(fn (User $u) => "https://www.google.com/maps?q={$u->lat},{$u->lng}", shouldOpenInNewTab: true),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRiders::route('/'),
        ];
    }
}

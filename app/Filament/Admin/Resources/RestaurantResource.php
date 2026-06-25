<?php

namespace App\Filament\Admin\Resources;

use App\Enums\RestaurantStatus;
use App\Filament\Admin\Resources\RestaurantResource\Pages;
use App\Models\Restaurant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    /** Show a red badge with the number of restaurants awaiting approval. */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', RestaurantStatus::PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Owner & Status')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Owner')
                            ->relationship(
                                'owner',
                                'name',
                                fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'restaurant_owner')),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options(collect(RestaurantStatus::cases())
                                ->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                            ->default(RestaurantStatus::PENDING->value)
                            ->required(),
                    ]),

                Forms\Components\Section::make('Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_open')
                            ->label('Currently accepting orders')
                            ->default(true),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('address')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('lat')
                            ->label('Latitude')
                            ->numeric(),
                        Forms\Components\TextInput::make('lng')
                            ->label('Longitude')
                            ->numeric(),
                    ]),

                Forms\Components\Section::make('Branding')
                    ->columns(2)
                    ->schema([
                        Forms\Components\FileUpload::make('logo')
                            ->image()
                            ->directory('restaurants/logos')
                            ->imageEditor(),
                        Forms\Components\FileUpload::make('banner')
                            ->image()
                            ->directory('restaurants/banners')
                            ->imageEditor(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Restaurant $r) => $r->address),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (RestaurantStatus $state) => match ($state) {
                        RestaurantStatus::APPROVED => 'success',
                        RestaurantStatus::PENDING  => 'warning',
                        RestaurantStatus::REJECTED => 'danger',
                    })
                    ->formatStateUsing(fn (RestaurantStatus $state) => $state->label()),
                Tables\Columns\IconColumn::make('is_open')
                    ->label('Open')
                    ->boolean(),
                Tables\Columns\TextColumn::make('rating')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(RestaurantStatus::cases())
                        ->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\TernaryFilter::make('is_open')
                    ->label('Open now'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Restaurant $r) => $r->status !== RestaurantStatus::APPROVED)
                    ->requiresConfirmation()
                    ->action(function (Restaurant $r) {
                        $r->approve();
                        Notification::make()
                            ->success()
                            ->title("{$r->name} approved")
                            ->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Restaurant $r) => $r->status !== RestaurantStatus::REJECTED)
                    ->requiresConfirmation()
                    ->action(function (Restaurant $r) {
                        $r->update(['status' => RestaurantStatus::REJECTED]);
                        Notification::make()
                            ->warning()
                            ->title("{$r->name} rejected")
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestaurants::route('/'),
            'create' => Pages\CreateRestaurant::route('/create'),
            'edit' => Pages\EditRestaurant::route('/{record}/edit'),
        ];
    }
}

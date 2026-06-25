<?php

namespace App\Filament\Vendor\Resources;

use App\Filament\Vendor\Concerns\ScopesToOwnerRestaurant;
use App\Filament\Vendor\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuItemResource extends Resource
{
    use ScopesToOwnerRestaurant;

    protected static ?string $model = MenuItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-cake';

    protected static ?string $navigationGroup = 'Menu';

    protected static ?string $navigationLabel = 'Menu Items';

    /** Owners can only add items once their restaurant exists. */
    public static function canCreate(): bool
    {
        return static::ownerRestaurant() !== null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix(Setting::get('currency_symbol', '$')),
                        Forms\Components\Toggle::make('is_available')
                            ->label('Available for ordering')
                            ->default(true),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('image')
                            ->image()
                            ->directory('menu-items')
                            ->imageEditor()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::get('currency', 'USD');

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->placeholder('Uncategorised')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_available')
                    ->label('Available'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('Availability'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('makeAvailable')
                        ->label('Mark available')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['is_available' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('makeUnavailable')
                        ->label('Mark unavailable')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each->update(['is_available' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('name')
            ->emptyStateHeading(
                static::ownerRestaurant()
                    ? 'No menu items yet'
                    : 'Set up your restaurant first'
            )
            ->emptyStateDescription(
                static::ownerRestaurant()
                    ? 'Add your first dish to start receiving orders.'
                    : 'You need a restaurant profile before you can add menu items.'
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }
}

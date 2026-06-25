<?php

namespace App\Filament\Vendor\Pages;

use App\Enums\RestaurantStatus;
use App\Models\Restaurant;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Single-record editor for the signed-in owner's own restaurant.
 *
 * Owners edit only operational/branding fields — never their approval status,
 * rating, or ownership. If they don't have a restaurant yet, saving creates one
 * in the "pending" state for a super-admin to approve.
 */
class RestaurantProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Business';

    protected static ?string $navigationLabel = 'Restaurant Profile';

    protected static ?string $title = 'Restaurant Profile';

    protected static string $view = 'filament.vendor.pages.restaurant-profile';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(
            auth()->user()->restaurant?->attributesToArray() ?? []
        );
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Status')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('approval_status')
                            ->label('Approval status')
                            ->content(fn () => auth()->user()->restaurant?->status?->label() ?? 'Not submitted'),
                        Forms\Components\Placeholder::make('rating')
                            ->label('Average rating')
                            ->content(fn () => number_format((float) (auth()->user()->restaurant?->rating ?? 0), 2) . ' / 5.00'),
                        Forms\Components\Toggle::make('is_open')
                            ->label('Currently accepting orders')
                            ->helperText('Turn this off to temporarily stop receiving new orders.')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('address')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
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
            ])
            ->statePath('data')
            ->model(auth()->user()->restaurant ?? Restaurant::class);
    }

    public function save(): void
    {
        // Only ever the whitelisted fields from the form — status/rating/user_id
        // are never in $data, so they can't be tampered with.
        $data = $this->form->getState();

        $restaurant = auth()->user()->restaurant;

        if ($restaurant) {
            $restaurant->update($data);
        } else {
            $restaurant = Restaurant::create([
                ...$data,
                'user_id' => auth()->id(),
                'status'  => RestaurantStatus::PENDING,
            ]);
            // Re-point the form at the freshly created record for future saves.
            $this->form->model($restaurant)->saveRelationships();
        }

        Notification::make()
            ->success()
            ->title('Restaurant profile saved')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->submit('save'),
        ];
    }
}

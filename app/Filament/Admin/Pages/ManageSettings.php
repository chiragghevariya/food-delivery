<?php

namespace App\Filament\Admin\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Global platform configuration, backed by the `settings` table
 * (read/written through App\Models\Setting, which is cached).
 */
class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 99;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Platform Settings';

    protected static string $view = 'filament.admin.pages.manage-settings';

    /** The settings this screen manages, with their storage type. */
    private const SCHEMA = [
        'platform_commission_rate' => 'float',
        'default_delivery_fee'     => 'float',
        'min_order_value'          => 'float',
        'currency'                 => 'string',
        'currency_symbol'          => 'string',
        'maintenance_mode'         => 'boolean',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'platform_commission_rate' => Setting::get('platform_commission_rate', 15),
            'default_delivery_fee'     => Setting::get('default_delivery_fee', 2.50),
            'min_order_value'          => Setting::get('min_order_value', 5.00),
            'currency'                 => Setting::get('currency', 'USD'),
            'currency_symbol'          => Setting::get('currency_symbol', '$'),
            'maintenance_mode'         => Setting::get('maintenance_mode', false),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Finance')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('platform_commission_rate')
                            ->label('Commission rate')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->helperText('Platform cut taken from each restaurant order.'),
                        Forms\Components\TextInput::make('default_delivery_fee')
                            ->label('Default delivery fee')
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn (Forms\Get $get) => $get('currency_symbol'))
                            ->required(),
                        Forms\Components\TextInput::make('min_order_value')
                            ->label('Minimum order value')
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn (Forms\Get $get) => $get('currency_symbol'))
                            ->required(),
                    ]),

                Forms\Components\Section::make('Localisation')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('currency')
                            ->label('Currency code')
                            ->maxLength(3)
                            ->required()
                            ->helperText('ISO code, e.g. USD, EUR, INR.'),
                        Forms\Components\TextInput::make('currency_symbol')
                            ->label('Currency symbol')
                            ->maxLength(4)
                            ->required()
                            ->live(),
                    ]),

                Forms\Components\Section::make('System')
                    ->schema([
                        Forms\Components\Toggle::make('maintenance_mode')
                            ->label('Maintenance mode')
                            ->helperText('When on, the customer app can show a downtime message.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (self::SCHEMA as $key => $type) {
            if (array_key_exists($key, $data)) {
                Setting::set($key, $data[$key], $type);
            }
        }

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->submit('save'),
        ];
    }
}

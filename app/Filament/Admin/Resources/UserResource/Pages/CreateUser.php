<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Keep the spatie role in sync with the `role` column.
     * (Roles live on the "api" guard — the User model forces that guard.)
     */
    protected function afterCreate(): void
    {
        $this->record->syncRoles([$this->record->role]);
    }
}

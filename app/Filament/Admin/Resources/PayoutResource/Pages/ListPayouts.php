<?php

namespace App\Filament\Admin\Resources\PayoutResource\Pages;

use App\Filament\Admin\Resources\PayoutResource;
use Filament\Resources\Pages\ListRecords;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    // Generate / Export live in the table header actions; no manual create.
    protected function getHeaderActions(): array
    {
        return [];
    }
}

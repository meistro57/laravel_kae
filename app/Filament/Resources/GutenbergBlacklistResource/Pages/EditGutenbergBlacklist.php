<?php

namespace App\Filament\Resources\GutenbergBlacklistResource\Pages;

use App\Filament\Resources\GutenbergBlacklistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGutenbergBlacklist extends EditRecord
{
    protected static string $resource = GutenbergBlacklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\AccessTokens\Pages;

use App\Filament\Resources\AccessTokens\AccessTokenResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAccessToken extends EditRecord
{
    protected static string $resource = AccessTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

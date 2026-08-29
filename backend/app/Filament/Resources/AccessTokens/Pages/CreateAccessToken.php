<?php

namespace App\Filament\Resources\AccessTokens\Pages;

use App\Filament\Resources\AccessTokens\AccessTokenResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccessToken extends CreateRecord
{
    protected static string $resource = AccessTokenResource::class;
}

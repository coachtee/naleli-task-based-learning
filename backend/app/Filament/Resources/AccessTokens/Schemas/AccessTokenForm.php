<?php

namespace App\Filament\Resources\AccessTokens\Schemas;

use App\Enums\TokenStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AccessTokenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('learner_id')
                    ->relationship('learner', 'id')
                    ->required(),
                Select::make('enrolment_id')
                    ->relationship('enrolment', 'id')
                    ->required(),
                TextInput::make('token_hash')
                    ->required(),
                TextInput::make('token_prefix')
                    ->required(),
                Select::make('status')
                    ->options(TokenStatus::class)
                    ->default('issued')
                    ->required(),
                DateTimePicker::make('issued_at')
                    ->required(),
                TextInput::make('issued_by')
                    ->numeric(),
                DateTimePicker::make('activated_at'),
                DateTimePicker::make('expires_at'),
                DateTimePicker::make('revoked_at'),
                TextInput::make('revoked_reason'),
            ]);
    }
}

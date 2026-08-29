<?php

namespace App\Filament\Resources\Entitlements\Schemas;

use App\Enums\EntitlementState;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EntitlementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('learner_id')
                    ->relationship('learner', 'id')
                    ->required(),
                Select::make('programme_id')
                    ->relationship('programme', 'name')
                    ->required(),
                Select::make('state')
                    ->options(EntitlementState::class)
                    ->default('locked')
                    ->required(),
                Select::make('source_enrolment_id')
                    ->relationship('sourceEnrolment', 'id'),
                DateTimePicker::make('unlocked_at'),
                DateTimePicker::make('expires_at'),
                TextInput::make('reason'),
            ]);
    }
}

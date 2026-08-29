<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ApplicationForm
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
                Select::make('intake_id')
                    ->relationship('intake', 'id'),
                Select::make('status')
                    ->options(ApplicationStatus::class)
                    ->default('applied')
                    ->required(),
                Select::make('source')
                    ->options(ApplicationSource::class)
                    ->required(),
                TextInput::make('source_form_id')
                    ->numeric(),
                TextInput::make('source_reference'),
                Textarea::make('payload')
                    ->columnSpanFull(),
                DateTimePicker::make('applied_at')
                    ->required(),
                DateTimePicker::make('decided_at'),
                TextInput::make('decided_by')
                    ->numeric(),
                TextInput::make('decision_note'),
            ]);
    }
}

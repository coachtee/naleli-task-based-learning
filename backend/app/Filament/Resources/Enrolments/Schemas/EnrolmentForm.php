<?php

namespace App\Filament\Resources\Enrolments\Schemas;

use App\Enums\EnrolmentStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class EnrolmentForm
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
                Select::make('application_id')
                    ->relationship('application', 'id'),
                Select::make('status')
                    ->options(EnrolmentStatus::class)
                    ->default('pending')
                    ->required(),
                DatePicker::make('starts_on'),
                DatePicker::make('ends_on'),
                DateTimePicker::make('activated_at'),
                DateTimePicker::make('completed_at'),
            ]);
    }
}

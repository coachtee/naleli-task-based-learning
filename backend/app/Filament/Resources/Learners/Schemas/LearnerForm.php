<?php

namespace App\Filament\Resources\Learners\Schemas;

use App\Enums\IdType;
use App\Enums\LearnerStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LearnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('learner_ref')
                    ->required(),
                TextInput::make('first_registered_year')
                    ->required()
                    ->numeric(),
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('middle_name'),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('preferred_name'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('whatsapp'),
                Select::make('id_type')
                    ->options(IdType::class),
                Textarea::make('id_number_encrypted')
                    ->columnSpanFull(),
                TextInput::make('id_number_hash'),
                TextInput::make('id_number_masked'),
                DatePicker::make('date_of_birth'),
                DateTimePicker::make('identity_verified_at'),
                TextInput::make('identity_verified_by')
                    ->numeric(),
                Select::make('status')
                    ->options(LearnerStatus::class)
                    ->default('prospect')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Programmes\Schemas;

use App\Enums\ProgrammeStatus;
use App\Enums\ProgrammeTier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProgrammeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Select::make('tier')
                    ->options(ProgrammeTier::class)
                    ->required(),
                TextInput::make('summary'),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('duration_label'),
                TextInput::make('duration_days')
                    ->numeric(),
                TextInput::make('weekly_hours'),
                TextInput::make('fee_note'),
                TextInput::make('content_code'),
                TextInput::make('content_version'),
                Select::make('status')
                    ->options(ProgrammeStatus::class)
                    ->default('draft')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}

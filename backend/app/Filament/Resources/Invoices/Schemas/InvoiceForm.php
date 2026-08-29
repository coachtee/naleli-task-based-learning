<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class InvoiceForm
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
                TextInput::make('sequence')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('description')
                    ->required(),
                TextInput::make('amount_cents')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('ZAR'),
                DatePicker::make('due_on'),
                Toggle::make('activates_enrolment')
                    ->required(),
                Select::make('status')
                    ->options(InvoiceStatus::class)
                    ->default('due')
                    ->required(),
                DateTimePicker::make('paid_at'),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('invoice_id')
                    ->relationship('invoice', 'id'),
                Select::make('learner_id')
                    ->relationship('learner', 'id')
                    ->required(),
                TextInput::make('amount_cents')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('ZAR'),
                TextInput::make('provider')
                    ->required(),
                TextInput::make('provider_reference'),
                Select::make('status')
                    ->options(PaymentStatus::class)
                    ->default('initiated')
                    ->required(),
                DateTimePicker::make('paid_at'),
                TextInput::make('confirmed_by')
                    ->numeric(),
                Textarea::make('raw_response')
                    ->columnSpanFull(),
            ]);
    }
}

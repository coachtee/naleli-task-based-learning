<?php

namespace App\Filament\Resources\Offerings\Schemas;

use App\Enums\ActivationRule;
use App\Enums\BillingModel;
use App\Enums\OfferingStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OfferingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('programme_id')
                    ->relationship('programme', 'name')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('description'),
                Select::make('billing_model')
                    ->options(BillingModel::class)
                    ->required(),
                TextInput::make('price_cents')
                    ->required()
                    ->numeric(),
                TextInput::make('deposit_cents')
                    ->numeric(),
                TextInput::make('instalment_count')
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('ZAR'),
                TextInput::make('access_duration_days')
                    ->numeric(),
                Select::make('activation_rule')
                    ->options(ActivationRule::class)
                    ->default('on_first_payment')
                    ->required(),
                Select::make('status')
                    ->options(OfferingStatus::class)
                    ->default('draft')
                    ->required(),
                DatePicker::make('available_from'),
                DatePicker::make('available_until'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}

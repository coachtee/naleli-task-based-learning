<?php

namespace App\Filament\Resources\Offerings;

use App\Filament\Resources\Offerings\Pages\CreateOffering;
use App\Filament\Resources\Offerings\Pages\EditOffering;
use App\Filament\Resources\Offerings\Pages\ListOfferings;
use App\Filament\Resources\Offerings\Schemas\OfferingForm;
use App\Filament\Resources\Offerings\Tables\OfferingsTable;
use App\Models\Offering;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OfferingResource extends Resource
{
    protected static ?string $model = Offering::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Records';

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return OfferingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OfferingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOfferings::route('/'),
            'create' => CreateOffering::route('/create'),
            'edit' => EditOffering::route('/{record}/edit'),
        ];
    }
}

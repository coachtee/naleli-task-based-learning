<?php

namespace App\Filament\Resources\AccessTokens;

use App\Filament\Resources\AccessTokens\Pages\CreateAccessToken;
use App\Filament\Resources\AccessTokens\Pages\EditAccessToken;
use App\Filament\Resources\AccessTokens\Pages\ListAccessTokens;
use App\Filament\Resources\AccessTokens\Schemas\AccessTokenForm;
use App\Filament\Resources\AccessTokens\Tables\AccessTokensTable;
use App\Models\AccessToken;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AccessTokenResource extends Resource
{
    protected static ?string $model = AccessToken::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Records';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    public static function form(Schema $schema): Schema
    {
        return AccessTokenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccessTokensTable::configure($table);
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
            'index' => ListAccessTokens::route('/'),
            'create' => CreateAccessToken::route('/create'),
            'edit' => EditAccessToken::route('/{record}/edit'),
        ];
    }
}

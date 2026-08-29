<?php

namespace App\Filament\Resources\Learners;

use App\Filament\Resources\Learners\Pages\CreateLearner;
use App\Filament\Resources\Learners\Pages\EditLearner;
use App\Filament\Resources\Learners\Pages\ListLearners;
use App\Filament\Resources\Learners\Schemas\LearnerForm;
use App\Filament\Resources\Learners\Tables\LearnersTable;
use App\Models\Learner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LearnerResource extends Resource
{
    protected static ?string $model = Learner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return LearnerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LearnersTable::configure($table);
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
            'index' => ListLearners::route('/'),
            'create' => CreateLearner::route('/create'),
            'edit' => EditLearner::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Learners\Tables;

use App\Models\Learner;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LearnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('learner_ref')
                    ->searchable(),
                TextColumn::make('first_registered_year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('middle_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('preferred_name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('whatsapp')
                    ->searchable(),
                TextColumn::make('id_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('id_number_hash')
                    ->searchable(),
                TextColumn::make('id_number_masked')
                    ->searchable(),
                TextColumn::make('date_of_birth')
                    ->date()
                    ->sortable(),
                TextColumn::make('identity_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('identity_verified_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // Sighting a passport or permit is a human act — only an SA ID
                // verifies itself from the number. Until this is done no token
                // can be issued for this learner.
                Action::make('verifyIdentity')
                    ->label('Verify identity')
                    ->icon('heroicon-o-identification')
                    ->color('warning')
                    ->visible(fn (Learner $record): bool => $record->id_number_hash !== null && ! $record->hasVerifiedIdentity())
                    ->requiresConfirmation()
                    ->modalHeading('Confirm you have sighted the document')
                    ->modalDescription(fn (Learner $record): string => "Recorded: {$record->id_type?->label()} {$record->id_number_masked}")
                    ->action(function (Learner $record): void {
                        $record->update([
                            'identity_verified_at' => now(),
                            'identity_verified_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Identity verified')
                            ->body("{$record->learner_ref} can now be issued an access token.")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Learners\Tables;

use App\Models\Learner;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LearnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('learner_ref')
                    ->label('Learner')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable()
                    ->description(fn ($record): string => trim("{$record->first_name} {$record->last_name}") ?: '—'),

                TextColumn::make('email')
                    ->searchable()
                    ->toggleable()
                    ->description(fn ($record): ?string => $record->phone),

                TextColumn::make('id_number_masked')
                    ->label('Identification')
                    ->placeholder('Not supplied')
                    // Never the full number in a list. Revealing it is a
                    // deliberate act on the record, not a side effect of
                    // scrolling past someone.
                    ->description(fn ($record): ?string => $record->id_type?->label()),

                IconColumn::make('identity_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => match ($state->value) {
                        'active' => 'success',
                        'alumni' => 'info',
                        'withdrawn', 'suspended' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->date('j M Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No learners yet')
            ->emptyStateDescription('Learners are created automatically when an application arrives from the website.')
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

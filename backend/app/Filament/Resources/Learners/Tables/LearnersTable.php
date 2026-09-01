<?php

namespace App\Filament\Resources\Learners\Tables;

use App\Models\Learner;
use App\Services\Messaging\PaymentMessage;
use App\Services\Registration\LearnerLinks;
use App\Services\Registration\ProfileCompleteness;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Actions\Action as NotificationAction;
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

                // The same measure the registration queue shows, on the record
                // that actually holds the fields.
                TextColumn::make('profile')
                    ->label('Profile')
                    ->state(fn (Learner $record): string => app(ProfileCompleteness::class)->percent($record).'%')
                    ->badge()
                    ->color(fn (Learner $record): string => match (true) {
                        app(ProfileCompleteness::class)->isComplete($record) => 'success',
                        app(ProfileCompleteness::class)->blocking($record) !== [] => 'danger',
                        default => 'warning',
                    })
                    ->toggleable(),

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
                // The step that used to have nowhere to happen: a learner
                // filling in their own identity, address and schooling. The
                // link is private to them and lapses, so it is sent rather
                // than published.
                Action::make('sendProfileLink')
                    ->label('Ask them to finish their profile')
                    ->icon('heroicon-o-identification')
                    ->color('warning')
                    ->visible(fn (Learner $record): bool => ! app(ProfileCompleteness::class)->isComplete($record))
                    ->modalHeading('Send the private profile link')
                    ->modalDescription(fn (Learner $record): string => 'Still outstanding: '
                        .strtolower(implode(', ', app(ProfileCompleteness::class)->missing($record))).'.')
                    ->modalSubmitActionLabel('Show me the link')
                    ->action(function (Learner $record): void {
                        $link = app(LearnerLinks::class)->friendlyProfile($record);
                        $whatsapp = app(PaymentMessage::class)->profileWhatsAppLink($record);

                        Notification::make()
                            ->title('Profile link for '.$record->learner_ref)
                            ->body($whatsapp === null
                                ? $link.' — no usable WhatsApp number on file, so send it another way.'
                                : $link)
                            ->success()
                            ->persistent()
                            ->actions(array_values(array_filter([
                                $whatsapp === null ? null : NotificationAction::make('wa')
                                    ->label('Send it on WhatsApp')
                                    ->url($whatsapp, shouldOpenInNewTab: true)
                                    ->button(),
                            ])))
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

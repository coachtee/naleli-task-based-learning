<?php

namespace App\Filament\Resources\AccessTokens\Tables;

use App\Enums\TokenStatus;
use App\Models\AccessToken;
use App\Services\Tokens\AccessTokenIssuer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccessTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('learner.learner_ref')
                    ->label('Learner')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (AccessToken $record): ?string => $record->enrolment?->programme?->name),

                TextColumn::make('token_prefix')
                    ->label('Token')
                    // The prefix is all we can ever show: only the hash is
                    // stored, and the full token is displayed once, at issue.
                    // It is enough to match a learner reading theirs aloud.
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : $state.'…')
                    ->searchable()
                    ->tooltip('The full token is shown once when it is issued and never stored in readable form.'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => match ($state->value) {
                        'active' => 'success',
                        'issued' => 'warning',
                        'revoked' => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (AccessToken $record): ?string => $record->revoked_reason),

                TextColumn::make('issued_at')
                    ->label('Issued')
                    ->date('j M Y')
                    ->sortable(),

                TextColumn::make('activated_at')
                    ->label('Opened in app')
                    ->date('j M Y')
                    ->placeholder('Not yet')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Access until')
                    ->date('j M Y')
                    ->placeholder('No expiry')
                    ->sortable(),

                TextColumn::make('token_hash')
                    ->label('Hash')
                    ->searchable()
                    ->limit(16)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('issued_at', 'desc')
            ->emptyStateHeading('No access tokens yet')
            ->emptyStateDescription('A token is issued automatically when an activating payment settles and the learner\'s identity is verified.')
            ->filters([
                //
            ])
            ->recordActions([
                // A lost phone loses access without the learner losing theirs:
                // revoke here, then issue a replacement from the enrolment.
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (AccessToken $record): bool => $record->status !== TokenStatus::REVOKED)
                    ->requiresConfirmation()
                    ->schema([
                        TextInput::make('reason')
                            ->label('Reason')
                            ->placeholder('Lost phone, issued in error, learner withdrew')
                            ->required()
                            ->maxLength(160),
                    ])
                    ->action(function (AccessToken $record, array $data): void {
                        app(AccessTokenIssuer::class)->revoke($record, $data['reason']);

                        Notification::make()
                            ->title('Token revoked')
                            ->body('The device using it loses access on its next sync.')
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
